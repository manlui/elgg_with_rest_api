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
function blog_get_posts($context, $username, $limit = 10, $offset = 0,$group_guid=0) {
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
        $latest_blogs = elgg_get_entities($params);
    } else if($context == "mine" || $context ==  "user"){
        $params = array(
            'types' => 'object',
            'subtypes' => 'blog',
            'owner_guid' => $user->guid,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => FALSE,
        );
        $latest_blogs = elgg_get_entities($params);
    } else if($context == "group"){
        $params = array(
            'types' => 'object',
            'subtypes' => 'blog',
            'container_guid'=> $group_guid,
            'limit' => $limit,
            'offset' => $offset,
            'full_view' => FALSE,
        );
        $latest_blogs = elgg_get_entities($params);
    }


    if($context == "friends"){
        $options = array(
            'type' => 'object',
            'subtype' => 'blog',
            'full_view' => false,
            'relationship' => 'friend',
            'relationship_guid' => $user->guid,
            'relationship_join_on' => 'container_guid',
            'limit' => $limit,
            'offset' => $offset,
        );
        $latest_blogs = elgg_get_entities_from_relationship($options);

    }


    if($latest_blogs) {
        foreach($latest_blogs as $single ) {
            $blog['guid'] = $single->guid;
            $blog['title'] = $single->title;
            $blog['excerpt'] = $single->excerpt;
            $blog['status'] = $single->status;
            $blog['link'] = $single->getURL();

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
            $blog['owner']['avatar_url'] = getProfileIcon($owner); //$owner->getIconURL('small');

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
                "limit" => 0,
            ));

            $blog['comment_count'] = sizeof($comments);
            if ($single->tags == null) {
                $blog['tags'] = '';
            } else {
                $blog['tags'] = $single->tags;
            }

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
        'context' => array ('type' => 'string', 'required' => true, 'default' => 'all'),
        'username' => array ('type' => 'string', 'required' => true),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
        'group_guid' => array ('type'=> 'int', 'required'=>false, 'default' =>0),
    ),
    "Get list of blog posts",
    'GET',
    true,
    true);


/**
 * Web service for making a blog post
 *
 * @param string $title the title of blog
 * @param $body
 * @param $comment_status
 * @param string $access Access level of blog
 *
 * @param $status
 * @param $username
 * @param string $tags tags for blog
 * @param string $excerpt the excerpt of blog
 * @return bool
 * @throws InvalidParameterException
 * @internal param $description
 * @internal param $container_guid
 * @internal param string $text the content of blog
 * @internal param string $username username of author
 */
function blog_save_post($title, $body, $comment_status, $access, $status, $username, $tags, $excerpt) {
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
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


    $blog = new ElggBlog();
    $blog->subtype = "blog";
    $blog->owner_guid = $user->guid;
    $blog->container_guid = $user->guid;
    $blog->access_id = $access_id;
    $blog->description = $body;
    $blog->title = elgg_substr(strip_tags($title), 0, 140);
    $blog->status = $status;
    $blog->comments_on = $comment_status;
    $blog->excerpt = strip_tags($excerpt);
    $blog->tags = string_to_tag_array($tags);

    $guid = $blog->save();
    $newStatus = $blog->status;

    if ($guid > 0 && $newStatus == 'published') {

        elgg_create_river_item(array(
            'view' => 'river/object/blog/create',
            'action_type' => 'create',
            'subject_guid' => $blog->owner_guid,
            'object_guid' => $blog->getGUID(),
        ));

        elgg_trigger_event('publish', 'object', $blog);

        if ($guid) {
            $blog->time_created = time();
            $blog->save();
        }

        $return['guid'] = $guid;
        $return['message'] = $newStatus;
    } else {
        $return['guid'] = $guid;
        $return['message'] = $status;
    }

    return $return;
}

elgg_ws_expose_function('blog.save_post',
    "blog_save_post",
    array(
        'title' => array ('type' => 'string', 'required' => true),
        'body' => array ('type' => 'string', 'required' => true),
        'comment_status' => array ('type' => 'string', 'required' => true, 'default' => "On"),
        'access' => array ('type' => 'string', 'required' => true, 'default'=>ACCESS_FRIENDS),
        'status' => array ('type' => 'string', 'required' => true, 'default' => "published"),
        'username' => array ('type' => 'string', 'required' => true),
        'tags' => array ('type' => 'string', 'required' => false, 'default' => ""),
        'excerpt' => array ('type' => 'string', 'required' => false, 'default' => ""),
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
    $return['status'] = $blog->status;
    $return['link'] = $blog->getURL();

    if ($blog->tags == null) {
        $return['tags'] = '';
    } else {
        $return['tags'] = $blog->tags;
    }

    $comments = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'comment',
        'container_guid' => $guid,
        'limit' => 0,
    ));

    $return['owner'] = getBlogOwner($blog->owner_guid);
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
    $owner['avatar_url'] = getProfileIcon($entity); //$entity->getIconURL('small');

    return $owner;
}