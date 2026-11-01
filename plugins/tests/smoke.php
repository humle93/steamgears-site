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
function do_action($h){ foreach($GLOBALS['actions'][$h]??[] as $cb) call_user_func($cb); }
function register_activation_hook(...$a){} function register_deactivation_hook(...$a){}
function is_admin(){return true;} function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;}
function update_option($k,$v){$GLOBALS['opts'][$k]=$v;} function add_option($k,$v){$GLOBALS['opts'][$k]??=$v;}
function register_setting($g,$k,$a){ $GLOBALS['settings'][$k]=$a; } function wp_clear_scheduled_hook($h){}
function add_menu_page($t,$m,$c,$slug,$cb){ add_action('page_'.$slug,$cb);} function add_submenu_page($p,$t,$m,$c,$slug,$cb){ add_action('page_'.$slug,$cb);}
function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function esc_url($s){return $s;}
function get_transient($k){return false;} function set_transient(...$a){} function delete_transient($k){} function settings_fields($g){} function submit_button(){}
function admin_url($p=''){return '/wp-admin/'.$p;} function rest_url($p=''){return '/wp-json/'.$p;} function home_url($p=''){return 'https://steamgears.eu'.$p;}
function wp_nonce_field(...$a){return '';} function wp_create_nonce($a=''){return 'n';} function selected($a,$b,$e){return $a===$b?' selected':'';} function checked($a,$b=true,$e=true){return $a===$b?' checked':'';}
function sanitize_text_field($s){return trim(strip_tags((string)$s));} function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));} function absint($v){return abs((int)$v);}
function esc_url_raw($u,$p=null){return preg_match('#^https?://#',$u)?$u:'';} function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
function wp_parse_args($a,$d){return array_merge($d,$a);} function add_query_arg($a,$u){return $u.(str_contains($u,'?')?'&':'?').http_build_query($a,'','&',PHP_QUERY_RFC3986);}
function current_time($t){return '2026-10-30 12:00:00';} function wp_date($f,$t=null){return date($f,$t??time());}
function current_user_can($c){return true;} function get_current_user_id(){return 1;} function get_site_transient($k){return false;} function set_site_transient(...$a){} function wp_remote_get(...$a){return new WP_Error('offline','offline');} function wp_nonce_field_stub(){} function delete_option($k){unset($GLOBALS["opts"][$k]);} function wp_json_encode($v){return json_encode($v);} function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error{ function __construct(public $code,public $msg,public $data=[]){} }
class WP_REST_Response{ function __construct(public $data,public $status=200){} }
class WP_REST_Request implements ArrayAccess{ function __construct(public array $p){} function get_param($k){return $this->p[$k]??null;}
 function offsetGet($k):mixed{return $this->p[$k]??null;} function offsetExists($k):bool{return isset($this->p[$k]);} function offsetSet($k,$v):void{} function offsetUnset($k):void{} }
function wp_next_scheduled($h){return false;} function wp_schedule_event(...$a){$GLOBALS['cron'][]=$a[2];} function flush_rewrite_rules($h=true){$GLOBALS['flushed']=1;} function add_rewrite_rule(...$a){$GLOBALS['rules'][]=$a[0];} function wp_set_option_autoload_values($a){$GLOBALS['noauto']=$a;}
class W{ public $prefix='wp_'; public $posts='wp_posts'; public $postmeta='wp_postmeta'; function get_charset_collate(){return '';} public $insert_id=0; public $log=[]; public $next=null;
 function get_var($q){$this->log[]=$q; return $this->next;} function prepare($q,...$a){ if(count($a)==1&&is_array($a[0]))$a=$a[0]; return vsprintf(str_replace(['%s','%d','%f'],["'%s'",'%d','%F'],$q),$a);} function esc_like($s){return $s;}
 function get_results($q,$o=null){$this->log[]=$q;return [];} function get_row($q){$this->log[]=$q;return null;} function query($q){$this->log[]=$q;return 0;}
 function insert($t,$d){$this->log[]="INSERT $t ".json_encode($d); $this->insert_id=42; return 1;} function update($t,$d,$w){$this->log[]="UPDATE $t";return 1;} }
$wpdb=new W;
require __DIR__.'/../steamgears-autopilot/steamgears-autopilot.php';
function check($name,$ok){ echo ($ok?'PASS ':'FAIL ')."$name\n"; if(!$ok) $GLOBALS['fail']=1; }
do_action('init'); do_action('admin_init'); do_action('admin_menu');
check('install: 8 tables', count($GLOBALS['ddl']??[])===8); check('install: cleanup cron', in_array('sgap_daily_cleanup',$GLOBALS['cron']??[])); check('install: /go/ rule + flush', ($GLOBALS['rules'][0]??'')==='^go/([0-9]+)/?$' && !empty($GLOBALS['flushed'])); check('install: secrets not autoloaded', isset($GLOBALS['noauto']['sgap_ai_api_key'])); check('version stored', $opts['sgap_version']===SGAP_VERSION);
foreach(['steamgears-autopilot','sgap-settings','sgap-queue','sgap-ai','sgap-campaigns','sgap-etsy','sgap-reports','sgap-fullauto','sgap-merchant'] as $p){ ob_start(); do_action('page_'.$p); $html=ob_get_clean(); check("page $p renders", strlen($html)>20); }
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
echo empty($GLOBALS['fail'])?"ALL PASS\n":"SOME FAILED\n";
exit(empty($GLOBALS['fail'])?0:1);
