<?php
define('ABSPATH',__DIR__.'/'); define('HOUR_IN_SECONDS',3600);
class WP_Error { public $code; function __construct($code){$this->code=$code;} }
function esc_html__($v,$domain=''){return$v;} function __( $v, $domain='' ){return $v;} function sanitize_textarea_field($v){return trim(strip_tags($v));} function sanitize_text_field($v){return trim(strip_tags($v));} function wp_unslash($v){return $v;} function wp_json_encode($v){return json_encode($v);} function get_current_user_id(){return 7;} function get_transient($key){return $GLOBALS['reviews'][$key]??false;} function delete_transient($key){unset($GLOBALS['reviews'][$key]);} function wp_die($message){throw new RuntimeException($message);}
class ContactPanda_Plugin { static function extension_contact_by_email($email){return $GLOBALS['contact']??false;} static function extension_value($r,$keys,$default=''){foreach($keys as$k)if(isset($r[$k]))return$r[$k];return$default;} }
require dirname(__DIR__).'/contactpanda/includes/class-contactpanda-import-review.php';
function current_user_can($cap){return 'manage_options'===$cap && ($GLOBALS['review_admin']??true);}
function check_admin_referer($action){if('contactpanda_csv_import'!==$action||false===($GLOBALS['review_nonce']??true))throw new RuntimeException('Invalid nonce');}
function expect($ok,$why){if(!$ok)throw new RuntimeException($why);}
$extra=ContactPanda_Import_Review::extra_tags(['tags'=>'Existing,VIP;vip'],['vip','New','New']);expect(strtolower($extra['tags'])==='existing,vip,new','Fixed import tags must merge with mapped columns without duplicates');
$fields=array_fill_keys(['email','full_name','tags','note_title','note_content'],true);
$m=ContactPanda_Import_Review::mapping(['tags'=>[2,3,2],'note_content'=>[4,5],'email'=>0],range(0,6),$fields);
expect($m['tags']===[2,3],'Repeated tag columns must remain unique');
expect(ContactPanda_Import_Review::mapping(['email'=>[99]],range(0,6),$fields) instanceof WP_Error,'Reject invalid mapping index');
expect(ContactPanda_Import_Review::mapping(['email'=>[[1]]],range(0,6),$fields) instanceof WP_Error,'Reject malformed mapping without notices');
$r=ContactPanda_Import_Review::record(['sample@example.invalid','Lena von Winter','VIP;Neu','vip,Neu','First note','Second note'],['email'=>[0],'full_name'=>[1],'tags'=>[2,3],'note_content'=>[4,5]]);
expect($r['first_name']==='Lena'&&$r['last_name']==='von Winter','Name split with particles');expect(strtolower($r['tags'])==='vip,neu','Deduplicate tag columns case-insensitively');expect($r['note_content']==="First note\n\nSecond note",'Append imported notes in source order');
expect(ContactPanda_Import_Review::split_name('Winter, Lena')===['Lena','Winter'],'Comma-separated name split');
$GLOBALS['contact']=['id'=>4,'first_name'=>'Previous','last_name'=>'Winter'];$method=new ReflectionMethod('ContactPanda_Import_Review','fingerprint');$fingerprint=$method->invoke(null,$GLOBALS['contact'],array_keys($r));
$review=['id'=>'review-1','mode'=>'update','rows'=>[$r],'fingerprints'=>[$fingerprint],'conflicts'=>[['first_name']],'split'=>[true]];
$GLOBALS['reviews']['contactpanda_review_7_token']=$review;$_POST=['review_confirmed'=>1,'review_id'=>'review-1','names'=>[['first_name'=>'Edited','last_name'=>'Winter']]];$data=['entity'=>'contacts'];
$committed=ContactPanda_Import_Review::resolve($data,'token',[]);expect(!isset($committed[0]['first_name']),'Keep old conflicting field unless explicitly selected');expect($committed[0]['note_content']===$r['note_content'],'Keep all note fragments');
$GLOBALS['reviews']['contactpanda_review_7_token']=$review;$_POST['replace']=[['first_name'=>1]];$committed=ContactPanda_Import_Review::resolve($data,'token',[]);expect($committed[0]['first_name']==='Edited','Review correction applied only after approval');
$GLOBALS['reviews']['contactpanda_review_7_token']=$review;$GLOBALS['contact']['last_name']='Changed concurrently';try{ContactPanda_Import_Review::resolve($data,'token',[]);throw new LogicException('Concurrent change accepted');}catch(RuntimeException$e){expect(str_contains($e->getMessage(),'changed after'),'Detect concurrent record changes');}
echo "Import review passed: multiple mappings, names, note append, conflict choices and concurrent changes.\n";

foreach (['review_admin','review_nonce'] as $guard) { $GLOBALS[$guard]=false; try { ContactPanda_Import_Review::resolve($data,'token',[]); throw new LogicException('Unauthorized import review accepted'); } catch (RuntimeException $e) { expect(str_contains($e->getMessage(), $guard==='review_admin'?'permission':'nonce'), 'Reject import review without permission and nonce'); } $GLOBALS[$guard]=true; }
