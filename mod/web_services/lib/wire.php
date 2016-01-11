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
function wire_get_posts($context, $limit = 10, $offset = 0, $username) {
    $user = get_user_by_username($username);
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }
		
	if($context == "all"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'thewire',
			'limit' => $limit,
			'full_view' => FALSE,
		);
	}
	if($context == "mine" || $context == "user"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'thewire',
			'owner_guid' => $user->guid,
			'limit' => $limit,
			'full_view' => FALSE,
		);
	}

	$latest_wire = elgg_get_entities($params);
		
	if($context == "friends"){
		$latest_wire = get_user_friends_objects($user->guid, 'thewire', $limit, $offset);
	}

    if($latest_wire){
        foreach($latest_wire as $single ) {
            $wire['guid'] = $single->guid;

            $owner = get_entity($single->owner_guid);
            $wire['owner']['guid'] = $owner->guid;
            $wire['owner']['name'] = $owner->name;
            $wire['owner']['avatar_url'] = get_entity_icon_url($owner,'small');

            $wire['time_created'] = (int)$single->time_created;
            $wire['description'] = $single->description;
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
				"Read lates wire post",
				'GET',
				true,
				true);



//function wire_get_user_river($context, $limit = 10, $offset = 0, $username) {
//    $user = get_user_by_username($username);
//    if (!$user) {
//        throw new InvalidParameterException('registration:usernamenotvalid');
//    }
//
//    if ($user) {
//        $postSize = 0;
//        do {
//            $params = array(
//                'types' => 'object',
//                'subject_guid' => $user->guid,
//                'limit' => $limit,
//                'offset' => $offset,
//                'full_view' => FALSE,
//            );
//
//
//            $latest_post = elgg_get_river($params);
//
//            if ($latest_post) {
//                foreach ($latest_post as $single) {
//
//                    $sub_type = $single->subtype;
//
//                    $url = $user->getIconURL();
//                    $url = elgg_format_url($url);
//                    $avatar_url = $url;
//
//                    $icon_url = "";
//                    $img_url = "";
//                    $batch_images = array();
//
//                    $entity_guid = $single->object_guid;
//                    $entity = get_entity($entity_guid);
//                    $like = '';
//                    if (likes_count($entity) > 0) {
//                        $list = elgg_get_annotations(array('guid' => $entity_guid, 'annotation_name' => 'likes', 'limit' => 99));
//                        foreach ($list as $singlelike) {
//                            if ($singlelike->owner_guid == $user->guid) {
//                                $like = 'like';
//                            }
//                        }
//                    }
//
//                    $options = array(
//                        "metadata_name" => "wire_thread",
//                        "metadata_value" => $entity_guid,
//                        "type" => "object",
//                        "subtype" => "thewire",
//                        "limit" => 99,
//                    );
//
//                    $comments = get_elgg_comments($options, 'elgg_get_entities_from_metadata');
//                    $comment_count = sizeof($comments);
//                    if ($comment_count >= 1) {
//                        $comment_count = $comment_count - 1;
//                    }
//
//                    $entityString = "";
//                    $entityTxt = "";
//                    $like_count_guid = "";
//                    $like_count_guid = $single->object_guid;
//                    $isObject = false;
//
//                    if ($sub_type == "tidypics_batch") {
//                        $singleImageId = (int)$single->object_guid;
//                        $singleImageId = $singleImageId - 1;
//                        $singleImageEntity = get_entity($singleImageId);
//                        if ($singleImageEntity) {
//                            $isObject = true;
//
//                            $batch_images = getBatchImagesList($single->object_guid);
//
//                            $file_name = $singleImageEntity->getFilenameOnFilestore();
//                            $file_name = str_replace(' ', '%20', $file_name);
//                            $original_file_name = $singleImageEntity->originalfilename;
//                            $site_url = get_config('wwwroot');
//                            $image_owner_guid_entity = $singleImageEntity->getOwnerEntity();
//                            $image_owner_guid = $image_owner_guid_entity->guid;
//                            $image_owner_join_date = $image_owner_guid_entity->get('time_created');
//
//                            $position = strrpos($file_name, '/');
//                            $position = $position + 1;
//                            $icon_file_name = substr_replace($file_name, 'largethumb', $position, 0);
//
//                            $image_icon_url = $site_url . 'mod/tidypics/imagedirect.php?lastcache=1430169821';
//                            $image_icon_url = $image_icon_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
//                                . '&name=' . $icon_file_name;
//                            $icon_url = elgg_format_url($image_icon_url);
//
//                            $image_url = $site_url . 'mod/tidypics/imagedirect.php?lastcache=1430169821';
//                            $image_url = $image_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
//                                . '&name=' . $file_name;
//                            $img_url = elgg_format_url($image_url);
//
//                            if (sizeof($batch_images) > 1) {
//                                $entityString = $singleImageEntity->description;
//                                $entityTxt = "Added the photos to the album.";
//                            } else {
//                                $entityString = $singleImageEntity->description;
//                                $entityTxt = "Added the photo " . $original_file_name . " to the album.";
//                            }
//
//                            $like_count_guid = $batch_images[0]['guid'];
//                            if (likes_count_number_of_likes($like_count_guid) > 0) {
//                                $list = elgg_get_annotations(array('guid' => $like_count_guid, 'annotation_name' => 'likes', 'limit' => 99));
//                                foreach ($list as $singlelike) {
//                                    if ($singlelike->owner_guid == $user->guid) {
//                                        $like = 'like';
//                                    }
//                                }
//                            }
//
//                            $comment_count = api_get_image_comment_count($like_count_guid);
//                        }
//
//                    } else if ($single->action_type == "friend" && $single->subtype == "") {
//                        $isObject = true;
//                        $msg = "is now a friend with";
//                        $friendEntity = get_entity($single->object_guid);
//                        $entityTxt = $msg . " " . $friendEntity->get("name");
//                    } else if ($single->action_type == "create" && $single->subtype == "album") {
//                        $isObject = true;
//                        $album = get_entity($single->object_guid);
//                        $entityTxt = "created a new photo album " . $album->get('title');
//                        $album_cover = $album->getCoverImage();
//
//                        $file_name = $album_cover->getFilenameOnFilestore();
//
//                        $site_url = get_config('wwwroot');
//                        $image_owner_guid_entity = $album_cover->getOwnerEntity();
//                        $image_owner_guid = $image_owner_guid_entity->guid;
//                        $image_owner_join_date = $image_owner_guid_entity->get('time_created');
//
//                        $position = strrpos($file_name, '/');
//                        $position = $position + 1;
//                        $icon_file_name = substr_replace($file_name, 'largethumb', $position, 0);
//
//                        $image_icon_url = $site_url . 'mod/tidypics/imagedirect.php?lastcache=1430169821';
//                        $image_icon_url = $image_icon_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
//                            . '&name=' . $icon_file_name;
//                        $icon_url = elgg_format_url($image_icon_url);
//
//                        $image_url = $site_url . 'mod/tidypics/imagedirect.php?lastcache=1430169821';
//                        $image_url = $image_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
//                            . '&name=' . $file_name;
//                        $img_url = elgg_format_url($image_url);
//
//                        $image['guid'] = $album_cover->get("guid");
//                        $image['title'] = $album_cover->get("title");
//                        $image['time_create'] = $image_owner_join_date;
//                        $image['owner_guid'] = $album_cover->get("owner_guid");
//                        $image['icon_url'] = $icon_url;
//                        $image['img_url'] = $img_url;
//
//                        $batch_images = $image;
//
//                    } else if ($single->action_type == "update") {
//                        $isObject = true;
//                        $entityTxt = "has a new avatar";
//                    } else if ($single->subtype == "image" && $single->action_type == "comment") {
//                        $isObject = true;
//                        $image_entity = get_entity($single->object_guid);
//                        $original_file_name = $image_entity->originalfilename;
//                        $image_comment = elgg_get_annotation_from_id($single->annotation_id);
//                        $entityTxt = 'Comment on image ' . $original_file_name . " " . $image_comment->value;
//                    } else if ($single->subtype == "album" && $single->action_type == "comment") {
//                        $isObject = true;
//                        $album_entity = get_entity($single->object_guid);
//                        $album_comment = elgg_get_annotation_from_id($single->annotation_id);
//                        $entityTxt = 'Comment on album ' . $album_entity->title . " " . $album_comment->value;
//                    } else {
//                        $isObject = true;
//                        $entityTxt = get_object_entity_as_row($single->object_guid)->description;
//                    }
//
//                    if ($isObject) {
//                        $postSize = $postSize + 1;
//                        $handle[] = array(
//                            'time' => time_ago($single->time_created),
//                            'type' => $single->type,
//                            'sub_type' => $single->subtype,
//                            'action_type' => $single->getType(),
//                            'object_guid' => $single->guid,
//                            'subject_guid' => $single->owner_guid,
//                            'view' => $single->view,
//                            'string' => $entityString,
//                            'txt' => $entityTxt,
//                            'name' => $user->name,
//                            'username' => $user->get('username'),
//                            'avatar_url' => $avatar_url,
//                            'icon_url' => $icon_url,
//                            'img_url' => $img_url,
//                            'like_count' => likes_count_number_of_likes($like_count_guid),
//                            'like' => $like,
//                            'comment_count' => $comment_count,
//                            'access_id' => $single->access_id,
//                            'batch_images' => $batch_images
//                        );
//                    }
//                }
//            }
//        } while ($postSize < $limit && $latest_post != null);
//
//    }
//    $jsonexport['activity'] = $handle;
//    return $jsonexport['activity'];
//}
//
//elgg_ws_expose_function('wire.get_user_river',
//    "wire_get_user_river",
//    array(	'context' => array ('type' => 'string', 'required' => true, 'default' => 'mine'),
//        'limit' => array ('type' => 'int', 'required' => true),
//        'offset' => array ('type' => 'int', 'required' => true),
//        'username' => array ('type' => 'string', 'required' =>true),
//    ),
//    "Read lates user river",
//    'GET',
//    true,
//    true);


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
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function wire_get_comments($guid, $limit = 99, $offset = 0){

    $options = array(
        "metadata_name" => "wire_thread",
        "metadata_value" => $guid,
        "type" => "object",
        "subtype" => "thewire",
        "limit" => 99,
    );

    $comments = get_elgg_comments($options, 'elgg_get_entities_from_metadata');

    if($comments){
        foreach($comments as $single){
            $comment['guid'] = $single->guid;
            $comment['description'] = $single->description;

            $owner = get_entity($single->owner_guid);
            $comment['owner']['guid'] = $owner->guid;
            $comment['owner']['name'] = $owner->name;
            $comment['owner']['username'] = $owner->username;
            $comment['owner']['avatar_url'] = get_entity_icon_url($owner,'small');

            $comment['time_created'] = time_ago($single->time_created);
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
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 10),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get comments for a wire post",
    'GET',
    true,
    false);

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
        $user = get_loggedin_user();
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

    $access_id = -2;
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