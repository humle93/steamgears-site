# SEO/AEO/GEO Ugerotations-log

ISO-uge mod 4: rest 1=SEO, rest 2=AEO, rest 3=GEO, rest 0=VEO/SMO/CRO.

## 2026-10-27 — ISO-uge 44 (44 mod 4 = 0 → VEO/SMO/CRO)

**NB:** Opgavebeskrivelsen og gemini-masterguide-11-discipliner.md matcher: uge 44 rest 0 = VEO/SMO/CRO.

### GSC (verificerbare tal, 13-26/10)
- Top side: /diy-steampunk-goggles/ — 5 klik, 80 imp, CTR 6,3%, pos 7,5
- /how-to-make-a-steampunk-costume/ — 2 klik, 32 imp, pos 8,3
- /steampunk-costume-ideas/ — 2 klik, 46 imp, CTR 4,3%, pos 13,0
- 0-klik prøver med imp: / (23, pos 13,3), /about/ (18, pos 3,1), /privacy-policy/ (17), /shop/ (17, pos 4), /steampunk-accessories/ (46, pos 19,3), /steampunk-cosplay-for-beginners/ (16, pos 9)
- Query-vindere: "steampunk costume ideas" (1 klik, pos 10), "steampunk goggles diy" (4 imp, pos 13), "steampunk hoodie" (4 imp, pos 5), "how to make steampunk goggles out of cardboard" (4 imp, pos 9)

### Title/desc-audit af 9 ugens nye sider (alle HTTP 200, live)
Fixed via Rank Math updateMeta REST:
- 891 halloween-men: titel 65→"Steampunk Halloween Costumes for Men: 10 Looks" (49 tegn)
- 805 3d-print: 83→"3D-Print Steampunk Parts: Gears & Props Guide"
- 875 dieselpunk: 64→"How to Build a Dieselpunk Costume: DIY Guide"
- 798 vs-dieselpunk: 73→"Steampunk vs. Dieselpunk: Pick Your Look"; desc 168→148 tegn
- 913 costume-women: desc afkortet ufuldstændig → ny 157-tegns desc
- 804 gift-guide: desc 159→141 tegn
- 803 closet: desc 162→141 tegn
- OK fra start: 791 (73 tegn titel — beholdt, brand-suffix udenfor 60-grænse), 806, 891 desc 145 OK

### Intern linkbuilding (2+ nye interlinks, REST PUT verified HTTP 200)
- Post 15 (steampunk-costume-ideas): ny paragraf med links til /steampunk-vs-dieselpunk/ + /dieselpunk-costume/ (beskrivende ankertekster), indsat efter "Building Any of These on a Budget"
- Post 17 (steampunk-cosplay-on-a-budget): ny paragraf med links til /sustainable-steampunk/ + /steampunk-gift-guide-2026/, indsat efter "Where to Spend"-listen
- Verificeret live: "steampunk vs. dieselpunk" og "sustainable steampunk guide" fundet i browser-markdown

### Sitemap-status
- post-sitemap.xml: 18 URLs. De 3 nyeste posts (891 halloween-men, 913 costume-women, 875 dieselpunk, alle publish + HTTP 200) mangler — Rank Math sitemap-cache fra 27/10 01:53 UTC.
- Resubmitted post-sitemap.xml via GSC API (success). W3TC/sitemap-cache-flush kræver wp-admin → Henning-trin.
- GSC: post-sitemap lastSubmitted 2026-10-27T03:21Z, 18 submitted / 12 billeder, 0 errors.

### INCIDENT (løst)
- Fejl-PUT med {"content":"PLACEHOLDER"} overskrev post 891 midlertidigt. Gendannet 100% fra revision 918 (9619 tegn, verificeret live). Post 572 ramtes også kort under sitemap-triggerforsøg; gendannet fra revision 843 (3457 tegn). Ingen permanent skade.
- Lektion: REST PUT på posts SKAL altid have fuldt content.raw med; brug updateMeta til meta-felter.

### Tilbage til Henning (wp-admin)
- W3TC: purge all caches → efter det viser /steampunk-halloween-costume-men/ ny titel og sitemap får 21 URLs
