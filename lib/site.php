<?php
/**
 * Created by PhpStorm.
 * User: mlui
 * Date: 1/12/2016
 * Time: 9:16 AM
 */

////////////////////////////////////////////////////////////////////////////////////
//get elgg site info
elgg_ws_expose_function('site.getinfo',
    "site_getinfo",
    array(),
    "Get site information",
    'GET',
    false,
    false);

/**
 * @return mixed
 */
function site_getinfo() {
    $site = elgg_get_config('site');
    $siteinfo['url'] = elgg_get_site_url();
    $siteinfo['sitename'] = $site->name;
    $siteinfo['logo'] = elgg_get_plugin_setting('ws_get_logo', 'elgg_with_rest_api');
    if ($site->description == null) {
        $siteinfo['description'] = '';
    } else {
        $siteinfo['description'] = $site->description;
    }
    $siteinfo['time_created'] = time_ago($site->time_created);
    $siteinfo['language'] = elgg_get_config('language');

    return $siteinfo;
}


function site_get_list_plugin() {
    $plugins = elgg_get_plugins($status = 'active', $site_guid = null);
    $return = array(
        'messages' => false,
        'thewire' => false,
        'blog' => false,
        'tidypics' => false,
        'file' => false,
        'bookmarks' => false,
        'groups' => false,
    );
    foreach ($plugins as $plugin) {
        $a = $plugin->title;
        if (array_key_exists($plugin->title, $return)) {
            $return[$plugin->title] = true;
        }
    }

    return $return;
}

elgg_ws_expose_function('site.get_list_plugin',
    "site_get_list_plugin",
    array(),
    "Get list site Plugin",
    'GET',
    false,
    false);
	
	
	
elgg_ws_expose_function(
	"site.getapi",
	"site_getapi",
	array(),
	"Get API Key",
	'POST',
	false,
	false
);

function site_getapi() {
    return get_api_key();
}





function site_get_content($type) {
	return elgg_get_plugin_setting($type, 'improvement');
}

elgg_ws_expose_function('site.get_content',
    "site_get_content",
	array('type' => array ('type' => 'string'),),
    "Get details like about us, terms, privacy etc.",
    'GET',
    false,
    false);
	
function user_can_edit($guid,$username) {
	$object = get_entity($comment_guid);
	$user = get_user_by_username($username);
	return $object->canEdit($user->guid);
}

elgg_ws_expose_function('site.user_can_edit',
    "user_can_edit",
	array(
		'guid' => array ('type'=> 'int', 'required'=>true),
		'username' => array ('type'=> 'string', 'required'=>true),
	),
    "Get about user if he/she ownes the object.",
    'GET',
    false,
    false);
	
/**
 * Delete comment entity
 * @parameter comment_guid
 */
 
function site_delete_comment($comment_guid,$username){
$comment = get_entity($comment_guid);
$user = get_user_by_username($username);
if (elgg_instanceof($comment, 'object', 'comment') && $comment->canEdit($user->guid)) {
	if ($comment->delete()) {
		$return['success'] = true;
		$return['message'] = elgg_echo("generic_comment:deleted");
	} else {
		$return['success'] = false;
		$return['message'] = elgg_echo("generic_comment:notdeleted");
	}
} else {
	$return['success'] = false;
	$return['message'] = elgg_echo("generic_comment:notfound");
}
	return $return;
}

elgg_ws_expose_function('site.delete_comment',
	"site_delete_comment",
	array(
		'comment_guid' => array ('type'=> 'int', 'required'=>true),
		'username' => array ('type'=> 'string', 'required'=>true),
	),
	"Delete comment",
	'GET',
	true,
	true);
