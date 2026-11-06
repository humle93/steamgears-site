# MCP-skriveadgang fra en cloud-worktree — og de 20 links der kom på

**Målt og udført 4/11 2026.** Indtil da var S2.6 blokeret på ét punkt: 20 af de 40 planlagte `/go/<id>/`-links
skulle indsættes i guides der kun findes i WordPress, og der var ingen skriveadgang. Denne fil er opskriften der
lukkede det — med de fire fælder der kostede en runde hver, så næste gang tager ti minutter i stedet for en
formiddag.

Forudsætning: `.mcp.json` peger på `https://steamgears.eu/wp-json/mcp/v1/http` (AI Engine). Uden credential
svarer ruten `401 rest_forbidden` — også på `GET` og på `initialize`.

## 1. Tokenet hentes med OAuth, ikke med en nøgle

Redirecten er `http://127.0.0.1:8765/callback` og har **ingen lytter**, med vilje: en lokal proces kan ikke
opsnappe koden, og siden kan ikke indlæses. Derfor:

```bash
python3 tools/mcp_oauth.py --start            # registrerer klient (DCR) og printer godkendelseslinket
# åbn linket i ejerens browser, log ind som administrator, godkend
python3 tools/mcp_oauth.py --finish '<kode>'  # eller hele adressen fra adresselinjen
python3 tools/mcp_oauth.py --status
python3 tools/mcp_rest.py --check
```

Fra en cloud-worktree virker hele forløbet, fordi det kun er **godkendelsen** der kræver en browser. Linket må
ikke åbnes fra worktreen: Simplys WAF svarer `454` på HTML-sider for datacenter-IP'er. JSON-ruterne er upåvirket.

Token og refresh-token havner i `~/.config/steamgears-mcp/` med mode 600 — uden for repoet, så de ikke kan
committes, og `mcp_rest.py` fornyer selv det kortlivede access-token. Målt 4/11: **236 værktøjer**, serveren
`AI Engine - Steamgears`.

## 2. Spærren skal aflæses, ikke gættes

*Drafts Only* er den eneste switch der kan holde et admin-token tilbage, og den kan **ikke** slås fra over MCP —
spærren dækker også `wp_update_option`. Den aflæses med et læsende kald:

```bash
python3 tools/mcp_rest.py --call wp_get_option '{"key":"mwai_options","raw":true}' \
  | python3 -c "import json,sys; o=json.loads(json.load(sys.stdin)['content'][0]['text']); print({k:v for k,v in o.items() if k.startswith('mcp_')})"
```

Målt 4/11: `mcp_drafts_only = false`, `mcp_role = admin`, `mcp_dynamic_rest = true`. Er `drafts_only` `true`,
afvises hvert skrivende kald før plugin'et ser det — og det er en ejerbeslutning om risikoprofil, ikke en fejl.

## 3. Skrivemønsteret

To værktøjer dækker alt hvad der blev brugt:

| Værktøj | Bruges til | Kravet |
| --- | --- | --- |
| `wp_alter_post` | målrettet søg-og-erstat i `post_content` | søgestrengen skal ramme **præcis én gang** |
| `wp_update_post` | helt nyt indhold (fx mange hrefs på én gang) | send kun det der er ændret, og verificér bagefter |

`wp_alter_post` har **ingen tør-kørsel**. Derfor blev hver redigering kørt først mod en gemt kopi af indholdet,
hvor scriptet tæller forekomster og nægter at skrive hvis en søgestreng rammer nul eller to gange. 16
redigeringer × 20 links gik igennem i første forsøg på den måde.

Til href-konvertering (outfit-guidens 14 direkte produktlinks) blev hele indholdet hentet, ændret lokalt og
skrevet tilbage — med en assertion om at teksten var **byte-identisk** bortset fra `href`-værdierne. Det er den
kontrol der gør en fuld genskivning forsvarlig.

## 4. De fire fælder

1. **`"isError": null` indeholder strengen "error".** En naiv `"error" in svar.lower()` læser et *vellykket*
   kald som en fejl. Kaldet svarede `1 replacement applied to post_content of post #8`, og scriptet stoppede.
   Tjek feltet (`res.get("isError") is True`), ikke teksten.
2. **En tilføjelse overlever sin egen søgestreng.** Redigeringer der *indsætter* et afsnit efter en søgestreng,
   kan køres igen og igen: søgestrengen findes stadig, så afsnittet blev sat ind to gange (tre posts, tre
   dubletter). Værnet er nu: står mållinket allerede i indholdet, springes redigeringen over — uanset
   søgestrengen. Generelt: gør skrivninger **idempotente**, ikke bare genoptagelige.
3. **En anden agent kan skrive i mellemtiden.** Kl. 09:44:28 — seks minutter efter mine redigeringer — blev post 8
   overskrevet af WordPress-brugeren **Juno**, og de tre links var væk. Revisionerne viser hvem og hvornår:
   `wp_get_revisions` → `author` og `size_change` pr. version. Links blev lagt tilbage oven på Junos version, og
   målingen blev kørt igen. Konsekvensen for planen: et `indsat` er kun sandt så længe målingen siger det.
4. **`wp_get_post` kan svare tomt.** Et enkelt kald returnerede `post_content` på 0 tegn midt i en kørsel. Var det
   blevet skrevet tilbage, havde posten været tom. Derfor en størrelsesvagt før hver skrivning: er svaret
   kortere end det man ville skrive, eller under nogle få hundrede tegn, standses kørslen.

## 5. Fundet der ændrede planen: én guide, to tekster

`/steampunk-outfit-ideas-for-women/` findes **to steder**, og de er ikke samme artikel:

| Kilde | Hvad den er | `/go/`-links |
| --- | --- | --- |
| Post 699, publiceret | "12 Complete Looks (With Prices)", 34 direkte `/product/`-links | **0** |
| `docs/library/steampunk-outfit-ideas-for-women.md` | omskrivning med 12 andre looks, alle 20 produkter linket — **erstattet 4/11 af et snapshot uden links** | 0 nu (20 i git-historikken) |

Planen havde kaldt de 20 for `indsat`, fordi de stod i filen. På sitet stod der nul. Det er præcis den slags
påstand et værn skal fange, og det gjorde det: målingen viste `nej` på alle 20. Løsningen blev at konvertere de
**14** af planens produkter der faktisk står i den publicerede artikel til `/go/<id>/`, og give de **6** der kun
findes i omskrivningen status `kladde`.

## 6. Sådan efterprøves det i dag

```bash
python3 tools/live_link_report.py --dato "4/11 2026"   # måler hver guide, følger hvert /go/, skriver dokumentet
python3 tools/live_link_report.py --dato "4/11 2026" --check   # fejler hvis dokumentet ikke matcher sitet
python3 tools/link_plan_check.py            # 40 rækker · 34 indsat · 6 klar · 0 uden pris
python3 tools/link_plan_check.py --selftest # 15 fixtures
```

`link_plan_check` kræver nu et `ja` i målingen for hver `indsat`-række og linket i filen for hver `kladde`-række.
Redigeres en side, skal målingen laves om, før planen må kalde linket indsat — et "indsat" uden en fersk måling
er en påstand, ikke en status.

## 7. Hvad der stadig kræver et menneske

- **Godkendelsen** af OAuth-flowet: én browser-klikning pr. token. Resten kan en agent gøre.
- **`drafts_only`**: kun ejeren kan slå den til eller fra, og det er med vilje.
- **En omskrivning** af outfit-guiden: de 6 produkter der ikke nævnes i artiklen bliver først `indsat` hvis
  teksten skrives om, så de kommer med — det er en redaktionel beslutning, ikke en teknisk.
