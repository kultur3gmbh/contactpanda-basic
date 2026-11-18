<?php
$r=getenv('CONTACTPANDA_TEST_WP_ROOT');if(!$r||!is_file($r.'/.panda-test-fixture'))exit(2);require $r.'/wp-load.php';$admin=get_user_by('login','fixture_admin');wp_set_current_user($admin->ID);$token=wp_generate_password(8,false,false);$user=wp_insert_user(['user_login'=>'synthetic_address_'.$token,'user_email'=>'address_'.$token.'@example.invalid','user_pass'=>wp_generate_password(40,true,true),'role'=>'subscriber']);if(is_wp_error($user))throw new RuntimeException('Synthetic user setup failed');$identity=ContactPanda_Identity::resolve_user($user,true);$company=ContactPanda_Store::create_company(['name'=>'Synthetic address authority '.$token]);ContactPanda_Store::update_company($company,'uid','SYNTHETIC-UNCHANGED');
try{
 wp_set_current_user($user);$_POST=[];foreach(['billing','shipping']as$prefix)foreach(['company'=>'Synthetic address authority '.$token,'first_name'=>'Synthetic','last_name'=>'Address','address_1'=>'Synthetic Street 1','address_2'=>'','postcode'=>'1010','city'=>'Synthetic City','country'=>'AT','state'=>'']as$key=>$value)$_POST[$prefix.'_'.$key]=$value;$_POST['vat_id']='';
 $method=new ReflectionMethod(ContactPanda_My_Account::class,'persist_addresses');if(PHP_VERSION_ID<80100)$method->setAccessible(true);$result=$method->invoke(null,$_POST);if(is_wp_error($result))throw new RuntimeException('Own address save failed');
 if(ContactPanda_Store::company($company)['vat_id']!=='SYNTHETIC-UNCHANGED')throw new RuntimeException('Unrelated company VAT changed');
 foreach(ContactPanda_Store::contact_company_relations($identity['id'])as$relation)if((int)$relation['company_id']===(int)$company)throw new RuntimeException('A billing label created company access');
 echo "PASS A customer can save their address without editing another company or gaining a company relationship\n";
}finally{
 wp_set_current_user($admin->ID);global $wpdb;$tables=ContactPanda_Schema::table_names();$wpdb->delete($tables['company_contacts'],['company_id'=>$company]);$wpdb->delete($tables['companies'],['id'=>$company]);$wpdb->delete($tables['contacts'],['id'=>$identity['id']]);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);
}
