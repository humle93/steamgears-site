# Fortrydelsesoplysningerne skal stå i ordremailen, ikke bag et link

**Dato:** 7/11 2026 · **Roadmap:** S4.12 · **Fil:** `wordpress/mu-plugins/sgap-withdrawal-email.php`

En ordrebekræftelse er det dokument kunden beholder. Et link til en side er det ikke: siden kan
ændre sig, flytte sig eller ligge nede, og så har kunden intet. Derfor stiller forbrugerretten krav
om, at oplysningerne leveres på et **varigt medium** — og bekræftelsen er netop det medium butikken
allerede sender.

S4.12 var formuleret som netop det: »Order confirmation email carries the withdrawal information
itself, not just a link«. Dette pas bygger den del, der kan bygges uden WordPress-adgang — og det er
hele teksten, fordi teksten **ikke** må opfindes.

## 1. Kilden er sitets egne sider, ikke en standardtekst

Hver sætning i mailen er et citat fra det butikken allerede lover på `/right-of-withdrawal/` og
`/refund_returns/`, fanget i [`trust-sider-2026-11-06.md`](trust-sider-2026-11-06.md). Det er ikke
en formalitet:

| Sætning i mailen | Hvor den kommer fra |
| --- | --- |
| 14 dage fra **modtagelsen**, ingen begrundelse | fortrydelsessiden |
| skriv først med ordrenummer og en klar meddelelse | retursiden: varer sendes fra forskellige lagre |
| send ikke retur, før du har hørt fra os | samme |
| du betaler returfragten, medmindre varen er fejlbehæftet | retursiden |
| tilbagebetaling inden 14 dage fra meddelelsen, samme betalingsmetode, inkl. den oprindelige standardfragt | retursiden |
| hygiejneundtagelsen ved tætsiddende tøj | retursiden |

Mailen **opfinder** derfor hverken en returadresse (butikken sender den rigtige pr. lager), en
fragtpris eller en hurtigere tilbagebetaling end de 14 dage sitet selv skriver. Det er de tre ting
en generisk fortrydelsestekst typisk lover, og som butikken ikke kan holde.

## 2. Hvilke mails der får blokken

Hooket er `woocommerce_email_after_order_table`, og beslutningen træffes på **mailens id** — ikke på
en gættet kontekst:

| Mail | Blok |
| --- | --- |
| `customer_processing_order` (bekræftelsen) | ja |
| `customer_completed_order` | ja, så oplysningen ikke forsvinder når ordren markeres færdig |
| `customer_on_hold_order` | ja, af samme grund |
| `new_order` (til butikken) | nej |
| `customer_invoice`, `customer_note` | nej — de er ikke bekræftelsen |
| alle mails med `sent_to_admin` | nej |

Listen kan ændres med filteret `sgap_withdrawal_email_ids`, så en butik der bruger en anden
mail-strøm ikke skal redigere i filen.

## 3. Én tekst, to formater

WooCommerce sender både HTML og ren tekst afhængigt af indstillingerne. Derfor er afsnittene skrevet
**én gang** (`sgap_withdrawal_paragraphs()`), og de to renderers læser samme liste. En tekst-only-mail
indeholder derfor de samme fakta som HTML-versionen — ikke et link og en hilsen.

- HTML: `<div class="sgap-withdrawal">` med `<h2>` og `<p>`, alt escaped.
- Tekst: afsnittene adskilt af blanke linjer, ingen tags.
- Ordrenummeret sættes ind i anden sætning, og det escapes: en ordre kan i teorien hedde noget med
  `<script>` i, og en mail er ikke stedet at opdage det.

## 4. Testen kan ikke bestå af sig selv

`tools/withdrawal_email_test.php` kalder den funktion WooCommerce kalder, med en stub-mail der kun
har `get_id()` og `get_content_type()`. **36 påstande**:

- hooket hænger på den rigtige handling med prioritet 20 og fire argumenter;
- bekræftelsen, completed og on-hold får blokken — `new_order`, `customer_note`, `customer_invoice`
  og alt med `sent_to_admin` gør ikke;
- hver af de seks fakta ovenfor står i den tekst kunden ville få;
- der er **ingen** fragtpris og ingen gratis fragt i teksten;
- ren tekst har ingen tags, men samme fakta og mindst fem afsnit;
- ordrenummeret escapes (`<script>` bliver `&lt;script&gt;`), og der indsættes ikke rå HTML-links;
- filtrene `sgap_withdrawal_email_ids`, `sgap_withdrawal_paragraphs` og `sgap_withdrawal_email_html`
  virker, og et filter på afsnittene rammer **begge** formater;
- to kald giver to blokke — der er ingen skjult tilstand der kan gøre den anden mail tom.

Testen er **værn nr. 25** i `tools/natlige-vaern.sh`, så en regression ikke kan snige sig ind.

## 5. Hvad der stadig mangler

**Ikke på det levende site.** Filen skal kopieres til `wp-content/mu-plugins/`, og den skal ses i en
rigtig ordrebekræftelse — både HTML- og tekstversionen — før S4.12 kan kaldes færdig. Det kræver
adgang, og sitet svarer stadig `Parse error` på alle dynamiske kald (`managed-wordpress-robots.php`),
så wp-admin er nede.

Tilbage af de 0,2 d: **0,05 d** — kopiér filen, send en testordre, læs mailen i begge formater.
Resten (0,15 d) er bygget og testet her.

## 6. Filer

| Fil | Ændring |
| --- | --- |
| `wordpress/mu-plugins/sgap-withdrawal-email.php` | ny: blokken, de to renderers og filtrene |
| `tools/withdrawal_email_test.php` | ny: 36 påstande |
| `tools/natlige-vaern.sh` | `fortrydelsesmail` er værn nr. 25 |
