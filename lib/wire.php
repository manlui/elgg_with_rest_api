<?php
/**
 * Web service for making a wire post
 *
 * @param string $text the content of wire post
 * @param int $access
 * @param string $wireMethod
 * @param string $username username of author
 * @return bool
 * @throws InvalidParameterException
 * @internal param string $acess access level for post{-1, 0, 1, 2, -2}
 * @internal param string $password password of user
 *
 */
function wire_save_post($text, $access = ACCESS_PUBLIC, $wireMethod = "api", $username) {

    $user = get_user_by_username($username);
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }
	
	$return['success'] = false;
	if (empty($text)) {
		$return['message'] = elgg_echo("thewire:blank");
		return $return;
	}

    if ($access == 'ACCESS_FRIENDS') {
        $access_id = -2;
    } elseif ($access == 'ACCESS_PRIVATE') {
        $access_id = 0;
    } elseif ($access == 'ACCESS_LOGGED_IN') {
        $access_id = 1;
    } elseif ($access == 'ACCESS_PUBLIC') {
        $access_id = 2;
    } else {
        $access_id = -2;
    }

    $parent_guid = 0;
	$guid = thewire_save_post($text, $user->guid, $access_id, $parent_guid, $wireMethod);

	if (!$guid) {
		$return['message'] = elgg_echo("thewire:error");
		return $return;
	}
	$return['success'] = true;
	return $return;
	} 
				
elgg_ws_expose_function('wire.save_post',
				"wire_save_post",
				array(
						'text' => array ('type' => 'string', 'required' => true),
						'access' => array ('type' => 'string', 'required' => true),
						'wireMethod' => array ('type' => 'string', 'required' => true),
						'username' => array ('type' => 'string', 'required' => true),
					),
				"Post a wire post",
				'POST',
				true,
    true);

/**
 * Web service for read latest wire post of user
 *
 * @param string $context all/mine/friends
 * @param int $limit
 * @param int $offset
 * @param string $username username of author
 * @return bool
 * @throws InvalidParameterException
 */
function wire_get_posts($context, $limit = 20, $offset = 0, $username) {
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $params = array();
	if($context == "all"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'thewire',
			'limit' => $limit,
            'offset' => $offset,
			'full_view' => FALSE,
		);
	}
	if($context == "mine" || $context == "user"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'thewire',
			'owner_guid' => $user->guid,
			'limit' => $limit,
            'offset' => $offset,
			'full_view' => FALSE,
		);
	}

	$latest_wire = elgg_get_entities($params);
		
	if($context == "friends"){
        $timelower = 0;
        $timeupper = 0;
//		$latest_wire = get_user_friends_objects($user->guid, 'thewire', $limit, $offset);
        $latest_wire = elgg_get_entities_from_relationship(array(
            'type' => 'object',
            'subtype' => 'thewire',
            'limit' => $limit,
            'offset' => $offset,
            'created_time_lower' => $timelower,
            'created_time_upper' => $timeupper,
            'relationship' => 'friend',
            'relationship_guid' => $user->guid,
            'relationship_join_on' => 'container_guid',
        ));
	}

    $return = array();
    if($latest_wire){
        foreach($latest_wire as $single ) {
            $wire['guid'] = $single->guid;

            $owner = get_entity($single->owner_guid);
            $wire['owner']['guid'] = $owner->guid;
            $wire['owner']['name'] = $owner->name;
            $wire['owner']['username'] = $owner->username;
            $wire['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');

            $wire['time_created'] = time_ago($single->time_created);
            $wire['description'] = $single->description;
            $wire['like_count'] = likes_count_number_of_likes($single->guid);
            $wire['like'] = checkLike($single->guid, $user->guid);

            $options = array(
                "metadata_name" => "wire_thread",
                "metadata_value" => $single->guid,
                "type" => "object",
                "subtype" => "thewire",
                "limit" => 0,
            );

            $comments = get_elgg_comments($options, 'elgg_get_entities_from_metadata');
            if (sizeof($comments) > 0) {
                $comment_count = sizeof($comments) - 1;
            } else {
                $comment_count = 0;
            }
            $wire['comment_count'] = $comment_count;

            $return[] = $wire;
        }
    } else {
            $msg = elgg_echo('thewire:noposts');
            throw new InvalidParameterException($msg);
    }
	
	return $return;
}
				
elgg_ws_expose_function('wire.get_posts',
				"wire_get_posts",
				array(	'context' => array ('type' => 'string', 'required' => false, 'default' => 'all'),
						'limit' => array ('type' => 'int', 'required' => false),
						'offset' => array ('type' => 'int', 'required' => false),
						'username' => array ('type' => 'string', 'required' =>false),
					),
				"Read latest wire post",
				'GET',
				true,
				true);

/**
 * Web service for delete a wire post
 *
 * @param string $username username
 * @param string $wireid GUID of wire post to delete
 * @return bool
 * @throws InvalidParameterException
 */
function wire_delete($username, $wireid) {
	$user = get_user_by_username($username);
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}
	
	$thewire = get_entity($wireid);
	$return['success'] = false;
	if ($thewire->getSubtype() == "thewire" && $thewire->canEdit($user->guid)) {
		$children = elgg_get_entities_from_relationship(array(
			'relationship' => 'parent',
			'relationship_guid' => $wireid,
			'inverse_relationship' => true,
		));
		if ($children) {
			foreach ($children as $child) {
				$child->reply = false;
			}
		}
		$rowsaffected = $thewire->delete();
		if ($rowsaffected > 0) {
			$return['success'] = true;
			$return['message'] = elgg_echo("thewire:deleted");
		} else {
			$return['message'] = elgg_echo("thewire:notdeleted");
		}
	}
	else {
		$return['message'] = elgg_echo("thewire:notdeleted");
	}
	return $return;
} 
				
elgg_ws_expose_function('wire.delete_posts',
				"wire_delete",
				array('username' => array ('type' => 'string'),
						'wireid' => array ('type' => 'int'),
					),
				"Delete a wire post",
				'POST',
				true,
				false);


/**
 * @param $guid
 * @param $username
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function wire_get_comments($guid, $username, $limit = 20, $offset = 0){
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $options = array(
        "metadata_name" => "wire_thread",
        "metadata_value" => $guid,
        "type" => "object",
        "subtype" => "thewire",
        "limit" => $limit,
        "offset" => $offset,
    );

    $comments = get_elgg_comments($options, 'elgg_get_entities_from_metadata');

    $return = array();
    if($comments){
        foreach($comments as $single){
            $comment['guid'] = $single->guid;
            $comment['description'] = $single->description;

            $owner = get_entity($single->owner_guid);
            $comment['owner']['guid'] = $owner->guid;
            $comment['owner']['name'] = $owner->name;
            $comment['owner']['username'] = $owner->username;
            $comment['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');

            $comment['time_created'] = time_ago($single->time_created);
            $comment['like_count'] = likes_count_number_of_likes($single->guid);
            $comment['like'] = checkLike($single->guid, $user->guid);
            $return[] = $comment;
        }
    } else {
        $msg = elgg_echo('generic_comment:none');
        throw new InvalidParameterException($msg);
    }
    return $return;
}
elgg_ws_expose_function('wire.get_comments',
    "wire_get_comments",
    array(	'guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get comments for a wire post",
    'GET',
    true,
    true);

/**
 * @param $parent_guid
 * @param $text
 * @param int $access
 * @param string $wireMethod
 * @param $username
 * @return mixed
 * @throws InvalidParameterException
 */
function wire_post_comment($parent_guid, $text, $access = ACCESS_PUBLIC, $wireMethod = "api", $username){

    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $return['success'] = false;
    if (empty($text)) {
        $return['message'] = elgg_echo("thewire:blank");
        return $return;
    }

    if ($access == 'ACCESS_FRIENDS') {
        $access_id = -2;
    } elseif ($access == 'ACCESS_PRIVATE') {
        $access_id = 0;
    } elseif ($access == 'ACCESS_LOGGED_IN') {
        $access_id = 1;
    } elseif ($access == 'ACCESS_PUBLIC') {
        $access_id = 2;
    } else {
        $access_id = -2;
    }



    $guid = thewire_save_post($text, $user->guid, $access_id, $parent_guid, $wireMethod);
    if (!$guid) {
        $return['message'] = elgg_echo("thewire:error");
        return $return;
    }
    $return['success'] = true;
    return $return;
}

elgg_ws_expose_function('wire.post_comment',
    "wire_post_comment",
    array(	'parent_guid' => array ('type' => 'int'),
        'text' => array ('type' => 'string'),
        'access' => array ('type' => 'string', 'required' => false),
        'wireMethod' => array ('type' => 'string', 'required' => false),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "Post a comment on a wire post",
    'POST',
    true,
    true);


/////////////////////////////////////////////////////////////////////////////////////

function get_elgg_comments(array $options = array(), $getter = 'elgg_get_entities') {

    global $autofeed;
    $autofeed = true;

    $offset_key = isset($options['offset_key']) ? $options['offset_key'] : 'offset';

    $defaults = array(
        'offset' => (int) max(get_input($offset_key, 0), 0),
        'limit' => (int) max(get_input('limit', 10), 0),
        'full_view' => TRUE,
        'list_type_toggle' => FALSE,
        'pagination' => TRUE,
    );

    $options = array_merge($defaults, $options);

    $options['count'] = TRUE;
    $count = $getter($options);

    $options['count'] = FALSE;
    $entities = $getter($options);

    $options['count'] = $count;

    return $entities;
}

function elgg_list_entities_annotations(array $options = array(), $getter = 'elgg_get_entities',
                                        $viewer = 'elgg_view_entity_list') {

    global $autofeed;
    $autofeed = true;

    $offset_key = isset($options['offset_key']) ? $options['offset_key'] : 'offset';

    $defaults = array(
        'offset' => (int) max(get_input($offset_key, 0), 0),
        'limit' => (int) max(get_input('limit', 10), 0),
        'full_view' => TRUE,
        'list_type_toggle' => FALSE,
        'pagination' => TRUE,
    );

    $options = array_merge($defaults, $options);

    //backwards compatibility
    if (isset($options['view_type_toggle'])) {
        $options['list_type_toggle'] = $options['view_type_toggle'];
    }

    $options['count'] = TRUE;
    $count = elgg_get_annotations($options);

    $options['count'] = FALSE;
    $entities = elgg_get_annotations($options);

    $options['count'] = $count;

    return $entities;
}