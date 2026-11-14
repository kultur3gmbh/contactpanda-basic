<?php
$fixture=getenv('CONTACTPANDA_TEST_WP_ROOT');
if(!$fixture||!is_file($fixture.'/.panda-test-fixture'))exit("Disposable fixture required.\n");
define('WP_ADMIN',true);define('DOING_AJAX',true);require $fixture.'/wp-load.php';
function selection_expect($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
add_filter('wp_die_ajax_handler',static function(){return static function(){throw new RuntimeException('ajax_exit');};});
function selection_ajax($args){$_POST=$args;$_REQUEST=$args;ob_start();try{ContactPanda_Selections::ajax();}catch(RuntimeException $e){if($e->getMessage()!=='ajax_exit'){ob_end_clean();throw $e;}}return json_decode(ob_get_clean(),true);}
wp_set_current_user((int)get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'))[0]);
$contacts=array();$post_id=0;$general=get_option('contactpanda_general_settings');
try{
 foreach(array('One','Two')as$name){$contact=ContactPanda_Store::create_contact(array('first_name'=>'Fixture '.$name,'email'=>strtolower($name).'-'.wp_generate_uuid4().'@example.invalid'));selection_expect(!is_wp_error($contact),'Synthetic person created');$contacts[]=(int)$contact;}
 $auth=array('nonce'=>wp_create_nonce('contactpanda_selection'));
 $invalid=selection_ajax(array('nonce'=>'invalid','operation'=>'save','name'=>'Rejected','ids'=>$contacts));selection_expect(empty($invalid['success']),'Invalid nonce cannot save a selection');
 $response=selection_ajax($auth+array('operation'=>'save','name'=>'Fixture selection','ids'=>array_merge($contacts,$contacts,array(999999999))));
 selection_expect(!empty($response['success']),'Selection saved through authenticated AJAX');$post_id=$response['data']['id'];
 $row=ContactPanda_Selections::get($post_id);selection_expect($row['ids']===$contacts,'Duplicate and absent IDs excluded');
 $response=selection_ajax($auth+array('operation'=>'rename','id'=>$post_id,'name'=>'Renamed fixture'));
 selection_expect($response['success']&&ContactPanda_Selections::get($post_id)['ids']===$contacts,'Renaming retains fixed membership');
 $_GET['selection']=$post_id;$method=new ReflectionMethod('ContactPanda_Newsletter_Campaigns','selected_contact_ids_from_request');$method->setAccessible(true);selection_expect($method->invoke(null)===$contacts,'Newsletter loads the same fixed selection');
 update_option('contactpanda_general_settings',array_merge((array)$general,array('data_source'=>'groundhogg')));selection_expect(null===ContactPanda_Selections::get($post_id),'Groundhogg cannot load standalone identities');update_option('contactpanda_general_settings',$general);
 $response=selection_ajax($auth+array('operation'=>'delete','id'=>$post_id));selection_expect(empty($response['success']),'Delete requires explicit confirmation');
 $response=selection_ajax($auth+array('operation'=>'delete','id'=>$post_id,'confirmed'=>'1'));selection_expect($response['success']&&'trash'===get_post_status($post_id),'Selection deletion uses recoverable trash');
 selection_expect((bool)ContactPanda_Plugin::extension_contact($contacts[0]),'Deleting selection never deletes people');
 wp_set_current_user(0);$response=selection_ajax($auth+array('operation'=>'load','id'=>$post_id));selection_expect(empty($response['success']),'Unauthenticated access rejected');
 wp_set_current_user((int)get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'))[0]);
 $denied=static function(){return false;};add_filter('contactpanda_record_merge_allowed',$denied,999);selection_expect(!ContactPanda_Data_Tools::merge_available(),'Merge unavailable without Plus entitlement');remove_filter('contactpanda_record_merge_allowed',$denied,999);
 $allowed=static function(){return true;};add_filter('contactpanda_record_merge_allowed',$allowed,999);selection_expect(ContactPanda_Data_Tools::merge_available(),'Entitled Plus enables shared merge view');remove_filter('contactpanda_record_merge_allowed',$allowed,999);
}finally{if($post_id)wp_delete_post($post_id,true);update_option('contactpanda_general_settings',$general);global $wpdb;$t=ContactPanda_Schema::table_names();foreach($contacts as$id){$wpdb->delete($t['contact_channels'],array('contact_id'=>$id));$wpdb->delete($t['contacts'],array('id'=>$id));}}
