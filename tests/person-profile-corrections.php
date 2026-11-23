<?php
/** Real synthetic person/company changes and Plus tab ordering in disposable WordPress. */
$root=getenv('CONTACTPANDA_TEST_WP_ROOT');if(!$root||!is_file($root.'/.panda-test-fixture'))exit("Disposable fixture required.\n");
define('WP_ADMIN',true);require $root.'/wp-load.php';switch_to_locale('en_US');
wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]);
function profile_expect($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$contacts=[];$company=0;$view_user=0;$admin_id=get_current_user_id();$general=get_option('contactpanda_general_settings');
try{
 foreach(['One','Two']as$name)$contacts[]=ContactPanda_Store::create_contact(['first_name'=>'Profile fixture '.$name,'email'=>strtolower($name).'-'.wp_generate_uuid4().'@example.invalid']);
 $company=ContactPanda_Store::create_company(['name'=>'Fixture Typoo','legal_name'=>'Independent legal name']);
 foreach($contacts as$i=>$id)ContactPanda_Store::link_company_contact($company,$id,'Role '.$i,false,true);
 $before=ContactPanda_Store::contact_company_relations($contacts[0])[0];
 $saved=ContactPanda_Store::update_contact_company_details($company,$contacts[0],'Fixture Typo','Corrected role',true);
 profile_expect(true===$saved,'Company name and person role saved');
 $first=ContactPanda_Store::contact_company_relations($contacts[0])[0];$second=ContactPanda_Store::contact_company_relations($contacts[1])[0];
 profile_expect($first['company_id']==$company&&$first['contact_id']===$before['contact_id'],'Existing company and relationship IDs retained');
 profile_expect($first['name']==='Fixture Typo'&&$second['name']==='Fixture Typo'&&$first['position']==='Corrected role'&&$second['position']==='Role 1','Shared name changes while the other person role is retained');
 profile_expect(ContactPanda_Store::company($company)['legal_name']==='Independent legal name','Legal name is not silently overwritten');
 profile_expect(is_wp_error(ContactPanda_Store::update_contact_company_details($company,$contacts[0],'','bad',false))&&ContactPanda_Store::contact_company_relations($contacts[0])[0]['position']==='Corrected role','Empty name rejected before any relation changes');
 $unlinked=ContactPanda_Store::create_contact(['first_name'=>'Unlinked fixture']);$contacts[]=$unlinked;
 profile_expect(is_wp_error(ContactPanda_Store::update_contact_company_details($company,$unlinked,'Hijacked','bad',false))&&ContactPanda_Store::company($company)['name']==='Fixture Typo','Unrelated company cannot be renamed through a forged assignment');
 $render=new ReflectionMethod('ContactPanda_Plugin','render_contact_companies');if(PHP_VERSION_ID<80100)$render->setAccessible(true);ob_start();$render->invoke(null,$contacts[0],false);$html=ob_get_clean();
 profile_expect(preg_match('/<input[^>]+name="company_name"[^>]*required/',$html)&&preg_match('/<input[^>]+name="position"/',$html),'Assignment dialog contains separate company name and role fields');
 update_option('contactpanda_general_settings',array_merge((array)$general,['data_source'=>'groundhogg']));
 profile_expect(is_wp_error(ContactPanda_Store::update_contact_company_details($company,$contacts[0],'Forbidden rename','bad',false)),'Groundhogg source remains read-only for company names');update_option('contactpanda_general_settings',$general);
 // A failed second write must roll back the company rename too.
 $table=ContactPanda_Schema::table_names()['company_contacts'];$failed=false;
 $break_relation=static function($sql)use($table,&$failed){if(!$failed&&preg_match('/^UPDATE\\s+`?'.preg_quote($table,'/').'`?\\s/i',$sql)){$failed=true;return 'INVALID_SYNTHETIC_RELATION_QUERY';}return $sql;};
 $old_errors=$wpdb->suppress_errors(true);add_filter('query',$break_relation);
 $result=ContactPanda_Store::update_contact_company_details($company,$contacts[0],'Rolled back name','bad',false);
 remove_filter('query',$break_relation);$wpdb->suppress_errors($old_errors);
 profile_expect($failed&&is_wp_error($result)&&ContactPanda_Store::company($company)['name']==='Fixture Typo'&&ContactPanda_Store::contact_company_relations($contacts[0])[0]['position']==='Corrected role','Failed relation write rolls back the name change');
 // Entry-point permission and CSRF checks must precede any write.
 $die=static function(){return static function(){throw new RuntimeException('profile_request_rejected');};};add_filter('wp_die_handler',$die);
 $_POST=['contact_id'=>$contacts[0],'company_id'=>$company,'operation'=>'update','company_name'=>'Forbidden','_wpnonce'=>'forged'];$_REQUEST=$_POST;
 try{ContactPanda_Plugin::handle_contact_company();throw new LogicException('Forged nonce bypass');}catch(RuntimeException$e){profile_expect($e->getMessage()==='profile_request_rejected','Company save rejects a forged nonce');}
 $view_user=wp_insert_user(['user_login'=>'profile-fixture-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>'subscriber']);$viewer=get_user_by('id',$view_user);$viewer->add_cap('contactpanda_view_contacts');wp_set_current_user($view_user);
 ob_start();$render->invoke(null,$contacts[0],false);$view_html=ob_get_clean();profile_expect(strpos($view_html,'contactpanda-company-relation-form')===false,'View-only user cannot open the name editor');
 try{ContactPanda_Plugin::handle_contact_company();throw new LogicException('View permission bypass');}catch(RuntimeException$e){profile_expect($e->getMessage()==='profile_request_rejected','Company save rejects a view-only user');}
 wp_set_current_user($admin_id);remove_filter('wp_die_handler',$die);
 profile_expect(ContactPanda_Store::company($company)['name']==='Fixture Typo','Rejected requests leave company unchanged');
 $contact=ContactPanda_Store::contact($contacts[0]);$tabs=new ReflectionMethod('ContactPanda_Plugin','render_person_tabs');if(PHP_VERSION_ID<80100)$tabs->setAccessible(true);
 $_GET=[];ob_start();$tabs->invoke(null,$contacts[0],null,$contact,[],[],false,[],true);$html=ob_get_clean();
 $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);$buttons=$xpath->query('//button[@role="tab"]');$panels=$xpath->query('//div[@role="tabpanel"]');
 profile_expect(trim($buttons[0]->textContent)==='Info'&&strpos($buttons[1]->getAttribute('id'),'-notes-tab')!==false,'Info is the first tab directly before Notes');
 profile_expect($buttons[0]->getAttribute('aria-selected')==='true'&&!$panels[0]->hasAttribute('hidden')&&$panels[1]->hasAttribute('hidden'),'About me opens as the sole active initial tab');
 profile_expect(substr_count($html,'id="contactpanda-biography-card"')===1,'Self-description editor rendered once');
 remove_filter('contactpanda_person_detail_tabs',['ContactPanda_Plus_Donations','tabs'],20);
 ob_start();$tabs->invoke(null,$contacts[0],null,$contact,[],[],false,[],false);$html=ob_get_clean();
 profile_expect(strpos($html,'-about_me-tab" role="tab" aria-selected="true"')!==false,'Basic retains About me without Plus');
}finally{
 wp_set_current_user($admin_id);if($view_user&&!is_wp_error($view_user)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($view_user);}
 update_option('contactpanda_general_settings',$general);
 global $wpdb;$t=ContactPanda_Schema::table_names();foreach($contacts as$id){foreach(['company_contacts','contact_channels','contactmeta','contact_tags','notes','activities']as$key){if(isset($t[$key]))$wpdb->delete($t[$key],['contact_id'=>$id]);}$wpdb->delete($t['contacts'],['id'=>$id]);}if($company)$wpdb->delete($t['companies'],['id'=>$company]);
}
