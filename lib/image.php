<?php
/**
 * Created by PhpStorm.
 * Date: 12/4/2015
 * Time: 11:38 PM
 * @param int $access
 * @param $album_guid
 * @param $username
 * @param $title
 * @param $caption
 * @return
 * @throws InvalidParameterException
 */

function image_save_post($access = ACCESS_FRIENDS, $album_guid, $username, $title, $caption) {
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }
    $path = elgg_get_plugins_path();
    include_once $path.'tidypics/lib/upload.php';

    $file = $_FILES["Image"];
    $imageMime = getimagesize($file['tmp_name']); // get temporary file REAL info
    $file['type'] = $imageMime['mime'];

    if ($album_guid == 0) {
        $album_guid = album_getid($user->username);

    }

    if (empty($file)) {
        $response['status'] = 1;
        $response['result'] = elgg_echo("image:blank");
        return $response;
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

    $batch_id = time();
    $album = get_entity($album_guid);
    if (!$album) {
        $response['status'] = 1;
        $response['result'] = "Album not found";

        return $response;
    }

    $image = new TidypicsImage();
    $image->container_guid = $album_guid;
    $image->setMimeType($file['type']);
    $image->access_id = $access_id;
    $image->batch = $batch_id;
    $image->owner_guid = $user->guid;
    $image->description = $caption;
    $image->title = $title;

    try {
        $result = $image->save($file);

    } catch (Exception $e) {
        // remove the bits that were saved
        if ($image_entity = get_entity($image->getGUID())) {
            $recursive = true;
            if ($image_entity->delete($recursive)) {
                $result = false;
            }
        }
        echo $e->getMessage();
        $response['status'] = 1;
        $response['result'] = $e->getMessage();

        return $response;
        exit;
    }

    set_input('tidypics_action_name', 'tidypics_photo_upload');

    if ($result) {
        $album->prependImageList(array($image->guid));
        $img_river_view = elgg_get_plugin_setting('img_river_view', 'tidypics');

        $batch = new TidypicsBatch();
        $batch->access_id = $album->access_id;
        $batch->container_guid = $album->guid;
        $batch->owner_guid = $user->guid;
        $batch->description = $caption;

        if ($batch->save()) {
            add_entity_relationship($image->guid, "belongs_to_batch", $batch->getGUID());
            //foreach ($images as $image) {
            //    add_entity_relationship($image->guid, "belongs_to_batch", $batch->getGUID());
            //}
        }

        $img_river_view = elgg_get_plugin_setting('img_river_view', 'tidypics');
        // "added images to album" river
        if ($img_river_view == "batch" && $album->new_album == false) {
            elgg_create_river_item(array(
                'view' => 'river/object/tidypics_batch/create',
                'action_type' => 'create',
                'subject_guid' => $batch->getOwnerGUID(),
                'object_guid' => $batch->getGUID(),
            ));
        } else if ($img_river_view == "1" && $album->new_album == false) {
            elgg_create_river_item(array(
                'view' => 'river/object/tidypics_batch/create_single_image',
                'action_type' => 'create',
                'subject_guid' => $batch->getOwnerGUID(),
                'object_guid' => $batch->getGUID(),
                'target_guid' => $album->getGUID(),
            ));
        }

        // "created album" river
        if ($album->new_album) {
            $album->new_album = false;
            $album->first_upload = true;

            $album_river_view = elgg_get_plugin_setting('album_river_view', 'tidypics');
            if ($album_river_view != "none") {
                elgg_create_river_item(array(
                    'view' => 'river/object/album/create',
                    'action_type' => 'create',
                    'subject_guid' => $album->getOwnerGUID(),
                    'object_guid' => $album->getGUID(),
                    'target_guid' => $album->getGUID(),
                ));
            }

            // "created album" notifications
            // we throw the notification manually here so users are not told about the new album until
            // there are at least a few photos in it
            if ($album->shouldNotify()) {
                _elgg_services()->events->trigger('album_first', 'album', $album);
                $album->last_notified = time();
            }
        } else {
            // "added image to album" notifications
            if ($album->first_upload) {
                $album->first_upload = false;
            }

            if ($album->shouldNotify()) {
                _elgg_services()->events->trigger('album_more', 'album', $album);
                $album->last_notified = time();
            }
        }

        $response['status'] = 0;
        $response['result'] = $image->guid;

        return $response;
    }
}

elgg_ws_expose_function('image.save_post',
    "image_save_post",
    array(
        'access' => array ('type' => 'string', 'required' => true),
        'album_guid' => array ('type' => 'string', 'required' => true),
        'username' => array ('type' => 'string', 'required' => true),
        'title' => array ('type' => 'string', 'required' => false, 'default' => ''),
        'caption' => array ('type' => 'string', 'required' => false, 'default' => ''),
    ),
    "Post a image post",
    'POST',
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
function wire_get_image_comments($guid, $username, $limit = 20, $offset = 0){

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
        'offset' => $offset,
        'limit' => $limit,
    ));

    if ($comments) {
        foreach($comments as $comment){
            $response['guid'] = $comment->guid;
            $response['type'] = $comment->type;
            $response['id'] = $comment->guid;
            $response['container_guid'] = $comment->container_guid;
            $response['description'] = strip_tags($comment->description);

            $owner = get_entity($comment->owner_guid);
            $response['owner']['guid'] = $owner->guid;
            $response['owner']['name'] = $owner->name;
            $response['owner']['username'] = $owner->username;
            $response['owner']['avatar_url'] = getProfileIcon($owner); // $owner->getIconURL('small');

            $response['time_created'] = time_ago($comment->time_created);
            $comment['like_count'] = likes_count_number_of_likes($comment->guid);
            $comment['like'] = checkLike($comment->guid, $user->guid);

            $return[] = $response;
        }

    } else {
        $msg = elgg_echo('generic_comment:none');
        throw new InvalidParameterException($msg);
    }

    return $return;
}

elgg_ws_expose_function('wire.get_image_comments',
    "wire_get_image_comments",
    array(	'guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 10),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get image comments for a wire post",
    'GET',
    true,
    true);


/**
 * @param $guid
 * @param $text
 * @param $username
 * @return mixed
 * @throws InvalidParameterException
 */
function wire_post_image_comment($guid, $text, $username)
{

    if (!$username) {
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

    $return['success'] = false;
    if (empty($text)) {
        $return['message'] = elgg_echo("thewire:blank");
        return $return;
    }

    if ($entity) {
        $comment = new ElggComment();
        $comment->description = $text;
        $comment->owner_guid = $user->guid;
        $comment->container_guid = $entity->guid;
        $comment->access_id = $entity->access_id;
        $comment_guid = $comment->save();

        if ($comment_guid) {
            $return['success'] = $comment_guid;
            elgg_create_river_item(array(
                'view' => 'river/object/comment/image',
                'action_type' => 'comment',
                'subject_guid' => $user->guid,
                'object_guid' => $comment_guid,
                'target_guid' => $entity->guid,
            ));
        }
    }
    return $return;
}

elgg_ws_expose_function('wire.post_image_comment',
    "wire_post_image_comment",
    array(	'guid' => array ('type' => 'int'),
        'text' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "Post a comment on a image post",
    'POST',
    true,
    true);


function image_get_photos($context,  $limit = 20, $offset = 0, $username) {

    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
        throw new InvalidParameterException('registration:usernamenotvalid');
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $loginUser = elgg_get_logged_in_user_entity();

    if($context == "all"){
        $params = array(
            'type' => 'object',
            'subtype' => 'image',
            'owner_guid' => NULL,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => false,
            'list_type' => 'gallery',
            'gallery_class' => 'tidypics-gallery'
        );
    } else if ($context == 'mine') {
        $params = array(
            'type' => 'object',
            'subtype' => 'image',
            'owner_guid' => $user->guid,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => false,
            'list_type' => 'gallery',
            'gallery_class' => 'tidypics-gallery'
        );
    } else if ($context == 'friends') {
        if ($friends = $user->getFriends(array('limit' => false))) {
            $friendguids = array();
            foreach ($friends as $friend) {
                $friendguids[] = $friend->getGUID();
            }

            $params = array(
                'type' => 'object',
                'subtype' => 'image',
                'owner_guids' => $friendguids,
                'limit' => $limit,
                'offset' => $offset,
                'full_view' => false,
                'list_type' => 'gallery',
                'gallery_class' => 'tidypics-gallery'
            );
        }
    } else {
        $params = array(
            'type' => 'object',
            'subtype' => 'image',
            'owner_guid' => NULL,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => false,
            'list_type' => 'gallery',
            'gallery_class' => 'tidypics-gallery'
        );
    }

    $photos = elgg_get_entities($params);


    $site_url = get_config('wwwroot');
    if($photos) {
        $return = array();
        foreach($photos as $single ) {
            $photo['guid'] = $single->guid;
            $file_name = $single->getFilenameOnFilestore();

            $image_owner_guid_entity = $single->getOwnerEntity();
            $image_owner_guid = $image_owner_guid_entity->guid;
            $image_owner_join_date = $image_owner_guid_entity->time_created;

            $position = strrpos($file_name, '/');
            $position = $position + 1;
            $icon_file_name = substr_replace($file_name, 'smallthumb', $position, 0);

            $image_icon_url = $site_url . 'services/api/rest/json/?method=image.get_post';
            $icon_url = $image_icon_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
                . '&name=' . $icon_file_name;
            $icon_url = elgg_format_url($icon_url);

            $image_url = $site_url . 'services/api/rest/json/?method=image.get_post';
            $img_url = $image_url . '&joindate=' . $image_owner_join_date . '&guid=' . $image_owner_guid
                . '&name=' . $file_name;
            $img_url = elgg_format_url($img_url);

            $photo['container_guid'] = $single->container_guid;
            if ($single->title != null) {
                $photo['title'] = $single->title;
            } else {
                $photo['title'] = '';
            }

            $photo['time_create'] = time_ago($single->time_created);
            if ($single->description != null) {
                $photo['description'] = $single->description;
            } else {
                $photo['description'] = '';
            }



            $owner = get_entity($single->owner_guid);
            $photo['owner']['guid'] = $owner->guid;
            $photo['owner']['name'] = $owner->name;
            $photo['owner']['username'] = $owner->username;
            $photo['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');

            $photo['icon_url'] =$icon_url;
            $photo['img_url'] = $img_url;
            $photo['like_count'] = likes_count_number_of_likes($single->guid);
            $photo['comment_count'] = api_get_image_comment_count($single->guid);
            $photo['like'] = checkLike($single->guid, $loginUser->guid);

            $return[] = $photo;
        }
    }
    else {
        $msg = elgg_echo('blog:none');
        throw new InvalidParameterException($msg);
    }


    return $return;
}

elgg_ws_expose_function('image.get_photos',
    "image_get_photos",
    array(	'context' => array ('type' => 'string'),
            'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
            'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
            'username' => array ('type' => 'string', 'required' => false),
    ),
    "GET all the photos",
    'GET',
    true,
    true);


/**
 * @param $joindate
 * @param $guid
 * @param $name
 */
function image_get_post($joindate, $guid, $name)
{
    global $CONFIG;

// won't be able to serve anything if no joindate or guid
    if (!isset($_GET['joindate']) || !isset($_GET['guid'])) {
        header("HTTP/1.1 404 Not Found");
        exit;
    }

    $join_date = (int)$_GET['joindate'];
    $last_cache = (int)$_GET['lastcache']; // icontime
    $guid = (int)$_GET['guid'];
    $filename = $_GET['name'];

// If is the same ETag, content didn't changed.
    $etag = $last_cache . $guid;
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == "\"$etag\"") {
        header("HTTP/1.1 304 Not Modified");
        exit;
    }

    $filename = str_replace(array('\\'), '',$filename);
    $filesize = @filesize($filename);
    if ($filesize) {
        header("Content-type: image/jpeg");
        header("Pragma: public");
        header("Cache-Control: public");
        header("Content-Length: $filesize");
        header("ETag: \"$etag\"");
        readfile($filename);
        exit;
    }

}

elgg_ws_expose_function('image.get_post',
    "image_get_post",
    array(	'joindate' => array ('type' => 'int'),
        'guid' => array ('type' => 'int'),
        'name' => array ('type' => 'string'),
    ),
    "Get image post",
    'GET',
    true,
    true);