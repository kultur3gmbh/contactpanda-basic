<?php
/** Regression checks: single-use challenges and explicit news consent. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
$options = array();
$transients = array();
$http_calls = 0;
$feed_calls = 0;
$cache_reads = 0;
class WP_Error {}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $k, $d = false ) { global $options; return $options[$k] ?? $d; }
function update_option( $k, $v, $a = null ) { global $options; $options[$k] = $v; }
function wp_parse_args( $v, $d ) { return array_merge( $d, $v ); }
function wp_unslash( $v ) { return $v; }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( $v ) ); }
function sanitize_email( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function home_url( $v ) { return 'https://example.test' . $v; }
function wp_parse_url( $v, $part ) { return parse_url( $v, $part ); }
function wp_salt( $v ) { return 'fixture-only'; }
function current_time( $v ) { return gmdate( 'Y-m-d H:i:s' ); }
function do_action( $hook, ...$args ) {}
function apply_filters( $hook, $v ) { return $v; }
function get_transient( $k ) { global $transients; return $transients[$k] ?? false; }
function set_transient( $k, $v, $ttl ) { global $transients; $transients[$k] = $v; }
function get_site_transient( $k ) { global $cache_reads; ++$cache_reads; return false; }
function set_site_transient( $k, $v, $ttl ) {}
function esc_url_raw( $v ) { return $v; }
function fetch_feed( $v ) { global $feed_calls; ++$feed_calls; return new WP_Error; }
function wp_remote_post( $url, $args ) {
 global $http_calls, $http_response;
 ++$http_calls;
 if ( $url !== 'https://www.google.com/recaptcha/api/siteverify' ) throw new Exception( 'Unexpected remote request.' );
 return $http_response;
}
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function expect( $condition, $message ) { if ( ! $condition ) throw new Exception( $message ); }
require dirname(__DIR__) . '/contactpanda/includes/class-contactpanda-spam-protection.php';
require dirname(__DIR__) . '/contactpanda/includes/class-contactpanda-plugin.php';
function challenge( $patch = array(), $status = 'allow', $http = 200 ) {
 global $options, $transients, $http_calls, $http_response;
 $options[ContactPanda_Spam_Protection::OPTION] = array( 'provider' => 'recaptcha_v3', 'recaptcha_secret_key' => 'not-a-real-key' );
 $transients = array(); $http_calls = 0;
 $data = array_merge( array( 'success' => true, 'action' => 'signup', 'hostname' => 'example.test', 'challenge_ts' => gmdate('c'), 'score' => 0.9 ), $patch );
 $http_response = array( 'response' => array( 'code' => $http ), 'body' => json_encode($data) );
 $result = ContactPanda_Spam_Protection::verify('signup', array('contactpanda_spam_started'=>time()-5,'g-recaptcha-response'=>'fixture-token'));
 expect( $http_calls === 1, 'A challenge must be verified exactly once.' );
 expect( $result['status'] === $status, 'Unexpected challenge decision: ' . $result['reason'] );
 return $result;
}
challenge();
challenge(array('success'=>false),'block');
challenge(array('action'=>'another-form'),'block');
challenge(array('hostname'=>'other.test'),'block');
challenge(array('challenge_ts'=>gmdate('c',time()-125)),'block');
challenge(array('challenge_ts'=>gmdate('c',time()+60)),'block');
challenge(array('score'=>0.1),'quarantine');
challenge(array('score'=>2),'block');
challenge(array('score'=>'invalid'),'block');
challenge(array(), 'quarantine', 503);
$transients=array(); $http_calls=0; $http_response=new WP_Error;
$result=ContactPanda_Spam_Protection::verify('signup',array('contactpanda_spam_started'=>time()-5,'g-recaptcha-response'=>'fixture-token'));
expect($http_calls===1 && $result['status']==='quarantine','Network failures must not allow the submission.');
$transients = array(); $http_calls = 0;
$result=ContactPanda_Spam_Protection::verify('signup',array('contactpanda_spam_started'=>time()-5,'g-recaptcha-response'=>array('bad')));
expect($result['status']==='block' && $http_calls===0,'Malformed token must fail locally.');
$options[ContactPanda_Spam_Protection::OPTION]=array('provider'=>'basic');$transients=array();$http_calls=0;
$result=ContactPanda_Spam_Protection::verify('signup',array('contactpanda_spam_started'=>time()-5));
expect($result['status']==='allow' && $http_calls===0,'Local protection must make no external request.');
$news = new ReflectionMethod('ContactPanda_Plugin','news_items');
if ( PHP_VERSION_ID < 80100 ) $news->setAccessible(true);
$options['contactpanda_general_settings']=array(); $feed_calls=0;$cache_reads=0;
expect($news->invoke(null,4)===array() && !$feed_calls && !$cache_reads,'No request or cached news without consent.');
$options['contactpanda_general_settings']['external_news_enabled']=1;
$news->invoke(null,4);expect($feed_calls===1,'Explicit consent must enable the feed request.');
$options['contactpanda_general_settings']['external_news_enabled']=0;
$news->invoke(null,4);expect($feed_calls===1,'Revoking permission must stop subsequent requests.');
echo "External services: one-use challenge, origin/action/expiry, HTTP errors, local protection and news opt-in passed.\n";
