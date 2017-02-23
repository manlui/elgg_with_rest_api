<?php
/* Site get list of notifications */

function site_get_notification($username) {
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }
	if (elgg_is_active_plugin('site_notifications')) {
	$return['list'] = elgg_list_entities_from_metadata(array(
		'type' => 'object',
		'subtype' => 'site_notification',
		'owner_guid' => $user->guid,
		'full_view' => false,
		'metadata_name' => 'read',
		'metadata_value' => false,
	));
	}else{
		$return['list'] = "";
	}
 
    return $return;
}

elgg_ws_expose_function('site.get_notifications',
    "site_get_notification",
    array(
        'username' => array ('type' => 'string', 'required' => true),
    ),
    "Get list of notifications",
    'GET',
    true,
    true);


	
	
	
/* Site get count of notifications */
	
function site_notification_count($username) {
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

	if (elgg_is_active_plugin('site_notifications')) {
         $return['count'] = elgg_get_entities_from_metadata(array(
				'type' => 'object',
				'subtype' => 'site_notification',
				'owner_guid' => $user->guid,
				'metadata_name' => 'read',
				'metadata_value' => false,
				'count' => true,
			));
	}else{
		$return['count'] = 0;
	}
 
    return $return;
}

elgg_ws_expose_function('site.notifications_count',
    "site_notification_count",
    array(
        'username' => array ('type' => 'string', 'required' => true),
    ),
    "Get list of notifications",
    'GET',
    true,
    true);