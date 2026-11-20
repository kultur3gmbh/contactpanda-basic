<?php
/** Synthetic fixtures: no source events or customer data are modified. */
define('ABSPATH', __DIR__);
$GLOBALS['event_search_admin'] = true;
$GLOBALS['event_search_logged_in'] = true;
$GLOBALS['pagenow'] = 'edit.php';
function is_admin(){return $GLOBALS['event_search_admin'];}
function is_user_logged_in(){return $GLOBALS['event_search_logged_in'];}
function post_type_exists($type){return 'tribe_events' === $type;}
function current_user_can($permission,$id){return 19 !== $id;}
function wp_strip_all_tags($text){return strip_tags($text);}
function get_post_status_object($status){return (object)array('label'=>array('draft'=>'Entwurf','private'=>'Privat','pending'=>'Ausstehend','future'=>'Geplant')[$status]);}
function get_posts($args){
 if(empty($args['tribe_suppress_query_filters']) || empty($args['suppress_filters'])) throw new RuntimeException('Occurrence/date filters must not hide choices.');
 if(!in_array('draft',$args['post_status'],true)) throw new RuntimeException('Drafts excluded.');
 return array_slice($GLOBALS['event_search_fixture'],($args['paged']-1)*250,250);
}
require dirname(__DIR__).'/contactpanda/includes/class-contactpanda-events.php';
function check_event_search($condition,$message){if(!$condition)throw new RuntimeException($message);}
$GLOBALS['event_search_fixture']=array();
for($id=1;$id<=275;$id++)$GLOBALS['event_search_fixture'][]=(object)array('ID'=>$id,'post_status'=>'publish','post_title'=>'Event '.$id);
$GLOBALS['event_search_fixture'][270]=(object)array('ID'=>271,'post_status'=>'draft','post_title'=>'Werksführung &#8211; &#038; Beispiel');
$options=ContactPanda_Events::search_options();
check_event_search(274===count($options),'Read all pages while enforcing per-event permissions.');
$by_id=array_column($options,'label','value');
check_event_search('Werksführung – & Beispiel — Entwurf'===$by_id[271],'Decode title entities and label drafts even beyond the first 250 results.');
check_event_search(!isset($by_id[19]),'Unreadable events must not leak.');
$GLOBALS['event_search_logged_in']=false;
check_event_search(array()===ContactPanda_Events::search_options(),'No unpublished titles for anonymous visitors.');
final class Event_Search_Query_Fixture{
 public $vars=array('post_type'=>'tribe_events','s'=>'werk');public $main=true;
 function get($key){return $this->vars[$key]??'';}function set($key,$value){$this->vars[$key]=$value;}function is_main_query(){return $this->main;}
}
$q=new Event_Search_Query_Fixture();ContactPanda_Events::prepare_admin_search($q);
check_event_search(!empty($q->vars['tribe_suppress_query_filters']) && !empty($q->vars['tec_events_ignore']),'Use ordinary post search in event administration.');
check_event_search(false===ContactPanda_Events::allow_extended_admin_search(true,$q),'Do not let an INNER meta join exclude matching event titles.');
foreach(array('frontend','secondary','empty','product')as$case){
 $q=new Event_Search_Query_Fixture();$GLOBALS['event_search_admin']='frontend'!==$case;
 if('secondary'===$case)$q->main=false;if('empty'===$case)$q->vars['s']='';if('product'===$case)$q->vars['post_type']='product';
 check_event_search(true===ContactPanda_Events::allow_extended_admin_search(true,$q),'Keep extended search for '.$case.' queries.');
 ContactPanda_Events::prepare_admin_search($q);check_event_search(!isset($q->vars['tribe_suppress_query_filters']),'Do not alter '.$case.' queries.');
}
echo "Event search options: passed\n";
