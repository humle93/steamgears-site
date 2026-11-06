# MCP-værktøjsskemaer og aimlapi-afvisningen — diagnose og rettelse

6/11 2026. Fejlen der udløste arbejdet, ordret fra chatten:

```
New aimlapi: Error: tools.165.custom.input_schema: input_schema does not support
oneOf, allOf, or anyOf at the top level (invalid_request_error)
```

**Kort:** ét værktøj ud af 165 havde `oneOf`/`anyOf`/`allOf` i toppen af sit skema.
Anthropic-formatet kræver at roden er et rent objekt og afviser **hele** forespørgslen
før noget værktøj kører. Rettelsen er et mu-plugin der folder kombinatorerne ud for
alle abilities — ikke en ændring i autopilot-pluginnet.

## 1. Hvad fejlen betyder

`tools.165` er indeks 165 i den liste klienten sendte (nul-baseret). `custom` er den
blok hvor et brugerdefineret værktøj står, og `input_schema` er dets JSON Schema.
Validatoreren hos Anthropic (som aimlapi videresender til) tillader `oneOf`, `anyOf` og
`allOf` **inde i** en property, men ikke i roden: roden skal være

```json
{ "type": "object", "properties": { }, "required": [ ] }
```

Et skema som dette afvises, uanset hvor fornuftigt det er som JSON Schema:

```json
{
  "oneOf": [
    { "type": "object", "properties": { "path":   { "type": "string" } }, "required": ["path"] },
    { "type": "object", "properties": { "cursor": { "type": "string" } }, "required": ["cursor"] }
  ]
}
```

Det er den dyre slags fejl: den rammer ikke det ene værktøj, den stopper hele
samtalen. Ingen af de 164 andre værktøjer bliver kørt, og fejlen peger kun på et
indeks — ikke på et navn.

## 2. Hvor fejlen ikke var

Første mistanke var vores eget plugin, `plugins/steamgears-autopilot` **7.33.1**, som
bygger værktøjslisten i `includes/chat.php` (`tools()`, `loop_chat_completions()`,
`loop_anthropic()`) og henter skemaerne fra `SGAP_Abilities::object()`. Et grep over
hele pluginnet og dets release-zip:

```bash
grep -rn "anyOf\|oneOf\|allOf" plugins/steamgears-autopilot
# ingen træf
```

Alle vores egne skemaer er `{type: object, properties: …, additionalProperties: false}`.
Pluginnet kan altså ikke have lavet `tools.165` i version 7.33.1. Rettelsen hører
derfor ikke hjemme i plugin-filerne — den skulle et sted hen hvor **alle** værktøjer
passerer, også dem vi ikke ejer.

## 3. Hvor de 165 værktøjer kommer fra

Værktøjerne er WordPress-**abilities** gjort til MCP-værktøjer af MCP-adapteren
(AI Engine's abilities-bro). Vores egne bidrag er kategorierne `steamgears-autopilot`
(ca. 44) og `steamgears-tools` (ca. 33); resten er core, WooCommerce, Rank Math, AI
Engine og tredjepart. 165 værktøjer er derfor et site-dækkende tal, ikke et
plugin-tal — og derfor kan den skyldige være registreret af hvad som helst.

Konsekvensen for rettelsen: den skal sidde på et **filter alle abilities går igennem**,
ikke på et enkelt `register_ability()`-kald. WordPress har `wp_register_ability_args`
(argumenter, ability-navn) siden **6.9**, og den kører før registrering og validering.

## 4. Rettelsen: to filer i `wordpress/mu-plugins/`

| Fil | Størrelse | Hvad den gør |
| --- | --- | --- |
| `aimlapi-tool-schema-sanitizer.php` | 14.410 B | Selve foldningen. Ingen WordPress-afhængigheder, kan testes med rent PHP |
| `sgap-mcp-schema-fix.php` | 9.233 B | mu-plugin: sætter sanitizeren på `wp_register_ability_args`, gemmer og viser hvad der blev rettet |

Begge ligger nu i repoet og er committet på `main` (commit `0a98fbb`).

**Foldningen, regel for regel:**

| Input i rod | Bliver til |
| --- | --- |
| `allOf` | Snit: properties slås sammen, `required` forenes |
| `anyOf` / `oneOf` | Union: properties slås sammen, kun felter der er krævet i **alle** grene forbliver `required` |
| Gren der kun er `null` (`{"type":"null"}`, `{"const":null}`) | Droppes — det er en nullable rod, ikke et valg |
| `$ref` mod samme dokument (`#/$defs/…`) | Opløses først, så roden kan vurderes |
| Rod der ikke kan blive et objekt (fx union af scalars) | Permissivt objekt `{type:object, properties:{}, additionalProperties:true}` |
| Alt andet | Uændret — filteret returnerer argumenterne som de kom |

Skemaer uden kombinator i roden røres ikke: der er kun et billigt tjek, så de 164
andre værktøjer koster stort set intet.

Efter foldningen ser eksemplet fra §1 sådan ud:

```json
{
  "type": "object",
  "properties": {
    "path":   { "type": "string" },
    "cursor": { "type": "string" }
  }
}
```

`required` forsvinder med vilje: intet felt er krævet i begge grene. Prisen er at
"enten `path` eller `cursor`" ikke længere kan udtrykkes i skemaet — den regel skal
håndhæves i selve værktøjets handler, ellers får modellen lov at sende et tomt kald.

## 5. Hvad der er verificeret — og hvad der ikke er

Testscriptet `aimlapi-tool-schema-sanitizer-test.php` kører **42 checks, 0 fejl** i
PHP 8.4, uden WordPress. Dækket:

- rent objektsskema passerer uændret; ingen falsk positiv på et gyldigt skema
- root `anyOf` → objekt, begge grenes properties bevaret, fælles `required` droppet
- `allOf` → properties slået sammen, `required` forenet
- `oneOf` under en objekt-rod, nullable rod, `$ref` til `$defs` (og `$defs` bevaret)
- union af scalars → permissivt objekt; ukendte `required`-nøgler fjernet
- idempotens (to gennemløb ændrer intet), input-array muteres ikke
- OpenAI-formen `function.parameters`, camelCase `inputSchema`, skema som JSON-streng
- 167 værktøjer hvor nr. 165 er det ødelagte: fundet på indeks og navn, resten urørt
- filteret selv: omskriver ødelagt skema, bevarer de øvrige argumenter, lader rent,
  manglende og streng-formet skema være
- notice- og Site Health-teksterne for 0, 1 og flere rettede abilities

**Ikke verificeret:** at fejlen faktisk forsvinder på det live site. Det kræver at
filerne ligger i `wp-content/mu-plugins/` på installationen hos Simply, og det er
ikke gjort endnu — repoet er dokumentation og assets, ikke deployment.

## 6. Sådan ses det i wp-admin

Tre steder, ingen database:

1. **Notice** på alle admin-sider for brugere med `manage_options`:
   "MCP tool schema fix: Repaired 2 ability schemas: core/read, …" med linket
   *Hide this message*. Skjulningen huskes pr. bruger (user meta
   `sgap_mcp_schema_fix_dismissed`), og nøglen er et md5 af listen — derfor dukker
   noticen op igen, når en **ny** ability bliver rettet.
2. **Værktøjer → Site Health → Info**, sektionen "MCP tool schema fix" med Status,
   Last repair (dato og tid) og listen af ability-navne.
3. **Optionerne** `sgap_mcp_schema_fixed` (listen) og `sgap_mcp_schema_fixed_time`
   (timestamp), begge ikke-autoloadede. Med `WP_DEBUG` skrives hver rettelse også
   som én linje i PHP-fejlloggen.

Det er den liste der svarer på §1's oprindelige spørgsmål: *hvilket* værktøj var
`tools.165`. Bliver listen tom og fejlen ved med at komme, er værktøjet ikke bygget
af en ability, og så skal den rå værktøjsliste fra klienten undersøges i stedet.

## 7. Fælder

- **mu-plugins indlæser hver `.php` i mappen.** Testscriptet må derfor ikke lægges
  der: det slutter med `exit()` og ville tage sitet ned. Det hører i `plugins/tests/`
  med justerede stier, ikke i `wordpress/mu-plugins/`.
- **PHP's tomme array er `[]`, ikke `{}`.** Et skema med `"properties": []` er ikke
  det samme som `"properties": {}` for en streng validator. Sanitizeren efterlader
  tomme `properties` som de er; skal de ud som objekt, kræver det `(object)` før
  `json_encode()` i den kode der sender forespørgslen.
- **Filteret retter skemaet, ikke hensigten.** Se §4 om "enten/eller"-felter.
- **Kun rod-niveauet.** Kombinatorer inde i properties er tilladte og bliver stående
  — også dem der er teknisk unødvendige.
- **Rækkefølgen er ikke garanteret.** `wp_register_ability_args` kører ved
  registrering, så en ability der registreres efter vores filter er også dækket;
  caches i MCP-adapteren skal derimod tømmes hvis listen ser uændret ud.

## 8. Filer, commits og kontrol

| Sti | Git-blob | Størrelse |
| --- | --- | --- |
| `wordpress/mu-plugins/aimlapi-tool-schema-sanitizer.php` | `58a461e` | 14.410 B |
| `wordpress/mu-plugins/sgap-mcp-schema-fix.php` | `96dc130` | 9.233 B |

Commit `0a98fbb` på `main`, med `Modul / Før / Efter / Δ / Grundlag` i beskeden som
`tools/commit_check.py` kræver. Efter commit blev begge filer læst tilbage fra `main`
og deres blob-SHA sammenlignet med de lokale filer — identiske, altså er der ikke
sket ændringer undervejs.

## 9. Næste skridt

1. Læg de to filer i `wp-content/mu-plugins/` på sitet (fladt, ikke i undermappe).
2. Send en besked til botten igen og se om `tools.N.custom.input_schema`-fejlen er væk.
3. Åbn wp-admin og aflæs noticen eller Site Health-sektionen: de navne der står der,
   er de abilities der havde et uforeneligt skema. Det er svaret på hvem `tools.165` var.
4. Kommer fejlen stadig, og listen er tom: værktøjet kommer ikke fra en ability, og
   næste skridt er at dumpe klientens værktøjsliste og finde indeks 165 direkte.

Selve foldningen er generel og kan blive relevant igen ved næste plugin-opdatering —
også hos tredjepart. Den koster ingenting når intet er i vejen, og den logger hvad
den rører.
