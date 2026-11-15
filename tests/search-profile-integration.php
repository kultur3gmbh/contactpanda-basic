<?php
/** Real WordPress search, settings, profile and permissions regression cases. */
$root = getenv('CONTACTPANDA_TEST_WP_ROOT');
if (!$root || !is_file($root.'/.panda-test-fixture')) exit("Disposable fixture required.\n");
define('WP_ADMIN', true);define('WP_DISABLE_FATAL_ERROR_HANDLER',true);require $root.'/wp-load.php';switch_to_locale('en_US');
wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]);
function next_expect($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$general=get_option('contactpanda_general_settings');$definitions=get_option(ContactPanda_Custom_Fields::OPTION);$ids=[];$companies=[];$subscriber=0;$admin=get_current_user_id();
try {
 update_option('contactpanda_general_settings',array_merge((array)$general,['data_source'=>'standalone','search_fields'=>['first_name','last_name','email','phone','company']]));
 $suffix=wp_generate_uuid4();
 foreach([['Anna','Müller'],['Peter','Schmidt'],['Anna','Berger']] as$i=>$names)$ids[]=ContactPanda_Store::create_contact(['first_name'=>$names[0].' '.$suffix,'last_name'=>$names[1].' '.$suffix,'email'=>'search-'.$i.'-'.$suffix.'@example.invalid']);
 $find=static function($q)use($suffix){return ContactPanda_Store::search_contacts($q.' '.$suffix,50,0);};
 $hit=$find('Müller Anna');next_expect($hit['total_items']===1&&$hit['contacts'][0]['id']==$ids[0],'Reversed first and last names match one record');
 next_expect($find(" Anna  \t Müll ")['total_items']===1,'Partial words and redundant whitespace match');
 next_expect($find('Anna Schmidt')['total_items']===0,'Words from different people do not combine');
 $company=ContactPanda_Store::create_company(['name'=>'Fixture Green Society '.$suffix]);$companies[]=$company;ContactPanda_Store::link_company_contact($company,$ids[0],'Fixture role',false,true);
 next_expect($find('Society Anna Green')['total_items']===1,'Three words match across linked company and person fields');
 ContactPanda_Identity_Store::add_channel($ids[0],'email','second-'.$suffix.'@example.invalid');
 next_expect($find('Anna second-'.$suffix)['total_items']===1,'Secondary email participates in matching');
 ContactPanda_Identity_Store::add_channel($ids[0],'phone','+43 123 4567');next_expect($find('Anna 4567')['total_items']===1,'Phone channels participate in matching');
 $settings=(array)get_option('contactpanda_general_settings');$settings['search_fields']=['last_name'];update_option('contactpanda_general_settings',$settings);
 next_expect($find('Anna Müller')['total_items']===0&&$find('Müller')['total_items']===1,'Disabled search fields cannot produce matches');
 $settings['search_fields']=[];update_option('contactpanda_general_settings',$settings);next_expect($find('Müller')['total_items']===0,'Explicit empty selection matches no text');
 $settings['search_fields']=['first_name','last_name'];update_option('contactpanda_general_settings',$settings);
 $page=ContactPanda_Store::search_contacts('Anna '.$suffix,1,1);next_expect($page['total_items']===2&&count($page['contacts'])===1,'Count and pagination share the full predicate');
 next_expect($find("' OR 1=1 --")['total_items']===0&&$find('%')['total_items']===0,'SQL syntax and LIKE wildcards remain literal');
 ContactPanda_Custom_Fields::ensure_definition('search_fixture','Fixture custom field','text','contacts');
 $settings['search_fields']=['first_name','contactpanda_custom_search_fixture'];update_option('contactpanda_general_settings',$settings);ContactPanda_Store::set_contact_meta($ids[0],'contactpanda_custom_search_fixture','Unique orchard');next_expect($find('Anna orchard')['total_items']===1,'Selected custom field combines with first name');
 $sanitized=ContactPanda_Registration::sanitize_general_settings(['search_fields_present'=>1,'search_fields'=>['first_name','forbidden_column'],'independent_scroll'=>1]);next_expect($sanitized['search_fields']===['first_name']&&$sanitized['independent_scroll']===1,'Settings allowlist fields and persist independent scrolling');
 $preserved=ContactPanda_Registration::sanitize_general_settings(['external_news_enabled'=>0]);next_expect($preserved['search_fields']===$settings['search_fields'],'Other general forms preserve search configuration');
 next_expect(true===contactpanda_update_person_profile($ids[0],['first_name'=>'Anna','website'=>'https://example.invalid','biography'=>'<h2>Fixture</h2><p><strong>Formatted</strong> biography</p><script>bad()</script>']),'Basic profile writes without Plus entitlement');
 $profile=contactpanda_get_person_profile($ids[0]);next_expect(strpos($profile['biography'],'<strong>')!==false&&strpos($profile['biography'],'<script>')===false&&$profile['website']==='https://example.invalid','Canonical profile preserves safe formatting');
 next_expect(strpos(contactpanda_get_person_biography_html($ids[0]),'<h2>')!==false,'Public biography retains formatted content');
 add_shortcode('panda_empty_fixture',static function(){return '';});contactpanda_update_person_profile($ids[0],['biography'=>'[panda_empty_fixture]']);next_expect(contactpanda_get_person_biography_html($ids[0])==='','Empty resolved biography hides product tab');
 next_expect(is_wp_error(contactpanda_update_person_profile($ids[0],['email'=>'not-allowed@example.invalid'])),'Profile API rejects unapproved fields');
 $before=contactpanda_get_person_profile($ids[0]);$table=ContactPanda_Schema::table_names()['contactmeta'];$failed=false;
 $break=static function($sql)use($table,&$failed){if(!$failed&&preg_match('/^(UPDATE|INSERT).*'.preg_quote($table,'/').'/i',$sql)){$failed=true;return 'INVALID_SYNTHETIC_PROFILE_QUERY';}return $sql;};
 $old=$wpdb->suppress_errors(true);add_filter('query',$break);$result=contactpanda_update_person_profile($ids[0],['first_name'=>'Rolledback','biography'=>'Failed']);remove_filter('query',$break);$wpdb->suppress_errors($old);next_expect($failed&&is_wp_error($result)&&contactpanda_get_person_profile($ids[0])['first_name']===$before['first_name'],'Failed profile write rolls back core and metadata together');
 // Same policy for Groundhogg-shaped records, without writing external source data.
 $records=[['id'=>7001,'first_name'=>'Anna','last_name'=>'Müller','email'=>'fixture@example.invalid'],['id'=>7002,'first_name'=>'Peter','last_name'=>'Schmidt']];$settings['search_fields']=['first_name','last_name'];update_option('contactpanda_general_settings',$settings);
 next_expect(count(ContactPanda_Search::filter_contacts($records,'Müller Anna'))===1&&count(ContactPanda_Search::filter_contacts($records,'Anna Schmidt'))===0,'Provider-neutral matching uses the same word policy');
 $tabs=new ReflectionMethod('ContactPanda_Plugin','render_person_tabs');$tabs->setAccessible(true);$_GET=[];remove_filter('contactpanda_person_detail_tabs',['ContactPanda_Plus_Donations','tabs'],20);ob_start();$tabs->invoke(null,$ids[0],null,ContactPanda_Store::contact($ids[0]),[],[],false,[],true);$html=ob_get_clean();next_expect(strpos($html,'contactpanda-biography-card')!==false&&substr_count($html,'contactpanda-biography-form')===1,'Basic renders one editor independently of Plus');
 $subscriber=wp_insert_user(['user_login'=>'search-fixture-'.$suffix,'user_pass'=>wp_generate_password(),'role'=>'subscriber']);wp_set_current_user($subscriber);
 next_expect(is_wp_error(contactpanda_search_people('Anna'))&&is_wp_error(contactpanda_get_person_profile($ids[0]))&&is_wp_error(contactpanda_update_person_profile($ids[0],['biography'=>'No access'])),'Search and profile APIs enforce CRM permissions');
 next_expect(ContactPanda_Search::matches('Society Green',['Green Fixture Society']),'Company matching is order independent');
} finally {
 wp_set_current_user($admin);if($subscriber&&!is_wp_error($subscriber)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($subscriber);}
 update_option('contactpanda_general_settings',$general);update_option(ContactPanda_Custom_Fields::OPTION,$definitions);
 $t=ContactPanda_Schema::table_names();foreach($ids as$id){foreach(['company_contacts','contact_channels','contactmeta','contact_tags','notes','activities']as$key)if(isset($t[$key]))$wpdb->delete($t[$key],['contact_id'=>$id]);$wpdb->delete($t['contacts'],['id'=>$id]);}foreach($companies as$id)$wpdb->delete($t['companies'],['id'=>$id]);
}
