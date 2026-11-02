<?php
/**
 * Smoke test for the Steamgears Autopilot plugin with stubbed WordPress functions.
 * No WordPress or database needed. Usage: php plugins/tests/smoke.php
 * (Real WordPress + MySQL is covered by plugins/tests/integration.php in CI.)
 */
define('ABSPATH', __DIR__.'/fixtures/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60); define('ARRAY_A','ARRAY_A');
$GLOBALS['actions']=[]; $opts=[]; $settings=[];
function add_action($h,$cb){ $GLOBALS['actions'][$h][]=$cb; } function add_filter($h,$cb){ $GLOBALS['actions'][$h][]=$cb; }
function apply_filters($h,$v){return $v;}
function do_action($h,...$a){ foreach($GLOBALS['actions'][$h]??[] as $cb) call_user_func_array($cb,$a); }
function register_activation_hook(...$a){} function register_deactivation_hook(...$a){}
function is_admin(){return true;} function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;}
function update_option($k,$v){$GLOBALS['opts'][$k]=$v;} function add_option($k,$v){$GLOBALS['opts'][$k]??=$v;} function wp_mail($to,$s,$b){$GLOBALS['mails'][]=[$to,$s,$b]; return true;}
function register_setting($g,$k,$a){ $GLOBALS['settings'][$k]=$a; } function wp_clear_scheduled_hook($h){}
function add_menu_page($t,$m,$c,$slug,$cb){ add_action('page_'.$slug,$cb);} function add_submenu_page($p,$t,$m,$c,$slug,$cb){ add_action('page_'.$slug,$cb);}
function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function esc_url($s){return $s;} function esc_textarea($s){return htmlspecialchars((string)$s);}
function get_transient($k){return false;} function set_transient(...$a){} function delete_transient($k){} function settings_fields($g){} function submit_button(){}
function admin_url($p=''){return '/wp-admin/'.$p;} function rest_url($p=''){return '/wp-json/'.$p;} function home_url($p=''){return 'https://steamgears.eu'.$p;}
function get_bloginfo($k=''){return 'Steamgears';} function wp_timezone_string(){return 'UTC';} function get_the_title($id){return 'Post '.$id;} function get_permalink($id){return 'https://steamgears.eu/?p='.$id;} function has_post_thumbnail($id){return false;}
function get_posts($a=[]){return [];} function post_type_exists($t){return in_array($t,['post','page'],true);} function get_post($id){return null;} function get_user_meta($u,$k,$s=false){return '';} function update_user_meta($u,$k,$v){return true;} function get_edit_post_link($id,$c=''){return '/edit/'.$id;} function get_preview_post_link($id){return '/preview/'.$id;}
function wp_nonce_field(...$a){return '';} function wp_create_nonce($a=''){return 'n';} function selected($a,$b,$e){return $a===$b?' selected':'';} function checked($a,$b=true,$e=true){return $a===$b?' checked':'';}
function sanitize_text_field($s){return trim(strip_tags((string)$s));} function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));} function absint($v){return abs((int)$v);}
function esc_url_raw($u,$p=null){return preg_match('#^https?://#',$u)?$u:'';} function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
function wp_parse_args($a,$d){return array_merge($d,$a);} function add_query_arg($a,$u){return $u.(str_contains($u,'?')?'&':'?').http_build_query($a,'','&',PHP_QUERY_RFC3986);}
function current_time($t){return '2026-10-30 12:00:00';} function wp_date($f,$t=null){return date($f,$t??time());}
function current_user_can($c){return true;} function get_current_user_id(){return 1;} function get_site_transient($k){return false;} function set_site_transient(...$a){} function wp_remote_get(...$a){return new WP_Error('offline','offline');} function wp_nonce_field_stub(){} function delete_option($k){unset($GLOBALS["opts"][$k]);} function wp_json_encode($v){return json_encode($v);} function is_wp_error($v){return $v instanceof WP_Error;}
function wp_strip_all_tags($s){return strip_tags((string)$s);} function attachment_url_to_postid($u){return (int)($GLOBALS['attachment_for'][$u]??0);} function get_post_meta($id,$k='',$s=false){return $GLOBALS['postmeta'][$id][$k]??'';} function url_to_postid($u){return (int)($GLOBALS['postid_for'][$u]??0);} function get_post_status($id){return (string)($GLOBALS['poststatus'][$id]??'publish');} function wp_get_attachment_metadata($id){return $GLOBALS['attachment_meta'][$id]??[];} function get_post_mime_type($id){return (string)($GLOBALS['attachment_mime'][$id]??'');} function wp_die($m=''){throw new RuntimeException((string)$m);} function check_admin_referer($a=''){return true;}
class WP_Error{ function __construct(public $code,public $msg,public $data=[]){} function get_error_message(){return $this->msg;} }
class WP_REST_Response{ function __construct(public $data,public $status=200){} }
class WP_REST_Request implements ArrayAccess{ function __construct(public array $p){} function get_param($k){return $this->p[$k]??null;}
 function offsetGet($k):mixed{return $this->p[$k]??null;} function offsetExists($k):bool{return isset($this->p[$k]);} function offsetSet($k,$v):void{} function offsetUnset($k):void{} }
function wp_next_scheduled($h){return false;} function wp_schedule_event(...$a){$GLOBALS['cron'][]=$a[2];} function flush_rewrite_rules($h=true){$GLOBALS['flushed']=1;} function add_rewrite_rule(...$a){$GLOBALS['rules'][]=$a[0];} function wp_set_option_autoload_values($a){$GLOBALS['noauto']=$a;}
class W{ public $prefix='wp_'; public $posts='wp_posts'; public $postmeta='wp_postmeta'; function get_charset_collate(){return '';} public $insert_id=0; public $log=[]; public $next=null; public $row=null;
 function get_var($q){$this->log[]=$q; return $this->next;} function prepare($q,...$a){ if(count($a)==1&&is_array($a[0]))$a=$a[0]; return vsprintf(str_replace(['%s','%d','%f'],["'%s'",'%d','%F'],$q),$a);} function esc_like($s){return $s;}
 function get_results($q,$o=null){$this->log[]=$q;return $GLOBALS['rows']??[];} function get_row($q){$this->log[]=$q;return $this->row ?? null;} function query($q){$this->log[]=$q;return 0;}
 function insert($t,$d){$this->log[]="INSERT $t ".json_encode($d); $this->insert_id=42; return 1;} function update($t,$d,$w){$this->log[]="UPDATE $t ".json_encode($d);return 1;} }
$wpdb=new W; $wpdb->row=null;
require __DIR__.'/../steamgears-autopilot/steamgears-autopilot.php';
function check($name,$ok){ echo ($ok?'PASS ':'FAIL ')."$name\n"; if(!$ok) $GLOBALS['fail']=1; }
do_action('init'); do_action('admin_init'); do_action('admin_menu');
check('install: 9 tables', count($GLOBALS['ddl']??[])===9); check('install: publish log table', str_contains(implode("\n",$GLOBALS['ddl']??[]),'sgap_publish_log')); check('install: cleanup cron', in_array('sgap_daily_cleanup',$GLOBALS['cron']??[])); check('install: /go/ rule + flush', ($GLOBALS['rules'][0]??'')==='^go/([0-9]+)/?$' && !empty($GLOBALS['flushed'])); check('install: secrets not autoloaded', isset($GLOBALS['noauto']['sgap_ai_api_key'])); check('version stored', $opts['sgap_version']===SGAP_VERSION);
foreach(['steamgears-autopilot','sgap-settings','sgap-queue','sgap-ai','sgap-campaigns','sgap-etsy','sgap-reports','sgap-fullauto','sgap-merchant','sgap-setup','sgap-studio','sgap-chat','sgap-log'] as $p){ ob_start(); do_action('page_'.$p); $html=ob_get_clean(); check("page $p renders", strlen($html)>20); }
// secrets never echoed
$opts['sgap_ai_api_key']='SECRET123'; ob_start(); do_action('page_sgap-settings'); $html=ob_get_clean();
check('secret not in settings html', !str_contains($html,'SECRET123') && str_contains($html,'Saved'));
$san=$settings['sgap_ai_api_key']['sanitize_callback'];
check('empty secret keeps old', $san('')==='SECRET123'); check('new secret saved',$san('NEW')==='NEW');
$_POST['sgap_clear']['ai_api_key']=1; check('clear secret', $san('')===''); unset($_POST['sgap_clear']);
define('SGAP_TELEGRAM_BOT_TOKEN','fromconfig'); check('wp-config constant wins', SGAP_Settings::get('telegram_bot_token')==='fromconfig');
check('hour clamp', $settings['sgap_quiet_start']['sanitize_callback']('99')===23);
check('bool sanitize', $settings['sgap_autopilot_enabled']['sanitize_callback']('hack')==='no');
// quiet hours
check('quiet 23-7 at 2', SGAP_Queue::in_quiet_hours(2,23,7)); check('quiet 23-7 at 12 open', !SGAP_Queue::in_quiet_hours(12,23,7));
check('quiet 23-7 at 7 open', !SGAP_Queue::in_quiet_hours(7,23,7)); check('quiet 1-5 at 3', SGAP_Queue::in_quiet_hours(3,1,5)); check('quiet equal = none', !SGAP_Queue::in_quiet_hours(3,4,4));
// bots
check('bot pinterestbot', SGAP_Tracking::is_bot('Pinterestbot/1.0')); check('bot fb preview', SGAP_Tracking::is_bot('facebookexternalhit/1.1'));
check('human iPhone pinterest app', !SGAP_Tracking::is_bot('Mozilla/5.0 (iPhone) AppleWebKit Mobile [Pinterest/iOS]')); check('empty ua = bot', SGAP_Tracking::is_bot(''));
// redirect safety
check('safe own host', SGAP_Tracking::safe_target('https://steamgears.eu/x?utm=1')==='https://steamgears.eu/x?utm=1');
check('safe etsy', SGAP_Tracking::safe_target('https://www.etsy.com/listing/1')==='https://www.etsy.com/listing/1');
check('evil host blocked', SGAP_Tracking::safe_target('https://evil.com/')==='https://steamgears.eu/');
// REST
$r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'']));
check('queue missing title 400', $r instanceof WP_Error && $r->data['status']===400);
$wpdb->next=null; $r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'Pinterest','title'=>'Goggles','destination_url'=>'https://steamgears.eu/g/','angle'=>'angle_b']));
check('queue created 201', $r instanceof WP_REST_Response && $r->status===201 && $r->data['tracking_url']==='https://steamgears.eu/go/42/');
check('utm built', str_contains($r->data['utm_url'],'utm_source=pinterest') && str_contains($r->data['utm_url'],'utm_content=angle_b_product_pin'));
$wpdb->next=7; $r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'Goggles','destination_url'=>'https://steamgears.eu/g/']));
check('duplicate 409', $r instanceof WP_Error && $r->data['status']===409);
$opts['sgap_autopilot_enabled']='no'; $r=SGAP_REST::due(new WP_REST_Request([])); check('due off', $r['reason']==='autopilot_disabled');
$opts['sgap_autopilot_enabled']='yes'; $opts['sgap_quiet_start']=0; $opts['sgap_quiet_end']=0; $wpdb->next=8; $opts['sgap_daily_cap']=8;
$r=SGAP_REST::due(new WP_REST_Request([])); check('due cap reached', $r['reason']==='daily_cap_reached');
$wpdb->next=2; $r=SGAP_REST::due(new WP_REST_Request([])); check('due limit = min(batch5, 8-2)', str_contains(end($wpdb->log),'LIMIT 5'));
$r=SGAP_REST::draft_status(new WP_REST_Request(['id'=>3,'status'=>'bogus'])); check('draft bad status 400', $r instanceof WP_Error);
$r=SGAP_REST::queue_status(new WP_REST_Request(['id'=>3,'status'=>'published'])); check('queue status unknown id 404', $r instanceof WP_Error && $r->data['status']===404);
// Dashboard snapshot: one read-only call for an outside app (Command Center).
update_option('sgap_ai_api_key','TOPSECRET');
$snap=SGAP_REST::snapshot(new WP_REST_Request(['days'=>30,'limit'=>5]));
check('snapshot: every section present', is_array($snap) && isset($snap['generated_at'],$snap['site'],$snap['status'],$snap['counts'],$snap['results'],$snap['queue'],$snap['drafts'],$snap['settings'],$snap['missing_tables']));
check('snapshot: reports the installed version', ($snap['version']??'')===SGAP_VERSION);
check('snapshot: secrets only as set/not set', !str_contains(wp_json_encode($snap),'TOPSECRET') && ($snap['settings']['AI content']['ai_api_key']['value']??'')==='set');
check('snapshot: window echoes the requested days', ($snap['window']['days']??0)===30);

// --- Publer payloads ---
$row=['title'=>'Brass goggles','body'=>'Look sharp.','tracking_url'=>'https://steamgears.eu/go/5/','image_url'=>'https://steamgears.eu/img.jpg'];
$fb=SGAP_Publer::network_payload('facebook',$row,'');
check('publer: facebook is a link post with tracking url', $fb['type']==='link' && $fb['link']['url']==='https://steamgears.eu/go/5/' && $fb['link']['images']===['https://steamgears.eu/img.jpg']);
check('publer: instagram without photo is an error', is_string(SGAP_Publer::network_payload('instagram',$row,'')));
$ig=SGAP_Publer::network_payload('instagram',$row,'m1');
check('publer: instagram photo post, no url in caption', $ig['type']==='photo' && $ig['media'][0]['id']==='m1' && !str_contains($ig['text'],'/go/'));
$pin=SGAP_Publer::network_payload('pinterest',$row+['body'=>''],'m2');
check('publer: pinterest pin has title, link and photo', $pin['type']==='photo' && $pin['url']==='https://steamgears.eu/go/5/' && $pin['title']==='Brass goggles' && $pin['media'][0]['id']==='m2');
$x=SGAP_Publer::network_payload('twitter',['body'=>str_repeat('a',400)]+$row,'');
check('publer: twitter fits 280 (links count as 23) with link appended', mb_strlen($x['text'])-mb_strlen('https://steamgears.eu/go/5/')+23<=280 && str_ends_with($x['text'],'https://steamgears.eu/go/5/'));
check('publer: unknown channel is an error', is_string(SGAP_Publer::network_payload('myspace',$row,'')));
check('publer: job working', SGAP_Publer::parse_job(['data'=>['status'=>'working']])['state']==='working');
check('publer: job complete (data shape)', SGAP_Publer::parse_job(['success'=>true,'data'=>['status'=>'complete','result'=>['payload'=>['failures'=>[]]]]])['state']==='done');
check('publer: job completed (flat shape)', SGAP_Publer::parse_job(['status'=>'completed','payload'=>[]])['state']==='done');
check('publer: complete with failures = failed', SGAP_Publer::parse_job(['data'=>['status'=>'complete','result'=>['payload'=>['failures'=>['x'=>'token expired']]]]])['state']==='failed');
check('publer: job failed', SGAP_Publer::parse_job(['status'=>'failed'])['state']==='failed');
// --- AI parsing ---
$json='{"posts":[{"channel":"facebook","title":"T","text":"Hello","hashtags":["#steampunk","cosplay"],"alt_text":"A"},{"channel":"facebook","title":"dup","text":"x","hashtags":[],"alt_text":""},{"channel":"tiktok","title":"T","text":"x","hashtags":[],"alt_text":""},{"channel":"instagram","title":"T","text":"","hashtags":[],"alt_text":""}]}';
$posts=SGAP_AI::parse_posts($json,['facebook','instagram']);
check('ai: keeps one valid post per requested channel', count($posts)===1 && $posts[0]['channel']==='facebook' && $posts[0]['hashtags']==='#steampunk #cosplay');
check('ai: bad json gives no posts', SGAP_AI::parse_posts('nope',['facebook'])===[]);
check('ai: anthropic text block found after thinking', SGAP_AI::anthropic_text(['stop_reason'=>'end_turn','content'=>[['type'=>'thinking','thinking'=>''],['type'=>'text','text'=>'{"posts":[]}']]])==='{"posts":[]}');
check('ai: refusal is an error', SGAP_AI::anthropic_text(['stop_reason'=>'refusal','content'=>[]]) instanceof WP_Error);
check('ai: truncation is an error', SGAP_AI::anthropic_text(['stop_reason'=>'max_tokens','content'=>[['type'=>'text','text'=>'{']]]) instanceof WP_Error);
$schema=SGAP_AI::schema(['facebook']);
check('ai: schema is strict', $schema['additionalProperties']===false && $schema['properties']['posts']['items']['additionalProperties']===false && $schema['properties']['posts']['items']['properties']['channel']['enum']===['facebook']);
update_option('sgap_ai_provider','aimlapi'); update_option('sgap_ai_model',''); update_option('sgap_ai_base_url','https://example.com/v1');
check('aimlapi: default model is Claude on AIMLAPI', SGAP_AI::model()==='anthropic/claude-opus-5.5');
check('aimlapi: always the AIMLAPI endpoint', SGAP_AI::base_url()==='https://api.aimlapi.com/v1' && SGAP_AI::uses_chat_completions());
$cb=SGAP_AI::chat_body(SGAP_AI::model(),'x');
check('aimlapi: low reasoning effort and JSON mode', ($cb['reasoning_effort']??'')==='low' && $cb['response_format']['type']==='json_object');
update_option('sgap_ai_provider','openai_compatible'); update_option('sgap_ai_model','gpt-x');
check('openai-compatible: own base url and model, no reasoning_effort', SGAP_AI::base_url()==='https://example.com/v1' && SGAP_AI::model()==='gpt-x' && !isset(SGAP_AI::chat_body('gpt-x','x')['reasoning_effort']));
update_option('sgap_ai_provider','anthropic'); update_option('sgap_ai_model',''); update_option('sgap_ai_base_url','');
check('anthropic: default model unchanged', SGAP_AI::model()==='claude-opus-5-5' && !SGAP_AI::uses_chat_completions());
check('ai: JSON inside a ```json fence is parsed', count(SGAP_AI::parse_posts("Here you go:\n```json\n{\"posts\":[{\"channel\":\"facebook\",\"text\":\"Hi\"}]}\n```",['facebook']))===1);
check('rss: Publer gets a query-string feed URL (page caches skip it)', SGAP_RSS::url('facebook')==='https://steamgears.eu/?sgap_feed=facebook');
check('rss: newlines become a literal \\n for Publer', SGAP_RSS::description("Line one\r\nLine two\nThree")==='Line one\\nLine two\\nThree');
check('bot: Publer link scraper is not a click', SGAP_Tracking::is_bot('facebookplatform/1.0 (+http://developers.facebook.com)'));
check('updater: reads Version from a plugin header', SGAP_Updater::parse_version("<?php\n/**\n* Plugin Name: X\n* Version: 7.6.2\n*/")==='7.6.2');
check('updater: no version in junk', SGAP_Updater::parse_version('404: Not Found')==='');
check('updater: package url points at the versioned zip on main', SGAP_Updater::package_url('7.6.2')==='https://raw.githubusercontent.com/humle93/steamgears-site/main/plugins/steamgears-autopilot-v7.6.2.zip');
// --- Alerts: P0.1 failed post, P0.2 queue running dry ---
$GLOBALS['mails']=[];
update_option('admin_email','admin@example.com'); update_option('sgap_alert_mode','immediate');
update_option('sgap_alert_email','ops@example.com'); update_option('sgap_alert_dry_days',2); update_option('sgap_daily_cap',8);
delete_option('sgap_alerts_last');
check('alerts: hook registered on queue failure', in_array([SGAP_Alerts::class,'on_failure'],$GLOBALS['actions']['sgap_queue_failed']??[],true));
check('alerts: configured recipient wins', SGAP_Alerts::recipient()==='ops@example.com');
delete_option('sgap_alert_email');
check('alerts: empty recipient falls back to the admin address', SGAP_Alerts::recipient()==='admin@example.com');
update_option('sgap_alert_email','ops@example.com');
SGAP_Alerts::on_failure(7,'pinterest','token expired');
check('alerts: failure mailed once with item id and reason', count($GLOBALS['mails'])===1 && str_contains($GLOBALS['mails'][0][2],'#7') && str_contains($GLOBALS['mails'][0][2],'token expired'));
SGAP_Alerts::on_failure(7,'pinterest','token expired');
check('alerts: the same item is not mailed twice inside the window', count($GLOBALS['mails'])===1);
SGAP_Alerts::on_failure(8,'facebook','boom');
check('alerts: a different item is mailed', count($GLOBALS['mails'])===2);
update_option('sgap_alert_mode','off');
SGAP_Alerts::on_failure(9,'facebook','boom');
check('alerts: off mode sends nothing', count($GLOBALS['mails'])===2);
$wpdb->next=20; check('alerts: runway = queued / daily cap', SGAP_Alerts::runway_days()===2.5);
update_option('sgap_alert_mode','immediate'); delete_option('sgap_alerts_last');
$wpdb->next=40; SGAP_Alerts::after_health([]);
check('alerts: five days of queue is not a warning', count($GLOBALS['mails'])===2);
$wpdb->next=8; SGAP_Alerts::after_health([]);
check('alerts: one day of queue sends the dry warning', count($GLOBALS['mails'])===3 && str_contains($GLOBALS['mails'][2][1],'running dry'));
update_option('sgap_alert_mode','daily'); delete_option('sgap_alerts_last');
$wpdb->next=0; SGAP_Alerts::after_health(['problems'=>['The autopilot is off.']]);
check('alerts: daily mode sends one digest listing the problems', count($GLOBALS['mails'])===4 && str_contains($GLOBALS['mails'][3][2],'The autopilot is off.'));
// --- Retry with backoff (P0.3) ---
check('backoff: 5, 15, 45 minutes by attempt', SGAP_Queue::retry_delay_minutes(1)===5 && SGAP_Queue::retry_delay_minutes(2)===15 && SGAP_Queue::retry_delay_minutes(3)===45);
check('backoff: clamped at both ends', SGAP_Queue::retry_delay_minutes(0)===5 && SGAP_Queue::retry_delay_minutes(99)===45);
$wpdb->row=(object)['channel'=>'pinterest','angle'=>'angle_a','attempts'=>1]; $wpdb->log=[];
$new=SGAP_Queue::mark(11,'failed','token expired');
check('backoff: first failure requeues instead of failing', $new==='queued' && str_contains(end($wpdb->log),'"scheduled_at"'));
$wpdb->row=(object)['channel'=>'pinterest','angle'=>'angle_a','attempts'=>3]; $wpdb->log=[]; update_option('sgap_alert_mode','off');
$new=SGAP_Queue::mark(12,'failed','token expired');
check('backoff: last attempt fails without a new schedule', $new==='failed' && !str_contains(end($wpdb->log),'"scheduled_at"'));
$wpdb->row=(object)['channel'=>'pinterest','angle'=>'angle_a','attempts'=>1]; $wpdb->log=[];
$new=SGAP_Queue::mark(13,'published');
check('backoff: publishing is untouched by the retry path', $new==='published' && str_contains(implode("\n",$wpdb->log),'"published_at"'));
// --- Publish audit log (P0.5) ---
check('audit: default retention is 90 days', SGAP_Settings::get('audit_keep_days',90)===90);
check('audit: excerpt collapses whitespace and strips tags', SGAP_Audit::excerpt("Publer: <b>token\n\n expired</b>")==='Publer: token expired');
check('audit: excerpt is capped at 500 chars', mb_strlen(SGAP_Audit::excerpt(str_repeat('x',900)))===500);
check('audit: http status is clamped to 0-599', SGAP_Audit::clamp_status(-5)===0 && SGAP_Audit::clamp_status(700)===0 && SGAP_Audit::clamp_status(404)===404);
check('audit: error status comes from WP_Error data', SGAP_Audit::status_of(new WP_Error('x','y',['status'=>401]))===401);
check('audit: a local failure has no status', SGAP_Audit::status_of('plain string')===0);
check('audit: actor is admin inside wp-admin', SGAP_Audit::actor()==='admin');
$wpdb->log=[]; SGAP_Audit::record(31,'pinterest','sent',200,'Publer job job_1');
$ins=implode("\n",$wpdb->log);
check('audit: a row records queue id, channel, outcome, status and actor', str_contains($ins,'INSERT wp_sgap_publish_log') && str_contains($ins,'"queue_id":31') && str_contains($ins,'"outcome":"sent"') && str_contains($ins,'"http_status":200') && str_contains($ins,'"actor":"admin"'));
$wpdb->log=[]; SGAP_Audit::record(32,'facebook','nonsense');
check('audit: an unknown outcome is stored as failed', str_contains(implode("\n",$wpdb->log),'"outcome":"failed"'));
$wpdb->log=[]; SGAP_Audit::record_error(33,'instagram',new WP_Error('sgap_publer_http','Publer: HTTP 429',['status'=>429]),'requeued');
$ins=implode("\n",$wpdb->log);
check('audit: record_error keeps status and message', str_contains($ins,'"http_status":429') && str_contains($ins,'"outcome":"requeued"') && str_contains($ins,'Publer: HTTP 429'));
update_option('sgap_audit_keep_days',0); $wpdb->log=[];
check('audit: retention 0 keeps everything and runs no delete', SGAP_Audit::prune()===0 && $wpdb->log===[]);
update_option('sgap_audit_keep_days',90); $wpdb->log=[]; SGAP_Audit::prune();
check('audit: prune deletes rows older than the window', str_contains(implode("\n",$wpdb->log),'DELETE FROM wp_sgap_publish_log WHERE created_at < '));
$wpdb->log=[]; SGAP_Audit::rows('failed','pinterest',25,50);
$q=implode("\n",$wpdb->log);
check('audit: rows filters by outcome and channel with paging', str_contains($q,"outcome = 'failed'") && str_contains($q,"channel = 'pinterest'") && str_contains($q,'LIMIT 25 OFFSET 50'));
check('audit: page size is clamped', str_contains((function(){global $wpdb; $wpdb->log=[]; SGAP_Audit::rows('','',9999,0); return implode("\n",$wpdb->log);})(),'LIMIT 200 OFFSET 0'));
$wpdb->next=7; check('audit: total counts with the same filter', SGAP_Audit::total('failed')===7);
ob_start(); do_action('page_sgap-log'); $html=ob_get_clean();
check('audit: the log page explains the retention window', str_contains($html,'Kept for 90 days'));
// ---- whole-site SEO / AEO / GEO (7.19.0) ----
$sgap_check = function (array $checks, string $id) { foreach ($checks as $c) { if ($c['id'] === $id) return $c; } return ['status' => 'missing', 'detail' => '']; };
$base = ['type' => 'post', 'slug' => 'steampunk-goggles', 'title' => 'Steampunk Goggles: How to Choose a Pair That Fits',
    'meta_title' => 'Steampunk Goggles: How to Choose a Pair That Fits', 'keyword' => 'steampunk goggles', 'featured' => true,
    'description' => str_repeat('Brass goggles that actually fit. ', 5)];
check('site seo: no rules scores 100', SGAP_Site_SEO::score([]) === 100);
check('site seo: pass + warn + fail averages to 50', SGAP_Site_SEO::score([['status' => 'pass'], ['status' => 'warn'], ['status' => 'fail']]) === 50);
check('site seo: skipped checks are ignored', SGAP_Site_SEO::score([['status' => 'pass'], ['status' => 'skip']]) === 100);
$empty = SGAP_Site_SEO::evaluate(array_merge($base, ['meta_title' => '', 'description' => '', 'keyword' => '', 'content' => '']));
check('site seo: missing meta and keyword fail', $sgap_check($empty, 'meta_title')['status'] === 'fail' && $sgap_check($empty, 'meta_description')['status'] === 'fail' && $sgap_check($empty, 'focus_keyword')['status'] === 'fail');
check('site seo: an empty page fails length and internal links', $sgap_check($empty, 'length')['status'] === 'fail' && $sgap_check($empty, 'internal_links')['status'] === 'fail');
check('site seo: no H1 warns instead of failing', $sgap_check($empty, 'h1')['status'] === 'warn');
check('site seo: two H1 elements fail', $sgap_check(SGAP_Site_SEO::evaluate(array_merge($base, ['content' => '<h1>A</h1><h1>B</h1>'])), 'h1')['status'] === 'fail');
$imgs = SGAP_Site_SEO::evaluate(array_merge($base, ['content' => '<img src="a.jpg"><img src="b.jpg" alt="brass">']));
check('site seo: a missing alt text fails and is counted', $sgap_check($imgs, 'image_alt')['status'] === 'fail' && str_contains($sgap_check($imgs, 'image_alt')['detail'], '1 of 2'));
$aeo = SGAP_Site_SEO::evaluate(array_merge($base, ['content' => '<h2>What size goggles do I need?</h2><p>' . str_repeat('word ', 60) . '</p><ul><li>x</li></ul><script type="application/ld+json">{"@type":"FAQPage"}</script>']));
check('site seo: question heading, FAQ schema and a list pass AEO', $sgap_check($aeo, 'question_headings')['status'] === 'pass' && $sgap_check($aeo, 'faq')['status'] === 'pass' && $sgap_check($aeo, 'scannable')['status'] === 'pass');
check('site seo: a plain heading is not a question heading', $sgap_check(SGAP_Site_SEO::evaluate(array_merge($base, ['content' => '<h2>Materials</h2><p>x</p>'])), 'question_headings')['status'] === 'warn');
$dup = SGAP_Site_SEO::evaluate(array_merge($base, ['duplicate_title' => true, 'duplicate_description' => true, 'content' => '<p>x</p>']));
check('site seo: duplicate title and description fail', $sgap_check($dup, 'duplicate')['status'] === 'fail' && $sgap_check($dup, 'duplicate_description')['status'] === 'fail');
check('site seo: a named brand is a GEO signal', $sgap_check(SGAP_Site_SEO::evaluate(array_merge($base, ['content' => '<p>Steamgears sells 12 designs.</p>'])), 'entity_named')['status'] === 'pass');
check('site seo: numbers are quotable facts', $sgap_check(SGAP_Site_SEO::evaluate(array_merge($base, ['content' => '<p>It costs 45 euro.</p>'])), 'quotable_facts')['status'] === 'pass');
$sum = SGAP_Site_SEO::summarize([['fails' => ['a'], 'warns' => ['b'], 'checks' => [1, 2, 3], 'fixed_at' => ''], ['fails' => [], 'warns' => [], 'checks' => [], 'fixed_at' => '']]);
check('site seo: the summary counts fails, warns and pages needing work', $sum['fail'] === 1 && $sum['warn'] === 1 && $sum['needs_work'] === 1 && $sum['pass'] === 1);
check('site seo: ids() picks one status only', SGAP_Site_SEO::ids([['id' => 'a', 'status' => 'fail'], ['id' => 'b', 'status' => 'pass']], 'fail') === ['a']);
ob_start(); do_action('page_sgap-studio'); $studio = ob_get_clean();
check('site seo: the studio page carries the whole-site card and its script', str_contains($studio, 'sgap-seo-site-out') && str_contains($studio, 'Scan the whole site') && str_contains($studio, 'sgap_seo_site'));
/* --- pre-flight per channel (P1.1) --- */
$sgap_url=SGAP_Tracking::url(7);
check('preflight: threads budget subtracts the tracking link', SGAP_Preflight::caption_budget('threads',$sgap_url)===500-mb_strlen($sgap_url)-2);
check('preflight: X counts a link as 23 characters', SGAP_Preflight::caption_budget('twitter',$sgap_url)===255);
check('preflight: instagram keeps the whole caption budget', SGAP_Preflight::caption_budget('instagram',$sgap_url)===2200);
check('preflight: an unknown channel has no budget', SGAP_Preflight::caption_budget('myspace',$sgap_url)===0);
$long=str_repeat('Steampunk goggles with brass frames. ',40);
$fix=SGAP_Preflight::repair('threads',['title'=>'t','body'=>$long,'tracking_url'=>$sgap_url]);
check('preflight: a long threads caption is cut to the budget', mb_strlen($fix['row']['body'])<=500-mb_strlen($sgap_url)-2 && str_ends_with($fix['row']['body'],'…'));
check('preflight: the cut is reported', (bool)array_filter($fix['notes'],fn($n)=>str_contains($n,'shortened')));
$fine=SGAP_Preflight::repair('facebook',['title'=>'t','body'=>'Short','tracking_url'=>$sgap_url]);
check('preflight: a caption within the limit is left alone', $fine['row']['body']==='Short' && $fine['notes']===[]);
$tags=SGAP_Preflight::repair('threads',['title'=>'t','body'=>'Brass #steampunk #goggles #brass','tracking_url'=>$sgap_url]);
check('preflight: threads keeps one hashtag', $tags['row']['body']==='Brass #steampunk' && count($tags['notes'])===1);
$many='x '.implode(' ',array_map(fn($i)=>'#tag'.$i,range(1,31)));
$ig=SGAP_Preflight::repair('instagram',['title'=>'t','body'=>$many,'tracking_url'=>$sgap_url]);
preg_match_all('/#\w+/',$ig['row']['body'],$tagm);
check('preflight: instagram keeps 30 hashtags', count($tagm[0])===30 && count($ig['notes'])===1);
$pin=SGAP_Preflight::repair('pinterest',['title'=>str_repeat('Brass goggles ',12),'body'=>'x','tracking_url'=>$sgap_url]);
check('preflight: pin title cut to 100 characters', mb_strlen($pin['row']['title'])<=100 && mb_strlen($pin['row']['title'])>60);
$nophoto=SGAP_Preflight::problems('instagram',['title'=>'t','body'=>'x','image_url'=>'']);
check('preflight: instagram without a photo is a hard problem', ($nophoto[0]['code']??'')==='no_photo' && str_contains($nophoto[0]['message'],'needs a photo'));
$board=SGAP_Preflight::problems('pinterest',['title'=>'t','body'=>'x','image_url'=>'https://steamgears.eu/a.jpg']);
check('preflight: pinterest without a board is refused', in_array('no_board',array_column($board,'code'),true));
$opts['sgap_publer_pinterest_board']='board_1';
check('preflight: pinterest passes once the board is set', SGAP_Preflight::problems('pinterest',['title'=>'t','body'=>'x','image_url'=>'https://steamgears.eu/a.jpg'])===[]);
$GLOBALS['attachment_for']=['https://steamgears.eu/wp-content/uploads/pin.png'=>91];
$GLOBALS['attachment_meta'][91]=['width'=>1000,'height'=>1500,'mime_type'=>'image/png'];
$info=SGAP_Preflight::image_info('https://steamgears.eu/wp-content/uploads/pin.png');
check('preflight: size and type come from the media library', $info['mime']==='image/png' && $info['width']===1000 && $info['source']==='attachment');
check('preflight: a PNG is refused on instagram', in_array('image_not_jpeg',array_column(SGAP_Preflight::problems('instagram',['image_url'=>'https://steamgears.eu/wp-content/uploads/pin.png']),'code'),true));
check('preflight: a portrait photo outside 4:5 is refused', in_array('image_out_of_ratio',array_column(SGAP_Preflight::problems('instagram',['image_url'=>'https://steamgears.eu/wp-content/uploads/pin.png']),'code'),true));
$GLOBALS['attachment_meta'][91]=['width'=>1080,'height'=>1080,'mime_type'=>'image/jpeg'];
check('preflight: a square JPEG passes on instagram', SGAP_Preflight::problems('instagram',['image_url'=>'https://steamgears.eu/wp-content/uploads/pin.png'])===[]);
$GLOBALS['attachment_for']=[]; $GLOBALS['attachment_meta']=[];
check('preflight: the extension decides without an attachment', SGAP_Preflight::image_info('https://cdn.example.test/x.webp')['mime']==='image/webp');
check('preflight: an unknown image is not blocked on a guess', SGAP_Preflight::problems('instagram',['image_url'=>'https://cdn.example.test/photo?id=3'])===[]);
$c=SGAP_Preflight::check('threads',['id'=>7,'title'=>'t','body'=>$long,'destination_url'=>'https://steamgears.eu/x/']);
check('preflight: check() repairs and still passes', $c['ok'] && mb_strlen($c['row']['body'])<=500-mb_strlen($sgap_url)-2 && $c['notes']!==[]);
check('preflight: a message names the problems', str_contains(SGAP_Preflight::message([['message'=>'A.'],['message'=>'B.']]),'A. B.'));
$gate=SGAP_Publer::publish(['id'=>7,'channel'=>'instagram','title'=>'Brass','body'=>'x','destination_url'=>'https://steamgears.eu/x/','image_url'=>'']);
check('preflight: publish refuses before any Publer call', is_wp_error($gate) && $gate->code==='sgap_preflight' && str_contains($gate->get_error_message(),'needs a photo'));
$wpdb->next=null;
$warn=SGAP_Queue::add(['channel'=>'instagram','title'=>'Warn','body'=>'x','destination_url'=>home_url('/warn/')]);
check('preflight: queueing warns about what would be refused', is_array($warn) && in_array(true,array_map(fn($w)=>str_contains($w,'needs a photo'),$warn['warnings']??[]),true));
/* --- blackout calendar (P1.3) --- */
$cal=SGAP_Blackout::parse("2026-12-24..2026-12-26 Christmas\n2026-12-31 New Year's Eve\nnot a date\n2026-13-45 .. 2026-13-46 broken\n2027-01-05..2027-01-02 reversed");
check('blackout: a range and two single days parse, garbage is ignored', count($cal)===3 && $cal[0]['from']==='2026-12-24' && $cal[0]['to']==='2026-12-26' && $cal[1]['label']==="New Year's Eve");
check('blackout: a reversed range is put in order', (end($cal)['from']??'')==='2027-01-02' && (end($cal)['to']??'')==='2027-01-05');
$opts['sgap_blackout_ranges']="2026-12-24..2026-12-26 Christmas\n2026-12-31 New Year's Eve";
check('blackout: the day before a range is free', SGAP_Blackout::active('2026-12-23')===null);
check('blackout: both ends of a range are blocked', SGAP_Blackout::active('2026-12-24')!==null && SGAP_Blackout::active('2026-12-26')!==null);
check('blackout: the day after a range is free again', SGAP_Blackout::active('2026-12-27')===null);
check('blackout: the label comes back with the range', (SGAP_Blackout::active('2026-12-25')['label']??'')==='Christmas');
check('blackout: hits() looks at the date part of a timestamp', SGAP_Blackout::hits('2026-12-25 09:30:00') && !SGAP_Blackout::hits('2026-12-27 09:30:00'));
check('blackout: a post inside the range moves to the first free day, same time', SGAP_Blackout::next_free('2026-12-25 09:30:00')==='2026-12-27 09:30:00');
check('blackout: a post on a free day stays put', SGAP_Blackout::next_free('2026-12-20 08:15:00')==='2026-12-20 08:15:00');
check('blackout: a post on the 31st moves past New Year', SGAP_Blackout::next_free('2026-12-31 12:00:00')==='2027-01-01 12:00:00');
$GLOBALS['rows']=[(object)['id'=>1,'scheduled_at'=>'2026-12-25 09:30:00'],(object)['id'=>2,'scheduled_at'=>'2026-12-27 09:30:00']];
$moved=SGAP_Blackout::reschedule();
check('blackout: reschedule moves only the post inside the range', count($moved)===1 && $moved[0]['id']===1 && $moved[0]['to']==='2026-12-27 09:30:00' && $moved[0]['reason']==='Christmas');
$GLOBALS['rows']=[(object)['id'=>3,'scheduled_at'=>'2026-12-24 07:00:00']];
$still=SGAP_Blackout::reschedule();
$GLOBALS['rows']=[(object)['id'=>3,'scheduled_at'=>$still[0]['to']??'x']];
check('blackout: a moved post is not moved again on the next tick', count($still)===1 && SGAP_Blackout::reschedule()===[]);
$GLOBALS['rows']=[];
$opts['sgap_blackout_ranges']="2026-12-24..2026-12-26 Christmas";
$wpdb->next=null;
$shift=SGAP_Queue::add(['channel'=>'facebook','title'=>'Holiday','body'=>'x','destination_url'=>home_url('/holiday/'),'scheduled_at'=>'2026-12-25 10:00:00']);
check('blackout: queueing inside a range schedules the next free day', is_array($shift) && ($shift['scheduled_at']??'')==='2026-12-27 10:00:00');
$opts['sgap_autopilot_enabled']='yes'; $opts['sgap_quiet_start']=0; $opts['sgap_quiet_end']=0; $wpdb->next=0;
$opts['sgap_blackout_ranges']=wp_date('Y-m-d').' Today off';
check('blackout: an active range pauses publishing', SGAP_Queue::blocked_reason()==='blackout');
check('blackout: the reason names the range and where posts go', str_contains(SGAP_Queue::blocked_message(),'blackout') && str_contains(SGAP_Queue::blocked_message(),'move to'));
$sum=SGAP_Blackout::summary();
check('blackout: summary says it is active and where posts go next', $sum['active']===true && $sum['ranges']===1 && $sum['moves_to']!==null);
check('blackout: the publish tick reports what it shifted', array_key_exists('shifted', SGAP_Publer::tick()));
ob_start(); do_action('page_sgap-settings'); $calhtml=ob_get_clean();
check('blackout: the calendar has a multi-line field on the settings page', str_contains($calhtml,'name="sgap_blackout_ranges"') && str_contains($calhtml,'<textarea'));
check('blackout: newlines survive saving the setting', ($settings['sgap_blackout_ranges']['sanitize_callback']??'')==='sanitize_textarea_field');
ob_start(); do_action('page_sgap-queue'); $qhtml=ob_get_clean();
check('blackout: the queue page says why nothing is going out', str_contains($qhtml,'Blackout'));
$opts['sgap_blackout_ranges']='';
check('blackout: no ranges means no work at all', SGAP_Blackout::reschedule()===[] && SGAP_Blackout::active()===null);
// ---- quality gate before queueing (P1.6) ----
$opts['sgap_quality_gate']='block'; $opts['sgap_banned_words']="scam\n#giveaway, cheap junk";
unset($GLOBALS['rows'],$GLOBALS['postid_for'],$GLOBALS['poststatus'],$GLOBALS['postmeta'],$GLOBALS['attachment_for']);
check('quality: words parsed from lines and commas', SGAP_Quality::words()===['scam','#giveaway','cheap junk']);
check('quality: links, punctuation and case are normalised away', SGAP_Quality::normalize('Hello,   World! https://x.se/a')==='hello world');
check('quality: a caption is 100 % itself', SGAP_Quality::similarity('Titanium gears for the daily carry','Titanium gears for the daily carry')===1.0);
check('quality: unrelated captions score low', SGAP_Quality::similarity('Titanium gears for the daily carry','Autumn notes on leather care and wax')<0.2);
check('quality: a banned word fires on whole words only', SGAP_Quality::banned('This is a scam')===['scam'] && SGAP_Quality::banned('Garlic scampi tonight')===[]);
check('quality: a hashtag counts as the word', SGAP_Quality::banned('Join the #giveaway now')===['#giveaway']);
check('quality: a two-word phrase is matched as one term', SGAP_Quality::banned('Only cheap junk here')===['cheap junk']);
check('quality: links are found without the sentence punctuation', SGAP_Quality::links('Se https://steamgears.eu/gear/ og https://x.se/a?b=1.')===['https://steamgears.eu/gear/','https://x.se/a?b=1']);
check('quality: a placeholder host is blocked', (SGAP_Quality::link_problem('https://example.com/gear')['severity']??'')==='block');
check('quality: a link without a domain is blocked', (SGAP_Quality::link_problem('https:///gear')['severity']??'')==='block');
check('quality: http is a warning, not a block', (SGAP_Quality::link_problem('http://steamgears.eu/gear/')['severity']??'')==='warn');
$GLOBALS['postid_for']['https://steamgears.eu/gear/']=7; $GLOBALS['poststatus'][7]='publish';
check('quality: a link to a live page passes', SGAP_Quality::link_problem('https://steamgears.eu/gear/')===null);
$GLOBALS['poststatus'][7]='draft';
check('quality: a link to a draft is blocked', (SGAP_Quality::link_problem('https://steamgears.eu/gear/')['severity']??'')==='block');
check('quality: an archive or a search is not a dead link', SGAP_Quality::link_problem('https://steamgears.eu/category/guides/')===null && SGAP_Quality::link_problem('https://steamgears.eu/?s=gear')===null);
check('quality: an internal address nobody can open is a warning', (SGAP_Quality::link_problem('https://steamgears.eu/no-such-page/')['severity']??'')==='warn');
check('quality: an external host is not probed', SGAP_Quality::link_problem('https://www.pinterest.com/pin/1')===null);
$GLOBALS['attachment_for']['https://steamgears.eu/i.jpg']=55;
check('quality: a photo without alt text is reported', SGAP_Quality::alt_problem('https://steamgears.eu/i.jpg')!=='');
$GLOBALS['postmeta'][55]['_wp_attachment_image_alt']='Brass gear close-up';
check('quality: a described photo passes', SGAP_Quality::alt_problem('https://steamgears.eu/i.jpg')==='');
check('quality: an image outside the media library cannot be judged', SGAP_Quality::alt_problem('https://cdn.example.org/x.jpg')==='');
$long='Brass and titanium gears for the daily carry, hand finished in the workshop and shipped in a linen pouch';
$GLOBALS['rows']=[['id'=>9,'title'=>'Gears','body'=>$long.' today','scheduled_at'=>'2026-11-03 09:00:00']];
check('quality: a repeated caption is caught', (SGAP_Quality::duplicate('instagram',$long)['id']??0)===9);
check('quality: the duplicate is compared within one channel', str_contains(implode("\n",$wpdb->log),'channel = '));
check('quality: short captions are not called duplicates', SGAP_Quality::duplicate('instagram','New drop')===null);
$GLOBALS['rows']=[];
$row=['channel'=>'instagram','title'=>'Gears','body'=>'Buy our scam gears at https://steamgears.eu/gear/','destination_url'=>'https://steamgears.eu/gear/'];
$gate=SGAP_Quality::check($row);
check('quality: a banned word blocks the post', $gate['ok']===false && in_array('banned_word',array_column($gate['problems'],'code'),true));
check('quality: the blocking problems are the refusal', count(SGAP_Quality::blocking($gate['problems']))>=1 && SGAP_Quality::message($gate['problems'])!=='');
check('quality: force queues it anyway and keeps the findings', SGAP_Quality::check($row,true)['ok']===true && SGAP_Quality::check($row,true)['problems']!==[]);
$opts['sgap_quality_gate']='warn';
check('quality: warn mode reports without refusing', SGAP_Quality::check($row)['ok']===true && SGAP_Quality::check($row)['problems']!==[]);
$opts['sgap_quality_gate']='off';
check('quality: off mode says nothing at all', SGAP_Quality::check($row)['problems']===[]);
$opts['sgap_quality_gate']='block';
$wpdb->next=null;
$refused=SGAP_Queue::add(['channel'=>'facebook','title'=>'Deal','body'=>'This is a scam, click now','destination_url'=>'https://steamgears.eu/gear/']);
check('quality: the queue refuses a post the gate blocks', is_wp_error($refused) && $refused->code==='sgap_quality');
check('quality: the refusal says what is wrong', str_contains($refused->get_error_message(),'scam'));
$forced=SGAP_Queue::add(['channel'=>'facebook','title'=>'Deal','body'=>'This is a scam, click now','destination_url'=>'https://steamgears.eu/gear/','force'=>true]);
check('quality: force puts it in the queue with the findings as warnings', is_array($forced) && str_contains(implode(' ',$forced['warnings']??[]),'scam'));
$GLOBALS['poststatus'][7]='publish';
$clean=SGAP_Queue::add(['channel'=>'facebook','title'=>'New gears','body'=>'Fresh titanium gears in the shop','destination_url'=>'https://steamgears.eu/gear/']);
check('quality: a clean post still goes through', is_array($clean) && ($clean['warnings']??[])===[]);
check('quality: the mode is one of three values', ($settings['sgap_quality_gate']['sanitize_callback']??'')('nonsense')==='block');
check('quality: the list is multi-line on the settings page', str_contains((string)($settings['sgap_banned_words']['sanitize_callback']??''),'sanitize_textarea_field'));
ob_start(); do_action('page_sgap-settings'); $qh=ob_get_clean();
check('quality: the settings page has the list and the mode', str_contains($qh,'name="sgap_banned_words"') && str_contains($qh,'name="sgap_quality_gate"'));
$qstatus=SGAP_Abilities::status();
check('quality: status says how the gate is set', ($qstatus['quality_gate']['mode']??'')==='block' && ($qstatus['quality_gate']['banned_words']??0)===3);
$dry=SGAP_Abilities::check_post(['channel'=>'facebook','title'=>'Deal','text'=>'Buy our scam gears','destination_url'=>'https://steamgears.eu/gear/']);
check('quality: the dry run covers both gates', isset($dry['quality']['problems']) && $dry['ok']===false && count($dry['quality']['problems'])>=1);
$opts['sgap_banned_words']=''; $GLOBALS['postid_for']=[]; $GLOBALS['poststatus']=[]; $GLOBALS['postmeta']=[]; $GLOBALS['attachment_for']=[];
$wpdb->row=null;
$wpdb->next=null;
exit(empty($GLOBALS['fail'])?0:1);
