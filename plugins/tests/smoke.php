<?php
/**
 * Smoke test for the Steamgears Autopilot plugin with stubbed WordPress functions.
 * No WordPress or database needed. Usage: php plugins/tests/smoke.php
 * (Real WordPress + MySQL is covered by plugins/tests/integration.php in CI.)
 */
define('ABSPATH', __DIR__.'/fixtures/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60); define('WEEK_IN_SECONDS',604800); define('ARRAY_A','ARRAY_A');
$GLOBALS['actions']=[]; $opts=[]; $settings=[];
function add_action($h,$cb){ $GLOBALS['actions'][$h][]=$cb; } function add_filter($h,$cb){ $GLOBALS['actions'][$h][]=$cb; }
$GLOBALS['routes']=[]; function register_rest_route($ns,$route,$args=[]){ $GLOBALS['routes'][$ns][]=$route; return true; }
function apply_filters($h,$v,...$a){return isset($GLOBALS['filters'][$h])?($GLOBALS['filters'][$h])($v,...$a):$v;}
function do_action($h,...$a){ foreach($GLOBALS['actions'][$h]??[] as $cb) call_user_func_array($cb,$a); }
function register_activation_hook(...$a){} function register_deactivation_hook(...$a){}
function is_admin(){return true;} function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;}
function update_option($k,$v,$autoload=null){$GLOBALS['opts'][$k]=$v; $GLOBALS['autoload'][$k]=$autoload;} function add_option($k,$v){$GLOBALS['opts'][$k]??=$v;} function wp_mail($to,$s,$b){$GLOBALS['mails'][]=[$to,$s,$b]; return true;}
function register_setting($g,$k,$a){ $GLOBALS['settings'][$k]=$a; } function wp_clear_scheduled_hook($h,$a=[]){ $GLOBALS['cleared'][]=$h; }
function add_menu_page($t,$m,$c,$slug,$cb){ add_action('page_'.$slug,$cb);} function add_submenu_page($p,$t,$m,$c,$slug,$cb){ add_action('page_'.$slug,$cb);}
function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function esc_url($s){return $s;} function esc_textarea($s){return htmlspecialchars((string)$s);}
function get_transient($k){return $GLOBALS['transients'][$k]??false;} function set_transient($k,$v,$t=0){ if(!empty($GLOBALS['transient_store'])) $GLOBALS['transients'][$k]=$v; } function delete_transient($k){ unset($GLOBALS['transients'][$k]); } function settings_fields($g){} function submit_button(){}
function site_url($p=''){return 'https://steamgears.eu/'.$p;} function admin_url($p=''){return '/wp-admin/'.$p;} function rest_url($p=''){return '/wp-json/'.$p;} function home_url($p=''){return 'https://steamgears.eu'.$p;}
function get_bloginfo($k=''){return 'Steamgears';} function wp_timezone_string(){return 'UTC';} function get_the_title($id){ if(is_object($id)){return (string)($id->post_title!==''?$id->post_title:'Post '.($id->ID??0));} $p=$GLOBALS['post_by_id'][(int)$id]??null; return $p? (string)$p->post_title : 'Post '.$id; } function get_permalink($id){return 'https://steamgears.eu/?p='.$id;} function has_post_thumbnail($id){return false;} function get_post_type($id){return (string)($GLOBALS['post_type_by_id'][(int)$id]??'post');} function pll_get_post($id,$locale=''){return (int)($GLOBALS['pll_map'][$locale]??0);}
function get_posts($a=[]){ $r=$GLOBALS['posts_result']??[]; return array_slice($r,0,(int)($a['numberposts']??(count($r)?:1))); } function post_type_exists($t){return in_array($t,['post','page'],true);} function get_post($id){return $GLOBALS['post_by_id'][(int)$id]??null;}
function update_post_meta($id,$k,$v){ $GLOBALS['postmeta'][(int)$id][$k]=$v; return true; } function delete_post_meta($id,$k){ unset($GLOBALS['postmeta'][(int)$id][$k]); return true; } function get_the_post_thumbnail_url($id,$s=''){ return $GLOBALS['thumb'][(int)$id]??''; } function add_meta_box(...$a){ $GLOBALS['metaboxes'][]=$a; } function wp_is_post_revision($id){ return false; } function wp_verify_nonce($n,$a=''){ return true; } function has_excerpt($p){ return is_object($p) && !empty($p->post_excerpt); } function strip_shortcodes($t){ return (string)$t; } function wp_trim_words($t,$n=55,$more='…'){ $w=preg_split('/\s+/',trim((string)$t)); return implode(' ',array_slice($w,0,$n)).(count($w)>$n?$more:''); } function get_user_meta($u,$k,$s=false){return '';} function update_user_meta($u,$k,$v){return true;} function get_edit_post_link($id,$c=''){return '/edit/'.$id;} function get_preview_post_link($id){return '/preview/'.$id;}
function wp_nonce_field(...$a){return '';} function wp_create_nonce($a=''){return 'n';} function selected($a,$b,$e){return $a===$b?' selected':'';} function checked($a,$b=true,$e=true){return $a===$b?' checked':'';}
function sanitize_text_field($s){return trim(strip_tags((string)$s));} function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));} function absint($v){return abs((int)$v);}
function esc_url_raw($u,$p=null){return preg_match('#^https?://#',$u)?$u:'';} function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
function wp_parse_args($a,$d){return array_merge($d,$a);} function add_query_arg($a,$u=null,$b=null){ if($b!==null){ $a=[(string)$a=>(string)$u]; $u=$b; } if(!is_array($a)){ $a=[(string)$a=>'']; } return $u.(str_contains((string)$u,'?')?'&':'?').http_build_query($a,'','&',PHP_QUERY_RFC3986); }
function current_time($t){return '2026-10-30 12:00:00';}
if (!function_exists('number_format_i18n')) { function number_format_i18n($n, $d = 0) { return number_format((float) $n, (int) $d); } } function wp_date($f,$t=null){return date($f,$t??time());} function mysql2date($f,$d,$t=null){return $d?date($f,strtotime((string)$d)):'';}
function plugins_url($p='',$f=''){ return 'https://steamgears.eu/wp-content/plugins/steamgears-autopilot/'.ltrim((string)$p,'/'); }
function wp_enqueue_script($h,$src='',$d=[],$v=false,$f=false){ $GLOBALS['enqueued_scripts'][$h]=$src; }
function wp_enqueue_style($h,$src='',$d=[],$v=false,$m='all'){ $GLOBALS['enqueued_styles'][$h]=$src; }
function wp_localize_script($h,$name,$data){ $GLOBALS['localized'][$h][$name]=$data; }
function current_user_can($c){ if(isset($GLOBALS['deny_caps']) && in_array($c,(array)$GLOBALS['deny_caps'],true)) return false; return true; } function get_current_user_id(){return 1;} function get_site_transient($k){return false;} function set_site_transient(...$a){} function wp_remote_get(...$a){return new WP_Error('offline','offline');} function wp_nonce_field_stub(){} function delete_option($k){unset($GLOBALS["opts"][$k]);} function wp_json_encode($v){return json_encode($v);} function is_wp_error($v){return $v instanceof WP_Error;}
function wp_strip_all_tags($s){return strip_tags((string)$s);} function attachment_url_to_postid($u){return (int)($GLOBALS['attachment_for'][$u]??0);} function get_post_meta($id,$k='',$s=false){return $GLOBALS['postmeta'][$id][$k]??'';} function url_to_postid($u){return (int)($GLOBALS['postid_for'][$u]??0);} function get_post_status($id){return (string)($GLOBALS['poststatus'][$id]??'publish');} function wp_get_attachment_metadata($id){return $GLOBALS['attachment_meta'][$id]??[];} function get_post_mime_type($id){return (string)($GLOBALS['attachment_mime'][$id]??'');} function wp_die($m=''){throw new RuntimeException((string)$m);} function check_admin_referer($a=''){return true;}
$GLOBALS['abilities']=[]; $GLOBALS['ability_categories']=[];
function wp_register_ability($n,$a=[]){ $GLOBALS['abilities'][$n]=$a; return true; } function wp_register_ability_category($s,$a=[]){ $GLOBALS['ability_categories'][$s]=$a; return true; }
if (!class_exists('WP_Post')) { class WP_Post {
    public $ID=0; public $post_type='post'; public $post_status='publish';
    public $post_title=''; public $post_content=''; public $post_excerpt='';
    function __construct($a=[]){ foreach($a as $k=>$v){ $this->$k=$v; } }
} }
class WP_Error{ function __construct(public $code,public $msg,public $data=[]){} function get_error_message(){return $this->msg;} function get_error_code(){return $this->code;} function get_error_data(){return $this->data;} }
class WP_REST_Response{ function __construct(public $data,public $status=200){} }
class WP_REST_Request implements ArrayAccess{ function __construct(public array $p){} function get_param($k){return $this->p[$k]??null;}
 function offsetGet($k):mixed{return $this->p[$k]??null;} function offsetExists($k):bool{return isset($this->p[$k]);} function offsetSet($k,$v):void{} function offsetUnset($k):void{} }
function wp_next_scheduled($h){return (int)($GLOBALS['next_scheduled'][$h]??0);} function wp_schedule_event(...$a){$GLOBALS['cron'][]=$a[2];} function flush_rewrite_rules($h=true){$GLOBALS['flushed']=1;} function add_rewrite_rule(...$a){$GLOBALS['rules'][]=$a[0];} function wp_set_option_autoload_values($a){$GLOBALS['noauto']=$a;}
class W{ public $prefix='wp_'; public $posts='wp_posts'; public $postmeta='wp_postmeta'; public $options='wp_options'; public $comments='wp_comments'; function get_charset_collate(){return '';} public $insert_id=0; public $log=[]; public $next=null; public $row=null;
 function get_var($q){$this->log[]=$q; return is_array($this->next)?array_shift($this->next):$this->next;} function prepare($q,...$a){ if(count($a)==1&&is_array($a[0]))$a=$a[0]; return vsprintf(str_replace(['%s','%d','%f'],["'%s'",'%d','%F'],$q),$a);} function esc_like($s){return $s;}
 function get_results($q,$o=null){$this->log[]=$q;return $GLOBALS['rows']??[];} function get_row($q){$this->log[]=$q;return $this->row ?? null;} function query($q){$this->log[]=$q;return 0;}
 function insert($t,$d){$this->log[]="INSERT $t ".json_encode($d); $this->insert_id=42; return 1;} function update($t,$d,$w){$this->log[]="UPDATE $t ".json_encode($d);return 1;} }
$wpdb=new W; $wpdb->row=null;
// ---- P5: forum + Reddit stubs (kept here so the plugin can boot without WordPress) ----
$GLOBALS['reg_post_types']=[]; $GLOBALS['reg_taxonomies']=[]; $GLOBALS['shortcodes']=[];
function register_post_type($t,$a=[]){ $GLOBALS['reg_post_types'][$t]=$a; return true; }
function register_taxonomy($t,$o,$a=[]){ $GLOBALS['reg_taxonomies'][$t]=$a; return true; }
function add_shortcode($t,$cb){ $GLOBALS['shortcodes'][$t]=$cb; }
function shortcode_atts($d,$a=[],$s=''){ return array_merge($d,(array)$a); }
function has_shortcode($c,$t){ return str_contains((string)$c,'['.$t); }
function is_singular($t=''){ return $t==='' ? (bool)($GLOBALS['is_singular']??false) : (($GLOBALS['is_singular_type']??'')===$t); }
function get_queried_object_id(){ return (int)($GLOBALS['queried_id']??0); }
function get_the_ID(){ return (int)($GLOBALS['queried_id']??0); }
function is_user_logged_in(){ return ($GLOBALS['logged_in']??true)!==false; }
function wp_login_url($r=''){ return 'https://steamgears.eu/wp-login.php'; }
function wp_registration_url(){ return 'https://steamgears.eu/wp-login.php?action=register'; }
function wp_safe_redirect($u,$s=302){ $GLOBALS['redirects'][]=$u; return true; }
function wp_kses_post($s){ return (string)$s; }
function wp_unslash($v){ return $v; }
function wp_insert_post($a,$e=false){ $GLOBALS['inserted_posts'][]=$a; return 123; }
function wp_insert_term($n,$t,$a=[]){ $GLOBALS['inserted_terms'][]=[$n,$t]; return ['term_id'=>7]; }
function term_exists($t,$tax=''){ return $GLOBALS['existing_terms'][$t]??null; }
function wp_set_object_terms($id,$terms,$tax,$append=false){ $GLOBALS['object_terms'][(int)$id]=$terms; return $terms; }
function get_terms($a=[]){ return $GLOBALS['terms']??[]; }
function wp_get_post_terms($id,$tax,$a=[]){ return $GLOBALS['terms_for'][(int)$id]??[]; }
function get_term_meta($id,$k,$single=false){ return $GLOBALS['termmeta'][(int)$id][$k] ?? $GLOBALS['term_meta'][(int)$id][$k] ?? ''; }
function get_post_type_archive_link($t){ return home_url('/forum/'); }
function get_query_var($k,$d=''){ return $GLOBALS['query_vars'][$k]??$d; }
function get_the_date($f='',$id=null){ return date($f, strtotime('2026-11-09 09:00:00')); }
function get_the_modified_date($f='',$id=null){ return date($f, strtotime('2026-11-09 10:00:00')); }
function get_post_field($f,$id){ return $GLOBALS['post_fields'][(int)$id][$f]??''; }
function get_the_author_meta($f,$id=0){ return $f==='display_name'?'Henning':'x'; }
function get_comments($a=[]){ return $GLOBALS['comments']??[]; }
function get_comments_number($id){ return (int)($GLOBALS['comments_for'][(int)$id]??0); }
function sanitize_title($s){ return trim(preg_replace('/[^a-z0-9]+/','-',strtolower((string)$s)),'-'); }
function paginate_links($a=[]){ return ''; }
class WP_Query {
    public $posts=[]; public $found_posts=0; public $max_num_pages=1;
    function __construct($a=[]){ $this->posts=$GLOBALS['query_posts']??[]; $this->found_posts=count($this->posts); }
    function have_posts(){ return false; }
}
require __DIR__.'/../steamgears-autopilot/steamgears-autopilot.php';
function check($name,$ok){ echo ($ok?'PASS ':'FAIL ')."$name\n"; if(!$ok) $GLOBALS['fail']=1; }
do_action('init'); do_action('admin_init'); do_action('admin_menu');
check('install: 10 tables', count($GLOBALS['ddl']??[])===10); check('install: publish log table', str_contains(implode("\n",$GLOBALS['ddl']??[]),'sgap_publish_log')); check('install: table list covers all ten', count(SGAP_Install::TABLES)===10 && in_array('sgap_publish_log',SGAP_Install::TABLES,true) && in_array('sgap_reddit_posts',SGAP_Install::TABLES,true)); check('install: cleanup cron', in_array('sgap_daily_cleanup',$GLOBALS['cron']??[])); check('install: /go/ rule + flush', ($GLOBALS['rules'][0]??'')==='^go/([0-9]+)/?$' && !empty($GLOBALS['flushed'])); check('install: secrets not autoloaded', isset($GLOBALS['noauto']['sgap_ai_api_key'])); check('version stored', $opts['sgap_version']===SGAP_VERSION);
// P3.3 — AI-profiler: persona, værktøjsadgang og access all
check('profiles: three profiles', count(SGAP_Profiles::all())===3 && isset(SGAP_Profiles::all()['site_admin'], SGAP_Profiles::all()['wp_seo_expert']));
check('profiles: default is the limited assistant', SGAP_Profiles::id()==='assistant' && !SGAP_Profiles::is_full());
check('profiles: assistant keeps the chat exclusions', in_array('manage-plugin', SGAP_Profiles::excluded(), true));
check('profiles: an unknown id never becomes full access', SGAP_Profiles::valid('root')==='assistant');
SGAP_Settings::set('ai_profile','wp_seo_expert');
check('profiles: seo profile still refuses update-settings', in_array('update-settings', SGAP_Profiles::excluded(), true) && SGAP_Profiles::access()==='limited');
check('profiles: seo persona is a WordPress SEO expert', str_contains(SGAP_Profiles::prompt(),'SEO expert'));
check('profiles: seo boosts the seo category first', in_array('seo', SGAP_Profiles::boost(), true));
check('profiles: chat system prompt carries the persona', str_contains(SGAP_Chat::system_prompt(),'SEO expert'));
$GLOBALS['opts']['mwai_options']=['mcp_dynamic_rest'=>false,'mcp_abilities'=>false];
$r=SGAP_Profiles::activate('site_admin');
check('profiles: site_admin activates access all', ($r['access']??'')==='all' && get_option('mwai_options')['mcp_dynamic_rest']===true && get_option('mwai_options')['mcp_abilities']===true);
check('profiles: access all drops the exclusions but keeps the full-access switch shut', SGAP_Profiles::excluded()===['enable-full-access']);
check('profiles: site_admin persona is a site administrator', str_contains(SGAP_Profiles::prompt(),'site administrator') && str_contains(SGAP_Chat::system_prompt(),'full access'));
check('profiles: unknown profile is refused', SGAP_Profiles::activate('nope') instanceof WP_Error);
check('profiles: set-ai-profile switches back to the assistant', (SGAP_AdminTools::set_ai_profile(['profile'=>'assistant'])['profile']??'')==='assistant' && SGAP_Profiles::id()==='assistant');
check('profiles: the switch is offered in Settings', isset(SGAP_Settings::CHOICE_KEYS['ai_profile']) && in_array('ai_profile', SGAP_Settings::SECTIONS['AI content'], true));
unset($GLOBALS['opts']['mwai_options']);
foreach(['steamgears-autopilot','sgap-settings','sgap-queue','sgap-ai','sgap-campaigns','sgap-etsy','sgap-reports','sgap-fullauto','sgap-merchant','sgap-setup','sgap-studio','sgap-chat','sgap-tools','sgap-log'] as $p){ ob_start(); do_action('page_'.$p); $html=ob_get_clean(); check("page $p renders", strlen($html)>20); }
// Etsy er manuel import: felterne er væk fra indstillingerne, og siden lover ikke en API der ikke findes.
ob_start(); do_action('page_sgap-settings'); $set_html=ob_get_clean();
check('etsy: no credential fields in Settings', !str_contains($set_html,'etsy_shop_id') && !str_contains($set_html,'etsy_oauth_token') && !str_contains($set_html,'Etsy shop ID'));
check('etsy: shop tab still renders the feed secret', str_contains($set_html,'merchant_feed_secret'));
ob_start(); do_action('page_sgap-etsy'); $etsy_html=ob_get_clean();
check('etsy: page says manual import', str_contains($etsy_html,'Manual import') && str_contains($etsy_html,'/etsy/orders'));
check('etsy: dead credential keys rejected', SGAP_AdminTools::update_settings(['settings'=>['etsy_shop_id'=>'x']]) instanceof WP_Error);
// secrets never echoed
$opts['sgap_ai_api_key']='SECRET123'; ob_start(); do_action('page_sgap-settings'); $html=ob_get_clean();
check('secret not in settings html', !str_contains($html,'SECRET123') && str_contains($html,'Saved'));
$san=$settings['sgap_ai_api_key']['sanitize_callback'];
check('empty secret keeps old', $san('')==='SECRET123'); check('new secret saved',$san('NEW')==='NEW');
$_POST['sgap_clear']['ai_api_key']=1; check('clear secret', $san('')===''); unset($_POST['sgap_clear']);
// Skrivevejene uden om formularen: nøgler må ikke autoloades, og et tomt felt må ikke slette dem.
$img_before=get_option('sgap_image_api_key','');
SGAP_Settings::set('image_api_key','IMGSECRET');
check('secret writes are never autoloaded', ($GLOBALS['autoload']['sgap_image_api_key']??null)===false && get_option('sgap_image_api_key')==='IMGSECRET');
$cap_before=get_option('sgap_daily_cap'); SGAP_Settings::set('daily_cap',5);
check('plain settings keep the default autoload', array_key_exists('sgap_daily_cap',$GLOBALS['autoload']) && $GLOBALS['autoload']['sgap_daily_cap']===null);
SGAP_Settings::set('daily_cap',$cap_before);
$r=SGAP_AdminTools::update_settings(['settings'=>['image_api_key'=>'']]);
check('empty secret through update-settings keeps the key', get_option('sgap_image_api_key')==='IMGSECRET' && ($r['changed']['image_api_key']??'')==='kept (the new value was empty)');
$r=SGAP_AdminTools::update_settings(['settings'=>['image_api_key'=>'IMGSECRET2']]);
check('new secret through update-settings is saved without autoload', get_option('sgap_image_api_key')==='IMGSECRET2' && ($GLOBALS['autoload']['sgap_image_api_key']??null)===false && ($r['changed']['image_api_key']??'')==='updated');
SGAP_Settings::set('image_api_key',$img_before);
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
// scheduled_at is site wall-clock time; an impossible date is a 400, not "now".
check('schedule: a local date keeps its wall-clock value', SGAP_Queue::schedule('2026-12-25 10:00')==='2026-12-25 10:00:00' && SGAP_Queue::schedule('2026-12-25')==='2026-12-25 00:00:00');
check('schedule: an impossible date is refused', SGAP_Queue::schedule('2026-02-31')===null && SGAP_Queue::schedule('2026-13-01 10:00')===null);
$wpdb->next=null; $wpdb->row=null;
$r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'Bad date','destination_url'=>'https://steamgears.eu/bad-date/','scheduled_at'=>'2026-02-31 10:00']));
check('queue: an impossible scheduled_at is 400', $r instanceof WP_Error && $r->data['status']===400);
$r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'Good date','destination_url'=>'https://steamgears.eu/good-date/','scheduled_at'=>'2026-12-25 10:00']));
check('queue: a valid scheduled_at is returned without a timezone shift', $r instanceof WP_REST_Response && ($r->data['scheduled_at']??'')==='2026-12-25 10:00:00');
$opts['sgap_quality_gate']='block'; $opts['sgap_banned_words']='scam'; $wpdb->next=null; $wpdb->row=null;
$r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'False force','destination_url'=>'https://steamgears.eu/false-force/','body'=>'This is a scam','force'=>'false']));
check('queue: force=false is false, not a truthy string', $r instanceof WP_Error && $r->code==='sgap_quality');
$r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'False remind','destination_url'=>'https://steamgears.eu/false-remind/','body'=>'Fresh titanium gears in the shop','remind'=>'false']));
check('queue: remind=false is false', $r instanceof WP_REST_Response && ($r->data['remind']??1)===0);
unset($opts['sgap_quality_gate'],$opts['sgap_banned_words']); $wpdb->next=null; $wpdb->row=null;
// utm_campaign: one setting for the everyday stream, one optional field for a named campaign.
$utm=fn(array $a)=>SGAP_UTM::build($a+['destination_url'=>'https://steamgears.eu/x/']);
check('utm campaign default', str_contains($utm(['channel'=>'pinterest']),'utm_campaign=steamgears_premium_evergreen'));
$opts['sgap_utm_campaign']='clockwork_punk_2026_11';
check('utm campaign from setting', str_contains($utm(['channel'=>'pinterest']),'utm_campaign=clockwork_punk_2026_11'));
check('utm campaign per post wins', str_contains($utm(['channel'=>'pinterest','campaign'=>'Spring Drop!']),'utm_campaign=springdrop'));
check('utm campaign sanitized', SGAP_UTM::campaign('Clockwork Punk 2026/11')==='clockworkpunk202611');
check('utm campaign never empty', SGAP_UTM::campaign('')==='clockwork_punk_2026_11');
unset($opts['sgap_utm_campaign']);
check('utm campaign falls back', SGAP_UTM::campaign('')==='steamgears_premium_evergreen');
$campaign_san=$settings['sgap_utm_campaign']['sanitize_callback'] ?? null;
check('utm campaign setting: empty = default', is_callable($campaign_san) && $campaign_san('')==='steamgears_premium_evergreen' && $campaign_san('Autumn Sale')==='autumnsale');
check('utm campaign setting on the page', in_array('utm_campaign',SGAP_Settings::SECTIONS['General'],true) && isset(SGAP_Settings::HELP['utm_campaign']));
$wpdb->next=null; $before=count($wpdb->log);
$r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'Campaign pin','destination_url'=>'https://steamgears.eu/g/','campaign'=>'Clockwork Punk 2026_11']));
check('queue: per-post campaign in utm url', $r instanceof WP_REST_Response && str_contains((string)($r->data['utm_url']??''),'utm_campaign=clockworkpunk2026_11'));
check('queue: per-post campaign saved on the row', str_contains(implode("\n",array_slice($wpdb->log,$before)),'utm_campaign=clockworkpunk2026_11'));
$wpdb->next=null; $wpdb->row=['id'=>7,'status'=>'queued','scheduled_at'=>'2026-10-30 12:00:00','published_at'=>null]; $r=SGAP_REST::create_queue(new WP_REST_Request(['channel'=>'pinterest','title'=>'Goggles','destination_url'=>'https://steamgears.eu/g/']));
check('duplicate 409', $r instanceof WP_Error && $r->data['status']===409);
// ---- P0.6: the same post cannot enter the queue twice, from any writer ----
check('dupe: the fingerprint is channel + destination + title', SGAP_Duplicate::fingerprint('pinterest','https://steamgears.eu/g/','Goggles')===hash('sha256','pinterest|https://steamgears.eu/g/|Goggles'));
check('dupe: another title is another post', SGAP_Duplicate::fingerprint('pinterest','https://steamgears.eu/g/','Goggles')!==SGAP_Duplicate::fingerprint('pinterest','https://steamgears.eu/g/','Goggles 2'));
unset($opts['sgap_dupe_days']);
check('dupe: the window defaults to 30 days', SGAP_Duplicate::days()===30);
$opts['sgap_dupe_days']=0; $wpdb->log=[];
$wpdb->row=['id'=>12,'status'=>'published','scheduled_at'=>'2026-01-01 10:00:00','published_at'=>'2026-01-01 10:05:00'];
$found=SGAP_Duplicate::find(SGAP_Duplicate::fingerprint('pinterest','https://steamgears.eu/g/','Goggles'));
check('dupe: 0 days means the whole history', is_array($found) && $found['id']===12);
check('dupe: no window in the SQL when it is off', !str_contains(implode("\n",$wpdb->log),'COALESCE'));
$opts['sgap_dupe_days']=30; $wpdb->log=[];
SGAP_Duplicate::find(SGAP_Duplicate::fingerprint('pinterest','https://steamgears.eu/g/','Goggles'));
$dup_sql=implode("\n",$wpdb->log);
check('dupe: a queued post blocks whatever its age', str_contains($dup_sql,"'queued', 'sending', 'draft'"));
check('dupe: a published post only blocks inside the window', str_contains($dup_sql,'COALESCE(published_at, sent_at, scheduled_at)'));
check('dupe: failed rows never block', str_contains($dup_sql,"status <> 'failed'"));
check('dupe: the lookup is by fingerprint', str_contains($dup_sql,'fingerprint = '));
$msg=SGAP_Duplicate::message(['id'=>12,'status'=>'queued','scheduled_at'=>'2026-11-08 10:00:00','published_at'=>null]);
check('dupe: the refusal names the row it collided with', str_contains($msg,'#12') && str_contains($msg,'queued') && str_contains($msg,'30 days'));
$wpdb->row=['id'=>12,'status'=>'queued','scheduled_at'=>'2026-11-08 10:00:00','published_at'=>null];
$again=SGAP_Queue::add(['channel'=>'pinterest','title'=>'Goggles','destination_url'=>'https://steamgears.eu/g/']);
check('dupe: the queue refuses a cross-system repeat with 409', is_wp_error($again) && $again->code==='sgap_duplicate' && ($again->data['status']??0)===409 && ($again->data['id']??0)===12);
check('dupe: the refusal carries the fingerprint', ($again->data['fingerprint']??'')===SGAP_Duplicate::fingerprint('pinterest','https://steamgears.eu/g/','Goggles'));
$wpdb->row=null; $wpdb->log=[];
$fresh=SGAP_Queue::add(['channel'=>'pinterest','title'=>'Goggles','destination_url'=>'https://steamgears.eu/g/','body'=>'Fresh titanium gears in the shop']);
check('dupe: an unseen post still queues', is_array($fresh));
check('dupe: the window is a number on the settings page', isset(SGAP_Settings::INT_KEYS[array_search('dupe_days',SGAP_Settings::INT_KEYS,true)]) && in_array('dupe_days',SGAP_Settings::SECTIONS['General'],true) && isset(SGAP_Settings::HELP['dupe_days']));
ob_start(); do_action('page_sgap-settings'); $dh=ob_get_clean();
check('dupe: the setting is rendered', str_contains($dh,'name="sgap_dupe_days"'));
$opts['sgap_autopilot_enabled']='no'; $r=SGAP_REST::due(new WP_REST_Request([])); check('due off', $r['reason']==='autopilot_disabled');
$opts['sgap_autopilot_enabled']='yes'; $opts['sgap_quiet_start']=0; $opts['sgap_quiet_end']=0; $wpdb->next=8; $opts['sgap_daily_cap']=8;
$r=SGAP_REST::due(new WP_REST_Request([])); check('due cap reached', $r['reason']==='daily_cap_reached');
$wpdb->next=2; $r=SGAP_REST::due(new WP_REST_Request([])); check('due limit = min(batch5, 8-2)', str_contains(end($wpdb->log),'LIMIT 5'));
$r=SGAP_REST::draft_status(new WP_REST_Request(['id'=>3,'status'=>'bogus'])); check('draft bad status 400', $r instanceof WP_Error);
$wpdb->next=0; $r=SGAP_REST::draft_status(new WP_REST_Request(['id'=>999,'status'=>'approved'])); check('draft unknown id 404', $r instanceof WP_Error && $r->data['status']===404);
$wpdb->row=null; $r=SGAP_REST::queue_status(new WP_REST_Request(['id'=>3,'status'=>'published'])); check('queue status unknown id 404', $r instanceof WP_Error && $r->data['status']===404);
$wpdb->next=3; $wpdb->log=[]; $r=SGAP_REST::queue_status(new WP_REST_Request(['id'=>3,'status'=>'queued']));
$qlog=implode("\n",$wpdb->log);
check('queue status queued retries and resets the row', is_array($r) && ($r['status']??'')==='queued' && str_contains($qlog, '"status":"queued"') && str_contains($qlog, '"attempts":0'));
$wpdb->next=0; $r=SGAP_REST::queue_status(new WP_REST_Request(['id'=>999,'status'=>'queued'])); check('queue status queued unknown id 404', $r instanceof WP_Error && $r->data['status']===404);
$wpdb->next=null;
// Dashboard snapshot: one read-only call for an outside app (Command Center).
update_option('sgap_ai_api_key','TOPSECRET');
$snap=SGAP_REST::snapshot(new WP_REST_Request(['days'=>30,'limit'=>5]));
check('snapshot: every section present', is_array($snap) && isset($snap['generated_at'],$snap['site'],$snap['status'],$snap['counts'],$snap['results'],$snap['queue'],$snap['drafts'],$snap['settings'],$snap['missing_tables']));
check('snapshot: reports the installed version', ($snap['version']??'')===SGAP_VERSION);
check('snapshot: secrets only as set/not set', !str_contains(wp_json_encode($snap),'TOPSECRET') && ($snap['settings']['AI content']['ai_api_key']['value']??'')==='set');
check('snapshot: window echoes the requested days', ($snap['window']['days']??0)===30);
check('snapshot: queue rows expose the duplicate fingerprint', array_key_exists('fingerprint', (array)($snap['queue'][0] ?? ['fingerprint'=>null])) || empty($snap['queue']));

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
// OpenRouter som egen provider: fast adresse, intet opdigtet standardmodel, og en nøgletest
// der beviser nøglen med ét token — OpenRouter svarer /models uden nøgle, så den vej ville
// kalde enhver nøgle gyldig.
update_option('sgap_ai_provider','openrouter'); update_option('sgap_ai_model','');
update_option('sgap_ai_base_url','https://wrong.example/v1'); update_option('sgap_ai_api_key','sk-or-v1-fixture');
check('openrouter: the provider is accepted and named', SGAP_AI::provider()==='openrouter' && SGAP_AI::provider_name()==='OpenRouter');
check('openrouter: the address is fixed, so a typed base url cannot redirect the key', SGAP_AI::base_url()==='https://openrouter.ai/api/v1' && SGAP_AI::uses_chat_completions());
check('openrouter: there is no invented default model', SGAP_AI::default_model()==='' && SGAP_AI::model()==='');
$GLOBALS['http_calls']=[];
$json=SGAP_AI::json_call('sys','prompt',['type'=>'object']);
check('openrouter: without a model the error names the provider and nothing is sent', $json instanceof WP_Error
    && str_contains($json->get_error_message(),'OpenRouter') && $GLOBALS['http_calls']===[]);
$no_model=SGAP_KeyCheck::check_ai();
check('openrouter: the key test asks for a model before spending a request', ($no_model['ok']??null)===false
    && str_contains($no_model['message'],'pick an AI model') && $GLOBALS['http_calls']===[]);
update_option('sgap_ai_model','anthropic/claude-sonnet-4.6');
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(401,['error'=>['message'=>'User not found.']])];
$bad=SGAP_KeyCheck::check_ai();
check('openrouter: a refused key is refused, with the api reason', ($bad['ok']??null)===false
    && str_contains($bad['message'],'refused') && str_contains($bad['message'],'User not found'));
$GLOBALS['http_queue']=[sgap_http(402,['error'=>['message'=>'Insufficient credits']])];
$no_credit=SGAP_KeyCheck::check_ai();
check('openrouter: an empty account is named as no credit', ($no_credit['ok']??null)===false && str_contains($no_credit['message'],'no credit'));
$GLOBALS['http_queue']=[sgap_http(404,['error'=>['message'=>'No endpoints found for model']])];
$gone=SGAP_KeyCheck::check_ai();
check('openrouter: a model it does not offer says so', str_contains($gone['message'],'does not offer the model'));
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['choices'=>[['message'=>['content'=>''],'finish_reason'=>'length']]])];
$ok=SGAP_KeyCheck::check_ai();
check('openrouter: a working key passes with one token spent, on its own endpoint', ($ok['ok']??null)===true
    && str_contains($ok['message'],'anthropic/claude-sonnet-4.6') && count($GLOBALS['http_calls'])===1
    && $GLOBALS['http_calls'][0][0]==='https://openrouter.ai/api/v1/chat/completions');
$GLOBALS['http_queue']=[];
update_option('sgap_ai_provider','anthropic'); update_option('sgap_ai_model',''); update_option('sgap_ai_base_url','');
check('anthropic: default model unchanged', SGAP_AI::model()==='claude-opus-5-5' && !SGAP_AI::uses_chat_completions());
check('ai: JSON inside a ```json fence is parsed', count(SGAP_AI::parse_posts("Here you go:\n```json\n{\"posts\":[{\"channel\":\"facebook\",\"text\":\"Hi\"}]}\n```",['facebook']))===1);
check('rss: Publer gets a query-string feed URL (page caches skip it)', SGAP_RSS::url('facebook')==='https://steamgears.eu/?sgap_feed=facebook');
check('rss: newlines become a literal \\n for Publer', SGAP_RSS::description("Line one\r\nLine two\nThree")==='Line one\\nLine two\\nThree');
check('bot: Publer link scraper is not a click', SGAP_Tracking::is_bot('facebookplatform/1.0 (+http://developers.facebook.com)'));
check('updater: reads Version from a plugin header', SGAP_Updater::parse_version("<?php\n/**\n* Plugin Name: X\n* Version: 7.6.2\n*/")==='7.6.2');
check('updater: no version in junk', SGAP_Updater::parse_version('404: Not Found')==='');
check('updater: package url points at the versioned zip on main', SGAP_Updater::package_url('7.6.2')==='https://raw.githubusercontent.com/humle93/steamgears-site/main/plugins/steamgears-autopilot-v7.6.2.zip');
// Release notes i update-modalen (P3.4): parses fra roadmapens changelog, nyeste først.
$notes_md="| Date | Plugin | Change |\n| --- | --- | --- |\n| 2026-11-06 | 7.34.0 | **Ny** note med `code` og <script>alert(1)</script> |\n| 2026-11-05 | 7.33.4 | Ældre note |\n";
$notes=SGAP_Updater::changelog_notes($notes_md, 2);
check('updater: changelog renders the newest rows', str_contains($notes,'7.34.0') && str_contains($notes,'<strong>Ny</strong>') && str_contains($notes,'<code>code</code>') && str_contains($notes,'7.33.4'));
check('updater: changelog escapes html from the repo', !str_contains($notes,'<script>') && str_contains($notes,'&lt;script&gt;'));
check('updater: changelog respects the limit', substr_count(SGAP_Updater::changelog_notes($notes_md,1),'<li>')===1);
check('updater: changelog is empty without rows', SGAP_Updater::changelog_notes('# no table here')==='');
$info=SGAP_Updater::plugin_info(null,'plugin_information',(object)['slug'=>'steamgears-autopilot']);
check('updater: modal always has a changelog section', isset($info->sections['changelog']) && strlen($info->sections['changelog'])>10);
check('updater: modal ignores other slugs', SGAP_Updater::plugin_info('keep','plugin_information',(object)['slug'=>'other'])==='keep');
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
// --- P0.2 per channel: a healthy total must not hide one channel running dry ---
$GLOBALS['rows']=[]; $GLOBALS['mails']=[]; update_option('sgap_alert_mode','immediate'); delete_option('sgap_alerts_last');
update_option('sgap_channel_caps','pinterest=2, facebook=3, pinterest99=2, instagram=0');
check('alerts: caps parse, unknown channels and bad numbers ignored', SGAP_Alerts::caps()===['pinterest'=>2,'facebook'=>3]);
$GLOBALS['rows']=[['channel'=>'pinterest','n'=>4],['channel'=>'facebook','n'=>8],['channel'=>'twitter','n'=>16]];
$chan=SGAP_Alerts::channel_runway();
check('alerts: a capped channel uses its own cap, the rest the daily cap', $chan['pinterest']['days']===2.0 && $chan['facebook']['cap']===3 && $chan['twitter']['cap']===8);
check('alerts: per-channel runway is queued divided by that cap', $chan['facebook']['days']===2.7 && $chan['twitter']['days']===2.0);
$GLOBALS['rows']=[['channel'=>'pinterest','n'=>1],['channel'=>'facebook','n'=>40]];
$dry=SGAP_Alerts::dry_channels();
check('alerts: a capped channel with a thin queue is dry', isset($dry['pinterest']) && $dry['pinterest']['queued']===1 && $dry['pinterest']['days']===0.5);
check('alerts: a channel above the warning level is not dry', !isset($dry['facebook']));
update_option('sgap_channel_caps',''); $GLOBALS['rows']=[['channel'=>'facebook','n'=>40]];
check('alerts: without a cap a healthy channel is never a gap', SGAP_Alerts::dry_channels()===[]);
update_option('sgap_channel_caps','pinterest=2'); $GLOBALS['rows']=[['channel'=>'pinterest','n'=>0],['channel'=>'facebook','n'=>40]];
$wpdb->next=[8,0]; SGAP_Alerts::after_health([]);
$pins=array_values(array_filter($GLOBALS['mails'],fn($m)=>str_contains($m[1],'pinterest is running dry')));
check('alerts: a dry channel gets its own mail', count($pins)===1);
check('alerts: the channel mail names the queue and the cap', str_contains($pins[0][2],'0 queued') && str_contains($pins[0][2],'2/day'));
$before=count($GLOBALS['mails']); SGAP_Alerts::after_health([]);
check('alerts: the same dry channel is not mailed twice', count($GLOBALS['mails'])===$before);
update_option('sgap_alert_mode','daily'); delete_option('sgap_alerts_last'); $GLOBALS['mails']=[];
$wpdb->next=[40,0]; $GLOBALS['rows']=[['channel'=>'pinterest','n'=>0],['channel'=>'facebook','n'=>40]];
SGAP_Alerts::after_health([]);
check('alerts: the digest lists each channel runway', count($GLOBALS['mails'])===1 && str_contains($GLOBALS['mails'][0][2],'pinterest: 0 queued') && str_contains($GLOBALS['mails'][0][2],'Channels below the warning level: pinterest'));
update_option('sgap_alert_dry_days',0); update_option('sgap_alert_mode','immediate'); delete_option('sgap_alerts_last'); $GLOBALS['mails']=[];
$wpdb->next=[8,0]; SGAP_Alerts::after_health([]);
check('alerts: warning level 0 turns the channel alarm off too', SGAP_Alerts::dry_channels()===[] && $GLOBALS['mails']===[]);
update_option('sgap_alert_dry_days',2); update_option('sgap_channel_caps',''); $GLOBALS['rows']=[]; $wpdb->next=0;
// --- channel_caps as a real ceiling (P0.2 follow-up) ---
update_option('sgap_channel_caps','pinterest=2'); $GLOBALS['rows']=[['channel'=>'pinterest','n'=>2]];
$wpdb->next=0;
check('ceiling: a channel at its cap is reported full', SGAP_Queue::full_channels()===['pinterest']);
update_option('sgap_channel_caps','pinterest=2'); $GLOBALS['rows']=[['channel'=>'pinterest','n'=>1]];
check('ceiling: a channel under its cap is not full', SGAP_Queue::full_channels()===[]);
update_option('sgap_channel_caps',''); $GLOBALS['rows']=[['channel'=>'pinterest','n'=>9]];
check('ceiling: without channel_caps nothing is ever full', SGAP_Queue::full_channels()===[]);
update_option('sgap_channel_caps','pinterest=2'); $GLOBALS['rows']=[['channel'=>'pinterest','n'=>2]];
$wpdb->log=[]; SGAP_Queue::claim(5);
check('ceiling: claim skips a full channel in the query', implode(' ',$wpdb->log) !== '' && str_contains(implode(' ',$wpdb->log),'NOT IN'));
update_option('sgap_channel_caps',''); $GLOBALS['rows']=[]; $wpdb->log=[];
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
// Siden tjekker først at tabellen findes (SHOW TABLES), derefter antallet: to svar i kø.
$wpdb->next=['wp_sgap_publish_log',7];
$GLOBALS['rows']=[
    (object)['id'=>3,'queue_id'=>901,'channel'=>'pinterest','outcome'=>'sent','http_status'=>200,'actor'=>'cron','message'=>'Publer job job_1','created_at'=>'2026-11-07 10:00:00'],
    (object)['id'=>2,'queue_id'=>902,'channel'=>'instagram','outcome'=>'failed','http_status'=>429,'actor'=>'cron','message'=>'Publer: HTTP 429','created_at'=>'2026-11-07 09:00:00'],
    (object)['id'=>1,'queue_id'=>903,'channel'=>'bluesky','outcome'=>'requeued','http_status'=>0,'actor'=>'admin','message'=>'Try again later','created_at'=>'2026-11-07 08:00:00'],
];
ob_start(); do_action('page_sgap-log'); $html=ob_get_clean();
check('audit: the log page explains the retention window', str_contains($html,'Kept for 90 days'));
$lf = strpos($html,'<h2>Filter</h2>'); $la = strpos($html,'<h2>Attempts</h2>');
check('log 7.55: filter first, then the attempts it produced', $lf !== false && $la !== false && $lf < $la);
check('log 7.55: the counts describe the rows on screen', str_contains($html,'<b>1</b><span>Sent</span>') && str_contains($html,'<b>1</b><span>Failed</span>') && str_contains($html,'<b>1</b><span>Requeued or held</span>'));
check('log 7.55: the rows are still there with their own message and queue link', str_contains($html,'Publer: HTTP 429') && str_contains($html,'#901'));
$GLOBALS['rows']=[]; $wpdb->next=null;

// AI Drafts: grupperet efter den side de er skrevet til, med tre tal over grupperne.
$wpdb->next='wp_sgap_ai_drafts';
$GLOBALS['rows']=[
    (object)['id'=>11,'post_id'=>5,'channel'=>'pinterest','title'=>'Brass goggles','body'=>'Body one','hashtags'=>'#steampunk','created_at'=>'2026-11-07 09:00:00','source'=>'How to pick goggles'],
    (object)['id'=>12,'post_id'=>5,'channel'=>'facebook','title'=>'Brass goggles again','body'=>'Body two','hashtags'=>'','created_at'=>'2026-11-07 09:05:00','source'=>'How to pick goggles'],
    (object)['id'=>13,'post_id'=>8,'channel'=>'bluesky','title'=>'Leather care','body'=>'Body three','hashtags'=>'','created_at'=>'2026-11-07 09:10:00','source'=>'Leather care 101'],
    (object)['id'=>14,'post_id'=>0,'channel'=>'mastodon','title'=>'Orphan','body'=>'Body four','hashtags'=>'','created_at'=>'2026-11-07 09:15:00','source'=>null],
];
ob_start(); do_action('page_sgap-ai'); $drafts55 = ob_get_clean();
check('drafts 7.55: one section and three numbers before the cards', str_contains($drafts55,'<h2>Waiting for your decision</h2>') && str_contains($drafts55,'<b>4</b><span>Drafts</span>') && str_contains($drafts55,'<b>3</b><span>Pages</span>') && str_contains($drafts55,'<b>4</b><span>Channels</span>'));
check('drafts 7.55: drafts for the same page are grouped under that page', str_contains($drafts55,'<h3>How to pick goggles</h3>') && str_contains($drafts55,'2 posts') && str_contains($drafts55,'<h3>Leather care 101</h3>'));
check('drafts 7.55: a draft without a source page is still shown, under its own heading', str_contains($drafts55,'<h3>No source page</h3>') && str_contains($drafts55,'>16</option>') === false && str_contains($drafts55,'#14'));
check('drafts 7.55: every draft keeps its three decisions', substr_count($drafts55,'value="queue"')===4 && substr_count($drafts55,'value="rejected"')===4);
$GLOBALS['rows']=[]; $wpdb->next=null;
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
// 6/11: site_checks() skrev «home» uden $ og gav en fatal error midt i en admin-request.
check('site seo: the domain checks run without a fatal error', count(SGAP_Site_SEO::site_checks()) === 4);
ob_start(); do_action('page_sgap-studio'); $studio = ob_get_clean();
check('site seo: the studio page carries the whole-site card and enqueues its scripts', str_contains($studio, 'sgap-seo-site-out') && str_contains($studio, 'Scan the whole site') && isset($GLOBALS['enqueued_scripts']['sgap-page-studio']) && isset($GLOBALS['enqueued_scripts']['sgap-page-seo']) && isset($GLOBALS['localized']['sgap-page-seo']['SGAP_SEO']['nonce']));
check('site seo: the card is not shipped as an inline script', !str_contains($studio, '<script>'));
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
check('quality: only real archive parameters count as an archive', SGAP_Quality::is_archive('https://steamgears.eu/?paged=2') && !SGAP_Quality::is_archive('https://steamgears.eu/gear/?utm_source=pinterest') && !SGAP_Quality::is_archive('https://steamgears.eu/?p=123'));
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
// ---- queue-post: a campaign post keeps its angle, format and campaign name ----
do_action('wp_abilities_api_init');
$qp=$GLOBALS['abilities']['steamgears-autopilot/queue-post']??null;
check('queue-post: angle, content_type and format are in the schema', is_array($qp) && isset($qp['input_schema']['properties']['angle'], $qp['input_schema']['properties']['content_type'], $qp['input_schema']['properties']['format'], $qp['input_schema']['properties']['campaign']));
$wpdb->next=null; $wpdb->log=[];
$camp=SGAP_Abilities::queue_post(['channel'=>'pinterest','title'=>'Campaign pin','text'=>'A brass moth with real escapements, printed to order for the wall.','destination_url'=>'https://steamgears.eu/gear/','image_url'=>'https://steamgears.eu/a.jpg','angle'=>'angle_c','content_type'=>'product','format'=>'pin','campaign'=>'Clockwork Punk 2026_11']);
$logged=implode("\n",$wpdb->log);
check('queue-post: the angle and format survive the ability', is_array($camp) && str_contains($logged,'"angle":"angle_c"') && str_contains($logged,'"format":"pin"') && str_contains($logged,'"content_type":"product"'));
check('queue-post: the campaign reaches the tracked link', is_array($camp) && str_contains($logged,'utm_campaign=clockworkpunk2026_11'));
$defaults=SGAP_Abilities::queue_post(['channel'=>'facebook','title'=>'Plain post','text'=>'A plain caption that is long enough to queue without a warning.','destination_url'=>'https://steamgears.eu/gear/']);
check('queue-post: defaults stay custom/post and angle_a', is_array($defaults) && str_contains(implode("\n",$wpdb->log),'"angle":"angle_a"') && str_contains(implode("\n",$wpdb->log),'"format":"post"'));
// ---- meta-audit: ability, summary, fixtures and the read-only promise ----
do_action('wp_abilities_api_init');
$ma=$GLOBALS['abilities']['steamgears-autopilot/meta-audit']??null;
check('meta-audit: ability registered', is_array($ma));
check('meta-audit: public for MCP and read-only', ($ma['meta']['mcp']['public']??false)===true && ($ma['meta']['annotations']['readonly']??false)===true && ($ma['meta']['annotations']['destructive']??true)===false);
check('meta-audit: requires manage_options', ($ma['permission_callback']??null)===[SGAP_Abilities::class,'can_manage']);
check('meta-audit: scan and limit are in the schema', isset($ma['input_schema']['properties']['scan'],$ma['input_schema']['properties']['limit']) && $ma['input_schema']['properties']['scan']['maximum']===500);
$rows=[
 ['id'=>1,'title'=>'Short','url'=>'u1','title_length'=>5,'description_length'=>0,'problems'=>['missing description']],
 ['id'=>2,'title'=>str_repeat('x',70),'url'=>'u2','title_length'=>70,'description_length'=>200,'problems'=>['description too long','title too long']],
 ['id'=>3,'title'=>'Fine','url'=>'u3','title_length'=>4,'description_length'=>120,'problems'=>[]]
];
$sum=SGAP_Meta_Audit::summarise($rows);
check('meta-audit: counts each problem type', $sum['counts']===['checked'=>3,'clean'=>1,'missing_description'=>1,'title_too_long'=>1,'description_too_long'=>1,'description_too_short'=>0]);
check('meta-audit: worst first, clean posts left out', count($sum['worst'])===2 && $sum['worst'][0]['id']===2 && $sum['worst'][1]['id']===1 && $sum['ok']===false);
check('meta-audit: an empty scan is ok', SGAP_Meta_Audit::summarise([])['ok']===true && SGAP_Meta_Audit::summarise([])['counts']['checked']===0);
check('meta-audit: length counts characters', SGAP_Meta_Audit::length('æøå')===3 || !function_exists('mb_strlen'));
$GLOBALS['posts_result']=[(object)['ID'=>11],(object)['ID'=>12]];
$GLOBALS['postmeta'][11]['rank_math_description']=str_repeat('d',160);
$GLOBALS['postmeta'][12]['rank_math_description']='';
$wpdb->log=[];
$audit=SGAP_Abilities::meta_audit(['scan'=>2,'limit'=>5]);
check('meta-audit: scans what it was asked to', ($audit['counts']['checked']??0)===2 && ($audit['scanned']??0)===2 && ($audit['seo_plugin']??'')!=='');
check('meta-audit: 160 characters is too long, an empty description is missing', ($audit['counts']['description_too_long']??0)===1 && ($audit['counts']['missing_description']??0)===1);
check('meta-audit: writes nothing to the database', preg_grep('/^(INSERT|UPDATE|DELETE)/',$wpdb->log)===[]);
$GLOBALS['posts_result']=[]; $GLOBALS['postmeta'][11]=[]; $GLOBALS['postmeta'][12]=[];
$opts['sgap_banned_words']=''; $GLOBALS['postid_for']=[]; $GLOBALS['poststatus']=[]; $GLOBALS['postmeta']=[]; $GLOBALS['attachment_for']=[];
$wpdb->row=null;
$wpdb->next=null;
// ---- MCP tool catalogue (includes/mcp-tools.php) ----
if (!function_exists('untrailingslashit')) { function untrailingslashit($s){ return rtrim((string)$s,'/'); } }
if (!function_exists('wp_unslash')) { function wp_unslash($v){ return $v; } }
if (!function_exists('nocache_headers')) { function nocache_headers(){} }
if (!function_exists('status_header')) { function status_header($c){ $GLOBALS['status']=$c; } }
if (!function_exists('get_query_var')) { function get_query_var($k){ return ''; } }
if (!function_exists('has_action')) { function has_action($h){ return false; } }
if (!function_exists('do_action_ref_array')) { function do_action_ref_array($h,$a=[]){ $GLOBALS['ran'][]=$h; } }
if (!function_exists('sanitize_title')) { function sanitize_title($s){ return trim(strtolower(preg_replace('/[^a-z0-9]+/i','-',(string)$s)),'-'); } }
if (!function_exists('wp_kses_post')) { function wp_kses_post($s){ return (string) $s; } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($r){ return 200; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($r){ return ''; } }
if (!function_exists('wp_get_abilities')) { function wp_get_abilities(){ return $GLOBALS['ability_objects']??[]; } }
if (!function_exists('wp_get_ability')) { function wp_get_ability($n){ return $GLOBALS['ability_objects'][$n]??null; } }
class SGAP_Fake_Ability { function __construct(public string $n, public array $a=[]){} function get_name(){return $this->n;} function get_label(){return 'Label '.$this->n;} function get_description(){return 'Does '.$this->n;} function get_category(){return $this->a['category']??'cat';} function get_input_schema(){return $this->a['input_schema']??null;} function get_meta(){return $this->a['meta']??['mcp'=>['public'=>true,'type'=>'tool']];} function check_permissions($x=[]){return true;} function execute($x=[]){return ['ran'=>$this->n,'input'=>$x];} }
do_action('wp_abilities_api_categories_init'); do_action('wp_abilities_api_init');
$tools=array_filter($GLOBALS['abilities'],fn($k)=>str_starts_with($k,'steamgears-tools/'),ARRAY_FILTER_USE_KEY);
check('tools: category registered', isset($GLOBALS['ability_categories']['steamgears-tools']));
check('tools: full catalogue registered', count($tools)===36);
check('tools: the actions we kept needing are there', !array_diff([
    'steamgears-tools/get-option','steamgears-tools/update-option','steamgears-tools/undo','steamgears-tools/set-plugin-state',
    'steamgears-tools/flush-rewrites','steamgears-tools/flush-caches','steamgears-tools/list-posts','steamgears-tools/update-post',
    'steamgears-tools/upload-media','steamgears-tools/update-product','steamgears-tools/indexnow-submit',
    'steamgears-tools/indexnow-status','steamgears-tools/sitemap-audit','steamgears-tools/list-abilities','steamgears-tools/call-ability'
], array_keys($tools)));
$bad=[];
foreach($tools as $n=>$a){
    if(!is_callable($a['execute_callback']??null)) $bad[]="$n: execute";
    if(!is_callable($a['permission_callback']??null)) $bad[]="$n: permission";
    if(!isset($a['meta']['mcp']['type'])) $bad[]="$n: mcp meta";
    if(isset($a['input_schema']) && ($a['input_schema']['type']??'')!=='object') $bad[]="$n: schema";
    if(isset($a['input_schema']['properties']) && ($a['input_schema']['additionalProperties']??null)!==false) $bad[]="$n: strict";
}
check('tools: every tool is callable and well-formed', $bad===[]);
check('tools: destructive tool is flagged', ($tools['steamgears-tools/trash-post']['meta']['annotations']['destructive']??false)===true);
check('tools: read-only tools are flagged read-only', ($tools['steamgears-tools/site-info']['meta']['annotations']['readonly']??false)===true);
check('tools: key options are never readable', SGAP_MCP_Tools::is_secret_option('rank_math_indexnow_key') && SGAP_MCP_Tools::is_secret_option('sgap_ai_api_key') && SGAP_MCP_Tools::is_secret_option('woocommerce_api_key'));
check('tools: ordinary options stay readable', !SGAP_MCP_Tools::is_secret_option('blogdescription') && !SGAP_MCP_Tools::is_secret_option('permalink_structure'));
check('tools: tagline is writable, siteurl is not', SGAP_MCP_Tools::is_writable_option('blogdescription') && !SGAP_MCP_Tools::is_writable_option('siteurl'));
check('tools: reading a key option is refused', SGAP_MCP_Tools::get_option_tool(['name'=>'rank_math_indexnow_key']) instanceof WP_Error);
$GLOBALS['opts']['blogdescription']='Old tagline';
$dry=SGAP_MCP_Tools::update_option_tool(['name'=>'blogdescription','value'=>'New']);
check('tools: writing is a dry run by default', $dry['written']===false && get_option('blogdescription')==='Old tagline');
check('tools: writing needs confirm', SGAP_MCP_Tools::update_option_tool(['name'=>'blogdescription','value'=>'New','dry_run'=>false])['written']===false);
$w=SGAP_MCP_Tools::update_option_tool(['name'=>'blogdescription','value'=>'New','dry_run'=>false,'confirm'=>true]);
check('tools: confirmed write lands and reports the old value', get_option('blogdescription')==='New' && $w['written']===true && $w['previous']==='Old tagline');
check('tools: undo restores the old value', SGAP_MCP_Tools::undo(['token'=>$w['undo_token']])['restored']==='blogdescription' && get_option('blogdescription')==='Old tagline');
check('tools: undo token works only once', SGAP_MCP_Tools::undo(['token'=>$w['undo_token']]) instanceof WP_Error);
check('tools: writing siteurl is refused', SGAP_MCP_Tools::update_option_tool(['name'=>'siteurl','value'=>'https://evil.example','dry_run'=>false,'confirm'=>true]) instanceof WP_Error);
$p=SGAP_MCP_Tools::indexnow_payload(['https://steamgears.eu/a','nope','https://steamgears.eu/b'],'k','https://steamgears.eu/k');
check('tools: indexnow payload has host, key, keyLocation and only http urls', $p['host']==='steamgears.eu' && $p['key']==='k' && $p['keyLocation']==='https://steamgears.eu/k' && $p['urlList']===['https://steamgears.eu/a','https://steamgears.eu/b']);
check('tools: indexnow payload is capped at 100 urls', count(SGAP_MCP_Tools::indexnow_payload(array_map(fn($i)=>"https://x/$i",range(1,150)),'k','u')['urlList'])===100);
check('tools: sitemap locs are parsed and trimmed', SGAP_MCP_Tools::sitemap_locs('<urlset><url><loc>https://a/1</loc></url><url><loc> https://a/2 </loc></url></urlset>')===['https://a/1','https://a/2']);
check('tools: empty or broken sitemap gives no locs', SGAP_MCP_Tools::sitemap_locs('<html>404</html>')===[]);
check('tools: key is 32 hex characters', preg_match('/^[a-f0-9]{32}$/',SGAP_MCP_Key::key())===1);
check('tools: key url carries the key on our own host', str_contains(SGAP_MCP_Key::url(),'sgap_indexnow_key='.SGAP_MCP_Key::key()));
check('tools: indexnow status probes without crashing', isset(SGAP_MCP_Tools::indexnow_status([])['key_url_served_correctly']));
check('tools: sitemap audit reports a fetch failure', isset(SGAP_MCP_Tools::sitemap_audit([])['error']));
check('tools: run-cron dispatches the hook', (SGAP_MCP_Tools::run_cron(['hook'=>'sgap_probe'])['ran']??false) && in_array('sgap_probe',$GLOBALS['ran']??[],true));
check('tools: empty registry is handled', (SGAP_MCP_Tools::list_abilities([])['count']??-1)===0);
check('tools: unknown ability is an error', SGAP_MCP_Tools::call_ability(['name'=>'nope/nope']) instanceof WP_Error);
check('tools: ability described from an array', SGAP_MCP_Tools::describe_ability(['name'=>'a/b','label'=>'A','category'=>'c'])['name']==='a/b');
check('tools: ability described from an object', SGAP_MCP_Tools::describe_ability(new SGAP_Fake_Ability('x/y'))['label']==='Label x/y');
$GLOBALS['ability_objects']=['x/y'=>new SGAP_Fake_Ability('x/y'),'z/q'=>new SGAP_Fake_Ability('z/q',['category'=>'other'])];
$la=SGAP_MCP_Tools::list_abilities([]);
check('tools: bridge lists every registered ability', $la['count']===2 && $la['abilities'][0]['name']==='x/y');
check('tools: bridge filters by category', SGAP_MCP_Tools::list_abilities(['category'=>'other'])['count']===1);
$called=SGAP_MCP_Tools::call_ability(['name'=>'x/y','input'=>['a'=>1]]);
check('tools: bridge runs an ability and echoes its name', $called['ran']==='x/y' && $called['_ability']==='x/y' && $called['input']===['a'=>1]);
// Indeks og kategorisøgning over abilities (7.38.0).
$described=[
 ['name'=>'steamgears-tools/list-posts','category'=>'steamgears-tools','description'=>'Lists posts','readonly'=>true],
 ['name'=>'steamgears-tools/create-post','category'=>'steamgears-tools','description'=>'Creates a post','readonly'=>false],
 ['name'=>'woo/report','category'=>'woo','description'=>'Sales report','readonly'=>true],
];
$idx=SGAP_MCP_Tools::index_abilities($described);
check('tools: the index groups by category with counts and read flags', ($idx['steamgears-tools']['count']??0)===2 && ($idx['steamgears-tools']['readonly']??0)===1 && ($idx['woo']['tools'][0]??'')==='woo/report');
$sr=SGAP_MCP_Tools::search_abilities($described,'report');
check('tools: search matches the description and counts hits', ($sr['matched']??0)===1 && ($sr['abilities'][0]['name']??'')==='woo/report');
check('tools: search can be limited to a category', (SGAP_MCP_Tools::search_abilities($described,'','steamgears-tools')['matched']??0)===2);
$lim=SGAP_MCP_Tools::search_abilities($described,'','',1);
check('tools: the limit slices the list without losing the count', ($lim['matched']??0)===3 && count($lim['abilities'])===1);
$aidx=SGAP_MCP_Tools::ability_index([]);
check('tools: ability-index reports the registered categories', ($aidx['total']??0)===2 && isset($aidx['categories']['other']));
$asearch=SGAP_MCP_Tools::search_abilities_tool(['query'=>'x/y']);
check('tools: search-abilities tool answers with the match', ($asearch['matched']??0)===1 && ($asearch['abilities'][0]['name']??'')==='x/y');
ob_start(); do_action('page_sgap-tools'); $tools_html=ob_get_clean();
check('tools page: renders a searchable, categorised browser', str_contains($tools_html,'sgap-tool-search') && str_contains($tools_html,'data-cat="other"') && str_contains($tools_html,'2 registered'));
unset($GLOBALS['ability_objects']);
// ---- Botten og MCP-fladen: chatten mÃ¥ lÃ¦se, men ikke skrive ----
$ro=SGAP_MCP_Tools::readonly_names();
check('chat: the read-only set covers the lookups', !array_diff(['site-info','get-option','list-posts','get-post','list-products','get-product','list-abilities'],$ro));
check('chat: the read-only set excludes every writer', !array_intersect(['create-post','update-post','trash-post','update-option','set-plugin-state','run-cron','upload-media','update-product','indexnow-submit','undo','call-ability'],$ro));
$GLOBALS['ability_objects']=[];
foreach($GLOBALS['abilities'] as $an=>$aa){ $GLOBALS['ability_objects'][$an]=new SGAP_Fake_Ability($an,['category'=>$aa['category']??'','input_schema'=>$aa['input_schema']??null]); }
$ct=SGAP_Chat::tools();
check('chat: the assistant reaches the site lookups', isset($ct['list-posts']) && isset($ct['site-info']) && isset($ct['get-product']));
check('chat: the assistant cannot write through a site tool', !isset($ct['create-post']) && !isset($ct['update-option']) && !isset($ct['set-plugin-state']) && !isset($ct['run-cron']));
check('chat: its own tools are still there', count($ct)>2 && !isset($ct['manage-plugin']));
// Chatten må ikke kunne skrive indstillinger: en gættet ai_model gjorde AIMLAPI-svaret til en fejl (7.38.3).
check('chat: the assistant can read settings but not change them', isset($ct['get-settings']) && !isset($ct['update-settings']));
check('chat: run-tool refuses to change settings', str_contains(SGAP_Chat::run_tool('run-tool',['name'=>'update-settings','input'=>['settings'=>['ai_model'=>'claude-3-5-sonnet']]],$ct),'may not use'));
check('chat: the prompt forbids guessing a model name', str_contains(SGAP_Chat::system_prompt(),'never guess at a model name'));
check('chat: every tool has a schema object', (function($t){ foreach($t as $v){ if(!is_array($v[1]) || ($v[1]['type']??'')!=='object') return false; } return true; })($ct));
// Nye værktøjer: et filter kan lægge dem til, og siden kan hente kataloget igen.
$GLOBALS['filters']['sgap_chat_tools']=fn($t)=>$t+['my-tool'=>['Does my thing',['type'=>'object','properties'=>[]],null],'bad-tool'=>'not a tool'];
$ct2=SGAP_Chat::tools();
check('chat: another plugin can add a tool through the filter', isset($ct2['my-tool']) && !isset($ct2['bad-tool']));
check('chat: the signature follows the catalogue', SGAP_Chat::tools_signature($ct2)!==SGAP_Chat::tools_signature($ct));
$cat=SGAP_Chat::tool_catalog();
check('chat: the catalogue reports count, names and signature', $cat['count']===count($ct2) && in_array('my-tool',$cat['names'],true) && strlen($cat['signature'])===32);
check('chat: a tool without a runner fails cleanly', str_contains(SGAP_Chat::run_tool('my-tool',[],$ct2),'no runner'));
unset($GLOBALS['filters']['sgap_chat_tools'], $GLOBALS['ability_objects']);
// Alt registreret på sitet skal kunne ses, ikke kun vores egne kataloger (7.37.0).
$GLOBALS['ability_objects']=[
 'core/analyse'=>new SGAP_Fake_Ability('core/analyse',['category'=>'core','meta'=>['annotations'=>['readonly'=>true]]]),
 'core/delete-all'=>new SGAP_Fake_Ability('core/delete-all',['category'=>'core']),
 'woo/product-report'=>new SGAP_Fake_Ability('woo/product-report',['category'=>'woo','meta'=>['annotations'=>['readonly'=>true]]]),
 'steamgears-autopilot/manage-plugin'=>new SGAP_Fake_Ability('steamgears-autopilot/manage-plugin',['category'=>'steamgears-autopilot']),
 '9 lives/x y'=>new SGAP_Fake_Ability('9 lives/x y',['category'=>'odd','meta'=>['annotations'=>['readonly'=>true]]]),
];
$ct3=SGAP_Chat::tools();
check('chat: read-only abilities from other plugins are bound', isset($ct3['analyse']) && isset($ct3['product-report']));
check('chat: writers from other plugins are not bound', !isset($ct3['delete-all']));
check('chat: the three meta tools are always bound', isset($ct3['find-tools'],$ct3['describe-tool'],$ct3['run-tool']));
$found=SGAP_Chat::find_tools(['query'=>'product']);
check('chat: find-tools reaches the whole site catalogue', ($found['total_available']??0)===5 && ($found['matched']??0)===1 && ($found['tools'][0]['name']??'')==='woo/product-report');
check('chat: find-tools hides the excluded tools', SGAP_Chat::find_tools(['query'=>'manage']??[])['matched']===0);
$desc=SGAP_Chat::describe_tool(['name'=>'product-report']);
check('chat: describe-tool answers by short name', ($desc['name']??'')==='woo/product-report' && array_key_exists('input_schema',$desc));
check('chat: run-tool runs an unbound ability', (SGAP_Chat::run_named(['name'=>'delete-all','input'=>['x'=>1]])['ran']??'')==='core/delete-all');
check('chat: run-tool refuses an excluded name', str_contains(SGAP_Chat::run_tool('run-tool',['name'=>'manage-plugin'],$ct3),'may not use'));
$cat3=SGAP_Chat::tool_catalog();
check('chat: the catalogue counts what the site registers', ($cat3['available']??0)===5 && ($cat3['categories']['core']??0)===2);
// AIMLAPI/Gemini afviser ugyldige funktionsnavne — hvert bundet navn skal være provider-sikkert.
check('chat: tool names are provider-safe', (function($t){ foreach(array_keys($t) as $n){ if(!preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]{0,63}$/',$n)) return false; } return true; })($ct3));
check('chat: a name with a space and a digit still becomes callable', isset($ct3['x_y']) && !isset($ct3['9 lives/x y']));
check('chat: safe_name repairs what providers reject', SGAP_Chat::safe_name('9lives')==='t_9lives' && SGAP_Chat::safe_name('core/list posts')==='core_list_posts' && SGAP_Chat::safe_name('/ /')==='tool' && strlen(SGAP_Chat::safe_name(str_repeat('a',200)))===64);
check('chat: unique_name never collides', SGAP_Chat::unique_name('list-posts',['list-posts'=>[]])==='list-posts__2');
// Diagnostik: providerens indeks skal kunne oversættes til det konkrete værktøj.
$aimlapi='* GenerateContentRequest.tools[0].function_declarations[30].name: Invalid function name. Must start with a letter or an underscore.';
$names=array_map(fn($i)=>'tool-'.$i, range(0,40));
check('chat: a rejected index resolves to the tool name', (SGAP_Chat::culprit($aimlapi,$names)['name']??'')==='tool-30');
check('chat: an error without an index has no culprit', SGAP_Chat::culprit('quota exceeded',$names)===null);
$call=SGAP_Chat::remember_call('AIMLAPI','m',0,$names,$aimlapi);
check('chat: a failed call is remembered with its culprit', ($call['culprit']['index']??-1)===30 && $call['count']===41 && ($call['error']??'')!=='');
check('chat: the log never holds a key', !str_contains((string) wp_json_encode($call['names']),'key'));
unset($GLOBALS['ability_objects']);

// Chat-loopet: modellen skal kunne kalde et værktøj, få resultatet tilbage og bruge det
// i næste runde. Begge provider-formater testes, fordi de har hver sin wire-format.
$chat_keep=[]; foreach(['sgap_ai_provider','sgap_ai_model','sgap_ai_api_key'] as $k){ $chat_keep[$k]=$GLOBALS['opts'][$k]??null; }
$abilities_keep=$GLOBALS['ability_objects']??null;
$GLOBALS['ability_objects']=['core/analyse'=>new SGAP_Fake_Ability('core/analyse',['category'=>'core','meta'=>['annotations'=>['readonly'=>true]]])];
$GLOBALS['opts']['sgap_ai_provider']='aimlapi'; $GLOBALS['opts']['sgap_ai_model']='anthropic/claude-opus-5.5'; $GLOBALS['opts']['sgap_ai_api_key']='KEY';
$GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[
 sgap_http(200,['choices'=>[['message'=>['content'=>'','tool_calls'=>[['id'=>'call_1','type'=>'function','function'=>['name'=>'analyse','arguments'=>'{"topic":"corsets"}']]]],'finish_reason'=>'tool_calls']]]),
 sgap_http(200,['choices'=>[['message'=>['content'=>'Endeligt svar fra værktøjet'],'finish_reason'=>'stop']]])
];
$loop=SGAP_Chat::reply([], 'Analysér corsets');
$first=json_decode((string)($GLOBALS['http_calls'][0][1]['body']??''),true); $second=json_decode((string)($GLOBALS['http_calls'][1][1]['body']??''),true);
$tool_msg=null; foreach(($second['messages']??[]) as $m){ if(($m['role']??'')==='tool'){ $tool_msg=$m; break; } }
$tool_result=json_decode((string)($tool_msg['content']??''),true);
check('chat loop: OpenAI-compatible runs the tool and answers', is_array($loop) && ($loop['text']??'')==='Endeligt svar fra værktøjet' && ($loop['tools']??[])===['analyse'] && count($GLOBALS['http_calls'])===2);
check('chat loop: OpenAI-compatible sends the tool schema', in_array('analyse',array_column(array_column($first['tools']??[],'function'),'name'),true));
check('chat loop: OpenAI-compatible returns the tool result under its call id', ($tool_msg['tool_call_id']??'')==='call_1' && ($tool_result['ran']??'')==='core/analyse' && ($tool_result['input']['topic']??'')==='corsets');

$GLOBALS['opts']['sgap_ai_provider']='anthropic'; $GLOBALS['opts']['sgap_ai_model']='claude-opus-5-5'; $GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[
 sgap_http(200,['content'=>[['type'=>'text','text'=>'Jeg slår det op.'],['type'=>'tool_use','id'=>'toolu_1','name'=>'analyse','input'=>['topic'=>'corsets']]],'stop_reason'=>'tool_use']),
 sgap_http(200,['content'=>[['type'=>'text','text'=>'Endeligt Anthropic-svar']],'stop_reason'=>'end_turn'])
];
$loop=SGAP_Chat::reply([], 'Analysér corsets');
$first_body=json_decode((string)($GLOBALS['http_calls'][0][1]['body']??''),true);
$second_body=json_decode((string)($GLOBALS['http_calls'][1][1]['body']??''),true);
$tool_result=null; foreach(($second_body['messages']??[]) as $m){ foreach((array)($m['content']??[]) as $b){ if(is_array($b) && ($b['type']??'')==='tool_result'){ $tool_result=$b; break 2; } } }
$tool_json=json_decode((string)($tool_result['content']??''),true);
check('chat loop: Anthropic runs the tool and answers', is_array($loop) && ($loop['text']??'')==='Endeligt Anthropic-svar' && ($loop['tools']??[])===['analyse'] && count($GLOBALS['http_calls'])===2);
check('chat loop: Anthropic uses native headers and sends the tool schema', ($GLOBALS['http_calls'][0][1]['headers']['x-api-key']??'')==='KEY' && ($GLOBALS['http_calls'][0][1]['headers']['anthropic-version']??'')===SGAP_AI::ANTHROPIC_VERSION && in_array('analyse',array_column($first_body['tools']??[],'name'),true));
check('chat loop: Anthropic returns the tool result under its tool_use id', ($tool_result['tool_use_id']??'')==='toolu_1' && ($tool_json['ran']??'')==='core/analyse' && ($tool_json['input']['topic']??'')==='corsets');

foreach($chat_keep as $k=>$v){ if($v===null){ unset($GLOBALS['opts'][$k]); } else { $GLOBALS['opts'][$k]=$v; } }
if($abilities_keep===null){ unset($GLOBALS['ability_objects']); } else { $GLOBALS['ability_objects']=$abilities_keep; }
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[];

// ---- P1.2: remind me instead of posting ----
if (!function_exists('is_email')) { function is_email($e){ return (bool) filter_var((string)$e, FILTER_VALIDATE_EMAIL); } }
$GLOBALS['opts']['sgap_alert_email']='owner@steamgears.eu';
check('reminder: the queue table has the flag', str_contains(implode("\n",$GLOBALS['ddl']??[]),'remind TINYINT'));
$wpdb->next=null; $wpdb->log=[];
$rem=SGAP_Queue::add(['channel'=>'pinterest','title'=>'Story test','destination_url'=>'https://steamgears.eu/p/1','format'=>'story','remind'=>true]);
check('reminder: add stores the flag and reports it', is_array($rem) && $rem['remind']===1 && str_contains(implode("\n",$wpdb->log),'"remind":1'));
$plain=SGAP_Queue::add(['channel'=>'pinterest','title'=>'Normal post','destination_url'=>'https://steamgears.eu/p/2']);
check('reminder: an unflagged post stores 0', is_array($plain) && $plain['remind']===0);
$wpdb->log=[]; $GLOBALS['rows']=[];
SGAP_Queue::claim(5);
check('reminder: claim selects the flag so the publisher can see it', str_contains(implode("\n",$wpdb->log),'remind, destination_url'));
check('reminder: the reason names the format for a story', str_contains(SGAP_Reminder::reason('pinterest','story'),'story') && str_contains(SGAP_Reminder::reason('pinterest','story'),'own app'));
check('reminder: the reason explains the flag for other formats', str_contains(SGAP_Reminder::reason('pinterest','pin'),'remind me'));
$row=['id'=>77,'channel'=>'pinterest','format'=>'story','title'=>'Goggles','body'=>'Clockwork goggles, 30% off.','image_url'=>'https://steamgears.eu/g.jpg','destination_url'=>'https://steamgears.eu/p/1','utm_url'=>'https://steamgears.eu/p/1?utm_source=pinterest','scheduled_at'=>'2026-11-04 18:00:00'];
check('reminder: subject names channel and format', SGAP_Reminder::subject($row)==='Steamgears: post this story on pinterest by hand');
$msg=SGAP_Reminder::message($row);
check('reminder: the mail carries text, image, link and queue id', str_contains($msg,'Clockwork goggles') && str_contains($msg,'https://steamgears.eu/g.jpg') && str_contains($msg,'utm_source=pinterest') && str_contains($msg,'#77') && str_contains($msg,'page=sgap-queue'));
check('reminder: the mail says it is not a failure', str_contains($msg,'not counted as a failure'));
$noimg=SGAP_Reminder::message(['id'=>78,'channel'=>'instagram','format'=>'carousel','title'=>'x','body'=>'y']);
check('reminder: a missing image points at the media library', str_contains($noimg,'media library') && str_contains($noimg,'carousel'));
$GLOBALS['mails']=[]; $GLOBALS['opts']['sgap_reminders_last']=[];
check('reminder: a mail is sent once', SGAP_Reminder::send($row)===true && count($GLOBALS['mails'])===1);
check('reminder: the same item is not mailed twice', SGAP_Reminder::send($row)===false && count($GLOBALS['mails'])===1);
check('reminder: the mail goes to the alert recipient', ($GLOBALS['mails'][0][0]??'')==='owner@steamgears.eu');
$GLOBALS['opts']['sgap_reminders_last']=[]; $GLOBALS['opts']['sgap_alert_email']=''; $GLOBALS['opts']['admin_email']='admin@steamgears.eu'; $GLOBALS['mails']=[];
check('reminder: an empty alert address falls back to the admin', SGAP_Reminder::send(['id'=>79,'channel'=>'pinterest'])===true && ($GLOBALS['mails'][0][0]??'')==='admin@steamgears.eu');
$GLOBALS['opts']['sgap_reminders_last']=[]; $GLOBALS['opts']['admin_email']=''; $GLOBALS['mails']=[];
check('reminder: no address at all means no mail', SGAP_Reminder::send(['id'=>80,'channel'=>'pinterest'])===false && $GLOBALS['mails']===[]);
$GLOBALS['opts']['sgap_alert_email']='owner@steamgears.eu';
$wpdb->row=(object)['channel'=>'pinterest','angle'=>'angle_a','attempts'=>1]; $wpdb->log=[]; $GLOBALS['mails']=[]; $GLOBALS['opts']['sgap_reminders_last']=[];
$done=SGAP_Reminder::deliver($row);
$log=implode("\n",$wpdb->log);
check('reminder: deliver marks the item reminded', $done['status']==='reminded' && str_contains($log,'"status":"reminded"'));
check('reminder: reminded is terminal, not a retry', !str_contains($log,'scheduled_at'));
check('reminder: the mail is the reminder, not a failure alert', count($GLOBALS['mails'])===1 && str_contains((string)($GLOBALS['mails'][0][1]??''),'by hand'));
check('reminder: the publish log has an outcome for it', isset(SGAP_Audit::OUTCOMES['reminded']));
$pub=file_get_contents(__DIR__.'/../steamgears-autopilot/includes/publer.php'); $feed=file_get_contents(__DIR__.'/../steamgears-autopilot/includes/rss.php');
check('reminder: both publishers route flagged items to the reminder', str_contains($pub,'SGAP_Reminder::deliver') && str_contains($feed,'SGAP_Reminder::deliver'));
$wpdb->next='wp_sgap_queue';
$GLOBALS['rows']=[(object)['id'=>77,'channel'=>'pinterest','title'=>'Story','status'=>'reminded','remind'=>1,'attempts'=>1,'scheduled_at'=>null,'published_at'=>null,'error'=>null]];
ob_start(); do_action('page_sgap-queue'); $qhtml=ob_get_clean();
check('reminder: the queue row offers the button and shows the badge', str_contains($qhtml,'value="remind"') && str_contains($qhtml,'>reminder</'));
$GLOBALS['rows']=[]; $wpdb->next=null;

// P3.2 — /feed og /reposts havde nul assertions, og merchant_feed kunne ikke testes: den
// echoede kroppen og kaldte exit. Nu returnerer den et svar, og begge ruter er dækket.
function esc_xml($s){return htmlspecialchars((string)$s, ENT_XML1|ENT_QUOTES, 'UTF-8');}
function wp_get_attachment_url($id){return $id ? 'https://steamgears.eu/img/'.$id.'.jpg' : '';}
function get_woocommerce_currency(){return 'USD';}
function delete_post_meta_by_key($k){$GLOBALS['deleted_meta_keys'][]=$k;}
class WP_HTTP_Response{ public function __construct(public $data, public $status=200, public $headers=[]){} function get_data(){return $this->data;} function get_status(){return $this->status;} function get_headers(){return $this->headers;} }
class Fake_Product{
 public function __construct(private array $p){}
 function get_id(){return $this->p['id'];} function get_sku(){return $this->p['sku']??'';}
 function get_name(){return $this->p['name'];} function get_price(){return $this->p['price']??'';}
 function get_image_id(){return $this->p['image_id']??0;}
 function get_short_description(){return $this->p['short']??'';} function get_description(){return $this->p['desc']??'';}
 function get_permalink(){return 'https://steamgears.eu/p/'.$this->p['id'].'/';}
 function is_in_stock(){return $this->p['stock']??true;} }

$GLOBALS['opts']['sgap_merchant_feed_secret']='';
$feed=SGAP_REST::merchant_feed(new WP_REST_Request(['key'=>'x']));
check('feed: no secret configured is 403', $feed instanceof WP_Error && $feed->data['status']===403);
$GLOBALS['opts']['sgap_merchant_feed_secret']='hemmelig';
$feed=SGAP_REST::merchant_feed(new WP_REST_Request(['key'=>'forkert']));
check('feed: a wrong key is 403', $feed instanceof WP_Error && $feed->data['status']===403);
$feed=SGAP_REST::merchant_feed(new WP_REST_Request(['key'=>'hemmelig']));
check('feed: no WooCommerce is 501, not a fatal', $feed instanceof WP_Error && $feed->data['status']===501);
if (!function_exists('wc_get_products')) { function wc_get_products($a=[]){ return $GLOBALS['wc_products'] ?? []; } }
$GLOBALS['wc_products']=[
 new Fake_Product(['id'=>1,'sku'=>'SG-TEE-1','name'=>'Corset & Goggles','price'=>'21.90','image_id'=>9,'short'=>'Short <b>copy</b>']),
 new Fake_Product(['id'=>2,'name'=>'Uden pris','price'=>'','image_id'=>9]),
 new Fake_Product(['id'=>3,'name'=>'Uden billede','price'=>'10.00','image_id'=>0]),
 new Fake_Product(['id'=>4,'name'=>'Uden SKU','price'=>'10.00','image_id'=>9,'stock'=>false]),
];
$feed=SGAP_REST::merchant_feed(new WP_REST_Request(['key'=>'hemmelig']));
$x=$feed instanceof WP_HTTP_Response ? (string)$feed->data : '';
check('feed: XML with the Google namespace', str_starts_with($x,'<?xml') && str_contains($x,'xmlns:g="http://base.google.com/ns/1.0"'));
check('feed: the SKU is g:id', str_contains($x,'<g:id>SG-TEE-1</g:id>'));
check('feed: a SKU-less product falls back to its id', str_contains($x,'<g:id>4</g:id>'));
check('feed: the price carries the store currency', str_contains($x,'<g:price>21.90 USD</g:price>'));
check('feed: the title is XML-escaped', str_contains($x,'Corset &amp; Goggles') && !str_contains($x,'Corset & Goggles'));
check('feed: no HTML survives in the description', str_contains($x,'Short copy') && !str_contains($x,'<b>'));
check('feed: a product without price or image is skipped', substr_count($x,'<item>')===2);
check('feed: availability follows WooCommerce', str_contains($x,'in_stock') && str_contains($x,'out_of_stock'));
check('feed: declares the XML content type', ($feed->get_headers()['Content-Type']??'')==='application/xml; charset=utf-8');

$wpdb->log=[]; $GLOBALS['rows']=[(object)['id'=>5,'original_queue_id'=>1,'repost_number'=>1]];
$list=SGAP_REST::list_reposts(new WP_REST_Request([]));
check('reposts: the list returns the stored rows', is_array($list) && (int)$list[0]->id===5);
$bad=SGAP_REST::create_repost(new WP_REST_Request(['original_queue_id'=>0,'repost_queue_id'=>2]));
check('reposts: missing ids is 400', $bad instanceof WP_Error && $bad->data['status']===400);
$wpdb->next='2'; // repost_max er 3
$ok=SGAP_REST::create_repost(new WP_REST_Request(['original_queue_id'=>1,'repost_queue_id'=>2]));
check('reposts: the next repost is numbered 3 and returns 201', $ok instanceof WP_REST_Response && $ok->status===201 && $ok->data['repost_number']===3);
check('reposts: the row is inserted with its number', str_contains(implode("\n",$wpdb->log),'"repost_number":3'));
$wpdb->next='3';
$cap=SGAP_REST::create_repost(new WP_REST_Request(['original_queue_id'=>1,'repost_queue_id'=>2]));
check('reposts: hitting the cap is 422, not a silent insert', $cap instanceof WP_Error && $cap->data['status']===422);
$wpdb->next=null; $GLOBALS['rows']=[];

// P3.5 — uninstall skal fjerne præcis det install oprettede. Listen tjekkes mod DDL'en,
// ikke mod hukommelsen, og filen køres rigtigt bagefter.
$created=[];
foreach ((array)($GLOBALS['ddl']??[]) as $ddl) {
    if (preg_match('/(?:CREATE TABLE (?:IF NOT EXISTS )?)?`?(?:wp_)?(sgap_[a-z_]+)`?/i', (string)$ddl, $mm)) { $created[$mm[1]]=$mm[1]; }
}
$created=array_values($created);
$uninstall=(string)file_get_contents(__DIR__.'/../steamgears-autopilot/uninstall.php');
preg_match_all("/'(sgap_[a-z_]+)'/", $uninstall, $m2);
$declared=array_values(array_unique($m2[1]));
check('uninstall: install creates 10 tables ('.count($created).')', count($created)===10);
check('uninstall: declares every table install creates', array_diff($created,$declared)===[]);
$wpdb->log=[]; $GLOBALS['deleted_meta_keys']=[]; $GLOBALS['cleared']=[];
if (!defined('WP_UNINSTALL_PLUGIN')) define('WP_UNINSTALL_PLUGIN', true);
include __DIR__.'/../steamgears-autopilot/uninstall.php';
$ulog=implode("\n",$wpdb->log);
$dropped=count(array_filter($created, fn($t)=>str_contains($ulog,"DROP TABLE IF EXISTS wp_$t")));
check('uninstall: drops every table install creates ('.$dropped.'/'.count($created).')', $dropped===count($created) && $dropped>0);
check('uninstall: deletes options and transients', str_contains($ulog,'DELETE FROM wp_options') && str_contains($ulog,"sgap\\_%"));
check('uninstall: removes every _sgap_ postmeta key', str_contains($ulog,'DELETE FROM wp_postmeta') && str_contains($ulog,"_sgap_%"));
check('uninstall: clears the module cron hooks', in_array('sgap_evergreen',$GLOBALS['cleared']??[],true) && in_array('sgap_coach_learn',$GLOBALS['cleared']??[],true) && in_array('sgap_rescue',$GLOBALS['cleared']??[],true) && in_array('sgap_cirla_daily',$GLOBALS['cleared']??[],true));

// P2.1 — Bluesky som direkte kanal. HTTP går gennem WordPress' eget API, så det kan stubbes:
// hvert kald lægger sig i http_calls, og svarene kommer fra http_queue i den rækkefølge
// plugin'et spørger (login, derefter opslag). Intet netværk, ingen rigtige nøgler.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[];
function wp_remote_post($url,$args=[]){ $GLOBALS['http_calls'][]=[$url,$args]; $q=$GLOBALS['http_queue']??[]; return $q?array_shift($GLOBALS['http_queue']):new WP_Error('offline','offline'); }
function wp_remote_request($url,$args=[]){ return wp_remote_post($url,$args); }
function wp_remote_retrieve_response_code($r){ return is_array($r)?(int)($r['response']['code']??0):0; }
function wp_remote_retrieve_body($r){ return is_array($r)?(string)($r['body']??''):''; }
function wp_remote_retrieve_header($r,$h){ $h=strtolower((string)$h); return is_array($r)&&isset($r['headers'])?(string)($r['headers'][$h]??''):''; }
function sgap_http($code,$body){ return ['response'=>['code'=>$code],'body'=>is_array($body)?json_encode($body):(string)$body]; }

// 7.38.4 — et gemt modelnavn AIMLAPI ikke kender må ikke lamme chatten: kaldet prøver én gang
// på standardmodellen, retter indstillingen og fortæller ejeren det. Auth, netværk og rate limit
// må ikke udløse fallback.
$ai_keep=[]; foreach(['sgap_ai_provider','sgap_ai_model','sgap_ai_api_key'] as $k){ $ai_keep[$k]=$GLOBALS['opts'][$k]??null; }
$GLOBALS['opts']['sgap_ai_provider']='aimlapi'; $GLOBALS['opts']['sgap_ai_api_key']='KEY'; $GLOBALS['opts']['sgap_ai_model']='claude-3-5-sonnet';
check('ai: only a model-name error counts as a model error', SGAP_AI::model_rejected('Model claude-3-5-sonnet not found') && SGAP_AI::model_rejected('The model `x` does not exist or you do not have access to it') && !SGAP_AI::model_rejected('Incorrect API key provided') && !SGAP_AI::model_rejected('Rate limit exceeded for model claude-3-5-sonnet') && !SGAP_AI::model_rejected('offline'));
$GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[
 sgap_http(404,['error'=>['message'=>'Model claude-3-5-sonnet not found']]),
 sgap_http(200,['model'=>'anthropic/claude-opus-5.5','choices'=>[['message'=>['content'=>'Hej fra standardmodellen'],'finish_reason'=>'stop']]])
];
$answer=SGAP_Chat::reply([], 'Hej');
check('chat: a stored model AIMLAPI rejects still answers', is_array($answer) && str_contains((string)($answer['text']??''),'Hej fra standardmodellen'));
check('chat: the retry runs on the default model, exactly once', count($GLOBALS['http_calls'])===2 && (json_decode((string)($GLOBALS['http_calls'][1][1]['body']??''),true)['model']??'')==='anthropic/claude-opus-5.5');
check('chat: the rejected model is repaired to the model that answers', get_option('sgap_ai_model','')==='anthropic/claude-opus-5.5' && SGAP_AI::model()==='anthropic/claude-opus-5.5');
check('chat: the owner is told which model was replaced', str_contains((string)($answer['text']??''),'claude-3-5-sonnet'));
check('chat: the answer also names the model it now uses', str_contains((string)($answer['text']??''),'anthropic/claude-opus-5.5'));
$GLOBALS['opts']['sgap_ai_model']='claude-3-5-sonnet'; $GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[sgap_http(401,['error'=>['message'=>'Incorrect API key provided']])];
$answer=SGAP_Chat::reply([], 'Hej');
check('chat: an auth error fails without a fallback call', $answer instanceof WP_Error && count($GLOBALS['http_calls'])===1 && get_option('sgap_ai_model')==='claude-3-5-sonnet');
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[];
$answer=SGAP_Chat::reply([], 'Hej');
check('chat: a network error fails without a fallback call', $answer instanceof WP_Error && count($GLOBALS['http_calls'])===1 && get_option('sgap_ai_model')==='claude-3-5-sonnet');
$GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[sgap_http(429,['error'=>['message'=>'Rate limit exceeded for model claude-3-5-sonnet']])];
$answer=SGAP_Chat::reply([], 'Hej');
check('chat: a rate limit error is reported, not retried on another model', $answer instanceof WP_Error && count($GLOBALS['http_calls'])===1 && get_option('sgap_ai_model')==='claude-3-5-sonnet');
foreach($ai_keep as $k=>$v){ if($v===null){ unset($GLOBALS['opts'][$k]); } else { $GLOBALS['opts'][$k]=$v; } }

// 7.39.0 — model-dropdown: kun det provideren faktisk udbyder kan vælges, og et gemt navn
// der er udgået vises som udgået i stedet for at forsvinde.
if (!function_exists('add_settings_error')) { function add_settings_error($setting,$code,$message,$type='error'){ $GLOBALS['settings_errors'][]=[$setting,$code,$message,$type]; } }
$GLOBALS['settings_errors']=[];
$GLOBALS['filters']['sgap_ai_model_catalog']=fn($list,$provider)=>['openai/gpt-x','anthropic/claude-opus-5.5','openai/gpt-x','anthropic/claude-sonnet-5.5'];
$GLOBALS['opts']['sgap_ai_provider']='aimlapi'; $GLOBALS['opts']['sgap_ai_model']='';
check('models: the catalogue dedupes, sorts and groups by vendor', SGAP_AI::catalog()===['anthropic/claude-opus-5.5','anthropic/claude-sonnet-5.5','openai/gpt-x'] && array_keys(SGAP_Setup::group_models(SGAP_AI::catalog()))===['anthropic','openai']);
$mf=SGAP_Setup::field('ai_model');
check('models: the field is a grouped dropdown with the default first', str_contains($mf,'<select id="sgap_ai_model"') && str_contains($mf,'<optgroup label="anthropic">') && substr_count($mf,'<option')===4);
update_option('sgap_ai_model','anthropic/claude-3-5-sonnet');
check('models: a stored model that is gone is shown as retired, not hidden', str_contains(SGAP_Setup::field('ai_model'),'(not offered any more)'));
check('models: a listed model may be saved', SGAP_Settings::sanitize_model('anthropic/claude-sonnet-5.5')==='anthropic/claude-sonnet-5.5');
check('models: an unlisted model is refused and the stored one kept', SGAP_Settings::sanitize_model('claude-3-5-sonnet')==='anthropic/claude-3-5-sonnet' && ($GLOBALS['settings_errors'][0][1]??'')==='sgap_model_unknown');
// Punktet der blev fanget her: "Provider default" må ikke gemmes som et tomt felt.
$model_san=$GLOBALS['settings']['sgap_ai_model']['sanitize_callback'] ?? null;
check('models: the save path is the registered sanitizer', is_callable($model_san));
$picked_default=$model_san('');
check('models: Provider default is stored as the model it resolves to', $picked_default==='anthropic/claude-opus-5.5');
update_option('sgap_ai_model',$picked_default);
check('models: after saving, the field holds the model and is not empty', get_option('sgap_ai_model')==='anthropic/claude-opus-5.5' && SGAP_AI::model()==='anthropic/claude-opus-5.5');
check('models: the stored default is the option the dropdown marks as selected', str_contains(SGAP_Setup::field('ai_model'),'<option value="anthropic/claude-opus-5.5" selected'));
check('models: the retired name cannot be saved back after that', $model_san('claude-3-5-sonnet')==='anthropic/claude-opus-5.5');
update_option('sgap_ai_model','');
ob_start(); do_action('page_sgap-settings'); $settings_html=ob_get_clean();
check('models: the settings page renders the dropdown', str_contains($settings_html,'id="sgap_ai_model"') && str_contains($settings_html,'<optgroup label="anthropic">'));
unset($GLOBALS['filters']['sgap_ai_model_catalog']);
$GLOBALS['opts']['sgap_ai_model']='';
check('models: without a catalogue the field is free text again', str_contains(SGAP_Setup::field('ai_model'),'type="text"') && !str_contains(SGAP_Setup::field('ai_model'),'<select'));
check('models: without a catalogue the typed name is kept', SGAP_Settings::sanitize_model('some/custom')==='some/custom');

// Punkt 4 mod den rigtige verden: kør med SGAP_LIVE_CATALOG=1, så hentes AIMLAPI's eget
// katalog og hver eneste <option> sammenlignes med de id'er provideren faktisk udbyder.
if (($live_src = (string) getenv('SGAP_LIVE_CATALOG')) !== '') {
    if ($live_src !== '1' && is_file($live_src)) {
        // En PHP uden allow_url_fopen kan få kataloget serveret som en fil hentet med curl.
        $raw = (string) file_get_contents($live_src);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 25, 'header' => "User-Agent: steamgears-smoke\r\n"]]);
        $raw = @file_get_contents('https://api.aimlapi.com/v1/models', false, $ctx);
    }
    $live_body = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($live_body)) {
        $live_ids = SGAP_AI::catalog_ids($live_body);
        $GLOBALS['filters']['sgap_ai_model_catalog'] = fn() => $live_ids;
        $GLOBALS['opts']['sgap_ai_provider'] = 'aimlapi';
        $GLOBALS['opts']['sgap_ai_model'] = 'anthropic/claude-opus-5.5';
        $live_html = SGAP_Setup::field('ai_model');
        preg_match_all('/<option value="([^"]*)"/', $live_html, $om);
        $picked = array_values(array_diff($om[1], ['']));
        $expect = $live_ids;
        sort($picked); sort($expect);
        check('live: AIMLAPI lists a usable catalogue', count($live_ids) > 100);
        check('live: every dropdown option is a model AIMLAPI offers (and no other)', $picked === $expect && count($om[1]) === count($live_ids) + 1);
        check('live: Provider default is the first option and names the default model', str_starts_with($live_html, '<select') && str_contains(substr($live_html, 0, strpos($live_html, '</option>') + 9), 'Provider default: anthropic/claude-opus-5.5'));
        check('live: the default model is one AIMLAPI offers', in_array('anthropic/claude-opus-5.5', $live_ids, true));
        echo 'LIVE: ' . count($live_ids) . " modeller fra AIMLAPI, alle valgbare i dropdownen\n";
        $GLOBALS['opts']['sgap_ai_model'] = '';
        unset($GLOBALS['filters']['sgap_ai_model_catalog']);
    } else {
        echo "SKIP live: AIMLAPI /models kunne ikke læses\n";
    }
}

$GLOBALS['opts']['sgap_bluesky_handle']='@steamgears.bsky.social';
$GLOBALS['opts']['sgap_bluesky_app_password']='abcd-efgh-ijkl-mnop';
$GLOBALS['opts']['sgap_channels']='facebook,bluesky';
check('bluesky: enabled when configured and listed as a channel', SGAP_Bluesky::enabled() && SGAP_Bluesky::handles('bluesky'));
check('bluesky: another channel is never routed to it', !SGAP_Bluesky::handles('instagram'));
$GLOBALS['opts']['sgap_channels']='facebook,instagram';
check('bluesky: removing it from the channel list turns it off', !SGAP_Bluesky::enabled() && !SGAP_Bluesky::handles('bluesky'));
$GLOBALS['opts']['sgap_channels']='facebook,bluesky';
check('bluesky: @ is optional in the handle', SGAP_Bluesky::handle()==='steamgears.bsky.social');

// 300 graphemes, ikke bytes: en emoji og et sammensat bogstav må ikke deles.
check('bluesky: length counts graphemes', SGAP_Bluesky::length("👩🏽‍🚀")===1 && SGAP_Bluesky::length("e\u{0301}")===1);
$long=str_repeat('Brass goggles and leather. ',20);
$cut=SGAP_Bluesky::shorten($long,40);
check('bluesky: a cut fits, ends in an ellipsis and stays valid UTF-8', SGAP_Bluesky::length($cut)<=40 && str_ends_with($cut,'…') && mb_check_encoding($cut,'UTF-8'));
check('bluesky: a cut never splits an emoji cluster', SGAP_Bluesky::shorten("👩🏽‍🚀👩🏽‍🚀👩🏽‍🚀",2)!=="👩🏽‍🚀👩");

$row=['id'=>5,'channel'=>'bluesky','title'=>'Brass goggles','body'=>'Look sharp.','tracking_url'=>'https://steamgears.eu/go/5/'];
$text=SGAP_Bluesky::compose($row,'https://steamgears.eu/go/5/');
check('bluesky: the link is kept whole and last', str_ends_with($text,"\n\nhttps://steamgears.eu/go/5/") && SGAP_Bluesky::length($text)<=300);
$long_text=SGAP_Bluesky::compose(['body'=>$long],'https://steamgears.eu/go/5/');
check('bluesky: a long body is trimmed inside 300 graphemes', SGAP_Bluesky::length($long_text)<=300 && str_contains($long_text,'…') && str_ends_with($long_text,'https://steamgears.eu/go/5/'));
$facets=SGAP_Bluesky::facets("Look sharp #steampunk\n\nhttps://steamgears.eu/go/5/",'https://steamgears.eu/go/5/');
check('bluesky: the link becomes a clickable link facet', count($facets)===2 && $facets[0]['features'][0]['uri']==='https://steamgears.eu/go/5/' && $facets[0]['features'][0]['$type']==='app.bsky.richtext.facet#link');
check('bluesky: the hashtag becomes a tag facet at the right byte offset', $facets[1]['features'][0]['tag']==='steampunk' && $facets[1]['index']['byteStart']===11);

$GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[sgap_http(200,['accessJwt'=>'jwt-1','did'=>'did:plc:abc','handle'=>'steamgears.bsky.social']), sgap_http(200,['uri'=>'at://did:plc:abc/app.bsky.feed.post/xyz','cid'=>'cid1'])];
$job=SGAP_Bluesky::publish($row);
check('bluesky: a published post returns its AT URI', $job==='at://did:plc:abc/app.bsky.feed.post/xyz');
check('bluesky: exactly two calls — login, then record', count($GLOBALS['http_calls'])===2
    && str_ends_with($GLOBALS['http_calls'][0][0],'/xrpc/com.atproto.server.createSession')
    && str_ends_with($GLOBALS['http_calls'][1][0],'/xrpc/com.atproto.repo.createRecord'));
$login=json_decode($GLOBALS['http_calls'][0][1]['body'],true);
check('bluesky: the login sends handle and app password as JSON', $login['identifier']==='steamgears.bsky.social' && $login['password']==='abcd-efgh-ijkl-mnop');
check('bluesky: the login carries no bearer token', !isset($GLOBALS['http_calls'][0][1]['headers']['Authorization']));
$rec=json_decode($GLOBALS['http_calls'][1][1]['body'],true);
check('bluesky: the record names repo, collection and type', $rec['repo']==='did:plc:abc' && $rec['collection']==='app.bsky.feed.post' && $rec['record']['$type']==='app.bsky.feed.post');
check('bluesky: the record call is authorised with the session token', ($GLOBALS['http_calls'][1][1]['headers']['Authorization']??'')==='Bearer jwt-1');
check('bluesky: the post text and createdAt travel in the record', str_contains($rec['record']['text'],'Look sharp.') && !empty($rec['record']['createdAt']) && isset($rec['record']['facets']));

$GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[sgap_http(200,['accessJwt'=>'jwt-1','did'=>'did:plc:abc']), sgap_http(200,['uri'=>'at://did:plc:abc/app.bsky.feed.post/xyz'])];
SGAP_Bluesky::publish(['id'=>5,'channel'=>'bluesky','title'=>'Titel','body'=>'']);
$rec=json_decode($GLOBALS['http_calls'][1][1]['body'],true);
check('bluesky: the tracking link is generated when the row has none', str_contains($rec['record']['text'],'https://steamgears.eu/go/5/'));
check('bluesky: an empty body falls back to the title', str_contains($rec['record']['text'],'Titel'));

// Fejlstierne: alle skal give en WP_Error med en brugbar besked — aldrig en tavs no-op.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[];
$e=SGAP_Bluesky::publish(['id'=>5,'channel'=>'bluesky','title'=>'','body'=>'']);
check('bluesky: an empty post is refused before any call', $e instanceof WP_Error && $e->code==='sgap_bluesky_text' && $GLOBALS['http_calls']===[]);

$GLOBALS['opts']['sgap_bluesky_app_password']='';
$GLOBALS['http_calls']=[];
$e=SGAP_Bluesky::publish($row);
check('bluesky: missing credentials is a setup error, not a silent no-op', $e instanceof WP_Error && $e->code==='sgap_bluesky_setup' && $GLOBALS['http_calls']===[]);
$GLOBALS['opts']['sgap_bluesky_app_password']='abcd-efgh-ijkl-mnop';

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(401,['error'=>'invalid_grant','message'=>'Invalid identifier or password'])];
$e=SGAP_Bluesky::publish($row);
check('bluesky: a refused login names the reason and stops after one call', $e instanceof WP_Error && str_contains($e->get_error_message(),'invalid_grant') && count($GLOBALS['http_calls'])===1);

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['accessJwt'=>'jwt-2','did'=>'did:plc:abc']), sgap_http(400,['error'=>'InvalidRequest','message'=>'Record text is too long'])];
$e=SGAP_Bluesky::publish($row);
check('bluesky: a refused record is reported with the API message', $e instanceof WP_Error && str_contains($e->get_error_message(),'Record text is too long') && (int)($e->data['status']??0)===400);

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[new WP_Error('http_request_failed','cURL error 28: timed out')];
$e=SGAP_Bluesky::publish($row);
check('bluesky: a network failure is an error, not a PHP warning', $e instanceof WP_Error && str_contains($e->get_error_message(),'could not be reached'));

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['accessJwt'=>'stale','did'=>'did:plc:abc']), sgap_http(401,['error'=>'ExpiredToken','message'=>'Token has expired']), sgap_http(200,['accessJwt'=>'fresh','did'=>'did:plc:abc']), sgap_http(200,['uri'=>'at://did:plc:abc/app.bsky.feed.post/new'])];
$job=SGAP_Bluesky::publish($row);
check('bluesky: an expired token is renewed once and the post retried', $job==='at://did:plc:abc/app.bsky.feed.post/new' && count($GLOBALS['http_calls'])===4);

$pubsrc=file_get_contents(__DIR__.'/../steamgears-autopilot/includes/publer.php');
check('bluesky: the publish loop routes it through the same loop', str_contains($pubsrc,'SGAP_Bluesky::handles(') && str_contains($pubsrc,'SGAP_Bluesky::publish($row)'));
check('bluesky: a direct post is marked published instead of polled as a Publer job', str_contains($pubsrc, "SGAP_Queue::mark((int) \$row['id'], 'published')") && str_contains($pubsrc,'Bluesky post '));

// P2.2/P2.3 — Mastodon og Telegram som direkte kanaler gennem samme plumbing som Bluesky.
// HTTP stubberne fra Bluesky-afsnittet bruges igen: intet netværk, ingen rigtige nøgler.
$GLOBALS['opts']['sgap_channels']='facebook,bluesky,mastodon,telegram';
$GLOBALS['opts']['sgap_mastodon_instance']='mastodon.example';
$GLOBALS['opts']['sgap_mastodon_token']='masto-token-1';
$row2=['id'=>6,'channel'=>'mastodon','title'=>'Brass goggles','body'=>'Look sharp.','tracking_url'=>'https://steamgears.eu/go/6/'];
check('mastodon: the instance gets https:// and no trailing slash', SGAP_Mastodon::instance()==='https://mastodon.example');
check('mastodon: enabled and routed only for its own channel', SGAP_Mastodon::enabled() && SGAP_Mastodon::handles('mastodon') && !SGAP_Mastodon::handles('telegram'));
check('mastodon: 500 is the default limit', SGAP_Mastodon::limit()===500);

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['id'=>'99','url'=>'https://mastodon.example/@steamgears/99'])];
$job=SGAP_Mastodon::publish($row2);
check('mastodon: one call to /api/v1/statuses', count($GLOBALS['http_calls'])===1 && $GLOBALS['http_calls'][0][0]==='https://mastodon.example/api/v1/statuses');
check('mastodon: the post url becomes the external id', $job==='https://mastodon.example/@steamgears/99');
check('mastodon: the token is a bearer header, never a query parameter', ($GLOBALS['http_calls'][0][1]['headers']['Authorization']??'')==='Bearer masto-token-1' && !str_contains($GLOBALS['http_calls'][0][0],'masto-token-1'));
$mbody=json_decode($GLOBALS['http_calls'][0][1]['body'],true);
check('mastodon: the status carries the text and the tracking link', str_contains($mbody['status'],'Look sharp.') && str_contains($mbody['status'],'https://steamgears.eu/go/6/'));

$long2=str_repeat('Brass goggles and leather. ',60);
$mtext=SGAP_Mastodon::compose(['body'=>$long2],'https://steamgears.eu/go/6/');
check('mastodon: a long post is trimmed inside 500 graphemes', SGAP_Text::length($mtext)<=500 && str_ends_with($mtext,'https://steamgears.eu/go/6/') && str_contains($mtext,'…'));
$GLOBALS['opts']['sgap_mastodon_limit']='800';
check('mastodon: the instance limit is a setting', SGAP_Mastodon::limit()===800);
$GLOBALS['opts']['sgap_mastodon_limit']='500';

$GLOBALS['opts']['sgap_mastodon_token']='';
$GLOBALS['http_calls']=[];
$e=SGAP_Mastodon::publish($row2);
check('mastodon: missing credentials is a setup error and sends nothing', $e instanceof WP_Error && $e->code==='sgap_mastodon_setup' && $GLOBALS['http_calls']===[]);
$GLOBALS['opts']['sgap_mastodon_token']='masto-token-1';

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(401,['error'=>'The access token is invalid'])];
$e=SGAP_Mastodon::publish($row2);
check('mastodon: a refused token is reported with the instance message', $e instanceof WP_Error && str_contains($e->get_error_message(),'The access token is invalid') && (int)($e->data['status']??0)===401);

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(422,['error'=>'Validation failed: Text character limit of 500 exceeded'])];
$e=SGAP_Mastodon::publish($row2);
check('mastodon: an API rejection keeps its own wording', $e instanceof WP_Error && str_contains($e->get_error_message(),'character limit of 500 exceeded'));

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[new WP_Error('http_request_failed','cURL error 28: timed out')];
$e=SGAP_Mastodon::publish($row2);
check('mastodon: a network failure is an error, not a warning', $e instanceof WP_Error && str_contains($e->get_error_message(),'could not be reached'));

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(201,['id'=>'100'])];
$job=SGAP_Mastodon::publish($row2);
check('mastodon: a response without a url still yields an id', $job==='mastodon:100');

// Telegram: token kommer fra SGAP_TELEGRAM_BOT_TOKEN-konstanten længere oppe, chat-id fra indstillingerne.
$GLOBALS['opts']['sgap_telegram_chat_id']='@steamgears';
$row3=['id'=>7,'channel'=>'telegram','title'=>'Brass goggles','body'=>'Look sharp.','tracking_url'=>'https://steamgears.eu/go/7/'];
check('telegram: enabled and routed only for its own channel', SGAP_Telegram::enabled() && SGAP_Telegram::handles('telegram') && !SGAP_Telegram::handles('mastodon'));
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['ok'=>true,'result'=>['message_id'=>321]])];
$job=SGAP_Telegram::publish($row3);
check('telegram: one call to bot<token>/sendMessage', count($GLOBALS['http_calls'])===1 && str_starts_with($GLOBALS['http_calls'][0][0],'https://api.telegram.org/bot') && str_ends_with($GLOBALS['http_calls'][0][0],'/sendMessage'));
check('telegram: the message id becomes the external id', $job==='telegram:321');
$tbody=json_decode($GLOBALS['http_calls'][0][1]['body'],true);
check('telegram: chat id, text and link travel in the body', $tbody['chat_id']==='@steamgears' && str_contains($tbody['text'],'Look sharp.') && str_contains($tbody['text'],'https://steamgears.eu/go/7/'));

$long3=str_repeat('Brass goggles and leather. ',400);
$ttext=SGAP_Telegram::compose(['body'=>$long3],'https://steamgears.eu/go/7/');
check('telegram: a long message is trimmed inside 4096 graphemes', SGAP_Text::length($ttext)<=4096 && str_ends_with($ttext,'https://steamgears.eu/go/7/') && str_contains($ttext,'…'));
check('telegram: preflight allows the real 4096, not 1024', SGAP_Preflight::caption_budget('telegram','https://steamgears.eu/go/7/')>3000);

$GLOBALS['opts']['sgap_telegram_chat_id']='';
$GLOBALS['http_calls']=[];
$e=SGAP_Telegram::publish($row3);
check('telegram: missing chat id is a setup error and sends nothing', $e instanceof WP_Error && $e->code==='sgap_telegram_setup' && $GLOBALS['http_calls']===[]);
$GLOBALS['opts']['sgap_telegram_chat_id']='@steamgears';

// HTTP 200 med ok:false er en fejl — ellers ville en tavs afvisning se ud som en post.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['ok'=>false,'error_code'=>400,'description'=>'Bad Request: chat not found'])];
$e=SGAP_Telegram::publish($row3);
check('telegram: ok=false at HTTP 200 is a failure, not a post', $e instanceof WP_Error && str_contains($e->get_error_message(),'chat not found'));

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(401,['ok'=>false,'error_code'=>401,'description'=>'Unauthorized'])];
$e=SGAP_Telegram::publish($row3);
check('telegram: a refused token is an error and the token is not echoed', $e instanceof WP_Error && str_contains($e->get_error_message(),'Unauthorized') && !str_contains($e->get_error_message(),'fromconfig'));

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[new WP_Error('http_request_failed','cURL error 6: Could not resolve host')];
$e=SGAP_Telegram::publish($row3);
check('telegram: a network failure is an error, not a warning', $e instanceof WP_Error && str_contains($e->get_error_message(),'could not be reached'));

$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,'not json')];
$e=SGAP_Telegram::publish($row3);
check('telegram: a non-JSON answer is an error', $e instanceof WP_Error && str_contains($e->get_error_message(),'not JSON'));

$pubsrc2=file_get_contents(__DIR__.'/../steamgears-autopilot/includes/publer.php');
check('mastodon: routed through the same publish loop', str_contains($pubsrc2,'SGAP_Mastodon::handles(') && str_contains($pubsrc2,'SGAP_Mastodon::publish($row)'));
check('telegram: routed through the same publish loop', str_contains($pubsrc2,'SGAP_Telegram::handles(') && str_contains($pubsrc2,'SGAP_Telegram::publish($row)'));
check('direct channels: all three are marked published at once, not polled', str_contains($pubsrc2, "'bluesky' => 'Bluesky post '") && str_contains($pubsrc2, "'mastodon' => 'Mastodon post '") && str_contains($pubsrc2, "'telegram' => 'Telegram message '"));

// ---------------- P2.5: multi-sprog — én kilde-post, én caption pr. locale ----------------
$GLOBALS['opts']['sgap_locale_source']='en'; $GLOBALS['opts']['sgap_locales']='da, SV ,da,xx1';
check('locales: the list is normalised, de-duplicated and validated', SGAP_Locales::locales()===['da','sv']);
check('locales: enabled when a language differs from the source', SGAP_Locales::enabled());
$GLOBALS['opts']['sgap_locales']='en';
check('locales: the source language never gets a variant', SGAP_Locales::locales()===[] && !SGAP_Locales::enabled());
check('locales: saving the list normalises it', SGAP_Settings::sanitize_locales('DA, sv ,da')==='da,sv');
check('locales: a nonsense source falls back to en', SGAP_Settings::sanitize_locale_code('nope')==='en');
$GLOBALS['opts']['sgap_locale_source']=''; $GLOBALS['opts']['sgap_locales']='da';
check('locales: an empty source counts as en', SGAP_Locales::source()==='en' && SGAP_Locales::enabled());
$GLOBALS['opts']['sgap_locale_source']='en'; $GLOBALS['opts']['sgap_locales']='da,sv,de,fr,es,it,pl,nl';
check('locales: at most six languages per post', count(SGAP_Locales::locales())===6);
check('locales: the settings page shows both fields with help', isset(SGAP_Settings::HELP['locales']) && in_array('locales', SGAP_Settings::SECTIONS['AI content'], true) && in_array('locale_source', SGAP_Settings::TEXT_KEYS, true));
$GLOBALS['opts']['sgap_locales']='da,sv';

// Oversat side: uden en oversættelse laves ingen variant — og fejlen siges højt.
$row_l = ['post_id'=>42,'channel'=>'pinterest','angle'=>'angle_a','content_type'=>'product','format'=>'pin','title'=>'Brass goggles','body'=>'Look sharp.','image_url'=>'https://x/y.jpg'];
$GLOBALS['pll_map']=[]; $GLOBALS['translated']=[];
$exp = SGAP_Locales::expand($row_l, 100, function($r,$l){ $GLOBALS['translated'][]=$l; return ['title'=>'T','body'=>'B']; });
check('locales: a language without a translated page is an error, not a variant', $exp['items']===[] && count($exp['errors'])===2);
check('locales: the error names the language and the reason', ($exp['errors'][0]['locale']??'')==='da' && str_contains($exp['errors'][0]['error'],'no translated page'));
check('locales: nothing is translated when the page has no translation', $GLOBALS['translated']===[]);

$GLOBALS['pll_map']=['da'=>421,'sv'=>422]; $GLOBALS['translated']=[];
$exp = SGAP_Locales::expand($row_l, 100, function($r,$l){ $GLOBALS['translated'][]=$l; return ['title'=>'Titel ' . $l,'body'=>'Body ' . $l]; });
check('locales: one variant per language', count($exp['items'])===2 && $exp['items'][0]['locale']==='da' && $exp['items'][1]['locale']==='sv');
check('locales: each variant points at its own translated page', $exp['items'][0]['destination_url']==='https://steamgears.eu/?p=421' && $exp['items'][1]['destination_url']==='https://steamgears.eu/?p=422');
check('locales: the variant keeps channel, angle, format and image', $exp['items'][0]['channel']==='pinterest' && $exp['items'][0]['angle']==='angle_a' && $exp['items'][0]['format']==='pin' && $exp['items'][0]['image_url']==='https://x/y.jpg');
check('locales: the variant knows its source post and queue id', $exp['items'][0]['post_id']===42 && $exp['items'][0]['source_id']===100);
check('locales: the caption is the translated one', $exp['items'][0]['title']==='Titel da' && $exp['items'][0]['body']==='Body da');
check('locales: every language is translated once', $GLOBALS['translated']===['da','sv']);

$exp = SGAP_Locales::expand($row_l, 100, fn()=>new WP_Error('sgap_ai','The model refused'));
check('locales: a translator failure is reported per language', $exp['items']===[] && str_contains($exp['errors'][0]['error'],'The model refused'));
$exp = SGAP_Locales::expand($row_l, 100, fn()=>['title'=>'','body'=>'']);
check('locales: an empty translation is an error, not an empty post', $exp['items']===[] && str_contains($exp['errors'][0]['error'],'empty'));
$exp = SGAP_Locales::expand($row_l, 100, fn()=>'not a caption');
check('locales: a non-caption answer is an error', $exp['items']===[] && str_contains($exp['errors'][0]['error'],'not a caption'));

// AI-stien: alle kald gennem WordPress-HTTP-API'et, så den kan stubbes.
$GLOBALS['opts']['sgap_ai_provider']='anthropic'; $GLOBALS['opts']['sgap_ai_api_key']=''; $GLOBALS['http_calls']=[];
$exp = SGAP_Locales::expand($row_l, 100);
check('locales: a missing AI key is an error, not a PHP warning', $exp['items']===[] && count($exp['errors'])===2 && str_contains($exp['errors'][0]['error'],'AI API key'));
check('locales: no HTTP call without a key', $GLOBALS['http_calls']===[]);
$GLOBALS['opts']['sgap_ai_api_key']='k'; $GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[sgap_http(200,['content'=>[['type'=>'text','text'=>'{"title":"Messingbrille","body":"Se skarp ud."}']]])];
$t = SGAP_Locales::ai_translate($row_l,'da');
check('locales: a translated caption is read from the model answer', is_array($t) && $t['title']==='Messingbrille' && $t['body']==='Se skarp ud.');
check('locales: the translation is exactly one stubbed HTTP call', count($GLOBALS['http_calls'])===1);
$GLOBALS['http_queue']=[sgap_http(500,['error'=>['message'=>'upstream is down']]),sgap_http(500,['error'=>['message'=>'upstream is down']])];
$exp = SGAP_Locales::expand($row_l, 100);
check('locales: an API error is reported per language', $exp['items']===[] && str_contains($exp['errors'][0]['error'],'upstream is down'));
$GLOBALS['http_queue']=[sgap_http(200,['content'=>[['type'=>'text','text'=>'not json']]]),sgap_http(200,['content'=>[['type'=>'text','text'=>'not json']]])];
$exp = SGAP_Locales::expand($row_l, 100);
check('locales: a non-JSON model answer is an error', $exp['items']===[] && count($exp['errors'])===2);

// Køen: kilden lægger varianterne i køen, og en variant arver ikke videre.
$GLOBALS['opts']['sgap_quality_gate']='off'; $GLOBALS['opts']['sgap_full_auto_enabled']='no'; $wpdb->next=null;
$GLOBALS['filters']['sgap_locale_translator']=fn($d)=>fn($r,$l)=>['title'=>'Titel ' . $l,'body'=>'Body ' . $l];
$wpdb->log=[];
$q = SGAP_Queue::add(['channel'=>'facebook','title'=>'Brass goggles','destination_url'=>'https://steamgears.eu/p/','post_id'=>42,'body'=>'A body long enough to pass.']);
$log = implode("\n", $wpdb->log);
check('locales: queueing a post also queues one variant per language', is_array($q) && count($q['locales'])===2 && $q['locale_errors']===[]);
check('locales: the variants are separate queue rows with their own language', substr_count($log,'INSERT wp_sgap_queue')===3 && str_contains($log,'"locale":"da"') && str_contains($log,'"locale":"sv"'));
check('locales: a variant carries the id of the post it came from', str_contains($log,'"source_id":42'));

$GLOBALS['filters']['sgap_locale_translator']=fn($d)=>fn()=>new WP_Error('sgap_ai','no answer');
$q = SGAP_Queue::add(['channel'=>'facebook','title'=>'Brass goggles','destination_url'=>'https://steamgears.eu/p/2/','post_id'=>42,'body'=>'A body long enough to pass.']);
check('locales: the source post is still queued when a translation fails', is_array($q) && $q['id']>0 && count($q['locale_errors'])===2 && $q['locales']===[]);
check('locales: the failed language is named in the result', ($q['locale_errors'][0]['locale']??'')==='da');

$wpdb->log=[];
SGAP_Queue::add(['channel'=>'facebook','title'=>'T','destination_url'=>'https://steamgears.eu/p/3/','post_id'=>42,'body'=>'A body long enough to pass.','locale'=>'da','source_id'=>100]);
check('locales: a variant never spawns variants of its own', substr_count(implode("\n",$wpdb->log),'INSERT wp_sgap_queue')===1);

$GLOBALS['opts']['sgap_locales']=''; $wpdb->log=[];
$q = SGAP_Queue::add(['channel'=>'facebook','title'=>'Brass goggles','destination_url'=>'https://steamgears.eu/p/4/','body'=>'A body long enough to pass.']);
check('locales: with no languages the queue behaves as before', is_array($q) && $q['locales']===[] && $q['locale_errors']===[] && substr_count(implode("\n",$wpdb->log),'INSERT wp_sgap_queue')===1);
unset($GLOBALS['filters']['sgap_locale_translator']);

$core_src = file_get_contents(__DIR__.'/../steamgears-autopilot/includes/core.php');
check('locales: the queue table stores language and source id', str_contains($core_src,'locale VARCHAR(10)') && str_contains($core_src,'source_id BIGINT UNSIGNED'));
check('locales: every caption is translated through the AI module', str_contains(file_get_contents(__DIR__.'/../steamgears-autopilot/includes/locales.php'),'SGAP_AI::json_call('));

// 7.32.0 — /go/<id>/ som produktkortlink: ruten sendte læseren til forsiden når id'et ikke var en kø-post.
if (!function_exists('wc_get_product')) { function wc_get_product($id){ return $GLOBALS['wc_product_by_id'][(int)$id] ?? false; } }
$GLOBALS['wc_product_by_id']=[
    477=>new Fake_Product(['id'=>477,'name'=>'Copper Pilot Goggles']),
    766=>new Fake_Product(['id'=>766,'name'=>'Steampunk Leather Utility Belt & Holster']),
];
check('product link: a product id resolves to its permalink', SGAP_Tracking::product_target(477)==='https://steamgears.eu/p/477/');
check('product link: an unknown id still lands on the front page', SGAP_Tracking::product_target(987654)===home_url('/'));
check('product link: a queue id is not treated as a product', SGAP_Tracking::product_target(0)===home_url('/'));
$coresrc=file_get_contents(__DIR__.'/../steamgears-autopilot/includes/core.php');
check('product link: the missing-row branch uses the product fallback', str_contains($coresrc,'wp_redirect(self::product_target($id), 302, \'Steamgears\')'));
check('product link: the product lookup is guarded, so the route cannot fatal without WooCommerce', str_contains($coresrc,"function_exists('wc_get_product')"));

// P1.4 — evergreen recycling: mark, variation, share budget, expiry, queue integration.
$GLOBALS['opts']['sgap_evergreen_enabled']='yes'; $GLOBALS['opts']['sgap_evergreen_after_days']=60;
$GLOBALS['opts']['sgap_evergreen_max_shares']=3; $GLOBALS['opts']['sgap_evergreen_batch']=1;
$GLOBALS['opts']['sgap_channels']='facebook,instagram,pinterest'; $GLOBALS['opts']['sgap_quality_gate']='off';
$GLOBALS['opts']['sgap_ai_api_key']=''; $GLOBALS['opts']['sgap_ai_provider']='anthropic'; $GLOBALS['opts']['sgap_ai_model']='';
$GLOBALS['postmeta']=[]; $GLOBALS['post_by_id']=[]; $GLOBALS['posts_result']=[]; $wpdb->row=null; $wpdb->next=null; $wpdb->log=[];
$ev1=new WP_Post(['ID'=>501,'post_type'=>'post','post_status'=>'publish','post_title'=>'Brass Goggles Guide','post_content'=>'A long guide about brass goggles and how to wear them well.','post_excerpt'=>'']);
$ev2=new WP_Post(['ID'=>502,'post_type'=>'post','post_status'=>'publish','post_title'=>'Corset Fitting Guide','post_content'=>'How a corset should fit, and what to do when it does not.','post_excerpt'=>'']);
$GLOBALS['post_by_id']=[501=>$ev1,502=>$ev2]; $GLOBALS['posts_result']=[$ev1,$ev2];
check('evergreen: the daily cron is scheduled', in_array('sgap_evergreen',$GLOBALS['cron']??[],true));
$marked=SGAP_Evergreen::mark(501,['max'=>2,'until'=>'2027-01-31']);
check('evergreen: mark stores the budget and expiry date', is_array($marked) && $marked['marked'] && $marked['max']===2 && $marked['until']==='2027-01-31');
check('evergreen: a marked page with no shares is ready', SGAP_Evergreen::status(501)['eligible'] && SGAP_Evergreen::shares(501)===0);
check('evergreen: a past expiry date is refused', SGAP_Evergreen::mark(502,['until'=>'2020-01-01']) instanceof WP_Error);
check('evergreen: an unsupported post type is refused', SGAP_Evergreen::mark(999) instanceof WP_Error);
check('evergreen: the marked page is a candidate', in_array(501,SGAP_Evergreen::candidates(10),true));
check('evergreen: the summary counts the mark', SGAP_Evergreen::summary()['marked']===1);

$GLOBALS['ev_counter']=0;
$GLOBALS['filters']['sgap_evergreen_variation']=function($v,$post,$channel,$angle,$history){ $GLOBALS['ev_counter']++; return ['title'=>'Fresh angle '.$GLOBALS['ev_counter'],'text'=>'A new way into the guide, written for '.$channel.'.','hashtags'=>['steampunk','brass'],'alt_text'=>'Brass goggles']; };
$first=SGAP_Evergreen::queue_one(501);
check('evergreen: the first share is queued through the normal queue', is_array($first) && $first['channel']==='facebook' && $first['share']===1 && $first['max']===2);
$evlog=implode("\n",$wpdb->log);
check('evergreen: the queue row carries the page, the angle and the variation', str_contains($evlog,'INSERT wp_sgap_queue') && str_contains($evlog,'"post_id":501') && str_contains($evlog,'"angle":"angle_a"') && str_contains($evlog,'"content_type":"guide"'));
check('evergreen: hashtags become part of the caption', str_contains($evlog,'#steampunk #brass'));
check('evergreen: the share is counted and the page is put on cooldown', SGAP_Evergreen::shares(501)===1 && SGAP_Evergreen::last_share(501)>0 && !SGAP_Evergreen::status(501)['eligible']);
check('evergreen: the waiting period is named', str_contains(SGAP_Evergreen::status(501)['reason'],'next share from'));
$tooSoon=SGAP_Evergreen::queue_one(501);
check('evergreen: a page cannot be recycled twice in the same week', is_wp_error($tooSoon) && ($tooSoon->data['status']??0)===409 && str_contains($tooSoon->get_error_message(),'next share'));
update_post_meta(501,SGAP_Evergreen::LAST,time()-100*DAY_IN_SECONDS); $wpdb->log=[];
$second=SGAP_Evergreen::queue_one(501);
check('evergreen: the next share rotates channel and angle', is_array($second) && $second['channel']==='instagram' && $second['share']===2);
check('evergreen: the rotated angle is in the queue row', str_contains(implode("\n",$wpdb->log),'"angle":"angle_b"'));
$full=SGAP_Evergreen::status(501);
check('evergreen: the budget closes the page', $full['shares']===2 && !$full['eligible'] && str_contains($full['reason'],'all 2 shares used'));
$over=SGAP_Evergreen::queue_one(501);
check('evergreen: a page over its budget is refused', is_wp_error($over) && str_contains($over->get_error_message(),'all 2 shares used'));

$GLOBALS['filters']['sgap_evergreen_variation']=fn()=>['title'=>'T','text'=>'Body for the second evergreen page.','hashtags'=>[],'alt_text'=>''];
update_post_meta(502,SGAP_Evergreen::MARK,'1'); update_post_meta(502,SGAP_Evergreen::UNTIL,'2026-01-01');
$expired=SGAP_Evergreen::status(502);
check('evergreen: an expired page is not ready and says why', !$expired['eligible'] && str_contains($expired['reason'],'expired on'));
update_post_meta(502,SGAP_Evergreen::UNTIL,wp_date('Y-m-d',time()+DAY_IN_SECONDS)); $wpdb->next=null;
check('evergreen: a page inside its expiry window is ready again', SGAP_Evergreen::status(502)['eligible']);
SGAP_Evergreen::unmark(502);
check('evergreen: unmark removes the mark and the budget', !SGAP_Evergreen::status(502)['marked'] && get_post_meta(502,SGAP_Evergreen::MAX,true)==='' && get_post_meta(502,SGAP_Evergreen::SHARES,true)==='');

// AI-stien: variationsprompten går gennem det konfigurerede AI-kald og kan stubbes.
unset($GLOBALS['filters']['sgap_evergreen_variation']);
SGAP_Evergreen::mark(502,['max'=>1]); $GLOBALS['opts']['sgap_ai_api_key']='k'; $wpdb->log=[]; $GLOBALS['http_calls']=[];
$GLOBALS['http_queue']=[sgap_http(200,['content'=>[['type'=>'text','text'=>json_encode(['title'=>'A second look at the corset','text'=>'The fit guide, revisited.','hashtags'=>['#corset'],'alt_text'=>'Corset on a mannequin'])]]])];
$ai=SGAP_Evergreen::queue_one(502);
check('evergreen: the AI variation is one structured call', is_array($ai) && count($GLOBALS['http_calls'])===1);
check('evergreen: the prompt asks for a new angle and forbids invented offers', str_contains((string)$GLOBALS['http_calls'][0][1]['body'],'genuinely different angle') && str_contains((string)$GLOBALS['http_calls'][0][1]['body'],'do not invent a discount'));
check('evergreen: the AI answer is what reaches the queue', str_contains(implode("\n",$wpdb->log),'A second look at the corset') && str_contains(implode("\n",$wpdb->log),'#corset'));
$GLOBALS['opts']['sgap_ai_api_key']='';

// P0.4: cron-honesty — Site Health-testen, rådgivningen og den præcise fix-linje.
$GLOBALS['next_scheduled']=[];
$registered=SGAP_Auto::site_status_tests(['direct'=>[]]);
check('cron: the Site Health test is registered under its own id', isset($registered['direct']['sgap_wp_cron']) && ($registered['direct']['sgap_wp_cron']['test'][1]??'')==='site_health_cron');
$GLOBALS['next_scheduled']=[SGAP_Install::PUBLISH_CRON=>time()-5*MINUTE_IN_SECONDS];
$onTime=SGAP_Auto::site_health_cron();
check('cron: a publisher under 30 minutes late is reported as good', $onTime['status']==='good' && $onTime['test']==='sgap_wp_cron');
$GLOBALS['next_scheduled']=[SGAP_Install::PUBLISH_CRON=>time()-45*MINUTE_IN_SECONDS];
$late=SGAP_Auto::site_health_cron();
check('cron: a publisher 45 minutes late turns the test critical', $late['status']==='critical' && str_contains($late['description'],'45 minutes behind'));
check('cron: the advice carries the exact wp-cron.php call and the 5-minute interval', str_contains($late['description'],'wp-cron.php') && str_contains($late['description'],'every 5 minutes'));
unset($GLOBALS['next_scheduled']);
// P0.5 — scheduler-adapteren. Uden Action Scheduler (som i denne suite, indtil stubbene
// nedenfor defineres) skal alt lande på WP-Cron med samme hook; med AS skal intet gøre det.
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($ts,$h,$a=[]){ $GLOBALS['single_events'][]=[$h,$ts]; return true; } }
check('scheduler: without Action Scheduler the backend is wp-cron', SGAP_Scheduler::backend()==='wp-cron' && !SGAP_Scheduler::available());
$GLOBALS['cron']=[]; $GLOBALS['next_scheduled']=[];
SGAP_Scheduler::recurring('sgap_test_hook',5*MINUTE_IN_SECONDS);
check('scheduler: a recurring job lands on WP-Cron with its hook intact', in_array('sgap_test_hook',$GLOBALS['cron'],true));
$cron_before=count($GLOBALS['cron']); $GLOBALS['next_scheduled']['sgap_test_hook']=time()+120;
check('scheduler: an existing occurrence is not scheduled twice', SGAP_Scheduler::recurring('sgap_test_hook',5*MINUTE_IN_SECONDS) && count($GLOBALS['cron'])===$cron_before);
unset($GLOBALS['next_scheduled']['sgap_test_hook']);
$GLOBALS['single_events']=[];
check('scheduler: a one-off job lands on WP-Cron', SGAP_Scheduler::single('sgap_test_once',time()+600) && ($GLOBALS['single_events'][0][0]??'')==='sgap_test_once');
if (!function_exists('as_schedule_recurring_action')) {
    function as_schedule_recurring_action($ts,$interval,$hook,$args=[],$group=''){ $GLOBALS['as']['recurring'][]=[$ts,$interval,$hook,$group]; return 77; }
    function as_schedule_single_action($ts,$hook,$args=[],$group=''){ $GLOBALS['as']['single'][]=[$ts,$hook,$group]; return 78; }
    function as_next_scheduled_action($hook,$args=[],$group=''){ return $GLOBALS['as']['next']; }
    function as_unschedule_all_actions($hook,$args=[],$group=''){ $GLOBALS['as']['unschedule'][]=[$hook,$group]; return 1; }
}
$GLOBALS['as']=['recurring'=>[],'single'=>[],'unschedule'=>[],'next'=>false];
check('scheduler: Action Scheduler is detected from its functions', SGAP_Scheduler::available() && SGAP_Scheduler::backend()==='action-scheduler');
check('scheduler: recurring jobs go to AS in the sgap group', SGAP_Scheduler::recurring('sgap_as_hook',DAY_IN_SECONDS) && ($GLOBALS['as']['recurring'][0][2]??'')==='sgap_as_hook' && ($GLOBALS['as']['recurring'][0][3]??'')==='sgap');
$GLOBALS['as']['next']=time()+300; $as_before=count($GLOBALS['as']['recurring']);
check('scheduler: an AS job that already exists is left alone', SGAP_Scheduler::recurring('sgap_as_hook',DAY_IN_SECONDS) && count($GLOBALS['as']['recurring'])===$as_before);
$GLOBALS['as']['next']=false;
check('scheduler: single jobs carry the group too', SGAP_Scheduler::single('sgap_as_once',time()+600) && ($GLOBALS['as']['single'][0][2]??'')==='sgap');
SGAP_Scheduler::unschedule('sgap_as_hook');
check('scheduler: unschedule clears the AS hook in our group', ($GLOBALS['as']['unschedule'][0]??null)===['sgap_as_hook','sgap']);
check('scheduler: AS present means WP-Cron is never asked', !in_array('sgap_as_hook',$GLOBALS['cron'],true));
check('scheduler: health names the backend and explains the difference', SGAP_Scheduler::health()['backend']==='action-scheduler' && str_contains(SGAP_Scheduler::health()['note'],'Action Scheduler'));

// P0.7b — alle jobs gennem adapteren.
$GLOBALS['as']['recurring']=[]; $GLOBALS['as']['single']=[]; $GLOBALS['as']['unschedule']=[]; $GLOBALS['as']['next']=0; $GLOBALS['cron']=[]; $GLOBALS['ddl']=[];
SGAP_Install::install();
$as_hooks = array_map(fn($r)=>$r[2], $GLOBALS['as']['recurring']);
check('scheduler: activation puts all five jobs on Action Scheduler', count(array_intersect(
    [SGAP_Install::CLEANUP_CRON, SGAP_Install::GENERATE_CRON, SGAP_Install::EVERGREEN_CRON, SGAP_Install::PUBLISH_CRON, SGAP_Install::MCP_CRON], $as_hooks))===5);
$publish = array_values(array_filter($GLOBALS['as']['recurring'], fn($r)=>$r[2]===SGAP_Install::PUBLISH_CRON));
check('scheduler: the publisher keeps its 15-minute interval on AS', ($publish[0][1]??0)===15*MINUTE_IN_SECONDS && ($publish[0][3]??'')==='sgap');
check('scheduler: the first run keeps its delay instead of waiting a whole interval', abs(($publish[0][0]??0) - (time()+5*MINUTE_IN_SECONDS)) < 5);
check('scheduler: one pass leaves WP-Cron empty when AS is there', $GLOBALS['cron']===[]);

$GLOBALS['as']['recurring']=[];
SGAP_Auto::schedule();
$health = array_values(array_filter($GLOBALS['as']['recurring'], fn($r)=>$r[2]===SGAP_Auto::HEALTH_CRON));
check('scheduler: the daily self-check goes through the adapter too', ($health[0][1]??0)===DAY_IN_SECONDS && abs(($health[0][0]??0)-(time()+2*HOUR_IN_SECONDS)) < 5);

$GLOBALS['cleared']=[]; $GLOBALS['as']['unschedule']=[];
SGAP_Scheduler::unschedule('sgap_both_hook');
check('scheduler: unschedule clears AS and WP-Cron, so a switch cannot leave a twin', ($GLOBALS['as']['unschedule'][0]??null)===['sgap_both_hook','sgap'] && in_array('sgap_both_hook',$GLOBALS['cleared'],true));

$GLOBALS['as']['next']=time()-45*MINUTE_IN_SECONDS;
$lag=SGAP_Auto::cron_lag();
check('scheduler: cron lag is measured on whichever backend runs', $lag>=44 && $lag<=46);
$GLOBALS['as']['next']=time()+5*MINUTE_IN_SECONDS;
check('scheduler: Site Health names the backend that is running', str_contains(SGAP_Auto::site_health_cron()['description'],'action-scheduler'));

$GLOBALS['filters']['sgap_scheduler_action_scheduler']=fn($v)=>false;
check('scheduler: the filter forces WP-Cron while AS is installed', SGAP_Scheduler::backend()==='wp-cron');
unset($GLOBALS['filters']['sgap_scheduler_action_scheduler']);

$GLOBALS['as']['next']=0; $GLOBALS['as']['recurring']=[];
check('scheduler: a zero next run in AS still means not scheduled', SGAP_Scheduler::recurring('sgap_as_zero',HOUR_IN_SECONDS) && count($GLOBALS['as']['recurring'])===1);
$GLOBALS['as']['next']=0; $GLOBALS['as']['recurring']=[]; $GLOBALS['as']['unschedule']=[];


// P0.7c — adminkort viser backend, næste publicering, forsinkelse og engagement-snapshots.
$GLOBALS['as']['next']=time()+15*MINUTE_IN_SECONDS;
ob_start(); SGAP_Auto::scheduler_card(); $scheduler_card=ob_get_clean();
check('scheduler admin: dashboard names Action Scheduler and the next publish tick', str_contains($scheduler_card,'Action Scheduler') && str_contains($scheduler_card,'Next publish tick:') && str_contains($scheduler_card,'Publish tick scheduled'));
$GLOBALS['as']['next']=time()-35*MINUTE_IN_SECONDS;
ob_start(); SGAP_Auto::scheduler_card(); $scheduler_card=ob_get_clean();
check('scheduler admin: overdue publish tick is shown with its measured lag', str_contains($scheduler_card,'Late by 35 min'));
$GLOBALS['as']['next']=0;
ob_start(); SGAP_Auto::scheduler_card(); $scheduler_card=ob_get_clean();
check('scheduler admin: missing publish event is not shown as healthy', str_contains($scheduler_card,'Publish tick not scheduled') && !str_contains($scheduler_card,'Publish tick scheduled'));
$GLOBALS['filters']['sgap_scheduler_action_scheduler']=fn($v)=>false;
$GLOBALS['next_scheduled']=[SGAP_Install::PUBLISH_CRON=>time()+15*MINUTE_IN_SECONDS];
ob_start(); SGAP_Auto::scheduler_card(); $scheduler_card=ob_get_clean();
check('scheduler admin: dashboard reports WP-Cron when the fallback is selected', str_contains($scheduler_card,'WP-Cron') && str_contains($scheduler_card,'Next publish tick:'));
unset($GLOBALS['filters']['sgap_scheduler_action_scheduler']); unset($GLOBALS['next_scheduled']); $GLOBALS['as']['next']=0;
$GLOBALS['rows']=[];
ob_start(); SGAP_Engagement::dashboard_card(); $engagement_card=ob_get_clean();
check('engagement admin: empty state explains that snapshots arrive after refresh', str_contains($engagement_card,'No engagement snapshots yet') && str_contains($engagement_card,'Bluesky and Mastodon'));
$GLOBALS['rows']=[
    ['channel'=>'bluesky','event_type'=>'likes','total'=>'12','rows_count'=>'3'],
    ['channel'=>'bluesky','event_type'=>'reposts','total'=>'3','rows_count'=>'3'],
    ['channel'=>'mastodon','event_type'=>'replies','total'=>'2','rows_count'=>'1'],
];
ob_start(); SGAP_Engagement::dashboard_card(); $engagement_card=ob_get_clean();
check('engagement admin: dashboard renders per-channel likes, reposts and replies', str_contains($engagement_card,'bluesky') && str_contains($engagement_card,'mastodon') && str_contains($engagement_card,'>12</td>') && str_contains($engagement_card,'>3</td>') && str_contains($engagement_card,'>2</td>'));
check('engagement admin: the totals sit above the table as metrics', str_contains($engagement_card,'sgap-metrics') && strpos($engagement_card,'sgap-metrics') < strpos($engagement_card,'<table'));
$GLOBALS['rows']=[];

// 7.52.0 — dashboardet læses oppefra: hvad kræver et menneske, hvad kører, hvor poster det,
// og hvad holder det i live. Hvert kort skal stå under det spørgsmål det svarer på.
ob_start(); do_action('page_steamgears-autopilot'); $dash = ob_get_clean();
$now = strpos($dash,'<h2>Now</h2>'); $chan = strpos($dash,'<h2>Channels</h2>'); $sys = strpos($dash,'<h2>System</h2>');
check('dashboard 7.52: three sections in reading order', $now !== false && $chan !== false && $sys !== false && $now < $chan && $chan < $sys);
check('dashboard 7.52: the attention strip is the first thing after the status pills', str_contains($dash,'sgap-attn') && strpos($dash,'sgap-attn') < strpos($dash,'sgap-stats'));
check('dashboard 7.52: run now, chat and scheduler answer "is it running"', $now < strpos($dash,'Run now') && strpos($dash,'Run now') < $chan
    && $now < strpos($dash,'Open AI Chat') && strpos($dash,'Open AI Chat') < $chan
    && $now < strpos($dash,'Scheduler</h2>') && strpos($dash,'Scheduler</h2>') < $chan);
check('dashboard 7.52: channel numbers and engagement share one section', $chan < strpos($dash,'Last 30 days per channel') && strpos($dash,'Last 30 days per channel') < $sys
    && $chan < strpos($dash,'Engagement · last 30 days') && strpos($dash,'Engagement · last 30 days') < $sys);
check('dashboard 7.52: the updater moved under System', $sys < strpos($dash,'Plugin update'));
check('dashboard 7.52: a healthy dashboard says so in one line instead of scattered pills', str_contains($dash,'sgap-attn is-ok') && str_contains($dash,'All clear'));
ob_start(); do_action('page_sgap-settings'); $set53 = ob_get_clean();
check('settings 7.54: every tab but Keys opens with a heading and a hint', substr_count($set53,'class="sgap-panel-head"') === count(SGAP_Setup::TABS) - 1);
check('settings 7.54: the hints cover exactly those tabs, in tab order', array_keys(SGAP_Setup::TAB_HINTS) === array_values(array_diff(array_keys(SGAP_Setup::TABS), ['keys'])));
check('settings 7.54: the hint is rendered next to the heading, not only stored', str_contains($set53,'<h2><span class="dashicons dashicons-admin-home"></span>Basics</h2>') && str_contains($set53,'What the autopilot may do on its own'));

// Køen er to spørgsmål, ikke én tabel: hvad er på vej ud, og hvad kom der ud af det.
$wpdb->next='wp_sgap_queue';
$GLOBALS['rows']=[
    (object)['id'=>901,'channel'=>'pinterest','title'=>'Waiting one','status'=>'queued','remind'=>0,'attempts'=>1,'scheduled_at'=>'2026-11-08 10:00:00','published_at'=>null,'error'=>null],
    (object)['id'=>902,'channel'=>'bluesky','title'=>'Done one','status'=>'published','remind'=>0,'attempts'=>1,'scheduled_at'=>'2026-11-07 09:00:00','published_at'=>'2026-11-07 09:01:00','error'=>null],
];
ob_start(); do_action('page_sgap-queue'); $queue53 = ob_get_clean();
$qw = strpos($queue53,'<h2>Waiting</h2>'); $qd = strpos($queue53,'<h2>Done</h2>');
check('queue 7.54: waiting and done are two sections, waiting first', $qw !== false && $qd !== false && $qw < $qd);
check('queue 7.54: a queued row sits under Waiting, a published row under Done', $qw !== false && $qd !== false
    && str_contains(substr($queue53,$qw,$qd-$qw),'>901</td>') && !str_contains(substr($queue53,$qw,$qd-$qw),'>902</td>')
    && str_contains(substr($queue53,$qd),'>902</td>'));
check('queue 7.54: the waiting hint counts what is left', str_contains(substr($queue53,$qw,$qd-$qw),'One post still has to go out.'));
$GLOBALS['rows']=[(object)['id'=>903,'channel'=>'bluesky','title'=>'Sending now','status'=>'sending','remind'=>0,'attempts'=>1,'scheduled_at'=>null,'published_at'=>null,'error'=>null]];
ob_start(); do_action('page_sgap-queue'); $q53b = ob_get_clean();
$qw2 = strpos($q53b,'<h2>Waiting</h2>');
check('queue 7.54: a sending row counts as waiting, and an empty Done group says so', $qw2 !== false
    && str_contains(substr($q53b,$qw2),'>903</td>') && str_contains($q53b,'Nothing has gone out yet.'));
$GLOBALS['rows']=[]; $wpdb->next=null;

ob_start(); do_action('page_sgap-studio'); $studio53 = ob_get_clean();
$sw = strpos($studio53,'<h2>Write</h2>'); $sf = strpos($studio53,'<h2>Fix</h2>');
check('studio 7.54: write and fix are two sections, write first', $sw !== false && $sf !== false && $sw < $sf);
check('studio 7.54: drafting and images under Write, SEO work under Fix', $sw !== false && $sf !== false
    && str_contains(substr($studio53,$sw,$sf-$sw),'Write an article or guide') && str_contains(substr($studio53,$sw,$sf-$sw),'AI image')
    && str_contains(substr($studio53,$sf),'Optimise SEO of a page') && str_contains(substr($studio53,$sf),'Optimise the whole site'));
check('studio 7.54: the score card still explains what it measures', str_contains(substr($studio53,$sf),'What the score measures') && str_contains(substr($studio53,$sf),'AEO'));
check('admin pages 7.54: css carries the panel head and its high-contrast rule', str_contains(SGAP_UI::stylesheet(), '.sgap-panel-head{') && str_contains(SGAP_UI::stylesheet(), '.sgap-panel-head,.sgap .sgap-section-head:after{border-color:CanvasText}'));
$css52 = SGAP_UI::stylesheet();
check('dashboard 7.52: css carries the section heading and the metric row', str_contains($css52,'.sgap-section-head{') && str_contains($css52,'.sgap-metrics{') && str_contains($css52,'forced-colors:active'));

// P1.5a — Bluesky- og Mastodon-engagement ind i sgap_events. Netværket går gennem
// SGAP_Engagement::$transport, fordi smoke-suiten allerede har defineret wp_remote_get.
$GLOBALS['opts']['sgap_mastodon_instance']='https://mastodon.social';
check('engagement: mastodon id from mastodon:<id>', SGAP_Engagement::mastodon_id('mastodon:12345')==='12345');
check('engagement: mastodon id from a post URL', SGAP_Engagement::mastodon_id('https://mastodon.social/@steamgears/999/')==='999');
check('engagement: a job id or AT URI is not a mastodon id', SGAP_Engagement::mastodon_id('job_abc')==='' && SGAP_Engagement::mastodon_id('at://did:plc:x/app.bsky.feed.post/1')==='');
$GLOBALS['engage_urls']=[];
SGAP_Engagement::$transport=function($url){
    $GLOBALS['engage_urls'][]=$url;
    if (str_contains($url,'getPosts')) {
        return sgap_http(200,['posts'=>[['uri'=>'at://did:plc:a/app.bsky.feed.post/1','likeCount'=>12,'repostCount'=>3,'replyCount'=>2]]]);
    }
    return sgap_http(200,['id'=>'555','favourites_count'=>7,'reblogs_count'=>4,'replies_count'=>1]);
};
$counts=SGAP_Engagement::bluesky(['at://did:plc:a/app.bsky.feed.post/1','at://did:plc:a/app.bsky.feed.post/1']);
check('engagement: bluesky reads likeCount/repostCount/replyCount', ($counts['at://did:plc:a/app.bsky.feed.post/1']??null)===['likes'=>12,'reposts'=>3,'replies'=>2]);
check('engagement: bluesky dedupes URIs into one call with repeated uris params', count($GLOBALS['engage_urls'])===1 && substr_count($GLOBALS['engage_urls'][0],'uris=')===1 && str_contains($GLOBALS['engage_urls'][0],'at%3A%2F%2F'));
$GLOBALS['engage_urls']=[];
$mast=SGAP_Engagement::mastodon(['mastodon:555','555','https://mastodon.social/@steamgears/555']);
check('engagement: mastodon reads favourites/reblogs/replies', ($mast['555']??null)===['likes'=>7,'reposts'=>4,'replies'=>1]);
check('engagement: three spellings of one status cost one call', count($GLOBALS['engage_urls'])===1 && str_ends_with($GLOBALS['engage_urls'][0],'/api/v1/statuses/555'));
$GLOBALS['rows']=[
    ['id'=>'7','channel'=>'bluesky','angle'=>'angle_a','external_id'=>'at://did:plc:a/app.bsky.feed.post/1'],
    ['id'=>'8','channel'=>'mastodon','angle'=>'angle_b','external_id'=>'mastodon:555'],
    ['id'=>'9','channel'=>'pinterest','angle'=>'angle_a','external_id'=>'pin_1'],
];
$GLOBALS['engage_urls']=[]; $wpdb->log=[];
$run=SGAP_Engagement::refresh();
check('engagement: refresh follows the two supported channels and leaves the rest alone', $run['targets']===3 && $run['updated']===2 && $run['errors']===[]);
check('engagement: every queue row is stored once per type', substr_count(implode("\n",$wpdb->log),'INSERT wp_sgap_events')===6 && substr_count(implode("\n",$wpdb->log),'likes')>=2);
check('engagement: the old snapshot is deleted before the new one is written', substr_count(implode("\n",$wpdb->log),'DELETE FROM wp_sgap_events WHERE queue_id')===2);
// The previous block left queue rows in $GLOBALS['rows'], which the fake wpdb hands to any
// query; clear them, or this check reads queue columns as snapshot columns — and it used to
// end in an always-true clause, so it could not fail at all. tools/fejlscan.py rejects that.
$GLOBALS['rows']=[];
check('engagement: no snapshots means an empty summary, not invented zeros', SGAP_Engagement::summary()===[]);
$GLOBALS['rows']=[['channel'=>'bluesky','event_type'=>'likes','total'=>'12','rows_count'=>'3'],['channel'=>'bluesky','event_type'=>'reposts','total'=>'3','rows_count'=>'3']];
$sum=SGAP_Engagement::summary();
check('engagement: summary sums per channel and reports reach as null', ($sum['bluesky']['likes']??null)===12.0 && ($sum['bluesky']['reposts']??null)===3.0 && array_key_exists('reach',$sum['bluesky']) && $sum['bluesky']['reach']===null);
SGAP_Engagement::$transport=function($url){ return sgap_http(500,['error'=>'boom']); };
$GLOBALS['rows']=[['id'=>'7','channel'=>'bluesky','angle'=>'angle_a','external_id'=>'at://did:plc:a/app.bsky.feed.post/1']];
$failed=SGAP_Engagement::refresh();
check('engagement: a 500 is reported and stores nothing', $failed['updated']===0 && count($failed['errors'])===1 && str_contains($failed['errors'][0],'500'));
SGAP_Engagement::$transport=null; $GLOBALS['rows']=[];


/* ---------------- P1.24: OpenRouter as an automatic backup for AIMLAPI ---------------- */
// Only a provider failure may start the backup: a 2xx answer — even an empty one — means the
// provider answered, and a second call would pay twice for the same request. Every call here is
// stubbed through http_queue, so nothing leaves the machine and no key is real.
$fb_keep = [];
foreach (['sgap_ai_provider','sgap_ai_model','sgap_ai_api_key','sgap_openrouter_api_key','sgap_openrouter_model'] as $k) {
    $fb_keep[$k] = $GLOBALS['opts'][$k] ?? null;
}
$fb_log_before = get_option('sgap_error_log', []);
update_option('sgap_ai_provider','aimlapi');
update_option('sgap_ai_model','anthropic/claude-opus-5.5');
update_option('sgap_ai_api_key','PRIMARY-KEY');
update_option('sgap_openrouter_api_key','sk-or-backup');
update_option('sgap_openrouter_model','anthropic/claude-sonnet-4.5');

check('fallback: ready only with a key, a model and a primary that is not OpenRouter',
    SGAP_AI::fallback_ready() && SGAP_AI::fallback_key()==='sk-or-backup' && SGAP_AI::fallback_model()==='anthropic/claude-sonnet-4.5');
update_option('sgap_openrouter_model','');
check('fallback: a key without a model is off, not guessed', !SGAP_AI::fallback_ready());
update_option('sgap_openrouter_model','anthropic/claude-sonnet-4.5');
$fb_provider = SGAP_AI::provider(); update_option('sgap_ai_provider','openrouter'); update_option('sgap_ai_model','anthropic/claude-sonnet-4.5');
check('fallback: OpenRouter as the provider never backs up to itself', !SGAP_AI::fallback_ready());
update_option('sgap_ai_provider','aimlapi'); update_option('sgap_ai_model','anthropic/claude-opus-5.5');
check('fallback: provider failures count, our own mistakes do not',
    SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>401]))
    && SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>403]))
    && SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>402]))
    && SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>429]))
    && SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>503]))
    && SGAP_AI::should_fallback(new WP_Error('offline','offline'))
    && !SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>400]))
    && !SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>422]))
    && !SGAP_AI::should_fallback(new WP_Error('x','y',['status'=>200])));

// A working primary is never doubled.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['choices'=>[['message'=>['content'=>'{"posts":[]}'],'finish_reason'=>'stop']]])];
$fb_json=SGAP_AI::json_call('sys','prompt',['type'=>'object']);
check('fallback: a working primary makes exactly one call and no backup',
    !is_wp_error($fb_json) && count($GLOBALS['http_calls'])===1 && SGAP_AI::last_fallback()===[] && SGAP_AI::fallback_note()==='');

// 402 — the case that actually happened on the live site: the account ran out of credit.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[
    sgap_http(402,['error'=>['message'=>'Insufficient credits']]),
    sgap_http(200,['choices'=>[['message'=>['content'=>'{"posts":[]}'],'finish_reason'=>'stop']]])
];
$fb_json=SGAP_AI::json_call('sys','prompt',['type'=>'object'],1200,'low');
$fb_backup=$GLOBALS['http_calls'][1]??[];
$fb_sent=json_decode((string)($fb_backup[1]['body']??''),true);
check('fallback: no credit starts the backup', !is_wp_error($fb_json) && count($GLOBALS['http_calls'])===2
    && ($fb_backup[0]??'')==='https://openrouter.ai/api/v1/chat/completions');
check('fallback: the backup sends its own key and its own model',
    ($fb_backup[1]['headers']['Authorization']??'')==='Bearer sk-or-backup' && ($fb_sent['model']??'')==='anthropic/claude-sonnet-4.5');
check('fallback: OpenRouter gets no reasoning_effort but the same request',
    !isset($fb_sent['reasoning_effort']) && isset($fb_sent['messages'][0]['content'],$fb_sent['response_format'])
    && ($fb_backup[1]['headers']['HTTP-Referer']??'')==='https://steamgears.eu/');
check('fallback: the result is recorded without the key',
    (SGAP_AI::last_fallback()['model']??'')==='anthropic/claude-sonnet-4.5'
    && (SGAP_AI::last_fallback()['status']??0)===402
    && !str_contains(wp_json_encode(SGAP_AI::last_fallback()),'sk-or-backup'));
$fb_note=SGAP_AI::fallback_note();
check('fallback: the owner is told the answer came from the backup',
    str_contains($fb_note,'OpenRouter backup') && str_contains($fb_note,'anthropic/claude-sonnet-4.5')
    && str_contains($fb_note,'Insufficient credits') && !str_contains($fb_note,'sk-or-backup'));
$fb_entries=SGAP_Diagnostics::entries();
check('fallback: the diagnostics log keeps one row, with the status and no key',
    ($fb_entries[0]['source']??'')==='ai-fallback' && (int)($fb_entries[0]['context']['status']??0)===402
    && !str_contains(wp_json_encode($fb_entries[0]),'sk-or-backup'));

foreach ([401,429,503] as $fb_code) {
    $GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[
        sgap_http($fb_code,['error'=>['message'=>'HTTP '.$fb_code]]),
        sgap_http(200,['choices'=>[['message'=>['content'=>'svar'],'finish_reason'=>'stop']]])
    ];
    $fb_out=SGAP_AI::chat_call('PRIMARY-KEY',['model'=>'anthropic/claude-opus-5.5','messages'=>[]]);
    check("fallback: HTTP {$fb_code} starts the backup", !is_wp_error($fb_out) && count($GLOBALS['http_calls'])===2);
}
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[
    new WP_Error('http_request_failed','cURL error 28: timed out'),
    sgap_http(200,['choices'=>[['message'=>['content'=>'svar'],'finish_reason'=>'stop']]])
];
$fb_out=SGAP_AI::chat_call('PRIMARY-KEY',['model'=>'anthropic/claude-opus-5.5','messages'=>[]]);
check('fallback: a transport error starts the backup', !is_wp_error($fb_out) && count($GLOBALS['http_calls'])===2);

// A rejected model name is the provider's own advice, not an outage: retry on its default first.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[
    sgap_http(400,['error'=>['message'=>'Model x/y not found']]),
    sgap_http(200,['choices'=>[['message'=>['content'=>'svar'],'finish_reason'=>'stop']]])
];
$fb_out=SGAP_AI::chat_call('PRIMARY-KEY',['model'=>'x/y','messages'=>[]]);
check('fallback: a rejected model is retried on the provider default, not backed up',
    !is_wp_error($fb_out) && count($GLOBALS['http_calls'])===2
    && (json_decode((string)($GLOBALS['http_calls'][1][1]['body']??''),true)['model']??'')==='anthropic/claude-opus-5.5'
    && SGAP_AI::last_fallback()===[]);
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(400,['error'=>['message'=>'Bad request']])];
$fb_out=SGAP_AI::chat_call('PRIMARY-KEY',['model'=>'anthropic/claude-opus-5.5','messages'=>[]]);
check('fallback: a 400 without a model error is reported, not backed up',
    is_wp_error($fb_out) && count($GLOBALS['http_calls'])===1);

// An empty or cut-off 200 answer is a real answer: no second call.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['choices'=>[['message'=>['content'=>''],'finish_reason'=>'length']]])];
$fb_gen=SGAP_AI::generate(['title'=>'T','type'=>'post','summary'=>'S'],['facebook']);
check('fallback: an empty 200 answer is an error, not a reason to call the backup',
    is_wp_error($fb_gen) && count($GLOBALS['http_calls'])===1);

// Both fail: the error names both providers and keeps the primary's status code.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[
    sgap_http(402,['error'=>['message'=>'Insufficient credits']]),
    sgap_http(401,['error'=>['message'=>'No auth credentials found']])
];
$fb_out=SGAP_AI::chat_call('PRIMARY-KEY',['model'=>'anthropic/claude-opus-5.5','messages'=>[]]);
check('fallback: a second failure names both providers and keeps the primary code',
    is_wp_error($fb_out) && str_contains($fb_out->get_error_message(),'Insufficient credits')
    && str_contains($fb_out->get_error_message(),'OpenRouter')
    && ($fb_out->get_error_data()['status']??0)===402);
check('fallback: nothing is recorded when the backup failed too', SGAP_AI::last_fallback()===[]);

// Without a model the backup is off — one attempt, the original error unchanged.
update_option('sgap_openrouter_model','');
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(402,['error'=>['message'=>'Insufficient credits']])];
$fb_out=SGAP_AI::chat_call('PRIMARY-KEY',['model'=>'anthropic/claude-opus-5.5','messages'=>[]]);
check('fallback: switched off is one attempt and the original error',
    is_wp_error($fb_out) && count($GLOBALS['http_calls'])===1 && !str_contains($fb_out->get_error_message(),'backup'));
update_option('sgap_openrouter_model','anthropic/claude-sonnet-4.5');

// The key test proves the backup pair, never the primary's model.
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(200,['choices'=>[['message'=>['content'=>''],'finish_reason'=>'length']]])];
$fb_check=SGAP_KeyCheck::check_fallback();
$fb_call=$GLOBALS['http_calls'][0]??[];
$fb_body=json_decode((string)($fb_call[1]['body']??''),true);
check('fallback: the key test asks OpenRouter with the backup key and the backup model',
    ($fb_check['ok']??null)===true && ($fb_call[0]??'')==='https://openrouter.ai/api/v1/chat/completions'
    && ($fb_call[1]['headers']['Authorization']??'')==='Bearer sk-or-backup'
    && ($fb_body['model']??'')==='anthropic/claude-sonnet-4.5');
check('fallback: the key test says the backup is off when the model is empty',
    (function () { update_option('sgap_openrouter_model',''); $r=SGAP_KeyCheck::check_fallback();
        update_option('sgap_openrouter_model','anthropic/claude-sonnet-4.5');
        return ($r['ok']??null)===false && str_contains($r['message'],'backup is off'); })());
check('fallback: the key test is silent when no backup key is saved',
    (function () { $k=get_option('sgap_openrouter_api_key'); update_option('sgap_openrouter_api_key','');
        $r=SGAP_KeyCheck::check_fallback(); update_option('sgap_openrouter_api_key',$k);
        return ($r['ok']??null)===null && $GLOBALS['http_calls']!==[]; })());

// Settings, list and save path.
check('fallback: the key is a secret and the model a plain setting',
    in_array('openrouter_api_key',SGAP_Settings::SECRET_KEYS,true)
    && in_array('openrouter_model',SGAP_Settings::TEXT_KEYS,true)
    && isset(SGAP_Settings::HELP['openrouter_api_key'],SGAP_Settings::HELP['openrouter_model'])
    && in_array('openrouter_model',SGAP_Settings::SECTIONS['AI content'],true));
check('fallback: the backup key has its own card, with the model beside it',
    (SGAP_Keys::PROVIDERS['openrouter_backup']['keys']??[])===['openrouter_api_key']
    && in_array('openrouter_model',SGAP_Keys::PROVIDERS['openrouter_backup']['extra']??[],true)
    && in_array('sgap_ai_catalog_openrouter_backup',SGAP_Keys::CACHE_MAP['openrouter_api_key']??[],true));
$GLOBALS['filters']['sgap_ai_model_catalog']=function($list,$provider){
    return $provider==='openrouter_backup'
        ? ['anthropic/claude-sonnet-4.5','openai/gpt-5','anthropic/claude-sonnet-4.5']
        : ['anthropic/claude-opus-5.5'];
};
check('fallback: the model list comes from OpenRouter, not from the chosen provider',
    SGAP_AI::catalog(false,'openrouter_backup')===['anthropic/claude-sonnet-4.5','openai/gpt-5']
    && SGAP_AI::catalog()===['anthropic/claude-opus-5.5']);
$fb_field=SGAP_Setup::backup_model_field('sgap_openrouter_model');
check('fallback: the field offers "off" first and groups the rest',
    str_contains($fb_field,'<select id="sgap_openrouter_model"') && str_contains($fb_field,'>Off —')
    && str_contains($fb_field,'<optgroup label="anthropic">'));
$fb_san=$GLOBALS['settings']['sgap_openrouter_model']['sanitize_callback']??null;
check('fallback: the save path keeps empty as off and refuses an unlisted model',
    is_callable($fb_san) && $fb_san('')==='' && $fb_san('openai/gpt-5')==='openai/gpt-5'
    && $fb_san('nope/nope')==='anthropic/claude-sonnet-4.5'
    && ($GLOBALS['settings_errors'][count($GLOBALS['settings_errors'])-1][1]??'')==='sgap_backup_model_unknown');
unset($GLOBALS['filters']['sgap_ai_model_catalog']);
ob_start(); do_action('page_sgap-settings'); $fb_html=ob_get_clean();
check('fallback: the keys tab renders the backup key and its model once each',
    substr_count($fb_html,'name="sgap_openrouter_api_key"')===1
    && substr_count($fb_html,'name="sgap_openrouter_model"')===1
    && str_contains($fb_html,'OpenRouter (backup)'));

// Put the settings back the way the rest of the suite expects them.
foreach ($fb_keep as $k=>$v) { if ($v===null) { unset($GLOBALS['opts'][$k]); } else { $GLOBALS['opts'][$k]=$v; } }
if ($fb_log_before === []) { delete_option('sgap_error_log'); } else { update_option('sgap_error_log',$fb_log_before,false); }
$GLOBALS['http_queue']=[]; $GLOBALS['http_calls']=[];


/* ---------------- P1.25: the key test names the real problem ---------------- */
// Measured 10/11: OpenRouter answered 401 "Missing Authentication header" for a value that was not
// an OpenRouter key at all. Both cases are pinned here, so the message can never again say
// "refused" when the truth is "that is not the key" or "the header never arrived".
update_option('sgap_ai_provider','openrouter');
update_option('sgap_ai_api_key',"  sk-or-v1-abcdef\n");
check('key diagnosis: a pasted key is trimmed before it becomes a header',
    SGAP_AI::api_key()==='sk-or-v1-abcdef' && SGAP_AI::api_key()===trim((string) get_option('sgap_ai_api_key')));
update_option('sgap_ai_api_key','badb337-aimlapi-lookalike');
$GLOBALS['http_calls']=[];
$kd=SGAP_KeyCheck::check_ai();
check('key diagnosis: a value without sk-or- is named before any request is spent',
    $kd['ok']===false && str_contains($kd['message'],'not an OpenRouter key')
    && str_contains($kd['message'],'characters') && count($GLOBALS['http_calls'])===0);
update_option('sgap_ai_api_key','sk-or-v1-abc123');
update_option('sgap_ai_model','anthropic/claude-sonnet-4.5');
$GLOBALS['http_queue'][]=sgap_http(401,['error'=>['message'=>'Missing Authentication header']]);
$kd=SGAP_KeyCheck::check_ai();
check('key diagnosis: "missing authentication header" is reported as a header problem, not a refusal',
    $kd['ok']===false && str_contains($kd['message'],'Authorization header')
    && !str_contains($kd['message'],'refused the key'));
$GLOBALS['http_queue'][]=sgap_http(200,['choices'=>[['message'=>['content'=>'pong']]]]);
$kd=SGAP_KeyCheck::check_ai();
check('key diagnosis: a well-shaped key still proves itself with one token', $kd['ok']===true
    && str_contains($kd['message'],'OpenRouter key works'));
update_option('sgap_openrouter_api_key','not-a-key-either');
update_option('sgap_openrouter_model','anthropic/claude-sonnet-4.5');
$kd=SGAP_KeyCheck::check_fallback();
check('key diagnosis: the backup key is shape-checked the same way',
    $kd['ok']===false && str_contains($kd['message'],'not an OpenRouter key'));
update_option('sgap_ai_provider','aimlapi');
update_option('sgap_ai_api_key','PRIMARY-KEY');
update_option('sgap_openrouter_api_key','sk-or-backup');

// 7.70.1 — the outgoing request itself, not a sentence about it. `http_calls` holds the arguments
// the plugin hands to WordPress' HTTP layer, so this is the request that leaves the site. A key
// is never printed: each check looks at a header or a length, and the message is searched for the
// fixture value to prove it does not leak.
$auth_of = static function (array $args): string {
    foreach ((array) ($args['headers'] ?? []) as $name => $value) {
        if (strcasecmp((string) $name, 'Authorization') === 0) {
            return (string) $value;
        }
    }
    return '';
};
check('key request: whitespace, a line break and invisible characters are removed from the token',
    SGAP_AI::bearer(" sk-or-v1-a\tb\r\nc\u{00A0}d\u{200B}e ") === 'sk-or-v1-abcde');
update_option('sgap_ai_provider', 'openrouter');
update_option('sgap_ai_model', 'anthropic/claude-sonnet-4.5');
update_option('sgap_ai_api_key', "sk-or-v1-abc\u{200B}123");
$GLOBALS['http_calls'] = []; $GLOBALS['http_queue'] = [sgap_http(200, ['choices' => [['message' => ['content' => 'pong']]]])];
$kd = SGAP_KeyCheck::check_ai();
$sent = $GLOBALS['http_calls'][0][1] ?? [];
check('key request: one POST to the fixed OpenRouter endpoint', count($GLOBALS['http_calls']) === 1
    && ($GLOBALS['http_calls'][0][0] ?? '') === 'https://openrouter.ai/api/v1/chat/completions');
check('key request: the request carries Authorization: Bearer with the saved key',
    $auth_of($sent) === 'Bearer sk-or-v1-abc123');
check('key request: the header value is one line of token characters only',
    (bool) preg_match('/^Bearer [A-Za-z0-9._\-]+$/', $auth_of($sent)));
check('key request: the test passes on that request and never echoes the key',
    ($kd['ok'] ?? null) === true && !str_contains((string) $kd['message'], 'sk-or-v1-abc123'));
check('key request: nothing filters the header away on a healthy site',
    SGAP_AI::stripped_by('sk-or-v1-x') === ['stripped' => false, 'filters' => []]);
// The production writers go through the same builder: a test that proves a header the real call
// never sends would prove nothing.
$GLOBALS['http_calls'] = []; $GLOBALS['http_queue'] = [sgap_http(200, ['choices' => [['message' => ['content' => 'ok']]]])];
SGAP_AI::chat_completions('sk-or-v1 gen', ['model' => 'x'], 5);
check('key request: the generator sends the same cleaned header',
    $auth_of($GLOBALS['http_calls'][0][1] ?? []) === 'Bearer sk-or-v1gen');
$GLOBALS['http_calls'] = []; $GLOBALS['http_queue'] = [sgap_http(200, ['choices' => [['message' => ['content' => 'ok']]]])];
SGAP_AI::openrouter_post('sk-or-v1-parity', ['model' => 'x'], 5);
check('key request: the tested request is byte-for-byte the production one',
    $auth_of($GLOBALS['http_calls'][0][1] ?? []) === SGAP_AI::openrouter_headers('sk-or-v1-parity')['Authorization']
    && ($GLOBALS['http_calls'][0][1]['headers']['X-OpenRouter-Title'] ?? '') === 'Steamgears Autopilot');
// A security plugin that strips Authorization from every outgoing call is the other way the header
// disappears after it was built. The check must name that filter, not blame the key.
$GLOBALS['filters']['http_request_args'] = static function ($args, $url) {
    $args['headers'] = array_diff_key((array) ($args['headers'] ?? []), ['Authorization' => 1]);
    return $args;
};
check('key request: the stripping filter is detected', SGAP_AI::stripped_by('sk-or-v1-x')['stripped'] === true);
$GLOBALS['http_queue'] = [sgap_http(401, ['error' => ['message' => 'Missing Authentication header']])];
$kd = SGAP_KeyCheck::check_ai();
check('key diagnosis: a filter that removes Authorization is named as the cause',
    ($kd['ok'] ?? null) === false && str_contains((string) $kd['message'], 'http_request_args')
    && str_contains((string) $kd['message'], 'not the problem')
    && !str_contains((string) $kd['message'], 'sk-or-v1-abc123'));
unset($GLOBALS['filters']['http_request_args']);
// With no filter on the site the header is built and survives it, so the loss is outside the site.
$GLOBALS['http_queue'] = [sgap_http(401, ['error' => ['message' => 'Missing Authentication header']])];
$kd = SGAP_KeyCheck::check_ai();
check('key diagnosis: without a stripping filter the message points outside the site',
    ($kd['ok'] ?? null) === false && str_contains((string) $kd['message'], 'survived this site')
    && !str_contains((string) $kd['message'], 'sk-or-v1-abc123'));
update_option('sgap_ai_provider', 'aimlapi');
update_option('sgap_ai_api_key', 'PRIMARY-KEY');
update_option('sgap_openrouter_api_key', 'sk-or-backup');

require __DIR__.'/roi.php';
require __DIR__.'/pinterest.php';
require __DIR__.'/instagram.php';
require __DIR__.'/diagnostics.php';
require __DIR__.'/facebook.php';
require __DIR__.'/mcp-woo.php';
require __DIR__.'/mcp.php';
require __DIR__.'/evergreen.php';
require __DIR__.'/keys.php';
require __DIR__.'/channel-health.php';
require __DIR__.'/menu.php';
require __DIR__.'/assets.php';
require __DIR__.'/niche-shop.php';
require __DIR__.'/queue-page.php';
require __DIR__.'/forum.php';
require __DIR__.'/campaign-agent.php';
require __DIR__.'/feed-health.php';
require __DIR__.'/coach.php';
require __DIR__.'/cirla.php';
require __DIR__.'/cirla-admin.php';
require __DIR__.'/cc-login.php';
// --- 7.80.0: slukkede kanaler venter, token-loft pr. kanal, selvtjek ved tom AI-kørsel ---
$GLOBALS['opts']['sgap_channels']='facebook,bluesky'; update_option('sgap_channel_caps',''); $GLOBALS['rows']=[]; $wpdb->log=[];
SGAP_Queue::claim(5); $q=implode("\n",$wpdb->log);
check('7.80 claim: only enabled channels (plus reminders) are released', str_contains($q,"remind = 1 OR channel IN ('facebook','bluesky')"));
$GLOBALS['opts']['sgap_channels']=''; $wpdb->log=[]; SGAP_Queue::claim(5);
check('7.80 claim: no channel setting = no extra filter (nothing is frozen by accident)', !str_contains(implode("\n",$wpdb->log),'remind = 1 OR'));
$GLOBALS['opts']['sgap_channels']='facebook,bluesky'; $wpdb->log=[]; SGAP_Queue::claim(5,'pinterest');
check('7.80 claim: an explicit channel (REST pull) is not filtered', !str_contains(implode("\n",$wpdb->log),'remind = 1 OR'));
$GLOBALS['rows']=[['channel'=>'pinterest','n'=>4],['channel'=>'reddit','n'=>2]];
$pk=SGAP_Queue::parked();
check('7.80 parked: counts queued posts per switched-off channel', $pk===['pinterest'=>4,'reddit'=>2] && str_contains(end($wpdb->log),"NOT IN ('facebook','bluesky')"));
$GLOBALS['opts']['sgap_channels']=''; $wpdb->log=[];
check('7.80 parked: no channel setting = nothing parked, no query', SGAP_Queue::parked()===[] && $wpdb->log===[]);
$GLOBALS['rows']=[];
check('7.80 tokens: budget grows with channels and stays under the old ceiling', SGAP_AI::token_budget(1)===2500 && SGAP_AI::token_budget(3)===3600 && SGAP_AI::token_budget(10)===8000 && SGAP_AI::token_budget(0)===8000);
check('7.80 tokens: chat body uses the channel budget', SGAP_AI::chat_body('m','x',3)['max_tokens']===3600 && SGAP_AI::chat_body('m','x')['max_tokens']===8000);
$GLOBALS['opts']['sgap_channels']='facebook,bluesky';
update_option('sgap_last_generate',['at'=>'2026-11-12 06:00:00','sources'=>2,'drafts'=>0,'queued'=>0,'errors'=>['OpenRouter: Insufficient credits']]);
$GLOBALS['rows']=[['channel'=>'pinterest','n'=>3]];
$hp=implode("\n",SGAP_Auto::output_problems());
check('7.80 health: a run that wrote nothing is a problem with its error', str_contains($hp,'latest AI run (2026-11-12 06:00:00) wrote no drafts: OpenRouter: Insufficient credits'));
check('7.80 health: posts waiting for switched-off channels are named', str_contains($hp,'queued posts wait for channels that are switched off') && str_contains($hp,'pinterest 3'));
update_option('sgap_last_generate',['at'=>'2026-11-12 06:00:00','sources'=>2,'drafts'=>4,'queued'=>4,'errors'=>['one page failed']]); $GLOBALS['rows']=[];
$hp=implode("\n",SGAP_Auto::output_problems());
check('7.80 health: a run that wrote drafts is not flagged, nothing parked', !str_contains($hp,'latest AI run') && !str_contains($hp,'switched off'));
delete_option('sgap_last_generate'); $GLOBALS['rows']=[]; $wpdb->log=[];
// --- 7.81.0: gratis OpenRouter-model når kreditten slipper op ---
update_option('sgap_ai_provider','openrouter'); update_option('sgap_ai_model','anthropic/claude-haiku-5.5'); update_option('sgap_ai_api_key','sk-or-v1-fixture');
$credit=new WP_Error('sgap_ai_http','OpenRouter: This request requires more credits, or fewer max_tokens. You requested up to 3600 tokens, but can only afford 405.',['status'=>402]);
check('7.81 free: a credit refusal on a paid model asks for the free retry', SGAP_AI::wants_free_retry('anthropic/claude-haiku-5.5',$credit));
check('7.81 free: a free model is never retried on itself', !SGAP_AI::wants_free_retry('openrouter/free',$credit) && !SGAP_AI::wants_free_retry('x/y:free',$credit));
check('7.81 free: other errors (401, 404) do not switch model', !SGAP_AI::wants_free_retry('a/b',new WP_Error('sgap_ai_http','OpenRouter: User not found.',['status'=>401])) && !SGAP_AI::wants_free_retry('a/b',new WP_Error('sgap_ai_http','OpenRouter: No endpoints',['status'=>404])));
$ok_posts=['choices'=>[['message'=>['content'=>json_encode(['posts'=>[['channel'=>'facebook','title'=>'Brass','text'=>'Brass goggles for every costume.','hashtags'=>['steampunk'],'alt_text'=>'goggles']]])]]],'model'=>'nvidia/nemotron-3-ultra-550b-a55b:free'];
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(402,['error'=>['message'=>'This request requires more credits, or fewer max_tokens.']]), sgap_http(200,$ok_posts)];
$paidbody=SGAP_AI::chat_body('anthropic/claude-haiku-5.5','x',1);
$got=SGAP_AI::chat_completions('sk-or-v1-fixture',$paidbody,5);
$got=is_wp_error($got)&&SGAP_AI::wants_free_retry('anthropic/claude-haiku-5.5',$got)?SGAP_AI::free_retry('sk-or-v1-fixture',$paidbody,$got):$got;
$second=json_decode((string)($GLOBALS['http_calls'][1][1]['body']??'{}'),true);
check('7.81 free: the retry asks the free list in order and keeps the token budget', is_array($got) && ($second['model']??'')==='nvidia/nemotron-3-ultra-550b-a55b:free' && ($second['models']??[])===SGAP_AI::FREE_MODELS && ($second['max_tokens']??0)===2500);
check('7.81 free: the switch is recorded with the model that answered', (SGAP_AI::last_fallback()['model']??'')==='nvidia/nemotron-3-ultra-550b-a55b:free' && str_starts_with((string)(SGAP_AI::last_fallback()['reason']??''),'no credit'));
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[sgap_http(429,['error'=>['message'=>'Rate limit exceeded: free-models-per-day']])];
$both=SGAP_AI::free_retry('sk-or-v1-fixture',$paidbody,$credit);
check('7.81 free: when the free models fail too, both reasons are named', is_wp_error($both) && str_contains($both->get_error_message(),'can only afford 405') && str_contains($both->get_error_message(),'free-models-per-day'));
$GLOBALS['filters']['sgap_free_models']=fn($v)=>[];
check('7.81 free: an empty filter list switches the retry off', !SGAP_AI::wants_free_retry('a/b',$credit));
unset($GLOBALS['filters']['sgap_free_models']);
update_option('sgap_ai_provider','anthropic');
check('7.81 free: only on OpenRouter', !SGAP_AI::wants_free_retry('a/b',$credit));
$GLOBALS['opts']['sgap_channels']='facebook,bluesky'; $GLOBALS['rows']=[];
update_option('sgap_last_generate',['at'=>'2026-11-13 06:00:00','sources'=>2,'drafts'=>4,'queued'=>4,'errors'=>[],'free'=>2,'free_model'=>'nvidia/nemotron-3-ultra-550b-a55b:free']);
check('7.81 health: pages written by the free model are named', str_contains(implode("\n",SGAP_Auto::output_problems()),'2 pages were written by the free model nvidia/nemotron-3-ultra-550b-a55b:free'));
delete_option('sgap_last_generate'); $GLOBALS['http_queue']=[]; $GLOBALS['http_calls']=[];
update_option('sgap_ai_provider','anthropic'); update_option('sgap_ai_model',''); update_option('sgap_ai_api_key','');
// --- 7.82.0: prioriteter fra Command Center ---
if (!function_exists('rest_ensure_response')) { function rest_ensure_response($d){ return new WP_REST_Response($d); } }
$GLOBALS['postid_for']=['https://steamgears.eu/goggles/'=>501,'https://steamgears.eu/draft/'=>502,'https://steamgears.eu/hat/'=>503];
$GLOBALS['poststatus'][502]='draft';
$pg=fn($id,$t)=>new WP_Post(['ID'=>$id,'post_title'=>$t,'post_content'=>"About $t.",'post_status'=>'publish']);
$GLOBALS['post_by_id'][501]=$pg(501,'Brass goggles'); $GLOBALS['post_by_id'][503]=$pg(503,'Top hat'); $GLOBALS['post_by_id'][504]=$pg(504,'Old guide');
delete_option('sgap_cc_priorities'); unset($GLOBALS['postmeta'][501],$GLOBALS['postmeta'][503],$GLOBALS['postmeta'][504]);
$sv=SGAP_Priorities::save([
  ['url'=>'https://steamgears.eu/goggles/','keyword'=>'steampunk goggles','position'=>7,'reason'=>'striking'],
  ['url'=>'https://steamgears.eu/draft/','keyword'=>'x'],
  ['url'=>'https://steamgears.eu/nope/','keyword'=>'y'],
  ['url'=>'https://steamgears.eu/goggles/','keyword'=>'dup'],
  ['post_id'=>503,'keyword'=>'<b>victorian top hat</b>','position'=>500,'reason'=>'New Article!'],
  'garbage']);
check('7.82 priorities: published pages are saved, the rest skipped with a reason', $sv['saved']===2
  && array_column($sv['skipped'],'reason')===['not a published post, page or product','unknown page','duplicate']);
$it=SGAP_Priorities::items();
check('7.82 priorities: fields are cleaned (tags, out-of-range position, reason key)', $it[1]['keyword']==='victorian top hat' && $it[1]['position']===0 && $it[1]['reason']==='newarticle' && $it[0]['position']===7);
check('7.82 priorities: keyword_for finds the phrase', SGAP_Priorities::keyword_for(501)==='steampunk goggles' && SGAP_Priorities::keyword_for(999)==='');
$GLOBALS['posts_result']=[$GLOBALS['post_by_id'][504],$GLOBALS['post_by_id'][503],$GLOBALS['post_by_id'][501]];
$picked=array_map(fn($p)=>$p->ID,SGAP_Generator::pick(3));
check('7.82 pick: priority pages come first in Command Center order, then the rest, no duplicates', $picked===[501,503,504]);
check('7.82 pick: the count still caps the day', array_map(fn($p)=>$p->ID,SGAP_Generator::pick(1))===[501]);
$GLOBALS['postmeta'][501][SGAP_Generator::META]=time()-2*86400;
check('7.82 pick: a priority page promoted 2 days ago waits (7-day cooldown)', !in_array(501,array_map(fn($p)=>$p->ID,SGAP_Priorities::due()),true));
unset($GLOBALS['postmeta'][501]);
$src=SGAP_Generator::source($GLOBALS['post_by_id'][501]);
check('7.82 source: the measured search phrase travels with the page', ($src['keyword']??'')==='steampunk goggles' && !isset(SGAP_Generator::source($GLOBALS['post_by_id'][504])['keyword']));
$pr=SGAP_AI::prompt($src,['facebook']);
check('7.82 prompt: the phrase is in the prompt with a no-stuffing rule', str_contains($pr,'"steampunk goggles"') && str_contains($pr,'do not stuff it'));
check('7.82 prompt: no phrase line for a page without one', !str_contains(SGAP_AI::prompt(SGAP_Generator::source($GLOBALS['post_by_id'][504]),['facebook']),'Search phrase'));
$st=SGAP_Priorities::status();
check('7.82 status: snapshot shows active and due', $st['active']===2 && $st['due']===2 && $st['received_at']!==null);
update_option('sgap_cc_priorities',['at'=>time()-15*86400,'items'=>$it]);
check('7.82 priorities: a list older than 14 days is ignored', SGAP_Priorities::items()===[] && SGAP_Priorities::due()===[]);
$bad=SGAP_Priorities::rest_save(new WP_REST_Request(['items'=>'x']));
check('7.82 rest: a body without items is a 400', $bad instanceof WP_Error && ($bad->get_error_data()['status']??0)===400);
$ok=SGAP_Priorities::rest_save(new WP_REST_Request(['items'=>[['url'=>'https://steamgears.eu/hat/','keyword'=>'top hat']]]));
check('7.82 rest: saving answers with counts and status', $ok instanceof WP_REST_Response && $ok->data['saved']===1 && $ok->data['status']['active']===1);
delete_option('sgap_cc_priorities'); $GLOBALS['posts_result']=[]; unset($GLOBALS['postid_for'],$GLOBALS['poststatus'][502]);
// --- 7.83.0: AI-redaktør på sociale opslag ---
update_option('sgap_ai_provider','openrouter'); update_option('sgap_ai_api_key','sk-or-v1-editor'); update_option('sgap_ai_model','anthropic/claude-haiku-5.5');
$GLOBALS['opts']['sgap_channels']='facebook,instagram';
check('7.83 editor: on with an OpenRouter key, free models only', SGAP_Editor::enabled() && !array_filter(SGAP_Editor::models(), fn($m)=>!str_ends_with($m,':free') && $m!=='openrouter/free'));
$ed_posts=[['channel'=>'facebook','title'=>'Brass','text'=>'Brass goggles for your next costume.','hashtags'=>['#steampunk']],['channel'=>'instagram','title'=>'Brass','text'=>'Get 50% off today only!','hashtags'=>[]]];
$ed_src=['title'=>'Brass goggles','summary'=>'Handmade brass goggles with leather strap.'];
$pr=SGAP_Editor::prompt($ed_posts,$ed_src);
check('7.83 editor: the prompt holds the page and every post, and forbids invented offers', str_contains($pr,'Handmade brass goggles') && str_contains($pr,'50% off') && str_contains($pr,'NOT in the page'));
$ans=fn($reviews)=>sgap_http(200,['model'=>'nvidia/nemotron-3-ultra-550b-a55b:free','choices'=>[['message'=>['content'=>"```json\n".json_encode(['reviews'=>$reviews])."\n```"]]]]);
$GLOBALS['http_calls']=[]; $GLOBALS['http_queue']=[$ans([['channel'=>'facebook','score'=>8,'problems'=>[]],['channel'=>'instagram','score'=>3,'problems'=>['invented 50% discount']],['channel'=>'x','score'=>99]])];
$rv=SGAP_Editor::review($ed_posts,$ed_src);
$sent=json_decode((string)($GLOBALS['http_calls'][0][1]['body']??'{}'),true);
check('7.83 editor: one request for all posts, to the free model list', count($GLOBALS['http_calls'])===1 && ($sent['models']??[])===SGAP_Editor::models() && ($sent['max_tokens']??0)===4000 && str_contains($GLOBALS['http_calls'][0][0],'openrouter.ai'));
check('7.83 editor: scores are read (also inside ```), out-of-range ones dropped', $rv['ok'] && $rv['by_channel']['facebook']['score']===8.0 && !isset($rv['by_channel']['x']));
$v1=SGAP_Editor::verdict($rv,'facebook'); $v2=SGAP_Editor::verdict($rv,'instagram'); $v3=SGAP_Editor::verdict($rv,'pinterest');
check('7.83 editor: 8/10 approves, 3/10 rejects with the reason, a missing channel waits', $v1['action']==='approve' && $v2['action']==='reject' && str_contains($v2['note'],'invented 50% discount') && $v3['action']==='hold');
$GLOBALS['http_queue']=[sgap_http(429,['error'=>['message'=>'Rate limit exceeded: free-models-per-day']])];
$down=SGAP_Editor::review($ed_posts,$ed_src);
check('7.83 editor: when no editor answers, posts wait (never approved by default)', !$down['ok'] && SGAP_Editor::verdict($down,'facebook')['action']==='hold' && str_contains(SGAP_Editor::verdict($down,'facebook')['note'],'waits for you'));
$GLOBALS['http_queue']=[sgap_http(200,['choices'=>[['message'=>['content'=>'Looks great!']]]])];
check('7.83 editor: an answer without JSON is not a pass', !SGAP_Editor::review($ed_posts,$ed_src)['ok']);
// hele vejen gennem write_for i Full auto
$gen=sgap_http(200,['choices'=>[['message'=>['content'=>json_encode(['posts'=>$ed_posts])]]]]);
$ed_page=new WP_Post(['ID'=>777,'post_title'=>'Brass goggles','post_content'=>'Handmade brass goggles with leather strap.','post_type'=>'post']);
$GLOBALS['http_queue']=[$gen,$ans([['channel'=>'facebook','score'=>9,'problems'=>[]],['channel'=>'instagram','score'=>4,'problems'=>['invented discount']]])];
$wpdb->log=[]; $rep=['sources'=>0,'drafts'=>0,'queued'=>0,'errors'=>[]];
SGAP_Generator::write_for($ed_page,['facebook','instagram'],true,$rep);
$ins=array_values(array_filter($wpdb->log,fn($l)=>str_starts_with($l,'INSERT wp_sgap_ai_drafts')));
check('7.83 write_for: the approved post is a pending draft headed for the queue, the rejected one is stored as rejected', count($ins)===2
  && str_contains($ins[0],'"status":"pending"') && str_contains($ins[0],'Editor: 9\/10') && str_contains($ins[1],'"status":"rejected"') && str_contains($ins[1],'invented discount'));
check('7.83 write_for: the report counts the rejection, and only the approved post is tried for the queue', ($rep['rejected']??0)===1 && $rep['drafts']===1
  && count(array_filter($wpdb->log,fn($l)=>str_contains($l,'sgap_ai_drafts WHERE id')))===1);
$GLOBALS['http_queue']=[$gen,sgap_http(503,['error'=>['message'=>'busy']])];
$wpdb->log=[]; $rep=['sources'=>0,'drafts'=>0,'queued'=>0,'errors'=>[]];
SGAP_Generator::write_for($ed_page,['facebook','instagram'],true,$rep);
check('7.83 write_for: editor down = both wait as pending drafts, nothing is queued even in Full auto', ($rep['held']??0)===2 && $rep['queued']===0
  && count(array_filter($wpdb->log,fn($l)=>str_contains($l,'sgap_ai_drafts WHERE id')))===0);
update_option('sgap_last_generate',['at'=>'2026-11-13 06:00:00','drafts'=>1,'errors'=>[],'rejected'=>1,'held'=>2]);
check('7.83 health: rejected and waiting posts are named in the self-check', str_contains(implode("\n",SGAP_Auto::output_problems()),'the editor rejected 1 posts and 2 wait for you'));
delete_option('sgap_last_generate'); unset($GLOBALS['postmeta'][777]);
update_option('sgap_ai_provider','anthropic'); update_option('sgap_ai_api_key',''); update_option('sgap_ai_model',''); $GLOBALS['http_queue']=[]; $GLOBALS['http_calls']=[];
$ed_bk=get_option('sgap_openrouter_api_key'); update_option('sgap_openrouter_api_key','');
$GLOBALS['filters']['sgap_editor_models']=fn($v)=>['a:free','b:free','c:free','d:free']; $GLOBALS['filters']['sgap_free_models']=fn($v)=>['a:free','b:free','c:free','d:free'];
check('7.83.1: never more than 3 models in a request (OpenRouter refuses more)', count(SGAP_Editor::models())===3 && count(SGAP_AI::free_models())===3);
unset($GLOBALS['filters']['sgap_editor_models'],$GLOBALS['filters']['sgap_free_models']);
check('7.83 editor: off without an OpenRouter key (Anthropic users keep the old flow)', !SGAP_Editor::enabled());
update_option('sgap_openrouter_api_key',$ed_bk);
// P3.2/P3.5-slut
exit(empty($GLOBALS['fail'])?0:1);
