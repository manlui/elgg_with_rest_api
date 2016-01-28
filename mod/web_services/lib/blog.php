<?php

/**
 * Web service to get file list by all users
 *
 * @param string $context eg. all, friends, mine, groups
 * @param int $limit (optional) default 10
 * @param int $offset (optional) default 0
 * @param int $group_guid (optional)  the guid of a group, $context must be set to 'group'
 * @param string $username (optional) the username of the user default loggedin user
 * @return array $file Array of files uploaded
 * @throws InvalidParameterException
 */
function blog_get_posts($context,  $limit = 10, $offset = 0,$group_guid, $username) {
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
            'subtypes' => 'blog',
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => FALSE,
        );
    }
    if($context == "mine" || $context ==  "user"){
        $params = array(
            'types' => 'object',
            'subtypes' => 'blog',
            'owner_guid' => $user->guid,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => FALSE,
        );
    }
    if($context == "group"){
        $params = array(
            'types' => 'object',
            'subtypes' => 'blog',
            'container_guid'=> $group_guid,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => FALSE,
        );
    }
    $latest_blogs = elgg_get_entities($params);

    if($context == "friends"){
        $latest_blogs = elgg_get_entities_from_relationship(array(
            'type' => 'object',
            'subtype' => 'blog',
            'limit' => $limit,
            'offset' => $offset,
            'relationship' => 'friend',
            'relationship_guid' => $user->guid,
            'relationship_join_on' => 'container_guid',
        ));
    }


    if($latest_blogs) {
        foreach($latest_blogs as $single ) {
            $blog['guid'] = $single->guid;
            $blog['title'] = $single->title;
            $blog['excerpt'] = $single->excerpt;

            if (strlen($single->description) > 300) {
                $entityString = substr(strip_tags($single->description), 0, 300);
                $blog['description'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';

            } else {
                $blog['description'] = strip_tags($single->description);
            }

            $owner = get_entity($single->owner_guid);
            $blog['owner']['guid'] = $owner->guid;
            $blog['owner']['name'] = $owner->name;
            $blog['owner']['username'] = $owner->username;
            $blog['owner']['avatar_url'] = get_entity_icon_url($owner,'small');

            $blog['container_guid'] = $single->container_guid;
            $blog['access_id'] = $single->access_id;
            $blog['time_created'] = time_ago($single->time_created);
            $blog['time_updated'] = time_ago($single->time_updated);
            $blog['last_action'] = time_ago($single->last_action);

            $blog['like_count'] = likes_count_number_of_likes($single->guid);
            $blog['like'] = checkLike($single->guid, $user->guid);

            $comments = elgg_get_entities(array(
                'type' => 'object',
                'subtype' => 'comment',
                'container_guid' => $single->guid,
                "limit" => 99,
            ));

            $blog['comment_count'] = sizeof($comments);

            $return[] = $blog;
        }
    }
    else {
        $msg = elgg_echo('blog:none');
        throw new InvalidParameterException($msg);
    }
    return $return;
}

elgg_ws_expose_function('blog.get_posts',
    "blog_get_posts",
    array(
        'context' => array ('type' => 'string', 'required' => false, 'default' => 'all'),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 10),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
        'group_guid' => array ('type'=> 'int', 'required'=>false, 'default' =>0),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "Get list of blog posts",
    'GET',
    true,
    true);




/**
 * Web service for read a blog post
 *
 * @param string $guid GUID of a blog entity
 * @param string $username Username of reader (Send NULL if no user logged in)
 * @return string $title       Title of blog post
 * @internal param string $password Password for authentication of username (Send NULL if no user logged in)
 *
 */
function blog_get_post($guid, $username) {
    $return = array();
    $blog = get_entity($guid);

    if (!elgg_instanceof($blog, 'object', 'blog')) {
        $return['content'] = elgg_echo('blog:error:post_not_found');
        return $return;
    }

    $user = get_user_by_username($username);
    if ($user) {
        if (!has_access_to_entity($blog, $user)) {
            $return['content'] = elgg_echo('blog:error:post_not_found');
            return $return;
        }

        if ($blog->status!='published' && $user->guid!=$blog->owner_guid) {
            $return['content'] = elgg_echo('blog:error:post_not_found');
            return $return;
        }
    } else {
        if($blog->access_id!=2) {
            $return['content'] = elgg_echo('blog:error:post_not_found');
            return $return;
        }
    }

    $return['guid'] = $guid;
    $return['title'] = htmlspecialchars($blog->title);
    $return['description'] = $blog->description;
    $return['excerpt'] = $blog->excerpt;

    if ($blog->tags == null) {
        $return['tags'] = '';
    } else {
        $return['tags'] = $blog->tags;
    }

    $comments = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'comment',
        'container_guid' => $guid,
        "limit" => 99,
    ));

    $return['owner'] = getBlogOwner($blog->owner_guid);
    //$return['owner_guid'] = $blog->owner_guid;
    $return['access_id'] = $blog->access_id;
    $return['status'] = $blog->status;
    $return['comments_on'] = $blog->comments_on;
    $return['time_created'] = time_ago($blog->time_created);
    $return['like_count'] = likes_count_number_of_likes($guid);
    $return['like'] = checkLike($guid, $user->guid);
    $return['comment_count'] = sizeof($comments);

    return $return;
}

elgg_ws_expose_function('blog.get_post',
    "blog_get_post",

    array('guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "Read a blog post",
    'GET',
    true,
    true);


/**
 * Web service to retrieve comments on a blog post
 *
 * @param string $guid blog guid
 * @param int|string $limit Number of users to return
 * @param int|string $offset Indexing offset, if any
 * @return array
 * @throws InvalidParameterException
 */
function blog_get_comments($guid, $limit = 10, $offset = 0){

    $user = elgg_get_logged_in_user_entity();
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    $comments = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'comment',
        'container_guid' => $guid,
        "limit" => 99,
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

elgg_ws_expose_function('blog.get_comments',
    "blog_get_comments",
    array(	'guid' => array ('type' => 'string'),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 10),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get comments for a blog post",
    'GET',
    true,
    true);



function getBlogOwner($guid) {
    $entity = get_entity($guid);

    $owner['guid'] = $guid;
    $owner['name'] = $entity->name;
    $owner['username'] = $entity->username;
    $owner['avatar_url'] = elgg_format_url($entity->getIconURL());

    return $owner;
}