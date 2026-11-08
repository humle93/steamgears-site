# SEO-sporet 8/11 — målte indgreb på S1.4, S1.5, S2.5b, S3.3 og de to defekter

_Alt målt live på steamgears.eu gennem Novamira (`wp eval` / HTTP) 8/11 2026. Hvert tal er før/efter fra
samme metode. S4 er **ikke** rørt: de punkter er juridisk bindende valg._

## 1. Defekten: `/go/`-links til kladder

Før: 45 unikke `/go/`-id'er i 30 publicerede indlæg, hvoraf **33 pegede på `draft`-produkter**.
Indgreb: hvert draft-id blev peget om til den nærmeste offentligt tilgjængelige vare (samme type og tema),
og tre id'er uden nogen ækvivalent blev **linket ud** (beholdt som tekst):

| Draft → offentlig | Eksempel |
| --- | --- |
| 475, 479 → **477** | goggles |
| 481, 485 → **483** | top hatte |
| 487 → **489**, 497 → **493** | corset, frakke |
| 393/395/397/405/409/411 → **399** | hoodies |
| 559 → **1255**, 553/561/565/956 → **954**, 555/563 → **557** | t-shirts |
| 567 → **966**, 719/721/728 → **967**, 942 → **940**, 953 → **965**, 968 → **966**, 969/971/972 → **970** | prints, smykker, covers |
| **766** (bælte), **767** (PDF-mønster), **809** (tjekliste-PDF) | **ingen ækvivalent — linket er fjernet** |

Efter: **21 indlæg opdateret, 18 unikke `/go/`-id'er, 0 der peger på en draft.** Verificeret:
`/go/940/` → 302 → `/product/clockwork-seer-pendant/` (200).

## 2. Indlæg 898 var tomt

`Steampunk Couple Costumes: 10 Pairs Ready to Buy (2026)` var publiceret med **4 tegn** indhold.
Nu: **5 291 tegn**, ti par med hver sin begrundelse, 23 `/go/`-links til offentlige produkter, svarer **200**.
Ingen af de linkede varer er kladder.

## 3. S3.3 — mediepas

- **Alt-tekst:** 41 billeder i brug i publicerede indlæg; **8 manglede** alt-tekst → alle 8 sat til
  billedets titel. Efter: **0 mangler** (målt igen på alle 41).
- **Vægt:** 8 JPEG'er var over 300 KB. To blev re-komprimeret (1105: 371 → 220 KB, 1096: 473 → 438 KB),
  hvorefter processen blev **dræbt af værten (exit 9, tom stderr)** — sandsynligvis hukommelse i
  `wp_get_image_editor()`. De sidste **6 er stadig 316–846 KB** (1117, 1124, 1126, 975, 976, 1395).
  Det er ikke et link- eller indholdsproblem: det kræver et batch-værktøj med mere hukommelse (Imagify/
  WP-CLI `media regenerate`), ikke endnu et `wp eval`.

## 4. S1.4 — produktkladder

**46** `draft`-produkter fundet, **46 flyttet til papirkurv**, **0 tilbage**. Publicerede produkter: **20**.
Papirkurven er reversibel; ingen publiceret side linker længere til dem (se punkt 1).

## 5. S1.6 og S2.7 — hvad der blokerer dem, med evidens

- **S1.6 (lead magnets, sider 801 og 764):** kræver et formular-endpoint. WPForms Lite kan ikke oprette
  formularer via API (dokumenteret i `library/plugin-audit-2026-11-02.md`), og der er ingen aktiv
  formular-plugin med API-adgang. Uden en formular kan en capture-vej ikke måles — en side med et
  `<form>` der ikke sender nogen steder hen er værre end ingen formular.
- **S2.7 (script-loft):** mu-plugin-ændringer kan ikke skrives af Novamira (den skriver kun PHP i sin egen
  sandkasse), så emoji-scriptet og de to pixels kan ikke fjernes herfra. Det kræver ejerens File Manager
  eller en plugin-indstilling — derfor står loftet uændret, og ingen dage er bogført.
