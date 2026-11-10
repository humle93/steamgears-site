# Agent-brief — steamgears.eu

_Målt **2026-11-10** af `tools/agent_brief.py` mod https://steamgears.eu. Markdown-kilden er sandheden; `seo_agent.py` læser filen som en kilde på linje med `docs/seo.md`._

## Butikken som den er nu

| Faktum | Målt værdi |
| --- | --- |
| Produkter i kataloget | 20 |
| Valuta | DKK |
| Prisinterval | 165–1 000 DKK |
| Produktkategorier | 9 |
| Publicerede indlæg | 33 |
| Publicerede sider | 32 |
| Nyeste indlæg | 2026-11-08 |

## Sådan bruger agenten den

- **Antal produkter og prisinterval er målte tal.** Skriv planen efter dem i stedet for at gentage ældre audits' katalogtal, og antag aldrig en anden valuta end den ovenfor.
- **Nævn kun produkter og priser der findes nu.** Et forslag der kræver et produkt der er væk, eller en pris der ikke står i butikken, skal afvises af agenten selv.
- **Manglende måling siges højt.** Står en række som `utilgængelig`, skal planen sige det og foreslå en måling — ikke gætte tallet.

## Hvad filen ikke ved

- **Klik, ordrer, konvertering og tilskrivning.** De ligger i pluginets `sgap_events` og læses af pluginets egne agenter gennem abilityen `agent-brief`; denne fil har dem ikke.
- **GSC, GA4 og annoncedata.** Kræver ejerens adgang og står i de daterede målinger i `docs/library/`.
- **Lager, leveringstid og kostpris.** Ingen af dem findes i det offentlige katalog.
