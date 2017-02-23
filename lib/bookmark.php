<?php
/**
 * Created by IntelliJ IDEA.
 * User: mlui
 * Date: 2/12/2016
 * Time: 11:32 AM
 *
 *
 * @param $context
 * @param int $limit
 * @param int $offset
 * @param $username
 * @return array
 * @throws InvalidParameterException
 */

function bookmark_get_posts($context,  $limit = 20, $offset = 0, $username, $from_guid) {

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

    if ($from_guid > 0) {
        $offset = $offset + getBookmarkGuidPosition($from_guid, $context, $loginUser);
    }

    if($context == "all"){
        $params = array(
            'type' => 'object',
            'subtype' => 'bookmarks',
            'full_view' => false,
            'view_toggle_type' => false,
            'no_results' => elgg_echo('bookmarks:none'),
            'preload_owners' => true,
            'distinct' => false,
            'limit' => $limit,
            'offset' => $offset,
        );
        $bookmarks = elgg_get_entities($params);
    } else if ($context == 'mine') {
        $params = array(
            'type' => 'object',
            'subtype' => 'bookmarks',
            'container_guid' => $loginUser->guid,
            'full_view' => false,
            'view_toggle_type' => false,
            'no_results' => elgg_echo('bookmarks:none'),
            'preload_owners' => true,
            'distinct' => false,
            'limit' => $limit,
            'offset' => $offset,
        );
        $bookmarks = elgg_get_entities($params);
    } else if ($context == 'friends') {
        $bookmarks = elgg_get_entities_from_relationship(array(
            'type' => 'object',
            'subtype' => 'bookmarks',
            'full_view' => false,
            'relationship' => 'friend',
            'relationship_guid' => $loginUser->guid,
            'relationship_join_on' => 'container_guid',
            'no_results' => elgg_echo('bookmarks:none'),
            'preload_owners' => true,
            'limit' => $limit,
            'offset' => $offset,
        ));
    } else {
        $params = array(
            'type' => 'object',
            'subtype' => 'bookmarks',
            'full_view' => false,
            'view_toggle_type' => false,
            'no_results' => elgg_echo('bookmarks:none'),
            'preload_owners' => true,
            'distinct' => false,
            'limit' => $limit,
            'offset' => $offset,
        );
        $bookmarks = elgg_get_entities($params);
    }

    if($bookmarks) {
        $return = array();
        foreach($bookmarks as $single ) {
            $bookmark['guid'] = $single->guid;

            if ($single->title != null) {
                $bookmark['title'] = $single->title;
            } else {
                $bookmark['title'] = '';
            }

            $bookmark['time_created'] = time_ago($single->time_created);
            if ($single->description != null) {
                if (strlen($single->description) > 300) {
                    $entityString = substr(strip_tags($single->description), 0, 300);
                    $bookmark['description'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';

                } else {
                    $bookmark['description'] = strip_tags($single->description);
                }
                $bookmark['image_link'] = getImageLink($single->description);
            } else {
                $bookmark['description'] = '';
            }
            $bookmark['address'] = $single->address;

            $bookmark['owner'] = getOwner($single->owner_guid);
            if ($single->tags == null) {
                $bookmark['tags'] = '';
            } else {
                $bookmark['tags'] = $single->tags;
            }

            $bookmark['like_count'] = likes_count_number_of_likes($single->guid);
            $bookmark['comment_count'] = api_get_image_comment_count($single->guid);
            $bookmark['like'] = checkLike($single->guid, $loginUser->guid);

            $return[] = $bookmark;
        }
    }
    else {
        $msg = elgg_echo('bookmarks:none');
        throw new InvalidParameterException($msg);
    }


    return $return;
}

elgg_ws_expose_function('bookmark.get_posts',
    "bookmark_get_posts",
    array(	'context' => array ('type' => 'string'),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
        'username' => array ('type' => 'string', 'required' => false),
        'from_guid' => array ('type' => 'int', 'required' => false, 'default' => 0),
    ),
    "GET all the bookmarks",
    'GET',
    true,
    true);


/**
 * @param $guid
 * @param $username
 * @return array
 * @throws InvalidParameterException
 */
function bookmark_get_post($guid, $username) {
    $return = array();
    $bookmark = get_entity($guid);

    if (!elgg_instanceof($bookmark, 'object', 'bookmarks')) {
        $return['content'] = elgg_echo('bookmark:error:post_not_found');
        return $return;
    }

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

    $return['guid'] = $guid;
    $return['title'] = $bookmark->title;
    $return['description'] = $bookmark->description;
    $return['address'] = $bookmark->address;

    if ($bookmark->tags == null) {
        $return['tags'] = '';
    } else {
        $return['tags'] = $bookmark->tags;
    }

    $comments = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'comment',
        'container_guid' => $guid,
        'limit' => 0,
    ));

    $return['owner'] = getOwner($bookmark->owner_guid);
    $return['access_id'] = $bookmark->access_id;
    $return['time_created'] = time_ago($bookmark->time_created);
    $return['like_count'] = likes_count_number_of_likes($guid);
    $return['like'] = checkLike($guid, $loginUser->guid);
    $return['comment_count'] = sizeof($comments);

    return $return;
}

elgg_ws_expose_function('bookmark.get_post',
    "bookmark_get_post",

    array('guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "Read a bookmark post",
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
function bookmark_get_comments($guid, $username, $limit = 20, $offset = 0){

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

elgg_ws_expose_function('bookmark.get_comments',
    "blog_get_comments",
    array(	'guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get comments for a bookmark post",
    'GET',
    true,
    true);


/**
 * @param $guid
 * @param $text
 * @return mixed
 * @throws InvalidParameterException
 */
function bookmark_post_comment($guid, $text){
    $user = elgg_get_logged_in_user_entity();
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    if ($guid) {
        $entity = get_entity($guid);
    } else {
        $return['message'] = elgg_echo("guid:blank");
        return $return;
    }

    $return['success'] = false;
    if (empty($text)) {
        $return['message'] = elgg_echo("comment:blank");
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
                'view' => 'river/object/comment/create',
                'action_type' => 'comment',
                'subject_guid' => $user->guid,
                'object_guid' => $comment_guid,
                'target_guid' => $entity->guid,
            ));
        }
    }
    return $return;
}

elgg_ws_expose_function('bookmark.post_comment',
    "bookmark_post_comment",
    array(	'guid' => array ('type' => 'int'),
        'text' => array ('type' => 'string'),
    ),
    "Post a comment on a bookmark post",
    'POST',
    true,
    true);


function bookmark_save_post($title, $address, $description, $access, $username, $tags){
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $loginUser = elgg_get_logged_in_user_entity();
    $guid = 0;

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

//    $share = get_input('share');
    $container_guid = $user->guid;

// don't use elgg_normalize_url() because we don't want
// relative links resolved to this site.
    if ($address && !preg_match("#^((ht|f)tps?:)?//#i", $address)) {
        $address = "http://$address";
    }

    if (!$title || !$address) {
        register_error(elgg_echo('bookmarks:save:failed'));
        throw new InvalidParameterException('bookmarks:save:failed');
    }

// see https://bugs.php.net/bug.php?id=51192
    $php_5_2_13_and_below = version_compare(PHP_VERSION, '5.2.14', '<');
    $php_5_3_0_to_5_3_2 = version_compare(PHP_VERSION, '5.3.0', '>=') &&
        version_compare(PHP_VERSION, '5.3.3', '<');

    $validated = false;
    if ($php_5_2_13_and_below || $php_5_3_0_to_5_3_2) {
        $tmp_address = str_replace("-", "", $address);
        $validated = filter_var($tmp_address, FILTER_VALIDATE_URL);
    } else {
        $validated = filter_var($address, FILTER_VALIDATE_URL);
    }
    if (!$validated) {
        register_error(elgg_echo('bookmarks:save:failed'));
        throw new InvalidParameterException('bookmarks:save:failed');
    }

    if ($guid == 0) {
        $bookmark = new ElggObject;
        $bookmark->subtype = "bookmarks";
        $bookmark->container_guid = $container_guid;
        $new = true;
    } else {
        $bookmark = get_entity($guid);
        if (!$bookmark->canEdit()) {
            system_message(elgg_echo('bookmarks:save:failed'));
        }
    }

    $tagarray = string_to_tag_array($tags);

    $bookmark->title = $title;
    $bookmark->address = $address;
    $bookmark->description = $description;
    $bookmark->access_id = $access_id;
    $bookmark->tags = $tagarray;

    if ($bookmark->save()) {

        //add to river only if new
        if ($new) {
            elgg_create_river_item(array(
                'view' => 'river/object/bookmarks/create',
                'action_type' => 'create',
                'subject_guid' => $user->guid,
                'object_guid' => $bookmark->getGUID(),
            ));

        }
        $return['guid'] = $bookmark->guid;
    } else {
        register_error(elgg_echo('bookmarks:save:failed'));
        $return['guid'] = 0;
    }

    return $return;
}

elgg_ws_expose_function('bookmark.save_post',
    "bookmark_save_post",
    array(
        'title' => array ('type' => 'string', 'required' => true),
        'address' => array ('type' => 'string', 'required' => true),
        'description' => array ('type' => 'string', 'required' => true,),
        'access' => array ('type' => 'string', 'required' => true, 'default'=>ACCESS_FRIENDS),
        'username' => array ('type' => 'string', 'required' => true),
        'tags' => array ('type' => 'string', 'required' => false, 'default' => ""),
    ),
    "Post a bookmark post",
    'POST',
    true,
    true);



function getOwner($guid) {
    $entity = get_entity($guid);

    $owner['guid'] = $guid;
    $owner['name'] = $entity->name;
    $owner['username'] = $entity->username;
    $owner['avatar_url'] = getProfileIcon($entity); //$entity->getIconURL('small');;

    return $owner;
}

function getImageLink($description) {
    $doc = new DOMDocument();
    @$doc->loadHTML($description);

    $tags = $doc->getElementsByTagName('img');

    $image_link = '';
    foreach ($tags as $tag) {
        $image_link =  $tag->getAttribute('src');
    }

    return $image_link;
}

function getBookmarkGuidPosition($guid, $context, $loginUser) {
    $notFound = true;
    $offset = 0;
    while($notFound) {
        if($context == "all"){
            $params = array(
                'type' => 'object',
                'subtype' => 'bookmarks',
                'full_view' => false,
                'view_toggle_type' => false,
                'no_results' => elgg_echo('bookmarks:none'),
                'preload_owners' => true,
                'distinct' => false,
                'limit' => 1,
                'offset' => $offset,
            );
            $bookmarks = elgg_get_entities($params);
        } else if ($context == 'mine') {
            $params = array(
                'type' => 'object',
                'subtype' => 'bookmarks',
                'container_guid' => $loginUser->guid,
                'full_view' => false,
                'view_toggle_type' => false,
                'no_results' => elgg_echo('bookmarks:none'),
                'preload_owners' => true,
                'distinct' => false,
                'limit' => 1,
                'offset' => $offset,
            );
            $bookmarks = elgg_get_entities($params);
        } else if ($context == 'friends') {
            $bookmarks = elgg_get_entities_from_relationship(array(
                'type' => 'object',
                'subtype' => 'bookmarks',
                'full_view' => false,
                'relationship' => 'friend',
                'relationship_guid' => $loginUser->guid,
                'relationship_join_on' => 'container_guid',
                'no_results' => elgg_echo('bookmarks:none'),
                'preload_owners' => true,
                'limit' => 1,
                'offset' => $offset,
            ));
        }

        if (sizeof($bookmarks) > 0) {
            if ($bookmarks[0]->guid == $guid) {
                $notFound = false;
            } else {
                $offset = $offset + 1;
            }
        } else {
            $notFound = false;
        }
    }

    return $offset;
}