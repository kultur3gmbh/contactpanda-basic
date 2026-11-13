<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
$wp_version = '7.1.4';
$responses = array(); $cache = array();
class WP_Error { public function __construct($code, $message) {} }
function is_wp_error($x) { return $x instanceof WP_Error; }
function add_filter(...$x) {} function add_action(...$x) {}
function __($x, $domain = '') { return $x; }
function absint($x) { return abs((int)$x); }
function get_site_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
function set_site_transient($key, $value, $expiry) { $GLOBALS['cache'][$key] = $value; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_header($r, $k) { return $r['headers'][$k] ?? ''; }
function wp_parse_url($x) { return parse_url($x); }
function wp_safe_remote_get($url, $args) { return array('code'=>200, 'body'=>json_encode($GLOBALS['responses'][$url] ?? array())); }
require dirname( __DIR__ ) . '/tools/github/class-panda-public-updater.php';
function ensure($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function fixture($prefix, $version, $id, $draft = false) {
 return array('tag_name'=>$prefix.$version, 'draft'=>$draft, 'prerelease'=>false, 'assets'=>array(
  array('name'=>'panda-seasons-free-'.$version.'.zip','id'=>$id,'size'=>100,'state'=>'uploaded'),
  array('name'=>'panda-seasons-free-update.json','id'=>$id+1,'size'=>100,'state'=>'uploaded')
 ));
}
$api = 'https://api.github.com/repos/kultur3gmbh/panda-seasons';
$responses[$api.'/releases?per_page=100'] = array(fixture('v','0.9.3',1),fixture('free-v','9.0.0',3,true),fixture('free-v','0.3.0',5),fixture('free-v','0.3.1',7));
$responses[$api.'/releases/assets/8'] = array('slug'=>'panda-seasons-free','version'=>'0.3.1','requires'=>'6.4','requires_php'=>'7.4','tested'=>'6.4','sha256'=>str_repeat('a',64));
$updater = new Panda_Public_Updater('panda-seasons-free/panda-seasons-free.php','0.3.0',array('repository'=>'kultur3gmbh/panda-seasons','slug'=>'panda-seasons-free','tag_prefix'=>'free-v','name'=>'Panda Seasons Free','asset_prefix'=>'panda-seasons-free','manifest'=>'panda-seasons-free-update.json'));
$release = $updater->release(true);
ensure(!is_wp_error($release) && $release['version']==='0.3.1','Free release must ignore Full, older versions and drafts');
$result = $updater->updates((object)array('checked'=>array('panda-seasons-free/panda-seasons-free.php'=>'0.3.0')));
ensure(isset($result->response['panda-seasons-free/panda-seasons-free.php']),'Offer the newer release');
ensure($result->response['panda-seasons-free/panda-seasons-free.php']->slug==='panda-seasons-free','Keep the exact WordPress slug');
$responses[$api.'/releases/assets/8']['sha256']='invalid';
ensure(is_wp_error($updater->release(true)),'Reject an invalid checksum manifest');
$responses[$api.'/releases/assets/8']['sha256']=str_repeat('a',64);
$responses[$api.'/releases/assets/8']['slug']='panda-seasons';
ensure(is_wp_error($updater->release(true)),'Reject a manifest for the other edition');
$quickApi='https://api.github.com/repos/kultur3gmbh/pandawoo-quick';
$responses[$quickApi.'/releases?per_page=100']=array(array('tag_name'=>'quick-v0.5.0','draft'=>false,'prerelease'=>false,'assets'=>array(array('id'=>11,'name'=>'pandawoo-quick-0.5.0.zip','size'=>100,'state'=>'uploaded'),array('id'=>12,'name'=>'pandawoo-quick-update.json','size'=>100,'state'=>'uploaded'))));
$responses[$quickApi.'/releases/assets/12']=array('slug'=>'pandawoo-quick','version'=>'0.5.0','requires'=>'6.4','requires_php'=>'7.4','sha256'=>str_repeat('b',64));
$quick=new Panda_Public_Updater('pandawoo-quick/pandawoo-quick.php','0.4.3',array('repository'=>'kultur3gmbh/pandawoo-quick','slug'=>'pandawoo-quick','tag_prefix'=>'quick-v','name'=>'PandaWoo Quick','asset_prefix'=>'pandawoo-quick','manifest'=>'pandawoo-quick-update.json'));
ensure($quick->release()['version']==='0.5.0','Products must not share cached releases');
$property=new ReflectionProperty(Panda_Public_Updater::class,'install_release');
if ( PHP_VERSION_ID < 80100 ) $property->setAccessible(true);
$property->setValue($updater,array('version'=>'0.3.1'));
ensure($updater->source('/tmp/other-plugin/','/tmp/',null,array('plugin'=>'other/other.php'))==='/tmp/other-plugin/','Do not intercept another plugin in a bulk update');
echo "Public update routing, edition isolation, cache separation and manifest validation passed.\n";
