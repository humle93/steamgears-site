# AdSense-setup — steamgears.eu

**Status pr. 25/10 2026 (verificeret via Gmail): KONTOEN ER STADIG DEAKTIVERET. Genaktivering er IKKE gennemført.**

- Publisher ID: pub-3051998776166018 (deaktiveret 18/9 2026)
- Ingen genaktiveringsbekræftelse fra Google findes i indbakken — ingen mail med titel om reaktivering, velkomst eller godkendelse efter 18/9.
- Eneste AdSense-mail efter deaktiveringen: "User removed" (23/10 2026 22:14) — se sikkerhedsnote herunder.

## Gmail-spor (verificeret 25/10 2026)
- 18/8 2026: Advarsel "deaktiveres om 30 dage" (adsense-noreply@google.com)
- 18/9 2026 15:05 UTC: DEAKTIVERINGSBEKRÆFTELSE — "Din AdSense-konto (pub-3051998776166018) er blevet deaktiveret" (ikke aktiveret inden for 6 måneder). Gmail: mail/u/0/#inbox/1a0b50cf9b97099b
- 23/10 2026 22:14: "User removed" — brugeren yofyocikna@gufum.com blev fjernet fra kontoen af administrator cpaleadsis@gmail.com. OBS: ukendt bruger har siddet på kontoen — verificér at Henning selv fjernede den (sikkerhedstjek: login-aktivitet på Google-kontoen). Hvis Henning IKKE selv fjernede brugeren: skift Google-adgangskode + gennemgå tredjepartsadgang med det samme.
- Officielle trin (support.google.com/adsense/answer/12290346 + 10246390): Log ind på adsense.google.com → klik "Genaktivér konto" → fuldfør opsætning → indsend site til godkendelse (steamgears.eu). Direkte link: https://adsense.google.com/adsense/signup/reactivate
- Tidsgrænse: efter 7 års deaktivering kan kontoen ikke genaktiveres — ingen fare her, men genaktivering snarest anbefales.

## Teknisk status (klart til genaktivering)
- ads.txt live og verificeret 25/10: `google.com, pub-3051998776166018, DIRECT, f08c47fec0942fa0` ✅
- Site Kit AdSense-snippet live (ca-pub-3051998776166018, verificeret 21/10) ✅
- Complianz cookie-banner live (EEA-krav opfyldt) ✅
- Alt på sitet er altså klar — det ENESTE der mangler, er Henning's manuelle login.

## Næste konkrete trin (kun Henning kan gøre dette)
1. Log ind på https://adsense.google.com med cpaleadsis@gmail.com
2. Klik **Genaktivér** (evt. direkte: https://adsense.google.com/adsense/signup/reactivate)
3. Fuldfør aktivering og tilføj **steamgears.eu** som site (indsend til godkendelse)
4. Mens man er inde: tjek Settings → Access & authorization → Users, og bekræft at yofyocikna@gufum.com er væk + ingen andre ukendte brugere
5. Sig til Juno bagefter → verificering af snippet/ads.txt/kontostatus + opdatering af denne fil
