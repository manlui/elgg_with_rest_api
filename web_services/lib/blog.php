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
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
        'group_guid' => array ('type'=> 'int', 'required'=>false, 'default' =>0),
        'username' => array ('type' => 'string', 'required' => false),
    ),
    "Get list of blog posts",
    'GET',
    true,
    true);


/**
 * Web service for making a blog post
 *
 * @param string $title the title of blog
 * @param $description
 * @param string $excerpt the excerpt of blog
 * @param string $tags tags for blog
 * @param string $access Access level of blog
 *
 * @param $container_guid
 * @return bool
 * @throws InvalidParameterException
 * @internal param string $text the content of blog
 * @internal param string $username username of author
 */
function blog_save($title, $description, $excerpt, $tags , $access, $container_guid) {
    $user = elgg_get_logged_in_user_entity();
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    $obj = new ElggObject();
    $obj->subtype = "blog";
    $obj->owner_guid = $user->guid;
    $obj->container_guid = $container_guid;
    $obj->access_id = strip_tags($access);
    $obj->method = "api";
    $obj->description = strip_tags($description);
    $obj->title = elgg_substr(strip_tags($title), 0, 140);
    $obj->status = 'published';
    $obj->comments_on = 'On';
    $obj->excerpt = strip_tags($excerpt);
    $obj->tags = strip_tags($tags);
    $guid = $obj->save();

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

    elgg_create_river_item(array(
        'view' => 'river/object/blog/create',
        'action_type' => 'create',
        'subject_guid' => $user->guid,
        'object_guid' => $obj->guid,
        'target_guid' => 0,
        'access_id' => $access_id,
        'posted' => 0,
        'annotation_id' => 0,
    ));

    $return['success'] = true;
    $return['message'] = elgg_echo('blog:message:saved');
    return $return;
}

elgg_ws_expose_function('blog.save_post',
    "blog_save",
    array(
        'title' => array ('type' => 'string', 'required' => true),
        'description' => array ('type' => 'string', 'required' => true),
        'excerpt' => array ('type' => 'string', 'required' => false),
        'tags' => array ('type' => 'string', 'required' => false, 'default' => "blog"),
        'access' => array ('type' => 'string', 'required' => false, 'default'=>ACCESS_PUBLIC),
        'container_guid' => array ('type' => 'int', 'required' => false, 'default' => 0),
    ),
    "Post a blog post",
    'POST',
    true,
    true);


/**
 * Web service for delete a blog post
 *
 * @param string $guid GUID of a blog entity
 * @param string $username Username of reader (Send NULL if no user logged in)
 * @return bool
 * @throws InvalidParameterException
 * @internal param string $password Password for authentication of username (Send NULL if no user logged in)
 *
 */
function blog_delete_post($guid, $username) {
    $return = array();
    $blog = get_entity($guid);
    $return['success'] = false;
    if (!elgg_instanceof($blog, 'object', 'blog')) {
        throw new InvalidParameterException('blog:error:post_not_found');
    }

    $user = get_user_by_username($username);
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }
    $blog = get_entity($guid);
    if($user->guid!=$blog->owner_guid) {
        $return['message'] = elgg_echo('blog:message:notauthorized');
    }

    if (elgg_instanceof($blog, 'object', 'blog') && $blog->canEdit()) {
        $container = get_entity($blog->container_guid);
        if ($blog->delete()) {
            $return['success'] = true;
            $return['message'] = elgg_echo('blog:message:deleted_post');
        } else {
            $return['message'] = elgg_echo('blog:error:cannot_delete_post');
        }
    } else {
        $return['message'] = elgg_echo('blog:error:post_not_found');
    }

    return $return;
}

elgg_ws_expose_function('blog.delete_post',
    "blog_delete_post",
    array('guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string'),
    ),
    "Read a blog post",
    'POST',
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
 * @param $username
 * @param int|string $limit Number of users to return
 * @param int|string $offset Indexing offset, if any
 * @return array
 * @throws InvalidParameterException
 */
function blog_get_comments($guid, $username, $limit = 20, $offset = 0){

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

elgg_ws_expose_function('blog.get_comments',
    "blog_get_comments",
    array(	'guid' => array ('type' => 'string'),
        'username' => array ('type' => 'string', 'required' => false),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),

    ),
    "Get comments for a blog post",
    'GET',
    true,
    true);


/**
 * Web service to comment on a post
 *
 * @param int $guid blog guid
 * @param string $text
 * @return array
 * @throws InvalidParameterException
 * @internal param int $access_id
 *
 */
function blog_post_comment($guid, $text){

    $entity = get_entity($guid);

    $user = elgg_get_logged_in_user_entity();

    $annotation = create_annotation($entity->guid,
        'generic_comment',
        $text,
        "",
        $user->guid,
        $entity->access_id);


    if($annotation){
        // notify if poster wasn't owner
        if ($entity->owner_guid != $user->guid) {

            notify_user($entity->owner_guid,
                $user->guid,
                elgg_echo('generic_comment:email:subject'),
                elgg_echo('generic_comment:email:body', array(
                    $entity->title,
                    $user->name,
                    $text,
                    $entity->getURL(),
                    $user->name,
                    $user->getURL()
                ))
            );
        }

        $return['success']['message'] = elgg_echo('generic_comment:posted');
    } else {
        $msg = elgg_echo('generic_comment:failure');
        throw new InvalidParameterException($msg);
    }
    return $return;
}
elgg_ws_expose_function('blog.post_comment',
    "blog_post_comment",
    array(	'guid' => array ('type' => 'int'),
        'text' => array ('type' => 'string'),
    ),
    "Post a comment on a blog post",
    'POST',
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