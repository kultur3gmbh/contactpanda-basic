<?php
/** Contract test for the preview-only Groundhogg integration. */
define('ABSPATH',__DIR__.'/');
function __($s,$d=''){return $s;}function absint($v){return abs((int)$v);}function current_user_can($c){return $GLOBALS['allowed'];}function is_wp_error($v){return $v instanceof WP_Error;}function sanitize_text_field($v){return strip_tags($v);}function wp_kses_post($v){return preg_replace('~<script.*?</script>~s','',$v);}function esc_url_raw($v,$p=[]){return strpos($v,'https://')===0?$v:'';}
class WP_Error{function __construct(...$args){}}
class ContactPanda_Capabilities{static function for_area($a){return $a;}}
class ContactPanda_Store{static function standalone(){return false;}}
class ContactPanda_Plugin{static function extension_contact($id){return $id===8?['id'=>8]:false;}}
function wp_get_ability($name){$GLOBALS['called']=$name;return new class{function execute($input){$GLOBALS['input']=$input;return $GLOBALS['result'];}};}
require __DIR__.'/../contactpanda/includes/class-contactpanda-person-profile.php';
function gh_expect($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$allowed=true;$result=true;
gh_expect(!ContactPanda_Person_Profile::can_edit(),'Groundhogg preview does not expose a profile editor');
gh_expect(is_wp_error(ContactPanda_Person_Profile::update(8,['first_name'=>'Anna','biography'=>'<p>Profile</p>'])),'Groundhogg profile update rejected without external writes');
gh_expect(!isset($GLOBALS['called'])&&!isset($GLOBALS['input']),'No Groundhogg write ability is invoked');
$allowed=false;gh_expect(is_wp_error(ContactPanda_Person_Profile::update(8,['biography'=>'Forbidden'])),'Provider preview still enforces permission');
