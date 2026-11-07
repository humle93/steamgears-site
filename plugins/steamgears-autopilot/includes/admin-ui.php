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
        echo self::stylesheet();
    }

    /** The stylesheet as a string, so a test can assert on it without rendering a page. */
    public static function stylesheet(): string {
        return '<style>
.sgap{--brass:#9b1c1f;--brass-d:#6e1114;--brass-l:#fbf3e1;--gold:#d4af37;--gold-d:#a8841f;--ink:#1f2328;--muted:#646970;--line:#e3c770;--ok:#1a7f37;--ok-l:#e6f4ea;--bad:#b32d2e;--bad-l:#fbeaea;--warn:#9a6700;--warn-l:#fff4d6;--card:#fff;max-width:1200px}
.sgap{--shadow-1:0 1px 2px rgba(16,24,40,.06),0 1px 3px rgba(16,24,40,.08);--shadow-2:0 10px 30px -14px rgba(31,35,40,.3);--ring:0 0 0 3px rgba(212,175,55,.4)}
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
.sgap .sgap-card{background:var(--card);border:1px solid var(--line);border-top:2px solid var(--gold);border-radius:16px;padding:18px 20px;margin:0 0 16px;box-shadow:var(--shadow-1);min-width:0;transition:box-shadow .18s ease}
.sgap .sgap-card:hover{box-shadow:var(--shadow-2)}
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
.sgap .sgap-caveat{margin:10px 0 0;padding:6px 0 0 10px;border-left:3px solid var(--brass);color:var(--muted);font-size:12px;line-height:1.5}
.sgap .sgap-caveats{margin:6px 0 0;padding-left:18px}
.sgap .sgap-caveats li{margin:0 0 8px;font-size:13px;line-height:1.55;color:#3c434a}
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
.sgap .sgap-chat-top-btns{display:inline-flex;gap:6px;flex-wrap:wrap}
.sgap .sgap-chat-top-btns .button{min-height:30px;line-height:28px}
.sgap #sgap-chat-tools-pill .sgap-pill{font-variant-numeric:tabular-nums}
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
.sgap .sgap-field-input input,.sgap .sgap-field-input select{border-radius:10px;border:1px solid #d9c9a3;min-height:40px;padding:6px 12px;background:#fff;transition:border-color .15s,box-shadow .15s}
.sgap .sgap-field-input input:focus,.sgap .sgap-field-input select:focus{border-color:var(--brass);box-shadow:var(--ring);outline:none}
.sgap .sgap-num{width:100px}
.sgap .sgap-secret{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sgap .sgap-clear{display:block;margin-top:6px;font-size:12px;color:var(--muted)}
.sgap .sgap-inline{display:flex;align-items:center;gap:8px}
.sgap .sgap-tabs{position:sticky;top:32px;z-index:6;display:flex;gap:6px;overflow-x:auto;margin:0 0 14px;padding:6px;border:1px solid var(--line);border-radius:999px;background:rgba(255,253,247,.92);backdrop-filter:blur(10px);box-shadow:var(--shadow-1);scrollbar-width:none}
.sgap .sgap-tabs::-webkit-scrollbar{display:none}
.sgap .sgap-tab{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;padding:9px 15px;border:1px solid transparent;border-radius:999px;background:none;cursor:pointer;color:var(--muted);font-weight:600;font-size:13px;text-decoration:none;transition:background .15s,color .15s,box-shadow .15s}
.sgap .sgap-tab:hover{color:var(--brass-d);background:var(--brass-l)}
.sgap .sgap-tab:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
.sgap .sgap-tab.on{background:var(--brass);border-color:var(--brass);color:#fff8e1;box-shadow:0 2px 10px -2px rgba(110,17,20,.45)}
.sgap .sgap-tab.on .dashicons{color:var(--gold)}
.sgap .sgap-tab-link{margin-left:auto}
.sgap .sgap-panel{border-radius:16px;padding:20px 22px}
.sgap .sgap-savebar{position:sticky;bottom:12px;z-index:6;display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 18px;margin-top:4px;background:rgba(255,253,247,.96);border:1px solid var(--line);border-radius:14px;box-shadow:0 12px 30px -16px rgba(31,35,40,.45);backdrop-filter:blur(10px)}
.sgap .sgap-dirty{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--warn);background:var(--warn-l);border:1px solid #f0dca0;border-radius:999px;padding:3px 10px}
.sgap .sgap-dirty::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--warn)}
.sgap .sgap-dirty[hidden]{display:none}
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
/* Tools browser (7.38.0): sticky search, category chips, card grid */
.sgap .sgap-toolsbar{position:sticky;top:46px;z-index:5;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);border:1px solid var(--line);border-radius:12px;padding:8px 12px;margin:0 0 12px;box-shadow:0 4px 14px rgba(110,17,20,.06)}
.sgap .sgap-toolsbar .dashicons{color:var(--brass);flex:none}
.sgap .sgap-toolsbar input[type=search]{flex:1;min-width:0;border:0;background:transparent;box-shadow:none;font-size:14px;padding:6px 2px;outline:none}
.sgap .sgap-toolsbar input[type=search]:focus{box-shadow:none}
.sgap .sgap-toolcount{flex:none;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;font-variant-numeric:tabular-nums}
.sgap .sgap-cats{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}
.sgap .sgap-cat{border:1px solid var(--line);background:#fffdf7;color:var(--brass-d);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s,transform .15s}
.sgap .sgap-cat span{color:var(--muted);font-weight:500;margin-left:4px}
.sgap .sgap-cat:hover{background:var(--brass-l);border-color:var(--gold)}
.sgap .sgap-cat:focus-visible{outline:2px solid var(--brass);outline-offset:2px}
.sgap .sgap-cat.is-on{background:var(--brass);border-color:var(--gold);color:#fff8e1}
.sgap .sgap-cat.is-on span{color:#f7dc8a}
.sgap .sgap-toolgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.sgap .sgap-toolcard{background:var(--card);border:1px solid var(--line);border-left:3px solid var(--gold);border-radius:12px;padding:12px 14px;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:box-shadow .15s,transform .15s}
.sgap .sgap-toolcard:hover{box-shadow:0 6px 18px rgba(110,17,20,.1);transform:translateY(-1px)}
.sgap .sgap-toolcard h3{margin:0 0 6px;font-size:13px;line-height:1.6;display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.sgap .sgap-toolcard code{background:#f7f4ee;border-radius:6px;padding:2px 6px;font-size:12px;color:var(--brass-d);word-break:break-word}
.sgap .sgap-badge{display:inline-flex;align-items:center;padding:1px 7px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.03em;text-transform:uppercase}
.sgap .sgap-badge.read{background:var(--ok-l);color:var(--ok)}
.sgap .sgap-badge.write{background:var(--warn-l);color:var(--warn)}
.sgap .sgap-badge.bound{background:var(--brass-l);color:var(--brass-d)}
.sgap .sgap-badge.mute{background:#f0f0f1;color:var(--muted)}
.sgap .sgap-toolmeta{margin:0 0 6px;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.sgap .sgap-tool-desc{margin:0;font-size:13px;line-height:1.5;color:var(--ink);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.sgap .sgap-toolempty{padding:18px;text-align:center;color:var(--muted)}
.sgap .sgap-calls-h{margin:22px 0 6px;font-size:15px;color:var(--ink)}
.sgap .sgap-calls{display:grid;gap:10px}
.sgap .sgap-call{border:1px solid var(--line);border-left:3px solid var(--ok);border-radius:10px;padding:10px 12px;background:var(--card)}
.sgap .sgap-call.is-bad{border-left-color:var(--bad);background:#fffdfd}
.sgap .sgap-call-top{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
.sgap .sgap-call-top strong{color:var(--ink);font-variant-numeric:tabular-nums}
.sgap .sgap-call-culprit{margin:6px 0;font-size:13px;color:var(--bad);font-weight:600}
.sgap .sgap-call-err{display:block;margin:6px 0;padding:8px;background:#fbf3f3;border-radius:8px;font-size:12px;white-space:pre-wrap;word-break:break-word;color:#7a1f20}
.sgap .sgap-call details{margin-top:6px}
.sgap .sgap-call summary{cursor:pointer;font-size:12px;color:var(--brass-d)}
.sgap .sgap-call details code{display:block;margin-top:6px;font-size:11px;line-height:1.6;white-space:pre-wrap;word-break:break-word;color:var(--muted)}
@media (max-width:782px){.sgap .sgap-toolsbar{top:0;border-radius:10px}.sgap .sgap-toolgrid{grid-template-columns:1fr}}
@media (prefers-reduced-motion:reduce){.sgap .sgap-toolcard,.sgap .sgap-cat{transition:none}}
/* 7.47.0 — Keys: one card per service, the pattern core uses for Settings → Connectors */
.sgap .sgap-panel--keys{border-top-color:var(--brass)}
.sgap .sgap-keys-top{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin:0 0 16px}
.sgap .sgap-keys-top h2{margin:0;font-size:18px}
.sgap .sgap-keys-top .sgap-sub{margin:6px 0 0;max-width:64ch}
.sgap .sgap-keys-tally{flex:none}
.sgap .sgap-keys{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:14px}
.sgap .sgap-keycard{display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;box-shadow:var(--shadow-1);transition:box-shadow .18s,border-color .18s}
.sgap .sgap-keycard:hover{box-shadow:var(--shadow-2)}
.sgap .sgap-keycard:focus-within{border-color:var(--brass);box-shadow:var(--shadow-2),var(--ring)}
.sgap .sgap-keycard--saved{border-left:3px solid var(--ok)}
.sgap .sgap-keycard--constant{border-left:3px solid var(--gold)}
.sgap .sgap-key-head{display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap}
.sgap .sgap-key-icon{flex:none;width:34px;height:34px;border-radius:10px;background:var(--brass-l);color:var(--brass);display:inline-flex;align-items:center;justify-content:center}
.sgap .sgap-key-icon .dashicons{font-size:18px;width:18px;height:18px}
.sgap .sgap-key-title{flex:1 1 180px;min-width:0}
.sgap .sgap-key-title h3{margin:0;font-size:14px;line-height:1.35;color:var(--ink)}
.sgap .sgap-key-title p{margin:4px 0 0;font-size:12px;line-height:1.5;color:var(--muted)}
.sgap .sgap-key-body{margin-top:12px;display:flex;flex-direction:column;gap:10px;flex:1}
.sgap .sgap-key-field label{display:block;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin:0 0 4px}
.sgap .sgap-key-field input[type=password]{width:100%;min-height:40px;border-radius:10px;border:1px solid #d9c9a3;padding:6px 12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.sgap .sgap-key-field input[type=password]:focus{border-color:var(--brass);box-shadow:var(--ring);outline:none}
.sgap .sgap-key-field .sgap-secret{width:100%}
.sgap .sgap-key-test{margin:0;font-size:12px;color:var(--muted);display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.sgap .sgap-key-foot{margin-top:auto;padding-top:10px;border-top:1px solid #f3ead6;display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
.sgap .sgap-key-foot p{margin:0;font-size:11px;line-height:1.5;color:var(--muted);flex:1 1 150px}
.sgap .sgap-key-links{display:inline-flex;gap:10px;flex-wrap:wrap}
.sgap .sgap-key-links a{font-size:12px;font-weight:600;color:var(--brass-d);text-decoration:none;display:inline-flex;align-items:center;gap:3px}
.sgap .sgap-key-links a:hover{text-decoration:underline}
.sgap .sgap-key-links .dashicons{font-size:13px;width:13px;height:13px}
.sgap .sgap-key-testbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0 0;padding-top:14px;border-top:1px solid #f3ead6}
.sgap .sgap-key-testout{font-size:12px;color:var(--muted);flex:1 1 200px}
.sgap .sgap-connected{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 12px;border:1px dashed var(--line);border-radius:12px;background:#fffdf7;font-size:13px}
@media (max-width:782px){.sgap .sgap-tabs{top:0;border-radius:14px}.sgap .sgap-keys{grid-template-columns:1fr}.sgap .sgap-savebar{bottom:0}}
/* 7.52.0 — dashboard: one heading per question, so every card below it has a place */
.sgap .sgap-section-head{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin:26px 0 12px}
.sgap .sgap-section-head h2{margin:0;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.sgap .sgap-section-head:after{content:"";flex:1 1 80px;height:1px;background:linear-gradient(90deg,var(--line),transparent)}
.sgap .sgap-section-hint{margin:0;font-size:12px;color:var(--muted)}
.sgap .sgap-attn{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 18px;padding:10px 14px;border:1px solid var(--line);border-left:4px solid var(--gold);border-radius:12px;background:#fffdf7}
.sgap .sgap-attn>strong{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
.sgap .sgap-attn.is-ok{border-left-color:var(--ok);background:#fbfdfb}
.sgap .sgap-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:10px;margin:0 0 14px}
.sgap .sgap-metric{border:1px solid #f0e6d2;border-radius:12px;padding:10px 12px;background:#fffdf7}
.sgap .sgap-metric b{display:block;font-size:20px;line-height:1.2;font-variant-numeric:tabular-nums;color:var(--ink)}
.sgap .sgap-metric span{display:block;font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)}
.sgap .sgap-metric em{display:block;margin-top:2px;font-style:normal;font-size:11px;color:var(--muted)}
@media (max-width:600px){.sgap .sgap-section-head{margin-top:20px}.sgap .sgap-metrics{grid-template-columns:1fr 1fr}}
/* 7.54.0 — every admin page says which question it answers, starting with the Settings tabs */
.sgap .sgap-panel-head{margin:0 0 18px;padding:0 0 12px;border-bottom:1px solid #f3ead6}
.sgap .sgap-panel-head h2{margin:0;font-size:16px;line-height:1.3;display:flex;align-items:center;gap:8px;color:var(--ink)}
.sgap .sgap-panel-head h2 .dashicons{font-size:18px;width:18px;height:18px;color:var(--brass)}
.sgap .sgap-panel-head .sgap-section-hint{margin:4px 0 0;font-size:12px;line-height:1.55;max-width:74ch}
.sgap .sgap-qgroup{margin:0}
.sgap .sgap-group-head{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin:18px 0 10px;padding:0 0 8px;border-bottom:1px solid #f3ead6}
.sgap .sgap-group-head h3{margin:0;font-size:14px;line-height:1.35;color:var(--ink)}
.sgap .sgap-group-head .sgap-pill{flex:none}
@media (forced-colors:active){
.sgap .sgap-card,.sgap .sgap-stat,.sgap .sgap-metric,.sgap .sgap-attn{border:1px solid CanvasText}
.sgap .sgap-pill,.sgap .sgap-badge,.sgap .sgap-tab,.sgap .sgap-cat{border:1px solid CanvasText;color:CanvasText}
.sgap .sgap-tab.on,.sgap .sgap-cat.is-on{background:Highlight;color:HighlightText}
.sgap .sgap-bar span,.sgap .sgap-progress progress::-webkit-progress-value{background:Highlight}
.sgap .sgap-panel-head,.sgap .sgap-section-head:after{border-color:CanvasText}
}
/* 7.60.0 — modern layer: same markup, new surface. Tokens first, then components; nothing below changes layout. */
.sgap{--brass:#14110e;--brass-d:#000;--brass-l:#f4efe6;--gold:#b08d57;--gold-d:#7a5c33;--ink:#17140f;--muted:#5b5348;--line:#e7dfd0;--card:#fff;--surface:#faf7f1;--r-sm:8px;--r-md:12px;--r-lg:18px;--hair:1px solid color-mix(in srgb,#b08d57 28%,#e7dfd0);--shadow-1:0 1px 1px rgba(20,17,14,.04),0 2px 6px -2px rgba(20,17,14,.08);--shadow-2:0 1px 1px rgba(20,17,14,.04),0 18px 36px -18px rgba(20,17,14,.28);--ring:0 0 0 3px color-mix(in srgb,#b08d57 45%,transparent);--ease:cubic-bezier(.16,1,.3,1);font-feature-settings:"tnum" 1,"cv11" 1;text-wrap:pretty}
.sgap h1,.sgap h2,.sgap h3{text-wrap:balance}
.sgap .sgap-head{position:relative;overflow:hidden;gap:16px;padding:22px 26px;border:0;border-radius:var(--r-lg);background:radial-gradient(120% 160% at 100% 0,rgba(176,141,87,.28),transparent 55%),linear-gradient(135deg,#14110e,#26201a);color:#e9dec4;box-shadow:var(--shadow-2),inset 0 0 0 1px rgba(176,141,87,.45)}
.sgap .sgap-head::after{content:"";position:absolute;inset:auto 26px 0 26px;height:1px;background:linear-gradient(90deg,transparent,#b08d57,transparent);opacity:.7}
.sgap .sgap-head h1{color:#f2eada;font-size:clamp(20px,1.2rem + .6vw,26px);font-weight:500;letter-spacing:.01em;text-shadow:none}
.sgap .sgap-head p{color:#c9b99a}
.sgap .sgap-logo{color:#d8b87a;font-size:28px}
.sgap .sgap-pill{border-radius:999px;padding:3px 11px;font-weight:600;letter-spacing:.01em;background:var(--surface);border:var(--hair);color:var(--ink)}
.sgap .sgap-pill.brass{background:var(--brass-l);color:#5a4326;border-color:#d9c7a3}
.sgap .sgap-pill.ok{background:#eaf5ee;color:#175c2c;border-color:#bfe0c9}
.sgap .sgap-pill.warn{background:#fff5dc;color:#7a5200;border-color:#efd9a0}
.sgap .sgap-pill.bad{background:#fcecec;color:#9b2323;border-color:#f0c6c6}
.sgap .sgap-card,.sgap .sgap-keycard,.sgap .sgap-draft,.sgap .sgap-toolcard,.sgap .sgap-stat,.sgap .sgap-rail,.sgap .form-table{border:var(--hair);border-top:var(--hair);border-radius:var(--r-lg);background:var(--card);box-shadow:var(--shadow-1)}
.sgap .sgap-stat{border-top:var(--hair);position:relative;transition:transform .35s var(--ease),box-shadow .35s var(--ease)}
.sgap .sgap-stat::before{content:"";position:absolute;inset:0 auto 0 0;width:3px;border-radius:var(--r-lg) 0 0 var(--r-lg);background:linear-gradient(#b08d57,#7a5c33)}
.sgap .sgap-stat b{font-weight:500;font-size:clamp(24px,1.2rem + .9vw,32px);letter-spacing:-.01em}
.sgap .sgap-stat span{letter-spacing:.1em;font-size:11px}
.sgap .sgap-card:hover,.sgap .sgap-keycard:hover,.sgap .sgap-stat:hover,.sgap .sgap-toolcard:hover{box-shadow:var(--shadow-2);transform:translateY(-2px)}
.sgap .sgap-card>h2,.sgap .sgap-panel-head h2{font-size:15px;font-weight:600;letter-spacing:.01em}
.sgap .sgap-card>h2 .dashicons,.sgap .sgap-panel-head h2 .dashicons{color:var(--gold-d)}
.sgap .sgap-section-head h2{letter-spacing:.16em;color:var(--gold-d)}
.sgap .sgap-section-head:after{background:linear-gradient(90deg,#d9c7a3,transparent)}
.sgap .button{border-radius:var(--r-sm);min-height:36px;font-weight:600;letter-spacing:.01em;transition:background-color .25s var(--ease),color .25s,border-color .25s,box-shadow .25s,transform .25s var(--ease)}
.sgap .button:active{transform:translateY(1px)}
.sgap .button-primary{background:#14110e;border:1px solid #14110e;color:#f2eada;box-shadow:inset 0 0 0 1px rgba(176,141,87,.5)}
.sgap .button-primary:hover,.sgap .button-primary:focus{background:#2a231b;border-color:#2a231b;color:#fff;box-shadow:inset 0 0 0 1px #b08d57}
.sgap .button:not(.button-primary){background:#fff;color:var(--ink);border:var(--hair)}
.sgap .button:not(.button-primary):hover{background:var(--brass-l);border-color:#b08d57;color:#000}
.sgap .button:focus-visible,.sgap a:focus-visible,.sgap summary:focus-visible{outline:2px solid #14110e;outline-offset:2px;box-shadow:0 0 0 4px #d8b87a}
.sgap .button.sgap-danger{color:#9b2323;border-color:#ebc4c4}
.sgap .sgap-field-input input,.sgap .sgap-field-input select,.sgap .sgap-key-field input[type=password],.sgap .sgap-key-field input[type=text],.sgap .sgap-studio-form input:not([type=checkbox]),.sgap .sgap-studio-form textarea,.sgap .sgap-studio-form select,.sgap .sgap-chat-form textarea{border-radius:var(--r-sm);border:1px solid #d8cdb8;background:#fffefb;min-height:40px;transition:border-color .2s,box-shadow .2s}
.sgap .sgap-field-input input:focus,.sgap .sgap-field-input select:focus,.sgap .sgap-key-field input:focus,.sgap .sgap-studio-form input:focus,.sgap .sgap-studio-form textarea:focus,.sgap .sgap-chat-form textarea:focus{border-color:#b08d57;box-shadow:var(--ring);outline:none}
.sgap .sgap-tabs{border:var(--hair);border-radius:var(--r-lg);background:color-mix(in srgb,#fff 82%,transparent);backdrop-filter:blur(14px) saturate(1.2);padding:5px}
.sgap .sgap-tab{border-radius:var(--r-md);color:var(--muted);font-weight:600;letter-spacing:.01em}
.sgap .sgap-tab:hover{background:var(--brass-l);color:var(--ink)}
.sgap .sgap-tab.on{background:#14110e;border-color:#14110e;color:#f2eada;box-shadow:0 6px 16px -8px rgba(20,17,14,.6)}
.sgap .sgap-tab.on .dashicons{color:#d8b87a}
.sgap .sgap-savebar{border:var(--hair);border-radius:var(--r-lg);background:color-mix(in srgb,#fff 88%,transparent);backdrop-filter:blur(14px) saturate(1.2);box-shadow:0 20px 40px -22px rgba(20,17,14,.5)}
.sgap .sgap-channel-card,.sgap .sgap-choice{border:var(--hair);border-radius:var(--r-md);background:#fff;transition:border-color .2s,box-shadow .2s,transform .3s var(--ease)}
.sgap .sgap-channel-card:hover,.sgap .sgap-choice:hover{transform:translateY(-1px);border-color:#b08d57}
.sgap .sgap-channel-card.on,.sgap .sgap-choice.on{border-color:#14110e;background:var(--brass-l);box-shadow:inset 0 0 0 1px #14110e}
.sgap .sgap-channel-card.on .dashicons{color:var(--gold-d)}
.sgap .sgap-switch input:checked+.sgap-slider{background:#14110e;box-shadow:inset 0 0 0 1px #b08d57}
.sgap .sgap-slider{background:#d6ccb8}
.sgap .sgap-bar{background:#eee6d6}.sgap .sgap-bar span,.sgap .sgap-progress progress::-webkit-progress-value{background:linear-gradient(90deg,#7a5c33,#d8b87a)}
.sgap .sgap-progress progress::-moz-progress-bar{background:linear-gradient(90deg,#7a5c33,#d8b87a)}
.sgap .sgap-rail li.on{background:var(--brass-l);color:var(--ink)}
.sgap .sgap-rail li.on .sgap-num{background:#14110e;border-color:#14110e}
.sgap .sgap-rail li.past .sgap-num,.sgap .sgap-steps li.done .sgap-dot{background:#1a7f37;border-color:#1a7f37}
.sgap .sgap-dot{border-color:#b08d57}
.sgap .sgap-attn{border:var(--hair);border-left:4px solid #b08d57;border-radius:var(--r-md);background:var(--surface)}
.sgap .sgap-metric,.sgap .sgap-connected{border:var(--hair);border-radius:var(--r-md);background:var(--surface)}
.sgap .sgap-key-icon{background:#14110e;color:#d8b87a;border-radius:var(--r-md)}
.sgap .sgap-keycard--saved{border-left:3px solid #1a7f37}.sgap .sgap-keycard--constant{border-left:3px solid #b08d57}
.sgap .sgap-key-links a,.sgap .sgap-more summary,.sgap .sgap-call summary{color:#5a4326}
.sgap .sgap-chat-log{background:var(--surface);border:var(--hair);border-radius:var(--r-lg)}
.sgap .sgap-user{background:#14110e;border-color:#14110e;color:#f2eada}
.sgap .sgap-assistant{background:#fff;border:var(--hair)}
.sgap .sgap-chip,.sgap .sgap-cat{border:var(--hair);background:#fff;color:var(--ink)}
.sgap .sgap-chip:hover,.sgap .sgap-cat:hover{background:var(--brass-l);border-color:#b08d57}
.sgap .sgap-cat.is-on{background:#14110e;border-color:#14110e;color:#f2eada}.sgap .sgap-cat.is-on span{color:#d8b87a}
.sgap .sgap-toolsbar{border:var(--hair);border-radius:var(--r-lg);background:color-mix(in srgb,#fff 85%,transparent);backdrop-filter:blur(14px)}
.sgap .sgap-toolcard{border-left:var(--hair)}
.sgap .sgap-toolcard code,.sgap .sgap-feed code{background:var(--surface);color:#5a4326}
.sgap table.widefat{border:var(--hair);border-radius:var(--r-lg)}
.sgap table.widefat thead th{background:var(--surface);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
.sgap table.widefat tbody tr{transition:background-color .2s}.sgap table.widefat tbody tr:hover td{background:#fcfaf5}
.sgap .sgap-empty .dashicons{color:#b08d57}
.sgap .notice{border-radius:var(--r-md);box-shadow:var(--shadow-1)}
/* Cards reflow by their own width, not the viewport: the same card works in a narrow column or a wide grid. */
.sgap .sgap-card,.sgap .sgap-keycard{container-type:inline-size}
@container (max-width:300px){.sgap .sgap-actions .button{flex:1 1 100%;justify-content:center}.sgap .sgap-key-foot{flex-direction:column}}
.sgap .sgap-field:has(input:focus-visible,select:focus-visible){background:linear-gradient(90deg,rgba(176,141,87,.07),transparent 70%);border-radius:var(--r-sm)}
.sgap .sgap-card:has(.sgap-bad),.sgap .sgap-card.sgap-bad{border-top-color:#b32d2e}
@media (prefers-reduced-motion:no-preference){@supports (animation-timeline:view()){.sgap .sgap-card,.sgap .sgap-keycard,.sgap .sgap-stat,.sgap .sgap-draft,.sgap .sgap-toolcard{animation:sgap-rise linear both;animation-timeline:view();animation-range:entry 0% entry 35%}}@keyframes sgap-rise{from{opacity:0;translate:0 14px}to{opacity:1;translate:0 0}}}
@media (prefers-reduced-motion:reduce){.sgap .sgap-card,.sgap .sgap-keycard,.sgap .sgap-stat,.sgap .sgap-channel-card,.sgap .sgap-choice,.sgap .button{transition:none!important;transform:none!important}}
@media (prefers-contrast:more){.sgap{--hair:1px solid #6b5a3c}.sgap .sgap-pill{border-color:#6b5a3c}}
@media (max-width:782px){.sgap .sgap-head{padding:16px 18px;border-radius:var(--r-md)}}
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

    /**
     * The line under a module's numbers that says what they leave out. Kept short here and
     * spelled out on the Fejlsøgning page, so the card stays readable and nothing is hidden.
     */
    public static function caveat(string $key): void {
        if (!class_exists('SGAP_Diagnostics')) {
            return;
        }
        $text = SGAP_Diagnostics::caveat($key);
        if ($text !== '') {
            echo '<p class="sgap-caveat"><strong>Caveat:</strong> ' . esc_html($text) . '</p>';
        }
    }

    public static function status_pill(string $status): string {
        $kinds = ['published' => 'ok', 'failed' => 'bad', 'sending' => 'warn', 'queued' => 'brass', 'reminded' => 'brass', 'pending' => 'brass', 'approved' => 'ok', 'rejected' => 'bad', 'used' => 'ok'];
        return self::pill($status, $kinds[$status] ?? '');
    }

    /** A dashboard group. One question per heading; the cards below it answer that question. */
    public static function section(string $title, string $hint = ''): void {
        echo '<div class="sgap-section-head"><h2>' . esc_html($title) . '</h2>'
            . ($hint !== '' ? '<p class="sgap-section-hint">' . esc_html($hint) . '</p>' : '') . '</div>';
    }

    /** One number with its label, for a row of related metrics inside a card. */
    public static function metric(string $label, $value, string $hint = ''): string {
        return '<div class="sgap-metric"><b>' . esc_html((string) $value) . '</b><span>' . esc_html($label) . '</span>'
            . ($hint !== '' ? '<em>' . esc_html($hint) . '</em>' : '') . '</div>';
    }

    public static function empty_state(string $icon, string $text): void {
        echo '<div class="sgap-card sgap-empty"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span><p>' . esc_html($text) . '</p></div>';
    }
}
