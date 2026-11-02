# Invitationer til Command Center (2026-11-02)

**Status: bygget og testet med stubbe — 20 nye checks, 150 smoke-checks i alt grønne.**
Invitationen til `cpaleadsis@gmail.com` ligger som **pending**; intet er sendt, fordi
det kræver WP-adgang. Koden er i
[`includes/invites.php`](../../plugins/steamgears-autopilot/includes/invites.php) (plugin v7.18.0).

## Hvad en invitation er

Command Center har ingen egne konti. Et login **er** en WordPress-bruger med et
application password, og hvert `sgap/v1`-kald kræver `manage_options`. En invitation
er derfor:

1. en rigtig WordPress-bruger med rollen **administrator**, oprettet af pluginnet,
2. WordPress' egen new-user-mail med link til at vælge adgangskode,
3. en record i optionen `sgap_command_center_invites` med status, bruger-id og tidspunkt.

Application password bliver **ikke** oprettet eller mailet. WordPress viser det én
gang til den person der er logget ind, under Brugere → Profil. At sende det i en mail
ville lægge en credential i en indbakke for altid — og en administrator kan selv lave
et nyt på ti sekunder.

## Tre valg der er taget med vilje

**Ingen REST-rute.** `SGAP_REST` kan kaldes med et application password. Et endpoint
der opretter administratorer ville gøre en lækket app-adgang til en vej til at lave
nye administratorer. Invitationen findes derfor kun som ability, som kræver
`manage_options` gennem WordPress' egen ability-API.

**`blocked` i stedet for en ubrugelig administrator.** WordPress tilbyder kun
application passwords over HTTPS. Er sitet på http, oprettes brugeren ikke — status
bliver `blocked` med grunden, for en administrator der ikke kan logge ind i appen er
værre end en ærlig fejl.

**Idempotent pr. adresse.** En invitation der allerede er sendt, opretter intet ved
andet kald; findes e-mailen som WP-bruger i forvejen, bliver status `exists` uden at
der oprettes en ny. Et kald der timer ud kan derfor gentages uden at give to brugere.

## Den ventende invitation

`SGAP_Invites::DEFAULT_EMAILS` indeholder `cpaleadsis@gmail.com`. Ved første load efter
opdateringen skrives den som `pending` — én gang, og `seed()` rører aldrig en record
der allerede har en status. Der sendes intet af sig selv: en invitation der udløser
en ny administrator ved en tilfældig sidevisning er ikke en rabatkupon.

Sådan sendes den:

```
Claude → steamgears-autopilot/list-invites      → viser cpaleadsis@gmail.com som pending
Claude → steamgears-autopilot/invite-user { "email": "cpaleadsis@gmail.com" }
                                                 → administrator oprettet, WP mailer linket
```

Derefter: linket i mailen → vælg adgangskode → log ind → Brugere → Profil →
Application Passwords → navngiv den "Command Center" → indsæt brugernavn + password i appen.

## Hvad testene dækker

20 checks i [`plugins/tests/smoke.php`](../../plugins/tests/smoke.php) med stubbet
`wp_insert_user`, `wp_send_new_user_notifications`, `get_user_by` og
`wp_is_application_passwords_available`:

- seed tilføjer adressen som pending, sender intet, og nulstiller ikke en status der er ændret
- ugyldig adresse afvises med `sgap_invalid_email`, og der oprettes intet
- uden `create_users` afvises kaldet med `sgap_forbidden`
- en pending invitation bliver administrator, og WordPress' mail sendes med `both`
- intet password optræder i recorden eller i svaret
- login udledes af adressen (`cpaleadsis`), små bogstaver og renset; optaget login får suffix
- eksisterende WP-bruger giver `exists` uden dublet
- `wp_insert_user`-fejl gemmes som `failed` med WordPress' egen besked
- uden application passwords bliver status `blocked`, og der oprettes ingen bruger
- `list-invites` tæller pending og rapporterer `role`; `invite-user` er registreret som
  writer, `list-invites` som read-only
- ingen REST-rute i `core.php` nævner invitationer (værn mod at featuren sniger sig ind i det åbne API)

`plugins/tests/integration.php` kræver nu 15 abilities og kører `list-invites` mod en
rigtig WordPress — det bekræfter seed-vejen uden at oprette brugere. `invite-user`
køres **ikke** i CI, fordi den opretter en administrator og sender en rigtig mail.

## Hvad der ikke er bevist

- **Intet er sendt.** Hverken invitationen eller mailen er prøvet mod steamgears.eu;
  `wp_insert_user` og `wp_send_new_user_notifications` er kun kørt som stubbe.
- **Mailen er ikke set.** Om Simply's SMTP faktisk leverer WordPress' new-user-mail
  (og ikke ender i spam) er ikke testet.
- **Rollen er administrator.** Det er nødvendigt for `manage_options`, men det betyder
  også at en invitation giver fuld adgang til hele WordPress. Det står i ability'ens
  beskrivelse, og det er grunden til at invitationen ikke kan kaldes over REST.
