# Vedligeholdelsesrutiner genoprettet — 24/10 2026

Efter genopbygningen (23/10) er de fire faste rutiner fra Master Workshop Plan sat op igen:

| Rutine | Skema | ID |
|---|---|---|
| Daglig drift/checkout-tjek | daglig 08:00 | a85782de-be2e-4049-882d-bec027477017 |
| Ugentlig backup + 1 indholdselement | søndag 12:00 (verificerer 3:30-backup) | 48ebd228-7ccf-4877-9b93-9cd66f13a989 |
| Månedlig GSC/SEO/links-tjek | månedlig dag 2, 09:00 | 5ae7b3a0-0157-4639-bcb7-093bb0cea35c |
| Kvartalsvis fuld crawl + top-20 | 5. jan/apr/jul/okt, 09:00 | 28cd2e8c-59d2-4719-bece-165c782c322d |

Alle prompts indeholder 429-reglen: W3TC WAF rate-limiter raw curl → browser-session REST eller vent 429 ud; mellemrum mellem kald.

## Testordre-status (verificeret via Woo REST 24/10 ~20:05)
- Ordeliste: **1 ordre i alt — #688** (stripe, $49.95, status `refunded`, 24/10 00:35). Ingen nye ordrer siden.
- #688 = gårsdagens **test-mode**-ordre (end-to-end verificeret 24/10 inkl. fuld refundering; ingen rigtige penge flyttet — se "Stripe-spor" i wiki).
- Stripe-gateway: `enabled=true`, **`testmode=no` → LIVE mode**.
- **Ikke kørt endnu: den rigtige live-ordre** (KYC/payout-verifikation + reelt gebyr). Det kræver en reel betaling og er Hennings/Henning-godkendt trin — ikke automatiseret.

## Eksisterende relaterede rutiner (uændret)
- Backup til Drive: søndag 03:30 (625f5d7f)
- GSC+GA4 performance-refresh: mandag 08:30 (61241897)
- Site check + SEO hver 3. dag: 10:17 (67c3caae)
