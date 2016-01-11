<?php

elgg_ws_expose_function('site.river_short',
    'site_river_short',
    array(
        'username' => array ('type' => 'string', 'required' =>true),
        'limit' => array ('type' => 'int', 'required' => false),
        'offset' => array ('type' => 'int', 'required' => false),
    ),
    "Read latest news feed",
    'GET',
    true,
    true);

/**
 * @param $username
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function site_river_short($username, $limit=20, $offset=0) {
    global $jsonexport;

    $user = get_user_by_username($username);
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    $options = array(
        'offset' => $offset,
        'limit' => $limit,
    );

    $activities = elgg_get_river($options);

    $login_user = $user;
    $handle = getRiverActivity($activities, $user, $login_user);

    $jsonexport['activity'] = $handle;

    return $jsonexport['activity'];
}

elgg_ws_expose_function('post.get_comments',
    "post_get_comments",
    array(	'guid' => array ('type' => 'string'),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 10),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get comments for a post",
    'GET',
    true,
    false);

/**
 * @param $guid
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function post_get_comments($guid, $limit = 99, $offset = 0){

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

elgg_ws_expose_function('album.getid',
    'album_getid',
    array(
        'username' => array ('type' => 'string', 'required' =>true),
    ),
    'Get album id',
    'GET',
    true,
    true);

/**
 * @param $username
 * @return array
 * @throws InvalidParameterException
 * @throws SecurityException
 */
function album_getid($username) {
    $user = get_user_by_username($username);
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    $albums = elgg_list_entitiesApi(array(
        'type' => 'object',
        'subtype' => 'album',
        'full_view' => false,
        'list_type' => 'gallery',
        'list_type_toggle' => false,
        'gallery_class' => 'tidypics-gallery',
        'owner_guids' => $user->guid,
        'title' => 'river',
    ));

    $return = array();
    $return['guid'] = 0;
    foreach($albums AS $album){
        if ($album->title == 'river' && $album->access_id != 0) {
            $return['guid'] = $album->guid;
        }
    }

    if ($return['guid'] == 0) {
        $new_album = new TidypicsAlbum();
        $new_album->owner_guid = $user->guid;
        $new_album->access_id = -2;
        $new_album->title = 'river';
        $new_album->description = 'shared with friends';

        if (!$new_album->save()) {
            register_error(elgg_echo("album:error"));
            forward(REFERER);
        } else {
            $return['guid'] = $new_album->guid;
        }
    }


    return $return;
}

elgg_ws_expose_function('user.river_short',
    'user_river_short',
    array(
        'username' => array ('type' => 'string', 'required' =>true),
        'limit' => array ('type' => 'int', 'required' => false),
        'offset' => array ('type' => 'int', 'required' => false),
    ),
    "Read latest news feed",
    'GET',
    true,
    true);

/**
 * @param $username
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function user_river_short($username, $limit=20, $offset=0) {
    global $jsonexport;

    $login_user = elgg_get_logged_in_user_entity();
    $user = get_user_by_username($username);
    if (!$login_user || !$user) {
        throw new InvalidParameterException('registration:username not valid');
    }

    $options = array(
        'offset' => $offset,
        'limit' => $limit,
        'subject_guid' => $user->guid,
    );

    $activities = elgg_get_river($options);
    $handle = getRiverActivity($activities, $user, $login_user);

    $jsonexport['activity'] = $handle;
    return $jsonexport['activity'];
}

elgg_ws_expose_function('count.like_comment',
    'count_like_comment',
    array(
        'entity_guid' => array ('type' => 'int', 'required' => true),
    ),
    "Get number count like and comment",
    'GET',
    true,
    true);

/**
 * @param $entity_guid
 * @return array
 * @throws InvalidParameterException
 * @internal param $username
 * @internal param int $limit
 * @internal param int $offset
 */
function count_like_comment($entity_guid)
{
    $user = elgg_get_logged_in_user_entity();
    if (!$user) {
        throw new InvalidParameterException('registration:username not valid');
    }

    $like_count = likes_count_number_of_likes($entity_guid);
    $comment_count = api_get_image_comment_count($entity_guid);
    $like = checkLike($entity_guid, $user->guid);

    $result['likecount'] = $like_count;
    $result['commentcount'] = $comment_count;
    $result['like'] = $like;

    return $result;
}

///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

function checkLike($like_count_guid, $user_guid)
{
    $like = '';
    if (likes_count_number_of_likes($like_count_guid) > 0) {
        $list = elgg_get_annotations(array('guid' => $like_count_guid, 'annotation_name' => 'likes', 'limit' => 99));
        foreach ($list as $singlelike) {
            if ($singlelike->owner_guid == $user_guid) {
                $like = 'like';
            }
        }
        return $like;
    } else {
        return $like;
    }
}

function elgg_list_entitiesApi(array $options = array(), $getter = 'elgg_get_entities') {

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
    $count = $getter($options);

    $options['count'] = FALSE;
    $entities = $getter($options);

    $options['count'] = $count;

    return $entities;
}

function api_get_image_comment_count($image_guid) {
    $comments = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'comment',
        'container_guid' => $image_guid,
    ));

    return sizeof($comments);
}

function createProfileImageBatch($guid, $timePost, $userEntity) {
    $image['guid'] = $guid;
    $image['container_guid'] = $guid;
    $image['title'] = $userEntity->name;
    $image['time_create'] = $timePost;
    $image['owner_guid'] = $userEntity->guid;
    $image['icon_url'] = $userEntity->getIconURL('large');
    $image['img_url'] = $userEntity->getIconURL('master');
    $image['like_count'] = likes_count_number_of_likes($guid);
    $image['comment_count'] = api_get_image_comment_count($guid);
    $image['like'] = checkLike($guid, $userEntity->guid);

    return $image;
}

function createAlbumCoverImage($album_cover, $image_owner_join_date, $icon_url, $img_url, $userEntity) {
    $image['guid'] = $album_cover->guid;
    $image['container_guid'] = $album_cover->container_guid;
    $image['title'] = $album_cover->title;
    $image['time_create'] = time_ago($image_owner_join_date);
    $image['owner_guid'] = $album_cover->owner_guid;
    $image['icon_url'] = $icon_url;
    $image['img_url'] = $img_url;
    $image['like_count'] = likes_count_number_of_likes($album_cover->guid);
    $image['comment_count'] = api_get_image_comment_count($album_cover->guid);
    $image['like'] = checkLike($album_cover->guid, $userEntity->guid);

    return $image;
}

function getRiverActivity($activities, $user, $login_user) {
    $handle = array();
    $site_url = get_config('wwwroot');

    foreach($activities AS $activity){
        $userEntity = get_entity($activity->subject_guid);

        $avatar_url = elgg_format_url($userEntity->getIconURL());

        $object_guid = $activity->object_guid;
        $entity = get_entity($object_guid);

        $activity_like= checkLike($activity->object_guid, $login_user->guid);
        $activity_comment_count = getCommentCount($activity);
        $activity_like_count = likes_count_number_of_likes($activity->object_guid);

        $entityString = "";
        $entityTxt = "";
        $icon_url="";
        $img_url="";
        $batch_images = array();
        $isObject = false;

        if ($activity->subtype == "tidypics_batch"){
            $isObject = true;
            $batch_images = getBatchImages($activity->object_guid, $user->guid);

            if (sizeof($batch_images) > 1) {
                $entityTxt = "added the photos to the album.";

                $album_guid = $batch_images[0]['container_guid'];

                $activity_like= checkLike($album_guid, $login_user->guid);
                $activity_comment_count = api_get_image_comment_count($album_guid);
                $activity_like_count = likes_count_number_of_likes($album_guid);

            } else if (sizeof($batch_images) == 1) {
                $activity_like_count = $batch_images[0]['like_count'];
                $activity_comment_count = $batch_images[0]['comment_count'];
                $activity_like = $batch_images[0]['like'];

                $img = get_entity($batch_images[0]['guid']);
                $original_file_name = $img->originalfilename;
                $entityTxt = "added the photo " . $original_file_name . " to the album.";

                $entityString = $img->description;
            }

        }  else if ($activity->action_type == "create" && $activity->subtype == "album") {
            $isObject = true;

            $album = get_entity($activity->object_guid);
            $entityTxt = "created a new photo album " . $album->title;

            $album_cover = $album->getCoverImage();

            $file_name = $album_cover->getFilenameOnFilestore();

            $image_join_date = $album_cover->time_created;

            $position = strrpos($file_name, '/');
            $position = $position + 1;
            $icon_file_name = substr_replace($file_name, 'largethumb', $position, 0);

            $image_icon_url = $site_url . 'services/api/rest/json/?method=image.get_post';
            $image_icon_url = $image_icon_url . '&joindate=' . $image_join_date . '&guid=' . $album_cover->guid
                . '&name=' . $icon_file_name;
            $image_icon_url = elgg_format_url($image_icon_url);

            $image_url = $site_url . 'services/api/rest/json/?method=image.get_post';
            $image_url = $image_url . '&joindate=' . $image_join_date . '&guid=' . $album_cover->guid
                . '&name=' . $file_name;
            $image_url = elgg_format_url($image_url);

            $batch_images[] = createAlbumCoverImage($album_cover, $image_join_date, $image_icon_url, $image_url, $user);

        } else if ($activity->action_type == "friend" && $activity->subtype == ""){
            $isObject = true;
            $msg = "is now a friend with";
            $friendEntity = get_entity($activity->object_guid);
            $entityTxt = $msg . " " .$friendEntity->name;
            $icon_url = $friendEntity->getIconURL();
            $icon_url = elgg_format_url($icon_url);
            $img_url = $friendEntity->getIconURL('master');
            if (strpos($img_url, 'user/defaultmaster.gif') !== false) {
                $img_url = $friendEntity->getIconURL('large');
            }
            $img_url = elgg_format_url($img_url);

        } else if ($activity->action_type == "update" && $activity->view == 'river/user/default/profileiconupdate') {
            $isObject = true;
            $entityTxt = "has a new avatar";
            $entityString = "";
            $timeCreated = time_ago($activity->posted);
            $batch_images[] = createProfileImageBatch($activity->object_guid, $timeCreated, $userEntity);

        } else if ($activity->subtype == "comment" && $activity->action_type == "comment" && $activity->view == 'river/object/comment/album') {
            $isObject = true;
            $album_comment = get_entity($activity->object_guid);
            $album_entity = '';
            if ($album_comment) {
                $album_entity = get_entity($album_comment->container_guid);
            }
            $entityTxt = 'comment on album ' . $album_entity->title;
            $entityString = $album_comment->description;
            $entityString = strip_tags($entityString);

        } else if ($activity->subtype == "file" && $activity->action_type == "create" && $activity->view == 'river/object/file/create') {
            $isObject = true;
            $file_entity = get_entity($activity->object_guid);
            $entityTxt = 'uploaded the file ';
            $entityString = $file_entity->title;
            $entityString = strip_tags($entityString);

        } else if ($activity->subtype == "comment" && $activity->action_type == "comment" && $activity->view == 'river/object/comment/create') {
            $isObject = true;
            $file_entity = get_entity($activity->target_guid);

            if ($file_entity->title) {
                $entityTxt = 'comment on ' . $file_entity->title;
            } else {
                $entityTxt = 'comment on post';
            }
            $entityString = $entity->description;
            $entityString = strip_tags($entityString);

        } else if ($activity->action_type == "comment" && $activity->subtype == "comment" && $activity->view == 'river/object/comment/image') {
            $isObject = true;
            $image_entity = get_entity($activity->target_guid);
            $image_file_name = $image_entity->originalfilename;
            if ($image_file_name) {
                $entityTxt = 'comment on photo ' . $image_file_name;
            } else {
                $entityTxt = 'comment on post';
            }
            $entityString = $entity->description;
            $entityString = strip_tags($entityString);

        } else if ($activity->action_type == "create" && $activity->subtype == "thewire" && $activity->view == 'river/object/thewire/create') {
            $isObject = true;
            $entityTxt = 'posted to the wire';
            $entityString = $entity->description;
            $entityString = strip_tags($entityString);

        } else {
            //$isObject = true;
            //$entityTxt = get_object_entity_as_row($activity->object_guid)->description;
        }

        if ($isObject) {
            $handle[] = array(
                'id' => $activity->id,
                'time' => time_ago($activity->posted),
                'type' => $activity->type,
                'sub_type' => $activity->subtype,
                'action_type' => $activity->action_type,
                'object_guid' => $activity->object_guid,
                'subject_guid' => $activity->subject_guid,
                'view' => $activity->view,
                'string' => $entityString,
                'txt' => $entityTxt,
                'name' => $userEntity->name,
                'username' => $userEntity->username,
                'avatar_url' => $avatar_url,
                'icon_url' => $icon_url,
                'img_url' => $img_url,
                'like_count' => $activity_like_count,
                'like' => $activity_like,
                'comment_count' => $activity_comment_count,
                'batch_images' => $batch_images
            );
        }
    }

    return $handle;
}

/**
 * @param $activity
 * @return int
 */
function getCommentCount($activity) {
    if ($activity->subtype == "thewire") {
        $options = array(
            "metadata_name" => "wire_thread",
            "metadata_value" => $activity->object_guid,
            "type" => "object",
            "subtype" => "thewire",
            "limit" => 99,
        );

        $comments = get_elgg_comments($options, 'elgg_get_entities_from_metadata');
    } else {
        $comments = elgg_get_entities(array(
            'type' => 'object',
            'subtype' => 'comment',
            'container_guid' => $activity->object_guid,
            "limit" => 99,
        ));
    }

    $comment_count = sizeof($comments);

    if ($comment_count >= 1 && $activity->subtype == "thewire") {
        $comment_count = $comment_count - 1;
    }

    return $comment_count;
}

function time_ago($time,$granularity=2) {

    $difference = time() - $time;
    $periods = array('decade' => 315360000,
        'year' => 31536000,
        'month' => 2628000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1);
    if ($difference < 30) { // less than 30 seconds ago, let's say "just now
        $retval = "just now";
        return $retval;
    } else {
        $retval = '';
        foreach ($periods as $key => $value) {
            if ($difference >= $value) {
                $time = floor($difference/$value);
                $difference %= $value;
                $retval .= ($retval ? ' ' : '').$time.' ';
                $retval .= (($time > 1) ? $key.'s' : $key);
                $granularity--;
            }
            if ($granularity == '0') { break; }
        }
        return $retval.' ago';
    }
}

//get batch images
function getBatchImages($id, $user_guid) {

    // Get images related to this batch
    $results = elgg_get_entities_from_relationship(array(
        'relationship' => 'belongs_to_batch',
        'relationship_guid' => $id,
        'inverse_relationship' => true,
        'type' => 'object',
        'subtype' => 'image',
        'offset' => 0,
    ));

    $site_url = get_config('wwwroot');
    if ($results) {
        foreach ($results AS $result) {

            if ($result->enabled == "yes") {

                $file_name = $result->getFilenameOnFilestore();

                $image_owner_guid_entity = $result->getOwnerEntity();
                $image_owner_guid = $image_owner_guid_entity->guid;
                $image_owner_join_date = $image_owner_guid_entity->time_created;

                $position = strrpos($file_name, '/');
                $position = $position + 1;
                $icon_file_name = substr_replace($file_name, 'largethumb', $position, 0);

                $image_icon_url = $site_url . 'services/api/rest/json/?method=image.get_post';
                $image_icon_url = $image_icon_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
                    . '&name=' . $icon_file_name;
                $icon_url = elgg_format_url($image_icon_url);

                $image_url = $site_url . 'services/api/rest/json/?method=image.get_post';
                $image_url = $image_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
                    . '&name=' . $file_name;
                $img_url = elgg_format_url($image_url);

                $image['guid'] = $result->guid;
                $image['container_guid'] = $result->container_guid;
                $image['title'] = $result->title;
                $image['time_create'] = $image_owner_join_date;
                $image['owner_guid'] = $result->owner_guid;
                $image['icon_url'] =$icon_url;
                $image['img_url'] = $img_url;
                $image['like_count'] = likes_count_number_of_likes($result->guid);
                $image['comment_count'] = api_get_image_comment_count($result->guid);
                $image['like'] = checkLike($result->guid, $user_guid);
            }

            $return[] = $image;
        }
    }

    return $return;
}