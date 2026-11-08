<?php
/**
 * Queue-siden: sproget og kildeposten skal kunne ses på rækken, ikke gættes ud fra
 * teksten. Required fra smoke.php.
 */

$GLOBALS['opts']['sgap_autopilot_enabled'] = 'yes';
$GLOBALS['opts']['sgap_quiet_start'] = 0;
$GLOBALS['opts']['sgap_quiet_end'] = 0;
$GLOBALS['opts']['sgap_locale_source'] = 'en';
$wpdb->next = 'wp_sgap_queue';

// En kilde-post og dens danske oversættelse, som køen ser dem.
$GLOBALS['rows'] = [
    (object)['id'=>102,'channel'=>'pinterest','title'=>'Messingbrille','status'=>'queued','remind'=>0,'attempts'=>0,
        'scheduled_at'=>'2026-11-09 10:00:00','published_at'=>null,'error'=>null,
        'locale'=>'da','source_id'=>100,'source_title'=>'Brass goggles'],
    (object)['id'=>100,'channel'=>'pinterest','title'=>'Brass goggles','status'=>'queued','remind'=>0,'attempts'=>0,
        'scheduled_at'=>'2026-11-09 10:00:00','published_at'=>null,'error'=>null,
        'locale'=>'','source_id'=>0,'source_title'=>null],
];
$wpdb->log = [];
ob_start(); SGAP_Admin::queue(); $html = ob_get_clean();
$sql = implode("\n", $wpdb->log);

check('queue page: the query reads language and source, not just the id', str_contains($sql,'q.locale') && str_contains($sql,'q.source_id') && str_contains($sql,'LEFT JOIN wp_sgap_queue'));
check('queue page: a language column names the language of each row', str_contains($html,'data-label="Language"') && str_contains($html,'<code>da</code>'));
check('queue page: a variant says it is a translation, the source says it is the original', str_contains($html,'<small>translation</small>') && str_contains($html,'<small>original</small>'));
check('queue page: a variant names and links the post it was translated from', str_contains($html,'href="#sgap-q-100"') && str_contains($html,'>#100</a>') && str_contains($html,'Brass goggles'));
check('queue page: a source row has no source of its own', substr_count($html,'data-label="Source">—') === 1);
check('queue page: every row can be jumped to by id', str_contains($html,'id="sgap-q-102"') && str_contains($html,'id="sgap-q-100"'));

$GLOBALS['rows'][0]->source_title = '<script>alert(1)</script>';
ob_start(); SGAP_Admin::queue(); $html = ob_get_clean();
check('queue page: a source title is escaped', str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>alert(1)</script>'));

// Kilden kan være slettet siden — så står id'et der, ikke et gæt.
$GLOBALS['rows'] = [
    (object)['id'=>103,'channel'=>'pinterest','title'=>'Messingbrille','status'=>'queued','remind'=>0,'attempts'=>0,
        'scheduled_at'=>'2026-11-09 10:00:00','published_at'=>null,'error'=>null,
        'locale'=>'da','source_id'=>7,'source_title'=>null],
];
ob_start(); SGAP_Admin::queue(); $html = ob_get_clean();
check('queue page: a deleted source is still named by its id', str_contains($html,'href="#sgap-q-7">#7</a>') && !str_contains($html,'data-label="Source">—'));

$GLOBALS['rows'] = [];
$wpdb->next = null;
