<?php
/**
 * @param $guid
 * @param $text
 * @param $username
 * @return mixed
 * @throws InvalidParameterException
 */
function file_post_comment($guid, $text, $username)
{
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

	if ($guid) {
		$entity = get_entity($guid);
	}

	if ($entity) {
		$return['success'] = false;
		if (empty($text)) {
			$return['message'] = elgg_echo("thefilecomment:blank");
			return $return;
		}

		if ($entity) {
			$comment = new ElggComment();
			$comment->description = $text;
			$comment->owner_guid = $user->guid;
			$comment->container_guid = $entity->guid;
			$comment->access_id = $entity->access_id;
			$guid_comment = $comment->save();

			if ($guid_comment) {
				$return['success'] = $guid_comment;
				elgg_create_river_item(array(
					'view' => 'river/object/comment/create',
					'action_type' => 'comment',
					'subject_guid' => $user->guid,
					'object_guid' => $guid_comment,
					'target_guid' => $entity->guid,
				));
			}
		}

		return $return;
	} else {
		$return['success'] = false;
		$return['message'] = 'Require guid from post';

		return $return;
	}
}

elgg_ws_expose_function('file.post_comment',
		"file_post_comment",
		array(	'guid' => array ('type' => 'int', 'required' => true),
				'text' => array ('type' => 'string', 'required' => true),
				'username' => array ('type' => 'string', 'required' => true),
		),
		"Post a comment on a file post",
		'POST',
		true,
		true);


/**
 * @param $context
 * @param int $limit
 * @param int $offset
 * @param $group_guid
 * @param $username
 * @return array
 * @throws InvalidParameterException
 */
function file_get_files($context, $limit = 20, $offset = 0, $group_guid, $username) {
	if(!$username) {
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
		if (!$user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}

	if($context == "all"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'file',
			'limit' => $limit,
            'offset' => $offset,
			'full_view' => FALSE,
		);
	}
	if($context == "mine" || $context == "user"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'file',
			'owner_guid' => $user->guid,
			'limit' => $limit,
            'offset' => $offset,
			'full_view' => FALSE,
		);
	}
	if($context == "group"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'file',
			'container_guid'=> $group_guid,
			'limit' => $limit,
            'offset' => $offset,
			'full_view' => FALSE,
		);
	}
	$latest_file = elgg_get_entities($params);

	if($context == "friends"){
		$latest_file = elgg_get_entities_from_relationship(array(
			'type' => 'object',
			'subtype' => 'file',
			'limit' => $limit,
			'offset' => $offset,
			'relationship' => 'friend',
			'relationship_guid' => $user->guid,
			'relationship_join_on' => 'container_guid',
		));
	}


	if($latest_file) {
        $site_url = get_config('wwwroot');
		foreach($latest_file as $single ) {
			$file['guid'] = $single->guid;
			$file['title'] = $single->title;

			if ($single->description == null) {
				$file['description'] = '';
			} else if (strlen($single->description) > 300) {
				$entityString = substr(strip_tags($single->description), 0, 300);
				$file['description'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';

			} else {
				$file['description'] = strip_tags($single->description);
			}

			$owner = get_entity($single->owner_guid);
			$file['owner']['guid'] = $owner->guid;
			$file['owner']['name'] = $owner->name;
            $file['owner']['username'] = $owner->username;
			$file['owner']['avatar_url'] = get_entity_icon_url($owner,'small');

			$file['container_guid'] = $single->container_guid;
			$file['access_id'] = $single->access_id;
			$file['time_created'] = time_ago($single->time_created);
			$file['time_updated'] = time_ago($single->time_updated);
			$file['last_action'] = time_ago($single->last_action);
			$file['MIMEType'] = $single->mimetype;
            $file['file_name'] = $single->originalfilename;
            $file['file_size'] = $single->getSize();

            $simpletype = $single->simpletype;
            if ($simpletype == "image") {
                $file['file_icon'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=smallthumb';
                $file['file_icon_large'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=largethumb';
                $file['file_url'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=original';
            } else {
                $file['file_icon'] = $single->getIconURL('small');
                $file['file_icon_large'] = $single->getIconURL('large');
                $file['file_url'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=' . $single->originalfilename;
            }

			$file['like_count'] = likes_count_number_of_likes($single->guid);

			$comments = elgg_get_entities(array(
				'type' => 'object',
				'subtype' => 'comment',
				'container_guid' => $single->guid,
				'limit' => 0,
			));

			$file['comment_count'] = sizeof($comments);
			$file['like'] = checkLike($single->guid, $user->guid);

			$return[] = $file;
		}
	}
	else {
		$msg = elgg_echo('file:none');
		throw new InvalidParameterException($msg);
	}
	return $return;
}

elgg_ws_expose_function('file.get_files',
	"file_get_files",
	array(
		'context' => array ('type' => 'string', 'required' => true, 'default' => 'all'),
		'limit' => array ('type' => 'int', 'required' => false, 'default' => 0),
		'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
		'group_guid' => array ('type'=> 'int', 'required'=>false, 'default' =>0),
		'username' => array ('type' => 'string', 'required' => true),
	),
	"Get file uploaded by all users",
	'GET',
	true,
	true);


/**
 * @param $guid
 * @param $size
 * @param $username
 * @throws InvalidParameterException
 */
function file_get_post($guid, $size, $username) {
	if(!$username) {
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
		if (!$user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}

	$file = get_entity($guid);
	if (!elgg_instanceof($file, 'object', 'file')) {
		exit;
	}

	$simpletype = $file->simpletype;
	if ($simpletype == "image") {

		// Get file thumbnail
		$mime = $file->getMimeType();
		if ($size == 'original') {
			$filename = $file->originalfilename;

			header("Content-type: $mime");
			if (strpos($mime, "image/") !== false || $mime == "application/pdf") {
				header("Content-Disposition: inline; filename=\"$filename\"");
			} else {
				header("Content-Disposition: attachment; filename=\"$filename\"");
			}
			header("Content-Length: {$file->getSize()}");

			while (ob_get_level()) {
				ob_end_clean();
			}
			flush();
			readfile($file->getFilenameOnFilestore());
			exit;
		} else {
			$thumbfile = $file->$size;
			// Grab the file
			if ($thumbfile && !empty($thumbfile)) {
				$readfile = new ElggFile();
				$readfile->owner_guid = $file->owner_guid;
				$readfile->setFilename($thumbfile);
				$mime = $file->getMimeType();
				$contents = $readfile->grabFile();

				// caching images for 10 days
				header("Content-type: $mime");
				header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime("+10 days")), true);
				header("Pragma: public", true);
				header("Cache-Control: public", true);
				header("Content-Length: " . strlen($contents));

				echo $contents;
				exit;
			}
		}
	} else {
        $mime = $file->getMimeType();
        if (!$mime) {
            $mime = "application/octet-stream";
        }

        $filename = $file->originalfilename;
        header("Pragma: public");

        header("Content-type: $mime");
        if (strpos($mime, "image/") !== false || $mime == "application/pdf") {
            header("Content-Disposition: inline; filename=\"$filename\"");
        } else {
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }
        header("Content-Length: {$file->getSize()}");

        while (ob_get_level()) {
            ob_end_clean();
        }
        flush();
        readfile($file->getFilenameOnFilestore());
        exit;
	}
}

elgg_ws_expose_function('file.get_post',
	"file_get_post",
	array(
		'guid' => array ('type'=> 'int', 'required'=>true),
		'size' => array ('type' => 'string', 'required' => true),
		'username' => array ('type' => 'string', 'required' => false),
	),
	"Get file post",
	'GET',
	true,
	true);


function file_get_file($guid, $username) {
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $site_url = get_config('wwwroot');

    $single = get_entity($guid);
    if (!elgg_instanceof($single, 'object', 'file')) {
        exit;
    }

    $file['guid'] = $single->guid;
    $file['title'] = $single->title;

    if ($single->description == null) {
        $file['description'] = '';
    } else {
        $file['description'] = strip_tags($single->description);
    }


    $owner = get_entity($single->owner_guid);
    $file['owner']['guid'] = $owner->guid;
    $file['owner']['name'] = $owner->name;
    $file['owner']['username'] = $owner->username;
    $file['owner']['avatar_url'] = get_entity_icon_url($owner,'small');

    $file['container_guid'] = $single->container_guid;
    $file['access_id'] = $single->access_id;
    $file['time_created'] = time_ago($single->time_created);
    $file['time_updated'] = time_ago($single->time_updated);
    $file['last_action'] = time_ago($single->last_action);
    $file['MIMEType'] = $single->mimetype;
    $file['file_name'] = $single->originalfilename;
    $file['file_size'] = $single->getSize();

    $simpletype = $single->simpletype;
    if ($simpletype == "image") {
        $file['file_icon'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=smallthumb';
        $file['file_icon_large'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=largethumb';
        $file['file_url'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=original';
    } else {
        $file['file_icon'] = $single->getIconURL('small');
        $file['file_icon_large'] = $single->getIconURL('large');
        $file['file_url'] = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $single->guid . '&size=' . $single->originalfilename;
    }

    $file['like_count'] = likes_count_number_of_likes($single->guid);
    $file['comment_count'] = api_get_image_comment_count($single->guid);
    $file['like'] = checkLike($single->guid, $user->guid);

    $return[] = $file;

    return $return;
}

elgg_ws_expose_function('file.get_file',
    "file_get_file",
    array(
        'guid' => array ('type'=> 'int', 'required'=>true),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "Get file data",
    'GET',
    true,
    true);


/**
 * @param $guid
 * @param $username
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function file_get_comments($guid, $username, $limit = 20, $offset = 0){
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $comments = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'comment',
        'container_guid' => $guid,
        'limit' => $limit,
        'offset' => $offset,
    ));

    $return = array();
    if($comments){
        foreach($comments as $single){
            $comment['guid'] = $single->guid;
            $comment['description'] = strip_tags($single->description);

            $owner = get_entity($single->owner_guid);
            $comment['owner']['guid'] = $owner->guid;
            $comment['owner']['name'] = $owner->name;
            $comment['owner']['username'] = $owner->username;
            $comment['owner']['avatar_url'] = get_entity_icon_url($owner,'small');

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

elgg_ws_expose_function('file.get_comments',
    "file_get_comments",
    array(	'guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get comments for a post",
    'GET',
    true,
    true);