<?php
/**
 * Genererer JSON-fixtures til Command Center-klientens tests ud fra den rigtige PHP-kode.
 *
 *   php android/core/src/test/fixtures/generate-fixtures.php snapshot > android/core/src/test/resources/snapshot.json
 *   php android/core/src/test/fixtures/generate-fixtures.php drafts   > android/core/src/test/resources/drafts.json
 *
 * Pointen er at DTO'erne testes mod det pluginnet faktisk sender: WordPress'
 * $wpdb giver tal som strings ("id":"12"), boolske felter som rigtige boolske
 * og null for tomme datoer. Skrives fixtures i hånden, holder de ikke.
 *
 * Ingen WordPress eller database: funktionerne herunder er stubbe, og $wpdb
 * svarer ud fra hvilken forespørgsel der stilles.
 */
define('ABSPATH', __DIR__.'/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60); define('ARRAY_A','ARRAY_A');
$GLOBALS['actions']=[];
function add_action($h,$cb){ $GLOBALS['actions'][$h][]=$cb; } function add_filter($h,$cb){ $GLOBALS['actions'][$h][]=$cb; }
function wp_register_ability($n,$a){} function wp_register_ability_category(...$a){}
function apply_filters($h,$v){return $v;}
function do_action($h){ foreach($GLOBALS['actions'][$h]??[] as $cb) call_user_func($cb); }
function register_activation_hook(...$a){} function register_deactivation_hook(...$a){}
function is_admin(){return true;} function get_option($k,$d=false){ return $GLOBALS['opts'][$k] ?? $d; }
function update_option($k,$v){$GLOBALS['opts'][$k]=$v;} function add_option($k,$v){$GLOBALS['opts'][$k]??=$v;}
function register_setting($g,$k,$a){} function wp_clear_scheduled_hook($h){}
function add_menu_page($t,$m,$c,$slug,$cb){} function add_submenu_page($p,$t,$m,$c,$slug,$cb){}
function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function esc_url($s){return $s;}
function get_transient($k){return false;} function set_transient(...$a){} function delete_transient($k){}
function admin_url($p=''){return '/wp-admin/'.$p;} function rest_url($p=''){return '/wp-json/'.$p;} function home_url($p=''){return 'https://steamgears.eu'.$p;}
function get_bloginfo($k=''){return 'Steamgears';} function wp_timezone_string(){return 'Europe/Copenhagen';}
function get_the_title($id){return $id===108 ? 'Steampunk Brass Goggles' : 'Steampunk Corset';}
function get_permalink($id){return 'https://steamgears.eu/product/'.$id.'/';} function has_post_thumbnail($id){return $id===108;}
function get_posts($a=[]){return [];} function post_type_exists($t){return in_array($t,['post','page','product'],true);} function get_post($id){return null;}
function get_user_meta($u,$k,$s=false){return '';} function update_user_meta($u,$k,$v){return true;}
function wp_nonce_field(...$a){return '';} function wp_create_nonce($a=''){return 'n';} function selected($a,$b,$e){return '';} function checked($a,$b=true,$e=true){return '';}
function sanitize_text_field($s){return trim(strip_tags((string)$s));} function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));} function absint($v){return abs((int)$v);}
function esc_url_raw($u,$p=null){return preg_match('#^https?://#',$u)?$u:'';} function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
function wp_parse_args($a,$d){return array_merge($d,$a);} function add_query_arg($a,$u){return $u.(str_contains($u,'?')?'&':'?').http_build_query($a,'','&',PHP_QUERY_RFC3986);}
function current_time($t){return '2026-11-02 21:00:00';} function wp_date($f,$t=null){return date($f,$t??time());}
function wp_upload_dir($t=null){return ['basedir'=>sys_get_temp_dir()];}
function current_user_can($c){return true;} function get_current_user_id(){return 1;}
function get_site_transient($k){return false;} function set_site_transient(...$a){} function wp_remote_get(...$a){return new WP_Error('offline','offline');}
function delete_option($k){unset($GLOBALS['opts'][$k]);} function wp_json_encode($v){return json_encode($v);} function is_wp_error($v){return $v instanceof WP_Error;}
function wp_next_scheduled($h){return false;} function wp_schedule_event(...$a){} function flush_rewrite_rules($h=true){} function add_rewrite_rule(...$a){}
function wp_set_option_autoload_values($a){}
class WP_Error{ function __construct(public $code,public $msg,public $data=[]){} function get_error_code(){return $this->code;} function get_error_message(){return $this->msg;} }
class WP_REST_Response{ function __construct(public $data,public $status=200){} }
class WP_REST_Request implements ArrayAccess{ function __construct(public array $p){} function get_param($k){return $this->p[$k]??null;}
 function offsetGet($k):mixed{return $this->p[$k]??null;} function offsetExists($k):bool{return isset($this->p[$k]);} function offsetSet($k,$v):void{} function offsetUnset($k):void{} }

$queue_rows = [
    ['id'=>'12','post_id'=>'108','channel'=>'pinterest','angle'=>'angle_a','content_type'=>'product','format'=>'pin',
     'destination_url'=>'https://steamgears.eu/product/goggles/','title'=>'Brass goggles, hand riveted',
     'body'=>'Look sharp.','image_url'=>'https://steamgears.eu/wp-content/uploads/goggles.jpg','status'=>'sent','attempts'=>'1',
     'scheduled_at'=>'2026-11-02 09:00:00','sent_at'=>'2026-11-02 09:01:00','published_at'=>'2026-11-02 09:02:00','error'=>''],
    ['id'=>'11','post_id'=>'104','channel'=>'facebook','angle'=>'angle_b','content_type'=>'product','format'=>'post',
     'destination_url'=>'https://steamgears.eu/product/corset/','title'=>'Corset with real whalebone',
     'body'=>'Built to last.','image_url'=>'https://steamgears.eu/wp-content/uploads/corset.jpg','status'=>'failed','attempts'=>'3',
     'scheduled_at'=>'2026-11-01 18:00:00','sent_at'=>null,'published_at'=>null,'error'=>'publer: token expired'],
];
$draft_rows = [
    ['id'=>'31','post_id'=>'108','channel'=>'instagram','angle'=>'angle_a','content_type'=>'product','format'=>'pin',
     'title'=>'Goggles that actually fit','body'=>'Hand riveted brass.','hashtags'=>'#steampunk #cosplay','alt_text'=>'Brass goggles on a bench','cta'=>'See the pair',
     'status'=>'approved','ai_model'=>'claude-opus-5.5','created_at'=>'2026-11-02 08:00:00'],
    ['id'=>'30','post_id'=>'0','channel'=>'facebook','angle'=>'angle_c','content_type'=>'blog','format'=>'post',
     'title'=>'Why brass ages well','body'=>'Patina is a feature.','hashtags'=>'','alt_text'=>'','cta'=>'',
     'status'=>'pending','ai_model'=>'claude-opus-5.5','created_at'=>'2026-11-01 08:00:00'],
];
class W {
    public $prefix='wp_'; public $posts='wp_posts'; public $postmeta='wp_postmeta'; public $insert_id=0; public $next=null; public $log=[];
    function get_charset_collate(){return '';}
    function prepare($q,...$a){ if(count($a)==1&&is_array($a[0]))$a=$a[0];
        return vsprintf(str_replace(['%s','%d','%f'],["'%s'",'%d','%F'],$q),$a); }
    function esc_like($s){return $s;}
    function get_var($q){ $this->log[]=$q;
        if (str_contains($q,'SHOW TABLES LIKE')) { preg_match("/LIKE '([^']+)'/",$q,$m); return $m[1] ?? ''; }
        if (str_contains($q,'SUM(value)')) return '1234.56';
        if (str_contains($q,'COUNT(*)')) return '2';
        return null; }
    function get_row($q){ $this->log[]=$q; return null; }
    function query($q){ $this->log[]=$q; return 0; }
    function insert($t,$d){ return 1; } function update($t,$d,$w){ return 1; }
    function get_results($q,$o=null){ global $queue_rows,$draft_rows; $this->log[]=$q;
        if (str_contains($q,'FROM wp_sgap_queue ORDER BY id DESC')) { $rows=[]; foreach($queue_rows as $r){ $r['tracking_url']='https://steamgears.eu/go/'.$r['id'].'/'; $rows[]=$r; } return $rows; }
        // post_id bliver i rækken: snapshot() læser det og fjerner det selv.
        if (str_contains($q,'FROM wp_sgap_ai_drafts ORDER BY id DESC')) return $draft_rows;
        if (str_contains($q,"SUM(event_type = 'publish')")) return [
            ['channel'=>'pinterest','published'=>'42','clicks'=>'310','orders'=>'7','revenue'=>'612.40'],
            ['channel'=>'facebook','published'=>'18','clicks'=>'96','orders'=>'2','revenue'=>'148.00'],
        ];
        if (str_contains($q,'GROUP BY day, channel, event_type')) return [
            ['day'=>'2026-11-01','channel'=>'pinterest','event_type'=>'publish','count'=>'6','value'=>'0'],
            ['day'=>'2026-11-01','channel'=>'pinterest','event_type'=>'click','count'=>'41','value'=>'0'],
            ['day'=>'2026-11-01','channel'=>'pinterest','event_type'=>'order','count'=>'1','value'=>'89.00'],
        ];
        if (str_contains($q,'FROM wp_sgap_cro')) return [
            ['order_id'=>'8891','revenue'=>'89.00','clicked_at'=>'2026-11-01 10:00:00','converted_at'=>'2026-11-01 10:14:00','channel'=>'pinterest','angle'=>'angle_a','title'=>'Brass goggles, hand riveted'],
        ];
        if (str_contains($q,'FROM wp_sgap_learning')) return [
            ['dimension'=>'channel','dimension_value'=>'pinterest','clicks'=>'310','orders'=>'7','revenue'=>'612.40','cost'=>'0.00','updated_at'=>'2026-11-02 06:00:00'],
        ];
        if (str_contains($q,'FROM wp_sgap_suppression')) return [];
        if (str_contains($q,'FROM wp_sgap_reposts')) return [];
        if (str_contains($q,'FROM wp_sgap_etsy_orders')) return [];
        // /stats bruger "COUNT(*) AS count", snapshot bruger "COUNT(*) AS n".
        if (str_contains($q,'FROM wp_sgap_queue GROUP BY status') && str_contains($q,'AS count')) return [['status'=>'sent','count'=>'11'],['status'=>'queued','count'=>'4'],['status'=>'failed','count'=>'1']];
        if (str_contains($q,'GROUP BY channel, event_type')) return [
            ['channel'=>'pinterest','event_type'=>'publish','count'=>'42','value'=>'0'],
            ['channel'=>'pinterest','event_type'=>'click','count'=>'310','value'=>'0'],
            ['channel'=>'pinterest','event_type'=>'order','count'=>'7','value'=>'612.40'],
        ];
        if (str_contains($q,'FROM wp_sgap_queue GROUP BY status')) return [['status'=>'sent','n'=>'11'],['status'=>'queued','n'=>'4'],['status'=>'failed','n'=>'1']];
        if (str_contains($q,'FROM wp_sgap_ai_drafts GROUP BY status')) return [['status'=>'approved','n'=>'3'],['status'=>'pending','n'=>'9']];
        if (str_contains($q,'FROM wp_sgap_ai_drafts WHERE status')) { $rows=[]; foreach($draft_rows as $r){ if($r['status']==='approved')$rows[]=$r; } return $rows; }
        return [];
    }
}
$wpdb=new W;
$GLOBALS['opts']=['sgap_autopilot_enabled'=>'yes','sgap_full_auto_enabled'=>'no','sgap_daily_cap'=>'8','sgap_batch_size'=>'5',
    'sgap_quiet_start'=>'23','sgap_quiet_end'=>'7','sgap_ai_api_key'=>'secret-value','sgap_ai_provider'=>'anthropic',
    'sgap_publer_api_key'=>'','sgap_monthly_budget'=>'1000'];
require __DIR__.'/../../../../../plugins/steamgears-autopilot/steamgears-autopilot.php';

$mode = $argv[1] ?? 'snapshot';
$flags = JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
if ($mode === 'snapshot') {
    echo json_encode(SGAP_REST::snapshot(new WP_REST_Request(['days'=>30,'limit'=>100])), $flags), "\n";
} elseif ($mode === 'drafts') {
    echo json_encode(SGAP_REST::list_drafts(new WP_REST_Request(['status'=>'approved','limit'=>50])), $flags), "\n";
} elseif ($mode === 'stats') {
    echo json_encode(SGAP_REST::stats(new WP_REST_Request(['days'=>30])), $flags), "\n";
} else {
    fwrite(STDERR, "usage: generate-fixtures.php snapshot|drafts|stats\n");
    exit(1);
}
