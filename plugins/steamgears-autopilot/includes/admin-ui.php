<?php
/**
 * Look of the Steamgears admin pages: one stylesheet scoped to .sgap and small
 * HTML helpers (cards, pills, stat tiles, channel badges). Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_UI {
    const ICONS = [
        'facebook' => 'facebook-alt', 'instagram' => 'instagram', 'pinterest' => 'pinterest', 'linkedin' => 'linkedin',
        'twitter' => 'twitter', 'threads' => 'format-status', 'bluesky' => 'cloud', 'mastodon' => 'admin-comments',
        'telegram' => 'email-alt', 'google' => 'google'
    ];

    private static $printed = false;

    public static function css(): void {
        if (self::$printed) {
            return;
        }
        self::$printed = true;
        echo '<style>
.sgap{--brass:#9b1c1f;--brass-d:#6e1114;--brass-l:#fbf3e1;--gold:#d4af37;--gold-d:#a8841f;--ink:#1f2328;--muted:#646970;--line:#e3c770;--ok:#1a7f37;--ok-l:#e6f4ea;--bad:#b32d2e;--bad-l:#fbeaea;--warn:#9a6700;--warn-l:#fff4d6;--card:#fff;max-width:1200px}
.sgap .sgap-head{display:flex;align-items:center;gap:14px;margin:8px 0 18px;padding:16px 18px;border-radius:14px;background:linear-gradient(135deg,#6e1114,#9b1c1f 55%,#b3272a);color:#fbecc0;border:2px solid var(--gold);box-shadow:0 0 0 3px rgba(212,175,55,.18),0 3px 12px rgba(110,17,20,.3)}
.sgap .sgap-head h1{color:#f7dc8a;margin:0;padding:0;font-size:22px;line-height:1.2;font-weight:600;text-shadow:0 1px 0 rgba(0,0,0,.25)}
.sgap .sgap-head p{margin:2px 0 0;color:#f3dfae;font-size:13px}
.sgap .sgap-logo{font-size:30px;line-height:1;color:var(--gold);animation:sgap-spin 12s linear infinite;display:inline-block}
@keyframes sgap-spin{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){.sgap .sgap-logo{animation:none}}
.sgap .sgap-pills{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px}
.sgap .sgap-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f0f0f1;color:var(--ink);border:1px solid #dcdcde;white-space:nowrap}
.sgap .sgap-pill.ok{background:var(--ok-l);color:var(--ok);border-color:#b7dfc2}
.sgap .sgap-pill.bad{background:var(--bad-l);color:var(--bad);border-color:#f0c4c4}
.sgap .sgap-pill.warn{background:var(--warn-l);color:var(--warn);border-color:#f0dca0}
.sgap .sgap-pill.brass{background:var(--brass-l);color:var(--brass-d);border-color:var(--line)}
.sgap .sgap-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:0 0 16px}
.sgap .sgap-stat{background:var(--card);border:1px solid var(--line);border-top:3px solid var(--gold);border-radius:12px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.sgap .sgap-stat b{display:block;font-size:26px;line-height:1.1;color:var(--ink);font-weight:600}
.sgap .sgap-stat span{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.sgap .sgap-stat a{text-decoration:none;color:inherit;display:block}
.sgap .sgap-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start}
.sgap .sgap-card{background:var(--card);border:1px solid var(--line);border-top:3px solid var(--gold);border-radius:14px;padding:16px 18px;margin:0 0 16px;box-shadow:0 1px 3px rgba(0,0,0,.05);min-width:0}
.sgap .sgap-card>h2{margin:0 0 12px;font-size:15px;display:flex;align-items:center;gap:8px;color:var(--ink)}
.sgap .sgap-card>h2 .dashicons{color:var(--brass)}
.sgap .sgap-card p.description{margin:6px 0}
.sgap .sgap-actions{display:flex;flex-wrap:wrap;gap:8px}
.sgap .sgap-actions form{margin:0}
.sgap .button{border-radius:8px;min-height:34px;line-height:32px;padding:0 14px;display:inline-flex;align-items:center;gap:6px}
.sgap .button .dashicons{font-size:16px;width:16px;height:16px;line-height:16px;margin:0!important;vertical-align:middle}
.sgap .button-primary{background:var(--brass);border:1px solid var(--gold);color:#fff8e1;box-shadow:inset 0 0 0 1px rgba(212,175,55,.35)}
.sgap .button-primary:hover,.sgap .button-primary:focus{background:var(--brass-d);border-color:var(--brass-d)}
.sgap .button:not(.button-primary){color:var(--brass-d);border-color:var(--gold);background:#fffdf7}
.sgap .button:not(.button-primary):hover{background:var(--brass-l);color:var(--brass-d);border-color:var(--brass)}
.sgap .button-small{min-height:28px;line-height:26px;padding:0 10px}
.sgap .button.sgap-danger{color:var(--bad);border-color:#e6b3b3}
.sgap .button.sgap-danger:hover{background:var(--bad-l);color:var(--bad);border-color:var(--bad)}
.sgap .button[disabled]{opacity:.65}
.sgap .sgap-progress{margin:14px 0 0}
.sgap .sgap-progress progress{-webkit-appearance:none;appearance:none;width:100%;height:12px;border:0;border-radius:999px;background:#efe6d7;overflow:hidden}
.sgap .sgap-progress progress::-webkit-progress-bar{background:#efe6d7;border-radius:999px}
.sgap .sgap-progress progress::-webkit-progress-value{background:linear-gradient(90deg,var(--brass),var(--gold));border-radius:999px;transition:width .4s}
.sgap .sgap-progress progress::-moz-progress-bar{background:linear-gradient(90deg,var(--brass),var(--gold));border-radius:999px}
.sgap .sgap-progress p{margin:6px 0 0;color:var(--muted)}
.sgap .sgap-feed{display:flex;align-items:center;gap:8px;padding:8px 0;border-top:1px solid #f0ebe2;flex-wrap:wrap}
.sgap .sgap-feed:first-of-type{border-top:0}
.sgap .sgap-feed code{flex:1 1 220px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:#f7f4ee;border-radius:6px;padding:5px 8px;font-size:12px}
.sgap .sgap-chan{display:inline-flex;align-items:center;gap:5px;font-weight:600;min-width:96px;text-transform:capitalize}
.sgap .sgap-chan .dashicons{color:var(--brass)}
.sgap .sgap-check{display:flex;gap:8px;align-items:flex-start;margin:8px 0}
.sgap .sgap-check .sgap-pill{flex:none}
.sgap table.widefat{border:1px solid var(--line);border-radius:12px;overflow:hidden;border-collapse:separate;border-spacing:0;box-shadow:none}
.sgap table.widefat thead th{background:#faf7f2;font-weight:600;color:var(--ink);border-bottom:1px solid var(--line)}
.sgap table.widefat td,.sgap table.widefat th{padding:9px 10px;vertical-align:middle}
.sgap .sgap-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.sgap .sgap-scroll table{min-width:640px}
.sgap .sgap-drafts{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
.sgap .sgap-draft{background:var(--card);border:1px solid var(--line);border-top:3px solid var(--gold);border-radius:14px;padding:14px 16px;display:flex;flex-direction:column;gap:8px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.sgap .sgap-draft h3{margin:0;font-size:15px;line-height:1.35}
.sgap .sgap-draft .sgap-text{white-space:pre-wrap;color:#2c3338;font-size:13px;line-height:1.5;max-height:12em;overflow:auto}
.sgap .sgap-draft .sgap-tags{color:var(--brass-d);font-size:12px}
.sgap .sgap-draft .sgap-meta{display:flex;justify-content:space-between;align-items:center;color:var(--muted);font-size:12px}
.sgap .sgap-draft .sgap-actions{margin-top:auto}
.sgap .sgap-empty{text-align:center;padding:30px 16px;color:var(--muted)}
.sgap .sgap-empty .dashicons{font-size:40px;width:40px;height:40px;color:#cdb894}
.sgap .notice{border-radius:10px}
.sgap .notice pre{font-family:inherit;margin:.6em 0}
.sgap .form-table{background:var(--card);border:1px solid var(--line);border-radius:14px;margin:0 0 18px;display:block;padding:4px 16px}
.sgap .form-table tbody{display:table;width:100%}
.sgap h2{color:var(--ink)}
.sgap .sgap-sub{color:var(--muted);margin:-6px 0 12px}
.sgap .sgap-chat{display:flex;flex-direction:column;gap:10px;max-width:900px}
.sgap .sgap-chat-top{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.sgap .sgap-chat-log{display:flex;flex-direction:column;gap:10px;min-height:280px;max-height:60vh;overflow-y:auto;padding:12px;background:#fbf8f3;border:1px solid #efe6d7;border-radius:12px}
.sgap .sgap-msg{max-width:85%;padding:10px 13px;border-radius:14px;line-height:1.5;font-size:14px;word-wrap:break-word}
.sgap .sgap-msg ul{margin:4px 0 4px 18px;list-style:disc}
.sgap .sgap-msg li{margin:2px 0}
.sgap .sgap-user{align-self:flex-end;background:var(--brass);color:#fff8e1;border:1px solid var(--gold);border-bottom-right-radius:4px}
.sgap .sgap-assistant{align-self:flex-start;background:#fff;border:1px solid var(--line);border-bottom-left-radius:4px}
.sgap .sgap-error{align-self:flex-start;background:var(--bad-l);color:var(--bad);border:1px solid #f0c4c4}
.sgap .sgap-msg-tools{margin-top:6px;font-size:11px;color:var(--muted)}
.sgap .sgap-typing span{display:inline-block;width:7px;height:7px;margin:0 2px;border-radius:50%;background:var(--brass);animation:sgap-dot 1s infinite ease-in-out}
.sgap .sgap-typing span:nth-child(2){animation-delay:.15s}.sgap .sgap-typing span:nth-child(3){animation-delay:.3s}
@keyframes sgap-dot{0%,80%,100%{opacity:.25;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}
.sgap .sgap-chat-suggest{display:flex;flex-wrap:wrap;gap:6px}
.sgap .sgap-chip{border:1px solid var(--gold);background:#fffaf2;color:var(--brass-d);border-radius:999px;padding:5px 12px;font-size:12px;cursor:pointer}
.sgap .sgap-chip:hover{background:var(--brass-l);border-color:var(--brass)}
.sgap .sgap-chat-form{display:flex;gap:8px;align-items:flex-end}
.sgap .sgap-chat-form textarea{flex:1;border-radius:10px;border-color:#dccaa8;padding:9px 12px;resize:vertical;min-height:44px}
.sgap .sgap-chat-form textarea:focus{border-color:var(--brass);box-shadow:0 0 0 1px var(--brass)}
.sgap .sgap-chat-form .button{min-height:44px}
.sgap .sgap-row label{display:inline-block;margin:0 10px 6px 0;vertical-align:top}
.sgap .sgap-studio-img{display:block;max-width:100%;max-height:420px;border-radius:10px;border:1px solid var(--line);margin:6px 0 10px}
.sgap .sgap-spin{animation:sgap-spin 1.2s linear infinite}
.sgap .sgap-card.sgap-bad{border-top-color:var(--bad)}
.sgap .sgap-studio-form input:not([type=checkbox]),.sgap .sgap-studio-form textarea,.sgap .sgap-studio-form select{border-radius:8px;border:1px solid #c3a65a;padding:6px 10px;min-height:38px;box-sizing:border-box}
.sgap .sgap-studio-form input:not([type=checkbox]):focus,.sgap .sgap-studio-form textarea:focus{border-color:var(--brass);box-shadow:0 0 0 1px var(--brass)}
.sgap .sgap-checklist .sgap-check-head{display:flex;justify-content:space-between;align-items:center;gap:8px}
.sgap .sgap-checklist h2{margin:0}
.sgap .sgap-bar{height:8px;border-radius:999px;background:#f1e6cc;margin:12px 0 6px;overflow:hidden}
.sgap .sgap-bar span{display:block;height:100%;background:linear-gradient(90deg,var(--brass),var(--gold));border-radius:999px}
.sgap .sgap-steps{list-style:none;margin:8px 0 0;padding:0}
.sgap .sgap-steps li{display:flex;align-items:center;gap:12px;padding:10px 0;border-top:1px solid #f3ead6}
.sgap .sgap-steps li:first-child{border-top:0}
.sgap .sgap-steps li div{flex:1;display:flex;flex-direction:column}
.sgap .sgap-steps li div span{color:var(--muted);font-size:12px}
.sgap .sgap-steps li.done strong{color:var(--muted);text-decoration:line-through}
.sgap .sgap-dot{flex:none;width:24px;height:24px;border-radius:50%;border:2px solid var(--gold);display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff}
.sgap .sgap-steps li.done .sgap-dot{background:var(--ok);border-color:var(--ok)}
.sgap .sgap-switch{display:inline-flex;align-items:center;gap:10px;cursor:pointer;position:relative}
.sgap .sgap-switch input[type=checkbox]{position:absolute;opacity:0;width:44px;height:24px;margin:0;cursor:pointer}
.sgap .sgap-slider{width:44px;height:24px;border-radius:999px;background:#cfc6b4;position:relative;transition:background .2s;flex:none}
.sgap .sgap-slider:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);transition:transform .2s}
.sgap .sgap-switch input:checked+.sgap-slider{background:var(--brass);box-shadow:inset 0 0 0 1px var(--gold)}
.sgap .sgap-switch input:checked+.sgap-slider:after{transform:translateX(20px)}
.sgap .sgap-switch input:focus-visible+.sgap-slider{outline:2px solid var(--gold);outline-offset:2px}
.sgap .sgap-channel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px}
.sgap .sgap-channel-card{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 8px;border:1px solid #e6d7b0;border-radius:12px;background:#fffdf7;cursor:pointer;text-align:center;font-weight:600;transition:all .15s}
.sgap .sgap-channel-card input{position:absolute;opacity:0}
.sgap .sgap-channel-card .dashicons{font-size:24px;width:24px;height:24px;color:#9a8a66}
.sgap .sgap-channel-card small{font-weight:400;color:var(--muted);font-size:11px}
.sgap .sgap-channel-card.on{border-color:var(--brass);background:#fdf1ef;box-shadow:inset 0 0 0 1px var(--brass)}
.sgap .sgap-channel-card.on .dashicons{color:var(--brass)}
.sgap .sgap-channel-card:focus-within{outline:2px solid var(--gold)}
.sgap .sgap-choice-row{display:flex;flex-wrap:wrap;gap:8px}
.sgap .sgap-choice{position:relative;display:flex;padding:9px 14px;border:1px solid #e6d7b0;border-radius:10px;background:#fffdf7;cursor:pointer}
.sgap .sgap-choice input{position:absolute;opacity:0}
.sgap .sgap-choice.on{border-color:var(--brass);background:#fdf1ef;box-shadow:inset 0 0 0 1px var(--brass)}
.sgap .sgap-choice:focus-within{outline:2px solid var(--gold)}
.sgap .sgap-big .sgap-choice{flex:1 1 200px;padding:14px 16px}
.sgap .sgap-big .sgap-choice span{display:flex;flex-direction:column;gap:4px}
.sgap .sgap-big .sgap-choice small{color:var(--muted);font-size:12px;line-height:1.4}
.sgap .sgap-field{display:grid;grid-template-columns:minmax(180px,34%) 1fr;gap:6px 20px;padding:14px 0;border-top:1px solid #f3ead6;align-items:start}
.sgap .sgap-field:first-child,.sgap .sgap-step h2+.sgap-field,.sgap .sgap-sub+.sgap-field{border-top:0}
.sgap .sgap-field-label label{font-weight:600;color:var(--ink)}
.sgap .sgap-field-label p{margin:3px 0 0;color:var(--muted);font-size:12px;line-height:1.45}
.sgap .sgap-field-input input.sgap-text,.sgap .sgap-secret input{width:100%;max-width:420px}
.sgap .sgap-field-input input,.sgap .sgap-field-input select{border-radius:8px;border:1px solid #c9b07a;min-height:38px;padding:4px 10px}
.sgap .sgap-field-input input:focus,.sgap .sgap-field-input select:focus{border-color:var(--brass);box-shadow:0 0 0 1px var(--brass)}
.sgap .sgap-num{width:100px}
.sgap .sgap-secret{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sgap .sgap-clear{display:block;margin-top:6px;font-size:12px;color:var(--muted)}
.sgap .sgap-inline{display:flex;align-items:center;gap:8px}
.sgap .sgap-tabs{display:flex;gap:4px;overflow-x:auto;margin:0 0 -1px;padding:0 2px;scrollbar-width:none}
.sgap .sgap-tabs::-webkit-scrollbar{display:none}
.sgap .sgap-tab{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;padding:10px 14px;border:1px solid transparent;border-bottom:0;border-radius:10px 10px 0 0;background:none;cursor:pointer;color:var(--muted);font-weight:600;font-size:13px;text-decoration:none}
.sgap .sgap-tab:hover{color:var(--brass-d)}
.sgap .sgap-tab.on{background:#fff;border-color:var(--line);border-top:3px solid var(--gold);color:var(--brass-d)}
.sgap .sgap-tab-link{margin-left:auto}
.sgap .sgap-panel{border-top-left-radius:0}
.sgap .sgap-savebar{position:sticky;bottom:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 16px;margin-top:-4px;background:rgba(255,253,247,.96);border:1px solid var(--line);border-radius:12px;box-shadow:0 -4px 14px rgba(0,0,0,.06);backdrop-filter:blur(4px)}
.sgap .sgap-wizard{display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start}
.sgap .sgap-rail{list-style:none;margin:0;padding:12px;background:#fff;border:1px solid var(--line);border-top:3px solid var(--gold);border-radius:14px;position:sticky;top:46px}
.sgap .sgap-rail li{display:flex;align-items:center;gap:10px;padding:9px 8px;border-radius:8px;cursor:pointer;color:var(--muted);font-weight:600}
.sgap .sgap-rail li:hover{background:var(--brass-l)}
.sgap .sgap-rail .sgap-num{width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;border:2px solid #e6d7b0;font-size:12px}
.sgap .sgap-rail li.on{color:var(--brass-d);background:var(--brass-l)}
.sgap .sgap-rail li.on .sgap-num{background:var(--brass);border-color:var(--gold);color:#fff}
.sgap .sgap-rail li.past .sgap-num{background:var(--ok);border-color:var(--ok);color:#fff}
.sgap .sgap-step h2{font-size:18px;margin:0 0 4px}
.sgap .sgap-step-nav{display:flex;justify-content:space-between;align-items:center;margin-top:18px;padding-top:14px;border-top:1px solid #f3ead6}
.sgap .sgap-more{margin:10px 0;border:1px dashed #e6d7b0;border-radius:10px;padding:6px 12px}
.sgap .sgap-more summary{cursor:pointer;color:var(--brass-d);font-weight:600;padding:4px 0}
.sgap .sgap-howto{margin:10px 0 12px 20px}
.sgap .sgap-howto li{margin:4px 0}
.sgap .sgap-done-box{white-space:nowrap;font-size:12px}
.sgap .sgap-test-result{margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sgap .sgap-done-list .sgap-card{box-shadow:none}
@media (max-width:782px){
.sgap .sgap-wizard{grid-template-columns:1fr}
.sgap .sgap-rail{position:static;display:flex;overflow-x:auto;gap:4px;padding:8px}
.sgap .sgap-rail li{flex:none;padding:6px 8px}
.sgap .sgap-rail li span:last-child{display:none}
.sgap .sgap-rail li.on span:last-child{display:inline}
.sgap .sgap-field{grid-template-columns:1fr;gap:6px}
.sgap .sgap-qtable thead{display:none}
.sgap .sgap-qtable,.sgap .sgap-qtable tbody,.sgap .sgap-qtable tr,.sgap .sgap-qtable td{display:block;width:100%;box-sizing:border-box}
.sgap .sgap-scroll .sgap-qtable{min-width:0}
.sgap .sgap-qtable tr{border:1px solid var(--line);border-radius:12px;margin:0 0 10px;padding:6px 10px;background:#fff}
.sgap .sgap-qtable td{display:flex;justify-content:space-between;gap:12px;padding:6px 0!important;border:0;text-align:right}
.sgap .sgap-qtable td:before{content:attr(data-label);font-weight:600;color:var(--muted);text-align:left}
.sgap .sgap-qtable td.sgap-qtitle{display:block;text-align:left;font-weight:600}
.sgap .sgap-qtable td.sgap-qtitle:before{display:none}
.sgap .sgap-savebar .description{display:none}
.sgap .button{min-height:40px}
}
@media (max-width:600px){.sgap .sgap-head{padding:12px 14px}.sgap .sgap-head h1{font-size:19px}.sgap .sgap-grid{grid-template-columns:1fr}.sgap .sgap-stat b{font-size:22px}.sgap .form-table{padding:4px 10px}}
</style>';
    }

    public static function pill(string $text, string $kind = ''): string {
        return '<span class="sgap-pill ' . esc_attr($kind) . '">' . esc_html($text) . '</span>';
    }

    public static function stat(string $label, $value, string $link = ''): string {
        $inner = '<b>' . esc_html((string) $value) . '</b><span>' . esc_html($label) . '</span>';
        return '<div class="sgap-stat">' . ($link !== '' ? '<a href="' . esc_url($link) . '">' . $inner . '</a>' : $inner) . '</div>';
    }

    public static function card_open(string $title, string $icon = ''): void {
        echo '<div class="sgap-card"><h2>' . ($icon !== '' ? '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>' : '') . esc_html($title) . '</h2>';
    }

    public static function card_close(): void {
        echo '</div>';
    }

    public static function channel(string $channel): string {
        $icon = self::ICONS[$channel] ?? 'share';
        return '<span class="sgap-chan"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span>' . esc_html($channel) . '</span>';
    }

    public static function status_pill(string $status): string {
        $kinds = ['published' => 'ok', 'failed' => 'bad', 'sending' => 'warn', 'queued' => 'brass', 'pending' => 'brass', 'approved' => 'ok', 'rejected' => 'bad', 'used' => 'ok'];
        return self::pill($status, $kinds[$status] ?? '');
    }

    public static function empty_state(string $icon, string $text): void {
        echo '<div class="sgap-card sgap-empty"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span><p>' . esc_html($text) . '</p></div>';
    }
}
