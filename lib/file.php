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
function file_get_files($context, $username, $limit = 20, $offset = 0, $group_guid=0) {
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
		$latest_file = elgg_get_entities($params);
	} else if($context == "mine" || $context == "user"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'file',
			'owner_guid' => $user->guid,
			'limit' => $limit,
            'offset' => $offset,
			'full_view' => FALSE,
		);
		$latest_file = elgg_get_entities($params);
	} else if($context == "group"){
		$params = array(
			'types' => 'object',
			'subtypes' => 'file',
			'container_guid'=> $group_guid,
			'limit' => $limit,
            'offset' => $offset,
			'full_view' => FALSE,
		);
		$latest_file = elgg_get_entities($params);
	}


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
			$file['tags'] = $single->tags;
			
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
			$file['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');

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
                $file['file_icon'] = getProfileIcon($single); //$single->getIconURL('small');
                $file['file_icon_large'] = getProfileIcon($single, 'large'); //$single->getIconURL('large');
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
		'username' => array ('type' => 'string', 'required' => true),
		'limit' => array ('type' => 'int', 'required' => false, 'default' => 0),
		'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
		'group_guid' => array ('type'=> 'int', 'required'=>false, 'default' =>0),
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


/**
 * @param $guid
 * @param $username
 * @return array
 * @throws InvalidParameterException
 */
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
	$file['tags'] = $single->tags;
	
    if ($single->description == null) {
        $file['description'] = '';
    } else {
        $file['description'] = strip_tags($single->description);
    }


    $owner = get_entity($single->owner_guid);
    $file['owner']['guid'] = $owner->guid;
    $file['owner']['name'] = $owner->name;
    $file['owner']['username'] = $owner->username;
    $file['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');

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
        $file['file_icon'] = getProfileIcon($owner); //$single->getIconURL('small');
        $file['file_icon_large'] = getProfileIcon($single, 'large'); //$single->getIconURL('large');
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

/**
 * @param $title
 * @param $description
 * @param $username
 * @param $access
 * @param $tags
 * @return array
 * @throws InvalidParameterException
 * @internal param $guid
 * @internal param $size
 */
function file_save_post($title, $description, $username, $access, $tags) {
	$return = array();
	if(!$username) {
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
		if (!$user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}
	$loginUser = elgg_get_logged_in_user_entity();
	$container_guid = $loginUser->guid;

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

	$file = $_FILES["upload"];

	if (empty($file)) {
		$response['status'] = 1;
		$response['result'] = elgg_echo("file:blank");
		return $response;
	}

	$new_file = true;

	if ($new_file) {
		$file = new ElggFile();
		$file->subtype = "file";

		// if no title on new upload, grab filename
		if (empty($title)) {
			$title = htmlspecialchars($_FILES['upload']['name'], ENT_QUOTES, 'UTF-8');
		}
	}

	$file->title = $title;
	$file->description = $description;
	$file->access_id = $access_id;
	$file->container_guid = $container_guid;
	$file->tags = string_to_tag_array($tags);

	// we have a file upload, so process it
	if (isset($_FILES['upload']['name']) && !empty($_FILES['upload']['name'])) {

		$prefix = "file/";

		$filestorename = elgg_strtolower(time().$_FILES['upload']['name']);
		$file->setFilename($prefix . $filestorename);
		$file->originalfilename = $_FILES['upload']['name'];

		$mime_type = $file->detectMimeType($_FILES['upload']['tmp_name'], $_FILES['upload']['type']);

		$file->setMimeType($mime_type);
		$file->simpletype = elgg_get_file_simple_type($mime_type);

		// Open the file to guarantee the directory exists
		$file->open("write");
		$file->close();
		move_uploaded_file($_FILES['upload']['tmp_name'], $file->getFilenameOnFilestore());

		$fileSaved = $file->save();

		// if image, we need to create thumbnails (this should be moved into a function)
		if ($fileSaved && $file->simpletype == "image") {
			$file->icontime = time();

			$thumbnail = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 60, 60, true);
			if ($thumbnail) {
				$thumb = new ElggFile();
				$thumb->setMimeType($_FILES['upload']['type']);

				$thumb->setFilename($prefix."thumb".$filestorename);
				$thumb->open("write");
				$thumb->write($thumbnail);
				$thumb->close();

				$file->thumbnail = $prefix."thumb".$filestorename;
				unset($thumbnail);
			}

			$thumbsmall = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 153, 153, true);
			if ($thumbsmall) {
				$thumb->setFilename($prefix."smallthumb".$filestorename);
				$thumb->open("write");
				$thumb->write($thumbsmall);
				$thumb->close();
				$file->smallthumb = $prefix."smallthumb".$filestorename;
				unset($thumbsmall);
			}

			$thumblarge = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 600, 600, false);
			if ($thumblarge) {
				$thumb->setFilename($prefix."largethumb".$filestorename);
				$thumb->open("write");
				$thumb->write($thumblarge);
				$thumb->close();
				$file->largethumb = $prefix."largethumb".$filestorename;
				unset($thumblarge);
			}
		} elseif ($file->icontime) {
			// if it is not an image, we do not need thumbnails
			unset($file->icontime);

			$thumb = new ElggFile();

			$thumb->setFilename($prefix . "thumb" . $filestorename);
			$thumb->delete();
			unset($file->thumbnail);

			$thumb->setFilename($prefix . "smallthumb" . $filestorename);
			$thumb->delete();
			unset($file->smallthumb);

			$thumb->setFilename($prefix . "largethumb" . $filestorename);
			$thumb->delete();
			unset($file->largethumb);
		}
	}  else {
		// not saving a file but still need to save the entity to push attributes to database
		$fileSaved = $file->save();
	}

	// handle results differently for new files and file updates
	if ($new_file) {
		if ($fileSaved) {
			elgg_create_river_item(array(
				'view' => 'river/object/file/create',
				'action_type' => 'create',
				'subject_guid' => elgg_get_logged_in_user_guid(),
				'object_guid' => $file->guid,
			));

			$return['guid'] = $file->guid;
			$return['message'] = 'success';
		} else {
			// failed to save file object - nothing we can do about this
			$return['guid'] = 0;
			$return['message'] = elgg_echo("file:uploadfailed");
		}

	} else {
		$return['guid'] = 0;
		$return['message'] = elgg_echo("file:uploadfailed");
	}

	return $return;
}

elgg_ws_expose_function('file.save_post',
	"file_save_post",
	array(
		'title' => array ('type' => 'string', 'required' => true),
		'description' => array ('type' => 'string', 'required' => false),
		'username' => array ('type' => 'string', 'required' => true),
		'access' => array ('type' => 'string', 'required' => true, 'default'=>ACCESS_FRIENDS),
		'tags' => array ('type' => 'string', 'required' => false, 'default'=>''),
	),
	"Upload file post",
	'POST',
	true,
	true);
	
/**
 * @param $guid
 * @param $title
 * @param $description
 * @param $username
 * @param $access
 * @param $tags
 * @return array
 * @throws InvalidParameterException
 */
	
function file_update_post($guid, $title, $description, $username, $access, $tags) {
    $return = array();
	if(!$username) {
		$user = elgg_get_logged_in_user_entity();
	} else {
		$user = get_user_by_username($username);
		if (!$user) {
			throw new InvalidParameterException('registration:usernamenotvalid');
		}
	}
	$container_guid = $user->guid;

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

    $single = get_entity($guid);
    if (!elgg_instanceof($single, 'object', 'file')) {
        exit;
    }
	
	$single->title = $title;
	$single->description = $description;
	$single->access_id = $access_id;
	$single->tags = string_to_tag_array($tags);
	$fileSaved = $single->save();
	
	if ($fileSaved) {
			$return['message'] = "File update success";
		} else {
			$return['message'] = "File update failed";
		}
		
	return $return;
}

elgg_ws_expose_function('file.update_post',
	"file_update_post",
	array(
		'guid' => array ('type'=> 'int', 'required'=>true),
		'title' => array ('type' => 'string', 'required' => true),
		'description' => array ('type' => 'string', 'required' => true),
		'username' => array ('type' => 'string', 'required' => true),
		'access' => array ('type' => 'string', 'required' => true, 'default'=>ACCESS_FRIENDS),
		'tags' => array ('type' => 'string', 'required' => false, 'default'=>''),
	),
	"Update file post",
	'POST',
	true,
	true);


/**
 * Delete file entity
 * @parameter guid
 */
function file_delete_post($guid,$username){
	$file = get_entity($guid);
	$user = get_user_by_username($username);
	if (elgg_instanceof($file, 'object', 'file') && $file->canEdit($user->guid)) {
			if (!$file->delete()) {
				$return['success'] = false;
				$return['message'] = elgg_echo("file:deletefailed");
			} else {
				$return['success'] = true;
				$return['message'] = elgg_echo("file:deleted");
			}
	} else {
		$return['success'] = false;
		$return['message'] = elgg_echo("file:deletefailed");
	}
	return $return;
}

elgg_ws_expose_function('file.delete_post',
	"file_delete_post",
	array(
		'guid' => array ('type'=> 'int', 'required'=>true),
		'username' => array ('type'=> 'string', 'required'=>true),
	),
	"Delete file post",
	'GET',
	true,
	true);