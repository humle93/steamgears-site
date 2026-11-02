<?php
/**
 * Niche-scanneren som plugin-modul: samme rubrik som `niche_scan.py`, kørt inde
 * i WordPress, så grønne nicher kan blive draft-rækker i køen uden om REST.
 *
 * Forskellen fra Python-værktøjet er kun hvor den kører. Tallene er stadig
 * input — modulet henter intet og har ingen API-nøgler, så samme kandidater
 * giver samme score. Grænserne (2.000 / 50.000 listings, 3x / 17x sving) kommer
 * fra `docs/library/niche-vurdering-2026-11-01.md` og er ikke justeret her.
 *
 * To ting modulet nægter:
 *  - at opfinde en destination. Mangler nichen i `links`, bliver den sprunget
 *    over med en grund i stedet for at få en gættet URL.
 *  - at sende noget. Alle rækker skrives med `status: draft`, så Publer først
 *    ser dem efter en godkendelse.
 *
 * Attribution (`niche`, `score`, `verdict`) gemmes i optionen
 * `sgap_niche_attribution` pr. queue-id, fordi `sgap_queue` ikke har kolonner
 * til det. Skal det i selve rækken, kræver det en migration af tabellen.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Niche_Error extends InvalidArgumentException {}

class SGAP_Niche {
    /* Grænserne kommer fra niche-vurderingen, ikke herfra. */
    const LISTINGS_EASY = 2000;
    const LISTINGS_HARD = 50000;
    const SEARCH_FLOOR = 100;
    const SEARCH_CEILING = 20000;
    const SWING_FLAT = 3.0;
    const SWING_BRUTAL = 17.0;

    const WEIGHTS = ['listings' => 45.0, 'searches' => 25.0, 'swing' => 20.0, 'competitor' => 10.0];
    const COMPETITOR_POINTS = ['handmade' => 3.0, 'mixed' => 7.0, 'pod' => 10.0];

    const COMPETITOR_ALIASES = [
        'handmade' => 'handmade', 'håndlavet' => 'handmade', 'handlavet' => 'handmade',
        'mixed' => 'mixed', 'blandet' => 'mixed',
        'pod' => 'pod', 'print' => 'pod', 'print-on-demand' => 'pod'
    ];

    const FIELD_ALIASES = [
        'niche' => 'niche', 'navn' => 'niche', 'keyword' => 'niche',
        'searches' => 'searches', 'søgninger' => 'searches', 'soegninger' => 'searches',
        'listings' => 'listings', 'antal_listings' => 'listings', 'konkurrenter' => 'listings',
        'swing' => 'swing', 'sæsonsving' => 'swing', 'saesonsving' => 'swing', 'seasonality' => 'swing',
        'competitor' => 'competitor', 'konkurrent' => 'competitor', 'konkurrenttype' => 'competitor',
        'difficulty' => 'difficulty', 'sværhedsgrad' => 'difficulty', 'svaerhedsgrad' => 'difficulty',
        'price' => 'price', 'pris' => 'price'
    ];

    const GREEN = 'grøn';
    const YELLOW = 'gul';
    const RED = 'nej';

    /* Køens standarder for manuelt oprettede opslag (samme som queue_post-ability'en). */
    const CONTENT_TYPE = 'custom';
    const FORMAT = 'post';

    const ATTRIBUTION_OPTION = 'sgap_niche_attribution';
    const ATTRIBUTION_MAX = 200;

    /* ---------- scoring (ren PHP, ingen WordPress-kald) ---------- */

    public static function normalise(array $raw): array {
        $out = [];
        foreach ($raw as $key => $value) {
            if ($key === null) {
                continue;
            }
            $canonical = self::FIELD_ALIASES[trim(mb_strtolower((string) $key))] ?? null;
            if ($canonical !== null) {
                $out[$canonical] = $value;
            }
        }
        return $out;
    }

    /** @throws SGAP_Niche_Error */
    private static function number($value, string $field, string $label, bool $required): ?float {
        if ($value === null || trim((string) $value) === '') {
            if ($required) {
                throw new SGAP_Niche_Error("$label: feltet '$field' mangler");
            }
            return null;
        }
        $text = str_replace([' ', "\u{00a0}"], '', trim((string) $value));
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
            throw new SGAP_Niche_Error("$label: '$field' = '$value' ser ud som tusindadskillelse — skriv " . str_replace('.', '', $text));
        }
        if (!is_numeric($text)) {
            throw new SGAP_Niche_Error("$label: '$field' er ikke et tal: '$value'");
        }
        $number = (float) $text;
        if (!is_finite($number)) {
            throw new SGAP_Niche_Error("$label: '$field' er ikke et endeligt tal: '$value'");
        }
        return $number;
    }

    public static function score_listings(float $listings): float {
        $weight = self::WEIGHTS['listings'];
        if ($listings <= self::LISTINGS_EASY) {
            return $weight;
        }
        if ($listings > self::LISTINGS_HARD) {
            return 0.0;
        }
        $lo = log10(self::LISTINGS_EASY);
        $hi = log10(self::LISTINGS_HARD);
        $share = (log10($listings) - $lo) / ($hi - $lo);
        return $weight - ($weight - 10.0) * $share;
    }

    public static function score_searches(float $searches): float {
        $weight = self::WEIGHTS['searches'];
        if ($searches <= self::SEARCH_FLOOR) {
            return 0.0;
        }
        if ($searches >= self::SEARCH_CEILING) {
            return $weight;
        }
        $lo = log10(self::SEARCH_FLOOR);
        $hi = log10(self::SEARCH_CEILING);
        return $weight * (log10($searches) - $lo) / ($hi - $lo);
    }

    public static function score_swing(?float $swing): ?float {
        if ($swing === null) {
            return null;
        }
        $weight = self::WEIGHTS['swing'];
        if ($swing <= self::SWING_FLAT) {
            return $weight;
        }
        if ($swing >= self::SWING_BRUTAL) {
            return 0.0;
        }
        $share = ($swing - self::SWING_FLAT) / (self::SWING_BRUTAL - self::SWING_FLAT);
        return $weight * (1.0 - $share);
    }

    /** @throws SGAP_Niche_Error */
    public static function score_row(array $raw, int $index): array {
        $row = self::normalise($raw);
        $niche = trim((string) ($row['niche'] ?? ''));
        $label = $niche === '' ? "række $index" : '«' . $niche . '»';

        $searches = self::number($row['searches'] ?? null, 'searches', $label, true);
        $listings = self::number($row['listings'] ?? null, 'listings', $label, true);
        $swing = self::number($row['swing'] ?? null, 'swing', $label, false);
        $difficulty = self::number($row['difficulty'] ?? null, 'difficulty', $label, false);
        $price = self::number($row['price'] ?? null, 'price', $label, false);

        if ($searches <= 0) {
            throw new SGAP_Niche_Error("$label: 'searches' skal være større end 0");
        }
        if ($listings <= 0) {
            throw new SGAP_Niche_Error("$label: 'listings' skal være større end 0");
        }
        if ($swing !== null && $swing < 1) {
            throw new SGAP_Niche_Error("$label: 'swing' skal være mindst 1 (okt/jun-forhold)");
        }
        if ($difficulty !== null && !($difficulty >= 0 && $difficulty <= 100)) {
            throw new SGAP_Niche_Error("$label: 'difficulty' skal ligge mellem 0 og 100");
        }

        $competitor_raw = mb_strtolower(trim((string) ($row['competitor'] ?? '')));
        $competitor = self::COMPETITOR_ALIASES[$competitor_raw] ?? null;
        if ($competitor === null) {
            $allowed = implode(', ', array_keys(self::COMPETITOR_POINTS));
            throw new SGAP_Niche_Error("$label: 'competitor' skal være en af $allowed — fik '$competitor_raw'");
        }

        $parts = [
            'listings' => self::score_listings($listings),
            'searches' => self::score_searches($searches),
            'swing' => self::score_swing($swing),
            'competitor' => self::COMPETITOR_POINTS[$competitor]
        ];
        $available = 0.0;
        $points = 0.0;
        foreach ($parts as $key => $value) {
            if ($value !== null) {
                $available += self::WEIGHTS[$key];
                $points += $value;
            }
        }
        $score = round(100.0 * $points / $available, 1);

        $notes = [];
        if ($listings > self::LISTINGS_HARD) {
            $notes[] = 'hårdt nej: over 50.000 listings';
            $verdict = self::RED;
        } elseif ($score >= 70) {
            $verdict = self::GREEN;
        } elseif ($score >= 45) {
            $verdict = self::YELLOW;
        } else {
            $verdict = self::RED;
        }
        if ($swing === null) {
            $notes[] = 'sæsonsving mangler — 20 % af grundlaget er ikke med';
        }
        if ($difficulty === null) {
            $notes[] = 'sværhedsgrad mangler';
        }
        if ($competitor === 'handmade') {
            $notes[] = 'topresultater er håndlavet — POD konkurrerer på pris, ikke håndværk';
        }
        if ($swing !== null && $swing >= self::SWING_BRUTAL) {
            $notes[] = 'sæson ' . self::num($swing) . 'x — likviditet skal planlægges over de flade måneder';
        }

        return [
            'niche' => $niche,
            'searches' => $searches,
            'listings' => $listings,
            'swing' => $swing,
            'competitor' => $competitor,
            'difficulty' => $difficulty,
            'price' => $price,
            'score' => $score,
            'verdict' => $verdict,
            'parts' => $parts,
            'available_weight' => $available,
            'notes' => $notes
        ];
    }

    /** Højeste score først; sværhedsgrad og navn gør rækkefølgen reproducerbar. */
    public static function rank(array $rows): array {
        usort($rows, function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $a['score'] < $b['score'] ? 1 : -1;
            }
            $da = $a['difficulty'] ?? 999;
            $db = $b['difficulty'] ?? 999;
            if ($da !== $db) {
                return $da < $db ? -1 : 1;
            }
            return strcmp(mb_strtolower($a['niche']), mb_strtolower($b['niche']));
        });
        return $rows;
    }

    /** @throws SGAP_Niche_Error */
    public static function scan(array $records): array {
        if (!$records) {
            throw new SGAP_Niche_Error('ingen kandidater i input');
        }
        $scored = [];
        $index = 0;
        foreach ($records as $raw) {
            $index++;
            if (!is_array($raw)) {
                throw new SGAP_Niche_Error("række $index er ikke et objekt");
            }
            $scored[] = self::score_row($raw, $index);
        }
        return self::rank($scored);
    }

    /* ---------- bro: kandidater + links -> draft-payloads ---------- */

    public static function title_for(string $niche): string {
        $name = trim($niche);
        return $name === '' ? '' : mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }

    public static function valid_url(string $url): bool {
        $url = trim($url);
        if ($url === '' || preg_match('/\s/', $url)) {
            return false;
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    /** Spejler SGAP_Queue::key() (sanitize_key + længdegrænse). */
    public static function clean_key($value, string $default, int $max): string {
        $cleaned = preg_replace('/[^a-z0-9_\-]/', '', mb_strtolower((string) $value));
        return substr($cleaned, 0, $max) ?: $default;
    }

    /** Spejler SGAP_Queue::add()'s duplikatnøgle: sha256(channel|url|title). */
    public static function fingerprint(string $channel, string $destination_url, string $title): string {
        return hash('sha256', $channel . '|' . $destination_url . '|' . $title);
    }

    private static function normalise_name($name): string {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }

    /** Linkkort: niche => URL. Nøglerne sammenlignes uden hensyn til store bogstaver og ekstra mellemrum. */
    public static function links(array $links): array {
        $out = [];
        foreach ($links as $niche => $url) {
            if (self::normalise_name($niche) === '') {
                continue;
            }
            $out[self::normalise_name($niche)] = trim((string) $url);
        }
        return $out;
    }

    /** Præcis de felter SGAP_Queue::add() læser. Intet mere, intet mindre. */
    public static function payload_for(string $niche, string $channel, string $destination_url): array {
        return [
            'post_id' => 0,
            'channel' => $channel,
            'angle' => self::clean_key('niche', 'angle_a', 20),
            'content_type' => self::CONTENT_TYPE,
            'format' => self::FORMAT,
            'destination_url' => $destination_url,
            'title' => self::title_for($niche),
            'body' => '',
            'image_url' => '',
            'status' => 'draft',
            'scheduled_at' => null
        ];
    }

    /**
     * Bygger draft-payloads for de nicher der har en URL.
     *
     * @return array{drafts: array, skipped: array}
     * @throws SGAP_Niche_Error
     */
    public static function drafts(array $ranked, array $links, array $channels, bool $include_yellow = false, bool $require_image = false): array {
        $allowed = array_values(array_filter($channels, fn($c) => $c !== ''));
        $unknown = array_diff($allowed, SGAP_Publer::CHANNELS);
        if ($unknown) {
            throw new SGAP_Niche_Error('Ukendte kanaler: ' . implode(', ', $unknown) . '. Tilladte: ' . implode(', ', SGAP_Publer::CHANNELS));
        }
        if (!$allowed) {
            throw new SGAP_Niche_Error('Vælg mindst én kanal.');
        }
        $wanted = $include_yellow ? [self::GREEN, self::YELLOW] : [self::GREEN];
        $links = self::links($links);

        $built = [];
        $skipped = [];
        $seen = [];
        foreach ($ranked as $row) {
            if (!in_array($row['verdict'], $wanted, true)) {
                continue;
            }
            $niche = $row['niche'];
            $url = $links[self::normalise_name($niche)] ?? '';
            if ($url === '') {
                $skipped[] = ['niche' => $niche, 'channel' => '', 'reason' => 'ingen URL i linklisten'];
                continue;
            }
            if (!self::valid_url($url)) {
                $skipped[] = ['niche' => $niche, 'channel' => '', 'reason' => "ikke en http(s)-URL: $url"];
                continue;
            }
            foreach ($allowed as $channel) {
                if ($require_image && in_array($channel, SGAP_Publer::NEEDS_PHOTO, true)) {
                    $skipped[] = ['niche' => $niche, 'channel' => $channel, 'reason' => "$channel kræver foto, og modulet laver ingen billeder"];
                    continue;
                }
                $key = self::fingerprint($channel, $url, self::title_for($niche));
                if (isset($seen[$key])) {
                    $skipped[] = ['niche' => $niche, 'channel' => $channel, 'reason' => 'duplikat: samme kanal, URL og titel er allerede med'];
                    continue;
                }
                $seen[$key] = true;
                $built[] = [
                    'payload' => self::payload_for($niche, $channel, $url),
                    'attribution' => ['niche' => $niche, 'score' => $row['score'], 'verdict' => $row['verdict']],
                    'fingerprint' => $key
                ];
            }
        }
        return ['drafts' => $built, 'skipped' => $skipped];
    }

    /** Skriver rækkerne i køen som `draft` og husker attributionen pr. queue-id. */
    public static function queue(array $drafts): array {
        $written = [];
        foreach ($drafts as $item) {
            $payload = $item['payload'];
            $payload['status'] = 'draft';
            if (!class_exists('SGAP_Queue')) {
                $written[] = ['niche' => $item['attribution']['niche'], 'channel' => $payload['channel'], 'error' => 'sgap_no_queue'];
                continue;
            }
            $result = SGAP_Queue::add($payload);
            if (is_wp_error($result)) {
                $written[] = [
                    'niche' => $item['attribution']['niche'],
                    'score' => $item['attribution']['score'],
                    'channel' => $payload['channel'],
                    'error' => $result->get_error_code(),
                    'message' => $result->get_error_message()
                ];
                continue;
            }
            $id = (int) ($result['id'] ?? 0);
            self::remember($id, $item);
            $written[] = [
                'niche' => $item['attribution']['niche'],
                'score' => $item['attribution']['score'],
                'verdict' => $item['attribution']['verdict'],
                'channel' => $payload['channel'],
                'queue_id' => $id,
                'fingerprint' => $item['fingerprint']
            ];
        }
        return $written;
    }

    private static function remember(int $queue_id, array $item): void {
        if ($queue_id <= 0) {
            return;
        }
        $map = self::attribution();
        $map[$queue_id] = $item['attribution'] + [
            'channel' => $item['payload']['channel'],
            'fingerprint' => $item['fingerprint'],
            'at' => current_time('mysql')
        ];
        update_option(self::ATTRIBUTION_OPTION, array_slice($map, -self::ATTRIBUTION_MAX, null, true));
    }

    /** queue-id => [niche, score, verdict, channel, fingerprint, at]. */
    public static function attribution(): array {
        $map = get_option(self::ATTRIBUTION_OPTION, []);
        return is_array($map) ? $map : [];
    }

    /* ---------- plan til godkendelse ---------- */

    public static function num(?float $value, int $digits = 0): string {
        if ($value === null) {
            return '—';
        }
        return number_format($value, $digits, ',', ' ');
    }

    public static function plan(array $drafts, array $skipped): string {
        $lines = ['# Niche-modul — draft-rækker til godkendelse', ''];
        if ($drafts) {
            $lines[] = '| # | Niche | Score | Kanal | Titel | Destination |';
            $lines[] = '| --- | --- | ---: | --- | --- | --- |';
            foreach ($drafts as $index => $item) {
                $row = $item['payload'];
                $attribute = $item['attribution'];
                $lines[] = '| ' . ($index + 1) . ' | ' . $attribute['niche'] . ' | ' . self::num((float) $attribute['score'], 1)
                    . ' | ' . $row['channel'] . ' | ' . $row['title'] . ' | ' . $row['destination_url'] . ' |';
            }
        } else {
            $lines[] = 'Ingen draft-rækker: ingen grøn niche havde en URL.';
        }
        $needs_photo = [];
        foreach ($drafts as $item) {
            $channel = $item['payload']['channel'];
            if (in_array($channel, SGAP_Publer::NEEDS_PHOTO, true)) {
                $needs_photo[$channel] = true;
            }
        }
        if ($needs_photo) {
            $lines[] = '';
            $lines[] = '**Mangler foto:** ' . implode(', ', array_keys($needs_photo)) . ' — Publer afviser opslaget uden et billede. Brug `generate-image` med `queue_id` efter rækken er oprettet, eller slå `require_image` til for at holde dem ude.';
        }
        if ($skipped) {
            $lines[] = '';
            $lines[] = '## Sprunget over';
            $lines[] = '';
            foreach ($skipped as $item) {
                $where = $item['channel'] !== '' ? ' (' . $item['channel'] . ')' : '';
                $lines[] = '- **' . $item['niche'] . '**' . $where . ' — ' . $item['reason'];
            }
        }
        $lines[] = '';
        $lines[] = 'Alt er `status: draft`; intet er sendt til Publer. Titel og destination er de eneste felter modulet udfylder — `body` og `image_url` er tomme med vilje.';
        return implode("\n", $lines) . "\n";
    }

    /* ---------- abilities ---------- */

    /** Forhåndsvisning: scorer kandidaterne og viser planen uden at skrive noget. */
    public static function preview($input = null) {
        $input = (array) $input;
        try {
            $ranked = self::scan($input['candidates'] ?? []);
            $result = self::drafts(
                $ranked,
                (array) ($input['links'] ?? []),
                array_values(array_filter((array) ($input['channels'] ?? ['pinterest']), 'is_string')),
                !empty($input['include_yellow']),
                !empty($input['require_image'])
            );
        } catch (SGAP_Niche_Error $error) {
            return new WP_Error('sgap_niche', $error->getMessage());
        }
        return [
            'ranked' => array_map(fn($row) => [
                'niche' => $row['niche'], 'score' => $row['score'], 'verdict' => $row['verdict'], 'notes' => $row['notes']
            ], $ranked),
            'drafts' => count($result['drafts']),
            'skipped' => $result['skipped'],
            'plan' => self::plan($result['drafts'], $result['skipped'])
        ];
    }

    /** Skriver de samme rækker i køen som draft og returnerer queue-id'erne. */
    public static function queue_ability($input = null) {
        $input = (array) $input;
        try {
            $ranked = self::scan($input['candidates'] ?? []);
            $result = self::drafts(
                $ranked,
                (array) ($input['links'] ?? []),
                array_values(array_filter((array) ($input['channels'] ?? ['pinterest']), 'is_string')),
                !empty($input['include_yellow']),
                !empty($input['require_image'])
            );
        } catch (SGAP_Niche_Error $error) {
            return new WP_Error('sgap_niche', $error->getMessage());
        }
        return [
            'created' => self::queue($result['drafts']),
            'skipped' => $result['skipped'],
            'note' => 'Alle rækker er status: draft. Godkend dem på Queue-siden, og tilføj foto til ' . implode('/', SGAP_Publer::NEEDS_PHOTO) . ' før afsendelse.'
        ];
    }
}
