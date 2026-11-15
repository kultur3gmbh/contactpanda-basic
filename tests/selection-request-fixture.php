<?php
/** Bridge for a real WordPress-localized browser regression; disposable data only. */
$root=getenv('CONTACTPANDA_TEST_WP_ROOT');
if(!$root||!is_file($root.'/.panda-test-fixture'))exit(1);
define('WP_ADMIN',true);define('DOING_AJAX',true);require $root.'/wp-load.php';
wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]);
switch_to_locale('en_US');
if(($argv[1]??'')==='cleanup'){
 $post=get_post(absint($argv[2]??0));if($post&&$post->post_type===ContactPanda_Selections::TYPE&&$post->post_title==='Zero regression fixture')wp_delete_post($post->ID,true);exit;
}
if(in_array(($argv[1]??''),['config','config-saved'],true)){
 $_GET=['page'=>'contactpanda-people'];
 if($argv[1]==='config-saved')$_GET['selection']=wp_insert_post(['post_type'=>ContactPanda_Selections::TYPE,'post_status'=>'private','post_title'=>'Zero regression fixture','post_content'=>'[730001,730002]','post_excerpt'=>ContactPanda_Selections::source()]);ContactPanda_Selections::assets();
 $data=wp_scripts()->get_data('contactpanda-selections','data');preg_match('/var CP_SELECTIONS = (\{.*?\});/s',$data,$m);$config=json_decode($m[1],true);
 // Preserve WordPress's real scalar types, omit saved lists and other user data.
 $config['rows']=[];$config['ajax']='/ajax';echo json_encode($config);exit;
}
parse_str(stream_get_contents(STDIN),$_POST);$_REQUEST=$_POST;
ContactPanda_Selections::ajax();
