<?php
// Only run against a disposable local WordPress database. Never contact the Gelato API.
if (wp_get_environment_type() !== 'local' || !function_exists('wc_create_order')) throw new RuntimeException('A local WooCommerce test installation is required');
add_filter('pre_wp_mail', static function () { return true; });
update_option('sggf_settings',['mode'=>'live','api_key'=>'fake','shipment'=>'normal']);
update_option('sggf_print_dir','sgap-integration-files');
$dir=wp_upload_dir()['basedir'].'/sgap-integration-files'; wp_mkdir_p($dir);
file_put_contents($dir.'/'.SGAP_Gelato::file_name('SG-CC-01','5070'),'test');
$product=new WC_Product_Simple(); $product->set_name('Local test poster'); $product->set_sku('SG-CC-01-5070'); $product->set_regular_price('10'); $product->save();
$GLOBALS['test_calls']=0; $GLOBALS['test_http']='timeout';
add_filter('pre_http_request',function($pre,$args,$url) {
 if ($url!==SGAP_Gelato::API) return $pre;
 $GLOBALS['test_calls']++;
 if ($GLOBALS['test_http']==='timeout') return new WP_Error('http_request_failed','test timeout');
 if ($GLOBALS['test_http']==='429') return ['response'=>['code'=>429,'message'=>'Too Many Requests'],'headers'=>[],'body'=>'{}','cookies'=>[]];
 return ['response'=>['code'=>201,'message'=>'Created'],'headers'=>[],'body'=>'{"id":"local-gelato-order"}','cookies'=>[]];
},10,3);
function check_gelato($name,$condition) { if (!$condition) throw new RuntimeException($name); echo 'PASS '.$name."\n"; }
function new_test_order($product) { $o=wc_create_order(); $o->add_product($product,1); $o->set_billing_email('test@example.test'); $o->set_currency('DKK'); $o->save(); return $o; }
$o=new_test_order($product); $id=$o->get_id(); $o->update_status('processing'); $fresh=wc_get_order($id); $fresh->read_meta_data(true);
check_gelato('real WooCommerce order marks timeout as uncertain', $fresh->get_meta(SGAP_Gelato::META_UNCERTAIN)==='yes' && $GLOBALS['test_calls']===1);
SGAP_Gelato::retry($id); SGAP_Gelato::manual_send(wc_get_order($id));
check_gelato('unknown outcome blocks repeated sends', $GLOBALS['test_calls']===1);
SGAP_Gelato::confirm_absent(wc_get_order($id)); $GLOBALS['test_http']='ok'; SGAP_Gelato::manual_send(wc_get_order($id)); $fresh=wc_get_order($id); $fresh->read_meta_data(true);
check_gelato('confirmed absence permits one success and persists id', $GLOBALS['test_calls']===2 && $fresh->get_meta(SGAP_Gelato::META_ID)==='local-gelato-order' && !$fresh->get_meta(SGAP_Gelato::META_UNCERTAIN));
SGAP_Gelato::on_paid($id); check_gelato('persisted id prevents a duplicate', $GLOBALS['test_calls']===2);
$GLOBALS['test_http']='429'; $o2=new_test_order($product);$id2=$o2->get_id();$o2->update_status('processing');
check_gelato('rate limit schedules retry',wp_next_scheduled(SGAP_Gelato::RETRY_HOOK,[$id2])!==false);
$o2->update_status('cancelled'); $before=$GLOBALS['test_calls']; SGAP_Gelato::retry($id2);
check_gelato('cancelled WooCommerce order is not resent',$before===$GLOBALS['test_calls']);
