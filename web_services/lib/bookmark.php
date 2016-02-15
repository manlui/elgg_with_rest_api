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

function bookmark_get_posts($context,  $limit = 20, $offset = 0, $username) {

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

            $bookmark['time_create'] = time_ago($single->time_created);
            if ($single->description != null) {
                if (strlen($single->description) > 300) {
                    $entityString = substr(strip_tags($single->description), 0, 300);
                    $bookmark['description'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';

                } else {
                    $bookmark['description'] = strip_tags($single->description);
                }
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
    $return['title'] = strip_tags($bookmark->title);
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


function getOwner($guid) {
    $entity = get_entity($guid);

    $owner['guid'] = $guid;
    $owner['name'] = $entity->name;
    $owner['username'] = $entity->username;
    $owner['avatar_url'] = elgg_format_url($entity->getIconURL());

    return $owner;
}