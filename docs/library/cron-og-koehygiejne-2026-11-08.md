# Cron og kø: fra 326 ventende leveringer til 0

Målt og udført 8/11 2026 · site-optimering **91,3 → 92,9 %** (20,85 af 22,45 d) · S3.6 lukket, S1.2 og S4.6 delvist lukket.

Køalarmerne fra WooCommerce-integrationen var ikke bare støj. De var symptomet på, at **der ikke var
noget, der kørte cron** på siden. Denne post beskriver hvordan det blev målt, hvad der blev bygget, og
hvad der stadig kræver ejeren.

---

## 1. Det der blev målt

| Måling 8/11 | Før | Efter |
|---|---|---|
| Ventende `woocommerce_deliver_webhook_async` | **326** (alle forfaldne) | **0** |
| Fejlede `facebook_for_woocommerce_process_logs_batch` | **1 084** | **0** |
| Aktive WooCommerce-webhooks | **40** (op til 5 pr. emne mod samme URL) | **29**, 11 dubletter slået fra |
| Forældreløse cron-events | 3 (bl.a. Facebook-pluginnets heartbeat) | **0** |
| `sgap_daily_health` sidst skrevet | 7/11 20:34 | 8/11 14:08 (kørt af kicker'en) |
| Action Scheduler-handlinger i alt | 7 113 | 6 651 |

### Rodårsagen

1. **`DISABLE_WP_CRON` er sand** i `wp-config.php`.
2. **`wp-cron.php` kører ikke de forfaldne opgaver.** Målt med en probe: et cron-event planlagt fem
   sekunder ude blev *ikke* afviklet af et direkte kald (0 bytes, 200, 0,5 s), og `doing_cron`-låsen var
   fri. Kernen afviser selv i `_wp_cron()` når konstanten er sand.
3. **W3TC cacher siderne foran PHP.** Selv et kald til `/feed/` eller en 404-serveret side gik gennem
   cachen, så et `shutdown`-hook i et mu-plugin blev aldrig kørt på de sider. Bevis: et debug-felt blev
   først skrevet, da vi kaldte `/wp-json/` — altså en request der med sikkerhed eksekverer PHP.

Konsekvensen var ikke kun 326 webhook-leveringer: den daglige helbredsmåling, evergreen-recycling,
ugentlig artikel og Action Schedulers egen kø stod stille, mens alt så "planlagt" ud i admin.

---

## 2. Det der blev bygget

### 2.1 `sgap-queue-hygiene.php` (mu-plugin, 9,5 KB)

Kører dagligt kl. 04:10 og gør fire ting, alle idempotente:

1. **Sletter handlinger for plugins der ikke findes** på disken (`facebook-for-woocommerce`,
   `all-in-one-seo-pack`, `wpforms-lite`, `mailpoet`, `woocommerce-payments`). De kan aldrig køre igen.
2. **Sletter fejlede handlinger ældre end 14 dage** — undtagen plugin'ets egne `sgap_*`.
3. **Slår dublerede webhooks fra**: af flere aktive med samme emne *og* samme URL beholdes den ældste;
   resten får status `disabled` (ikke slettet), så det kan rulles tilbage med ét klik.
4. **Fjerner forældreløse cron-events** og tømmer køen i op til seks batches, hvis der er over 50
   ventende.

Alt skrives til optionen `sgap_queue_hygiene` med tal for hver kørsel, så effekten kan læses uden at
læse koden.

### 2.2 `sgap-cron-kicker.php` (mu-plugin, 8,3 KB)

To veje til at få cron til at køre igen uden at røre `wp-config.php`:

- **På almindelige sidevisninger:** et `shutdown`-hook kører forfaldne opgaver (højst otte, højst 30 s,
  højst hvert andet minut). Virker kun når PHP faktisk eksekverer — det gør det på ukachede sider.
- **`GET /wp-json/sgap/v1/tick`:** et offentligt endpoint med samme kontrakt som `wp-cron.php` — det
  kører kun det, der allerede er forfaldent, og højst én gang i minuttet (transient-lås). Det er den vej
  der ikke afhænger af trafik eller cache.

Endpointet tåler at blive kaldt af en ur: `.github/workflows/cron-tick.yml` prikker det **hvert 15.
minut** og fejler jobbet med en annotation, hvis svaret ikke er 200 efter tre forsøg.

**Fælde, der kostede en runde:** `tick` returnerer 200 med *tom* krop. Det er ikke en fejl i endpointet:
en af de kørte cron-opgaver (W3TC's oprydning) afslutter requesten, før JSON'en er sendt. Derfor skriver
kicker'en sin log **før** opgaverne kører (`state: running`) og igen bagefter, så en afbrudt kørsel stadig
kan dokumenteres. Workflowen accepterer 200 og læser ikke kroppen.

### 2.3 Målt virkning

```
tick 13:15:40 → ran: action_scheduler_run_queue, cmplz_every_five_minutes_hook,
                w3_pgcache_cleanup, w3_objectcache_cleanup,
                facebook_for_woocommerce_hourly_heartbeat_cron, dbclnr_cron_tasks,
                wc_admin_process_orders_milestone, wc_admin_unsnooze_admin_notes
                due_before: 18   seconds: 0.11
```

Efter kørslen: **pending = 0**, Facebook-handlinger **0**, webhook-dubletter **11 slået fra**,
cron-events for det fjernede Facebook-plugin **0**. Hygiejnen kørt to gange i træk giver
`orphans_deleted: 0, duplicates_disabled: 0` — den er idempotent.

---

## 3. De to roadmap-punkter der flyttede sig

### S1.2 — 0,2 af 0,5 d

Præmissen var forældet: **940 og 941 er allerede publiceret** og målt live
(`/product/clockwork-seer-pendant/` og `/product/the-aeronaut-pocket-watch/`, begge 200 med
`Product`-schema og priserne 129/149). De øvrige fire — **942, 765, 766 og 767** — lå i papirkurven efter
S1.4-oprydningen og er nu **gendannet som kladder** med pris, SKU, beskrivelse (975–1 376 tegn),
kategori og SEO-titel/-beskrivelse intakt. Det der mangler, er ikke arbejde: det er ejerens **ja/nej pr.
produkt** til at sælge dem.

### S4.6 — 0,1 af 0,4 d

Cache-bekymringen er **afkræftet med måling**: `/cart/`, `/checkout/` og `/my-account/` svarer alle med
`cache-control: no-cache, must-revalidate, max-age=0, private`, så W3TC ikke kan levere en cachet kurv —
uden at W3TC's config blev rørt.

Det der *ikke* er i orden, er forsendelsen: **kun én zone (Danmark)** med `free_shipping` og **intet
minimum**, og **ingen zone for EU/UK/RoW**. En kunde uden for Danmark får ingen forsendelsesmetode i
checkout. Det kræver ejerens priser og zoner — ikke kode.

---

## 4. Hvad der stadig kræver ejeren

| Punkt | Præcis mangel |
|---|---|
| AIMLAPI-kredit | Sidste generering 7/11 fejlede med *»You've run out of funds«* — der laves ingen nye kladder før kontoen er fyldt op |
| `sgap_alert_email` | Feltet er tomt; fejl registreres i `sgap_health`, men ingen mail går ud |
| Full auto vs. godkendelse | `sgap_full_auto_enabled = no`, og 40 elementer venter på godkendelse |
| Distributionsvej | `publish_mode = rss`, 0 Publer-konti, Pinterest 401, fem kanaler uden token |
| S1.2 | Ja/nej pr. produkt til 942, 765, 766, 767 |
| S4.6 | Zoner (EU/UK/RoW), fragtklasser og fri fragt-grænse |

De fire øverste står nu i `blocked_by` på plugin-projektet i `projektstatus.json`, så de også står i
`docs/projektstatus.md` §2.

---

## 5. Hvad der ikke er bevist

- **GitHub-workflowen er ikke set køre endnu.** Den ligger i repoet og kører første gang ved næste
  15-minutters-slot; endpointet er verificeret med `curl` (200, og loggen i `sgap_cron_kicker` viser
  hvilke opgaver der blev kørt).
- **At `wp-cron.php` er død er målt, men ikke forklaret** på host-niveau. Det kan være Simplys egen
  blokering eller en WAF-regel. Kicker'en gør forklaringen unødvendig, men hvis webhotellet senere
  retter det, kører begge veje parallelt uden skade (begge er låst til ét kald pr. minut).
- **Webhook-dubletterne kan komme igen**, hvis den eksterne tjeneste genopretter dem. Hygiejnen kører
  dagligt og slår dem fra igen; den er ikke en permanent fjernelse.
