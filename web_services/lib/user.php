<?php
/**
 * Web service to get profile labels
 *
 * @return string $profile_labels Array of profile labels
 */
function user_get_profile_fields() {	
	$user_fields = elgg_get_config('profile_fields');
	foreach ($user_fields as $key => $type) {
		$profile_labels[$key]['label'] = elgg_echo('profile:'.$key);
		$profile_labels[$key]['type'] = $type;
	}
	return $profile_labels;
}


/**
 * Web service to get profile information
 *
 * @param string $username username to get profile information
 * @return string $profile_info Array containin 'core', 'profile_fields' and 'avatar_url'
 * @throws InvalidParameterException
 */
function user_get_profile($username) {
	//if $username is not provided then try and get the loggedin user
	if(!$username){
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
	}
	
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}
	
	$user_fields = elgg_get_config('profile_fields');

	foreach ($user_fields as $key => $type) {
		if ($user->$key) {
			$profile_fields[$key]['label'] = elgg_echo('profile:' . $key);
			$profile_fields[$key]['type'] = $type;
			if (is_array($user->$key)) {
				$profile_fields[$key]['value'] = $user->$key;

			} else {
				$profile_fields[$key]['value'] = strip_tags($user->$key);
			}
		}
	}

	
	$core['name'] = $user->name;
	$core['username'] = $user->username;
	
	$profile_info['core'] = $core;
	$profile_info['profile_fields'] = $profile_fields;
	$profile_info['avatar_url'] = getProfileIcon($user);
	return $profile_info;
}


/**
 * Web service to update profile information
 *
 * @param string $username username to update profile information
 *
 * @param $profile
 * @return bool
 * @throws InvalidParameterException
 */
function user_save_profile($username, $profile) {
	if(!$username){
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
	}
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}
	$owner = get_entity($user->guid);
	$profile_fields = elgg_get_config('profile_fields');
	foreach ($profile_fields as $shortname => $valuetype) {
		$value = $profile[$shortname];
		$value = html_entity_decode($value, ENT_COMPAT, 'UTF-8');

		if ($valuetype != 'longtext' && elgg_strlen($value) > 250) {
			$error = elgg_echo('profile:field_too_long', array(elgg_echo("profile:{$shortname}")));
			return $error;
		}

		if ($valuetype == 'tags') {
			$value = string_to_tag_array($value);
		}
		$input[$shortname] = $value;
	}
	
	$name = strip_tags($profile['name']);
	if ($name) {
		if (elgg_strlen($name) > 50) {
			return elgg_echo('user:name:fail');
		} elseif ($owner->name != $name) {
			$owner->name = $name;
			return $owner->save();
		}
	}
	
	if (sizeof($input) > 0) {
		foreach ($input as $shortname => $value) {
			$options = array(
				'guid' => $owner->guid,
				'metadata_name' => $shortname
			);
			elgg_delete_metadata($options);
			
			if (isset($accesslevel[$shortname])) {
				$access_id = (int) $accesslevel[$shortname];
			} else {
				// this should never be executed since the access level should always be set
				$access_id = ACCESS_DEFAULT;
			}
			
			if (is_array($value)) {
				$i = 0;
				foreach ($value as $interval) {
					$i++;
					$multiple = ($i > 1) ? TRUE : FALSE;
					create_metadata($owner->guid, $shortname, $interval, 'text', $owner->guid, $access_id, $multiple);
				}
				
			} else {
				create_metadata($owner->guid, $shortname, $value, 'text', $owner->guid, $access_id);
			}
		}
		
	}
	
	return "Success";
}


/**
 * Web service to get all users registered with an email ID
 *
 * @param string $email Email ID to check for
 * @return string $foundusers Array of usernames registered with this email ID
 * @throws InvalidParameterException
 * @throws RegistrationException
 */
function user_get_user_by_email($email) {
	if (!validate_email_address($email)) {
		throw new RegistrationException(elgg_echo('registration:notemail'));
	}

	$user = get_user_by_email($email);
	if (!$user) {
		throw new InvalidParameterException('registration:emailnotvalid');
	}
	foreach ($user as $key => $singleuser) {
		$foundusers[$key] = $singleuser->username;
	}
	return $foundusers;
}



/**
 * Web service to check availability of username
 *
 * @param string $username Username to check for availaility 
 *
 * @return bool
 */           
function user_check_username_availability($username) {
	$user = get_user_by_username($username);
	if (!$user) {
		return true;
	} else {
		return false;
	}
}



/**
 * Web service to register user
 *
 * @param string $name     Display name 
 * @param string $email    Email ID 
 * @param string $username Username
 * @param string $password Password 
 *
 * @return bool
 */           
function user_register($name, $email, $username, $password) {
	$user = get_user_by_username($username);
	if (!$user) {
		$return['success'] = true;
		$return['guid'] = register_user($username, $password, $name, $email);
	} else {
		$return['success'] = false;
		$return['message'] = elgg_echo('registration:userexists');
	}
	return $return;
}


/**
 * Web service to add as friend
 *
 * @param string $friend Username to be added as friend
 *
 * @param string $username Username
 * @return bool
 * @throws InvalidParameterException
 */
function user_friend_add($friend, $username) {
	if(!$username){
		$user = get_loggedin_user();
	} else {
		$user = get_user_by_username($username);
	}
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}
	
	$friend_user = get_user_by_username($friend);
	if (!$friend_user) {
		$msg = elgg_echo("friends:add:failure", array($friend_user->name));
	 	throw new InvalidParameterException($msg);
	}
	
	if($friend_user->isFriendOf($user->guid)) {
		$msg = elgg_echo('friends:alreadyadded', array($friend_user->name));
	 	throw new InvalidParameterException($msg);
	}
	
	
	if ($user->addFriend($friend_user->guid)) {
		// add to river
		add_to_river('river/relationship/friend/create', 'friend', $user->guid, $friend_user->guid);
		$return['success'] = true;
		$return['message'] = elgg_echo('friends:add:successful' , array($friend_user->name));
	} else {
		$msg = elgg_echo("friends:add:failure", array($friend_user->name));
	 	throw new InvalidParameterException($msg);
	}
	return $return;
}



function user_friend_is_friend_of($friend, $username) {
	if(!$username){
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
	}
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}

	$friend_user = get_user_by_username($friend);
	if (!$friend_user) {
		$msg = elgg_echo("friends:add:failure", array($friend_user->name));
		throw new InvalidParameterException($msg);
	}

	if($friend_user->isFriendOf($user->guid)) {
		$return['message'] = elgg_echo('YES');
	} else {
		$return['message'] = elgg_echo('NO');
	}


	return $return;
}


/**
 * Web service to remove friend
 *
 * @param string $friend Username to be removed from friend
 *
 * @param string $username Username
 * @return bool
 * @throws InvalidParameterException
 */
function user_friend_remove($friend,$username) {
	if(!$username){
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
	}
	if (!$user) {
	 	throw new InvalidParameterException('registration:usernamenotvalid');
	}
	
	$friend_user = get_user_by_username($friend);
	if (!$friend_user) {
		$msg = elgg_echo("friends:remove:failure", array($friend_user->name));
	 	throw new InvalidParameterException($msg);
	}
	
	if(!$friend_user->isFriendOf($user->guid)) {
		$msg = elgg_echo("friends:remove:notfriend", array($friend_user->name));
	 	throw new InvalidParameterException($msg);
	}
	
	
		if ($user->removeFriend($friend_user->guid)) {
		
		$return['message'] = elgg_echo("friends:remove:successful", array($friend->name));
		$return['success'] = true;
	} else {
		$msg = elgg_echo("friends:add:failure", array($friend_user->name));
	 	throw new InvalidParameterException($msg);
	}
	return $return;
}


/**
 * Web service to get friends of a user
 *
 * @param string $username Username
 * @param int|string $limit Number of users to return
 * @param int|string $offset Indexing offset, if any
 * @return array
 * @throws InvalidParameterException
 */
function user_get_friends($username, $limit = 10, $offset = 0) {
	if($username){
		$user = get_user_by_username($username);
	} else {
		$user = elgg_get_logged_in_user_entity();
	}
	if (!$user) {
		throw new InvalidParameterException(elgg_echo('registration:usernamenotvalid'));
	}
	$friends = elgg_get_entities_from_relationship(array(
		'relationship' => 'friend',
		'relationship_guid' => $user->guid,
		'type' => 'user',
		'subtype' => "",
		'limit' => $limit,
		'offset' => $offset
	));
	
	if($friends){
	foreach($friends as $single) {
		$friend['guid'] = $single->guid;
		$friend['username'] = $single->username;
		$friend['name'] = $single->name;
		$friend['avatar_url'] = getProfileIcon($single); //$single->getIconURL('small');
		$friend['friend'] = 'FRIEND';

		$return[] = $friend;
	}
	} else {
		$msg = elgg_echo('friends:none');
		throw new InvalidParameterException($msg);
	}
	return $return;
}

elgg_ws_expose_function('user.get_friends',
	"user_get_friends",
	array('username' => array ('type' => 'string', 'required' => false),
		'limit' => array ('type' => 'int', 'required' => false),
		'offset' => array ('type' => 'int', 'required' => false),
	),
	"Register user",
	'GET',
	true,
	true);


/**
 * Web service to obtains the people who have made a given user a friend
 *
 * @param string $username Username
 * @param int|string $limit Number of users to return
 * @param int|string $offset Indexing offset, if any
 * @return array
 * @throws InvalidParameterException
 */
function user_get_friends_of($username, $limit = 10, $offset = 0) {
	if(!$username){
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
	}
	if (!$user) {
		throw new InvalidParameterException(elgg_echo('registration:usernamenotvalid'));
	}
	$friends = get_user_friends_of($user->guid, '' , $limit, $offset);
	
	$success = false;
	foreach($friends as $friend) {
		$return['guid'] = $friend->guid;
		$return['username'] = $friend->username;
		$return['name'] = $friend->name;
		$return['avatar_url'] = getProfileIcon($friend); //$friend->getIconURL('small');
		$success = true;
	}
	
	if(!$success) {
		$return['error']['message'] = elgg_echo('friends:none');
	}
	return $return;
}


/**
 * Web service to retrieve the messageboard for a user
 *
 * @param int|string $limit Number of users to return
 * @param int|string $offset Indexing offset, if any
 *
 * @param string $username Username
 * @return array
 * @throws InvalidParameterException
 */
function user_get_messageboard($limit = 10, $offset = 0, $username){
	if(!$username){
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
		if (!$user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}
	
$options = array(
	'annotations_name' => 'messageboard',
	'guid' => $user->guid,
	'limit' => $limit,
	'pagination' => false,
	'reverse_order_by' => true,
);

	$messageboard = elgg_get_annotations($options);
	
	if($messageboard){
	foreach($messageboard as $single){
		$post['id'] = $single->id;
		$post['description'] = $single->value;
		
		$owner = get_entity($single->owner_guid);
		$post['owner']['guid'] = $owner->guid;
		$post['owner']['name'] = $owner->name;
		$post['owner']['username'] = $owner->username;
		$post['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');
		
		$post['time_created'] = (int)$single->time_created;
		$return[] = $post;
	}
} else {
		$msg = elgg_echo('messageboard:none');
		throw new InvalidParameterException($msg);
	}
 	return $return;
}

/**
 * Web service to post to a messageboard
 *
 * @param string $text
 * @param string $to - username
 * @param string $from - username
 * @return array
 * @throws InvalidParameterException
 */
function user_post_messageboard($text, $to, $from){
	if(!$to){
		$to_user = get_loggedin_user();
	} else {
		$to_user = get_user_by_username($to);
		if (!$to_user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}
	if(!$from){
		$from_user = get_loggedin_user();
	} else {
		$from_user = get_user_by_username($from);
		if (!$from_user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}
	
	$result = messageboard_add($from_user, $to_user, $text, 2);

	if($result){
		$return['success']['message'] = elgg_echo('messageboard:posted');
	} else {
		$return['error']['message'] = elgg_echo('messageboard:failure');
	}
	return $return;
}


/**
 * Web service to get list members
 *
 * @param string $username - username
 *
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function user_list_members($username, $limit = 20, $offset = 0)
{
	if(!$username) {
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
		if (!$user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}

    $options = array(
        'type' => 'user',
        'full_view' => false,
		'offset' => $offset,
		'limit' => $limit,
    );

    if ($user) {
        $results = get_elgg_list_members($options);
        if ($results) {
            foreach ($results AS $result) {

                if ($result->get("enabled") == "yes") {
                    $friend_user = get_entity($result->get("guid"));

                    $member['guid'] = $result->get("guid");
                    $member['name'] = $result->get("name");
                    $member['username'] = $result->get("username");
                    $member['avatar_url'] = getProfileIcon($result); //$result->getIconURL('small');

                    if ($friend_user->isFriendOf($user->guid)) {
                        $member['friend'] = 'FRIEND';
                    } else if ($friend_user->guid == $user->guid) {
                        $member['friend'] = 'SELF';
                    } else {
                        $member['friend'] = '';
                    }
                }

                $return[] = $member;
            }
        }
    }

    return $return;
}

elgg_ws_expose_function('user.list_members',
	"user_list_members",
	array('username' => array ('type' => 'string', 'required' => true),
		'limit' => array ('type' => 'int', 'required' => false),
		'offset' => array ('type' => 'int', 'required' => false),
	),
	"list members",
	'GET',
	true,
	true);



function get_elgg_list_members(array $options = array(), $getter = 'elgg_get_entities') {

    global $autofeed;
    $autofeed = true;

    $offset_key = isset($options['offset_key']) ? $options['offset_key'] : 'offset';

    $defaults = array(
        'offset' => (int) max(get_input($offset_key, 0), 0),
        'limit' => (int) max(get_input('limit', 10), 0),
        'full_view' => FALSE,
        'list_type_toggle' => FALSE,
        'pagination' => TRUE,
    );

    $options = array_merge($defaults, $options);

    //backwards compatibility
    if (isset($options['view_type_toggle'])) {
        $options['list_type_toggle'] = $options['view_type_toggle'];
    }

    $options['count'] = TRUE;
    $count = $getter($options);

    $options['count'] = FALSE;
    $entities = $getter($options);

    $options['count'] = $count;

    return $entities;
}

/**
 * Web service to search members
 *
 * @param string $username - username
 *
 * @return array
 */
function user_search($username, $limit = 20, $offset = 0, $search_name)
{
    $db_prefix = elgg_get_config('dbprefix');

    $options = array(
        'type' => 'user',
        'full_view' => false,
        'joins' => array("JOIN {$db_prefix}users_entity u ON e.guid=u.guid"),
        'wheres' => array("(u.name LIKE \"%{$search_name}%\" OR u.username LIKE \"%{$search_name}%\")"),
        'offset' => (int) max(get_input('offset', 0), 0),
        'limit' => (int) max(get_input('limit', 10), 0),
    );

    $user = get_user_by_username($username);
    if ($user) {
        $results = get_elgg_list_members($options);
        if ($results) {
            foreach ($results AS $result) {

                if ($result->get("enabled") == "yes") {
                    $friend_user = get_entity($result->get("guid"));

                    $member['guid'] = $result->get("guid");
                    $member['name'] = $result->get("name");
                    $member['username'] = $result->get("username");
                    $member['avatar_url'] = getProfileIcon($result); //$result->getIconURL('small');

                    if ($friend_user->isFriendOf($user->guid)) {
                        $member['friend'] = 'FRIEND';
                    } else if ($friend_user->guid == $user->guid) {
                        $member['friend'] = 'SELF';
                    } else {
                        $member['friend'] = '';
                    }
                }

                $return[] = $member;
            }
        }
    }

    return $return;
}


elgg_ws_expose_function('user.get_profile_fields',
	"user_get_profile_fields",
	array(),
	"Get user profile labels",
	'GET',
	true,
	true);

elgg_ws_expose_function('user.get_profile',
	"user_get_profile",
	array('username' => array ('type' => 'string', 'required' => false)
	),
	"Get user profile information",
	'GET',
	true,
	true);

elgg_ws_expose_function('user.save_profile',
	"user_save_profile",
	array('username' => array ('type' => 'string'),
		'profile' => array ('type' => 'array'),
	),
	"Get user profile information with username",
	'POST',
	true,
	true);

elgg_ws_expose_function('user.get_user_by_email',
	"user_get_user_by_email",
	array('email' => array ('type' => 'string'),
	),
	"Get Username by email",
	'GET',
    true,
    true);

elgg_ws_expose_function('user.check_username_availability',
	"user_check_username_availability",
	array('username' => array ('type' => 'string'),
	),
	"Get Username by email",
	'GET',
    true,
    true);

elgg_ws_expose_function('user.register',
	"user_register",
	array('name' => array ('type' => 'string'),
		'email' => array ('type' => 'string'),
		'username' => array ('type' => 'string'),
		'password' => array ('type' => 'string'),
	),
	"Register user",
	'GET',
    true,
    true);

elgg_ws_expose_function('user.friend.add',
	"user_friend_add",
	array(
		'friend' => array ('type' => 'string'),
		'username' => array ('type' => 'string', 'required' =>false),
	),
	"Add a user as friend",
	'POST',
	true,
    true);

elgg_ws_expose_function('user.friend.is.friend.of',
	"user_friend_is_friend_of",
	array(
		'friend' => array ('type' => 'string'),
		'username' => array ('type' => 'string', 'required' =>false),
	),
	"Check a user is friend",
	'POST',
	true,
    true);

elgg_ws_expose_function('user.friend.remove',
	"user_friend_remove",
	array(
		'friend' => array ('type' => 'string'),
		'username' => array ('type' => 'string', 'required' => false),
	),
	"Remove friend",
	'GET',
	true,
	true);

elgg_ws_expose_function('user.friend.get_friends',
	"user_get_friends",
	array('username' => array ('type' => 'string', 'required' => false),
		'limit' => array ('type' => 'int', 'required' => false),
		'offset' => array ('type' => 'int', 'required' => false),
	),
	"Register user",
	'GET',
	false,
	false);

elgg_ws_expose_function('user.friend.get_friends_of',
	"user_get_friends_of",
	array('username' => array ('type' => 'string', 'required' => true),
		'limit' => array ('type' => 'int', 'required' => false),
		'offset' => array ('type' => 'int', 'required' => false),
	),
	"Register user",
	'GET',
    true,
    true);

elgg_ws_expose_function('user.get_messageboard',
	"user_get_messageboard",
	array(
		'limit' => array ('type' => 'int', 'required' => false, 'default' => 10),
		'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
		'username' => array ('type' => 'string', 'required' => false),
	),
	"Get a users messageboard",
	'GET',
    true,
    true);

elgg_ws_expose_function('user.post_messageboard',
	"user_post_messageboard",
	array(
		'text' => array ('type' => 'string'),
		'to' => array ('type' => 'string', 'required' => false),
		'from' => array ('type' => 'string', 'required' => false),
	),
	"Post a messageboard post",
	'POST',
	true,
	true);

elgg_ws_expose_function('user.search',
	"user_search",
	array('username' => array ('type' => 'string', 'required' => true),
		'limit' => array ('type' => 'int', 'required' => false),
		'offset' => array ('type' => 'int', 'required' => false),
		'search_name' => array ('type' => 'string', 'required' => true),
	),
	"search user",
	'GET',
	true,
	true);

function getProfileIcon($user, $size='small') {
	$site_url = get_config('wwwroot');

	$profileUrl = $user->getIconURL($size);

	if (strpos($profileUrl, 'json/icons/user')) {
		$profileUrl = $site_url . 'mod/profile/icondirect.php?size='. $size . '&guid=' . $user->guid;
	} else if (strpos($profileUrl, 'json/file/icons')) {
		$icon_file_name = basename($profileUrl);
		$profileUrl = $site_url . 'mod/file/graphics/icons/' . $icon_file_name;
	} else if (strpos($profileUrl, 'json/groups/')) {
		$icon_file_name = basename($profileUrl);
		$profileUrl = $site_url . 'mod/groups/graphics/' . $icon_file_name;
	}

	return $profileUrl;
}