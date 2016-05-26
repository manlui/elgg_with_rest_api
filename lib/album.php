<?php
/**
 * Created by IntelliJ IDEA.
 * User: mlui
 * Date: 2/11/2016
 * Time: 4:40 PM
 */

function album_get_posts($context,  $limit = 20, $offset = 0, $username) {

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
            'subtype' => 'album',
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
            'subtype' => 'album',
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
                'subtype' => 'album',
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
            'subtype' => 'album',
            'owner_guid' => NULL,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => false,
            'list_type' => 'gallery',
            'gallery_class' => 'tidypics-gallery'
        );
    }

    $albums = elgg_get_entities($params);


    $site_url = get_config('wwwroot');
    if($albums) {
        $return = array();
        foreach($albums as $single ) {
            $album['guid'] = $single->guid;
            $album_cover = $single->getCoverImage();

            $file_name = $album_cover->getFilenameOnFilestore();
            $image_join_date = $album_cover->time_created;

            $position = strrpos($file_name, '/');
            $position = $position + 1;
            $icon_file_name = substr_replace($file_name, 'smallthumb', $position, 0);

            $image_icon_url = $site_url . 'services/api/rest/json/?method=image.get_post';
            $image_icon_url = $image_icon_url . '&joindate=' . $image_join_date . '&guid=' . $album_cover->guid
                . '&name=' . $icon_file_name;

            $image_url = $site_url . 'services/api/rest/json/?method=image.get_post';
            $image_url = $image_url . '&joindate=' . $image_join_date . '&guid=' . $album_cover->guid
                . '&name=' . $file_name;

            $album['cover_icon_url'] = $image_icon_url;
            $album['cover_image_url'] = $image_url;


            if ($single->title != null) {
                $album['title'] = $single->title;
            } else {
                $album['title'] = '';
            }

            $album['time_create'] = time_ago($single->time_created);
            if ($single->description != null) {
                if (strlen($single->description) > 300) {
                    $entityString = substr(strip_tags($single->description), 0, 300);
                    $album['description'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';

                } else {
                    $album['description'] = strip_tags($single->description);
                }
            } else {
                $album['description'] = '';
            }



            $owner = get_entity($single->owner_guid);
            $album['owner']['guid'] = $owner->guid;
            $album['owner']['name'] = $owner->name;
            $album['owner']['username'] = $owner->username;
            $album['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');


//            $photo['img_url'] = $img_url;
            $album['like_count'] = likes_count_number_of_likes($single->guid);
            $album['comment_count'] = api_get_image_comment_count($single->guid);
            $album['like'] = checkLike($single->guid, $loginUser->guid);

            $return[] = $album;
        }
    }
    else {
        $msg = elgg_echo('blog:none');
        throw new InvalidParameterException($msg);
    }


    return $return;
}

elgg_ws_expose_function('album.get_posts',
    "album_get_posts",
    array(	'context' => array ('type' => 'string'),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "GET all the albums",
    'GET',
    true,
    true);