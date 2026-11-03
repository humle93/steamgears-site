# `/go/<id>/` var en kø-rute, ikke et produktlink — og det er rettet i 7.30.0

Link-planen for de ældre guides bruger `/go/<id>/` som kortlink for produkter. Den 3/11 blev ruten målt
mod det levende site, og den gjorde **ikke** det planen gik ud fra: alle fire prøvede produkt-ID'er svarede
`302` til **forsiden**. En læser der klikkede på et produktlink, endte altså på `https://steamgears.eu/` —
ikke på produktet. Det er den slags fejl der ser ud som en 302 og føles som et dødt link.

## 1. Målingen

```bash
curl -sS -o /dev/null -A 'Mozilla/5.0' -w '%{http_code} %{redirect_url}\n' https://steamgears.eu/go/477/
```

| ID | Produkt | Svar før 7.30.0 | Landede på |
| --- | --- | --- | --- |
| 477 | Copper Pilot Goggles | `302` | `https://steamgears.eu/` |
| 767 | Victorian Corset & Vest PDF | `302` | `https://steamgears.eu/` |
| 940 | Clockwork Seer pendant | `302` | `https://steamgears.eu/` |
| 809 | Steampunk Costume Checklist | `302` | `https://steamgears.eu/` |

Headerne viste `x-redirect-by: Steamgears`, så det var **vores eget plugin** der svarede — ikke
Redirection, Rank Math eller hosten. Det flyttede fejlen fra «mangler en opsætning i WordPress» til
«ruten slår det forkerte op».

## 2. Årsagen — set i kilden

`includes/core.php`, `SGAP_Tracking`:

```php
add_rewrite_rule('^go/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
...
$row = $wpdb->get_row($wpdb->prepare(
    "SELECT id, channel, angle, destination_url, utm_url FROM {$wpdb->prefix}sgap_queue WHERE id = %d", $id));
if (!$row) {
    wp_redirect(home_url('/'), 302, 'Steamgears');   // her forsvandt produktlinket
```

Ruten var bygget til **kø-poster**: `id` er en række i `wp_sgap_queue`, ikke et produkt. Produkt-ID'er
(399–968) findes ikke i den tabel, så opslaget returnerede `null` og fallback'en sendte læseren til
forsiden. Ruten gjorde præcis det den skulle — den blev bare brugt til noget andet end den var lavet til.

Det er værd at bemærke hvad der **ikke** var galt: rewrite-reglen matchede, `template_redirect` kørte,
og der var ingen 404. Alt så rigtigt ud i en statusbaseret crawl. Først da svaret blev læst — `location:
https://steamgears.eu/` — stod fejlen tydeligt.

## 3. Rettelsen (plugin 7.30.0)

Mangler kø-posten, forsøger ruten nu et produktopslag:

```php
public static function product_target(int $id): string {
    if ($id > 0 && function_exists('wc_get_product')) {
        $product = wc_get_product($id);
        if ($product && method_exists($product, 'get_permalink')) {
            $url = (string) $product->get_permalink();
            if ($url !== '') { return $url; }
        }
    }
    return home_url('/');
}
```

- **Kø-posten vinder stadig.** Er der en række i `sgap_queue` med det ID, redirecter den til postens
  destination og logger klikket som før — ingen ændring i den eksisterende adfærd.
- **Produktopslaget er guardet** med `function_exists('wc_get_product')`, så ruten ikke kan fatale på et
  site uden WooCommerce.
- **Ukendte ID'er lander på forsiden** som før — ingen ny 404-flade.
- **Produktklik tælles ikke** i kanal-statistikken: der er ingen kø-post at attribute til, og en kunstig
  række med `queue_id = 0` ville forurene ROI-tallene. Det står nu også i plugin-README (§Click tracking).

5 nye smoke-checks (341 PASS i alt) dækker produktopslaget, ukendt ID, `id 0` og de to kildekrav
(fallback-kaldet og guard'en).

## 4. Virkningen for S2.6

Planen for de ældre guides kan nu bruges som den er: **`/go/<id>/` peger på produktet** så snart
7.30.0 er aktiv på sitet. Det gælder også de fire guide-kladder i repoet
(`docs/library/guides-2026-11-03-*.md`), der allerede bruger `/go/411/`, `/go/475/`, `/go/481/` osv.

To ting skal stadig ske på sitet, og begge kræver ejeren:

1. **Plugins → Update** til 7.30.0 (zip'en ligger på `main`, selvopdateringen henter den).
2. De **20 `klar`-links** i link-planen skal indsættes på de guides der kun findes i WordPress.

Målt 3/11 har de publicerede guides **nul** `/go/`-links — `/how-to-make-a-steampunk-costume/`,
`/steampunk-gift-guide-2026/` og `/best-steampunk-books/` linker ingen produkter. Der er altså intet
live-link der peger forkert nu; fejlen ville først være opstået i det øjeblik linkene blev sat ind.

## 5. Kontrol bagefter

Efter opdateringen skal de fire ID'er give `302` til en `/product/…`-sti i stedet for til forsiden:

```bash
for id in 477 767 940 809; do
  curl -sS -o /dev/null -w "$id %{http_code} %{redirect_url}\n" "https://steamgears.eu/go/$id/"
done
```

Facit: `302 https://steamgears.eu/product/...`. Kommer der stadig `https://steamgears.eu/`, kører den
gamle plugin-version, eller WooCommerce er ikke aktiv — ikke at ruten mangler.
