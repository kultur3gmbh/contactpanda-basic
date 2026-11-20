<?php
/** Extension metadata stays scoped in both supported source modes. */
define('ABSPATH',__DIR__.'/');
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));}
function sanitize_text_field($v){return strip_tags($v);} function wp_kses_post($v){return preg_replace('~<script.*?</script>~s','',$v);}
function esc_url_raw($v,$protocols=array()){return preg_match('~^https?://~',$v)?$v:'';} function is_wp_error($v){return $v instanceof Exception;}
class ContactPanda_Custom_Fields{const PREFIX='contactpanda_custom_';}
class ContactPanda_Store{static $standalone=true;static $saved;static function standalone(){return self::$standalone;}static function update_contact($id,$data){self::$saved=[$id,$data];return true;}static function update_company($id,$key,$value){self::$saved=[$id,$key,$value];return true;}}
function wp_get_ability($name){$GLOBALS['ability_name']=$name;return new class{function execute($input){$GLOBALS['ability_input']=$input;return true;}};}
require __DIR__.'/../contactpanda/includes/class-contactpanda-plugin.php';
function meta_expect($ok,$text){if(!$ok)throw new RuntimeException($text);echo "PASS $text\n";}
$values=['email'=>'forbidden@example.invalid','contactpanda_custom_biography'=>'<p><b>Fixture</b></p><script>bad()</script>','contactpanda_custom_website'=>'javascript:bad()','contactpanda_custom_phone'=>'<b>123</b>'];
meta_expect(ContactPanda_Plugin::extension_update_custom_meta('contacts',8,$values),'Standalone update accepted');
$data=ContactPanda_Store::$saved[1]['meta'];meta_expect(!isset($data['email'])&&$data['contactpanda_custom_biography']==='<p><b>Fixture</b></p>'&&$data['contactpanda_custom_website']===''&&$data['contactpanda_custom_phone']==='123','Keys and shared profile values sanitized');
ContactPanda_Store::$standalone=false;
meta_expect(!ContactPanda_Plugin::extension_update_custom_meta('contacts',8,$values),'Groundhogg person metadata remains read-only');
meta_expect(!ContactPanda_Plugin::extension_update_custom_meta('companies',3,['contactpanda_custom_company_name'=>'Fixture']),'Groundhogg company metadata remains read-only');
meta_expect(!isset($GLOBALS['ability_name']),'No external metadata write ability is called');
meta_expect(!ContactPanda_Plugin::extension_update_custom_meta('contacts',8,['email'=>'bad'])&&!ContactPanda_Plugin::extension_update_custom_meta('unknown',8,$values),'Empty and invalid writes rejected');
$generic=new ReflectionMethod('ContactPanda_Plugin','execute_ability');if(PHP_VERSION_ID<80100)$generic->setAccessible(true);
meta_expect(false===$generic->invoke(null,'groundhogg/update-contact',['id'=>8,'email'=>'bad']),'General Groundhogg API remains read-only');
