<?php

elgg_ws_expose_function('site.river_short',
    'site_river_short',
    array(
        'username' => array ('type' => 'string', 'required' =>true),
        'limit' => array ('type' => 'int', 'required' => false),
        'offset' => array ('type' => 'int', 'required' => false),
        'from_guid' => array ('type' => 'int', 'required' => false, 'default' => 0),
    ),
    "Read latest news feed",
    'GET',
    true,
    true);

/**
 * @param $username
 * @param int $limit
 * @param int $offset
 * @param $from_guid
 * @return array
 * @throws InvalidParameterException
 */
function site_river_short($username, $limit=20, $offset=0, $from_guid) {

    $user = get_user_by_username($username);
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    if ($from_guid > 0) {
        $offset = $offset + getRiverGuidPosition($from_guid);
    }
    $options = array(
        'distinct' => false,
        'offset' => $offset,
        'limit' => $limit,
    );

    $activities = elgg_get_river($options);
    //$test2 = elgg_list_river($options);
    //error_log($test2, 3, "web_error_log");

    $login_user = $user;
    $handle = getRiverActivity($activities, $user, $login_user);

    return $handle;
}

elgg_ws_expose_function('post.get_comments',
    "post_get_comments",
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
 * @param $guid
 * @param $username
 * @param int $limit
 * @param int $offset
 * @return array
 * @throws InvalidParameterException
 */
function post_get_comments($guid, $username, $limit = 20, $offset = 0){

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
            $comment['description'] = $single->description;

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

    return $handle;
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
        'limit' => 0,
    ));

    return sizeof($comments);
}

function createProfileImageBatch($guid, $timePost, $userEntity) {
    $image['guid'] = $guid;
    $image['container_guid'] = $guid;
    $image['title'] = $userEntity->name;
    $image['time_create'] = $timePost;
    $image['owner_guid'] = $userEntity->guid;
    $image['icon_url'] = getProfileIcon($userEntity, 'large'); //$userEntity->getIconURL('large');
    $image['img_url'] = getProfileIcon($userEntity, 'master'); //$userEntity->getIconURL('master');
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

        $avatar_url = getProfileIcon($userEntity);

        $object_guid = $activity->object_guid;
        $entity = get_entity($object_guid);

        $activity_like= checkLike($activity->object_guid, $login_user->guid);
        $activity_comment_count = getCommentCount($activity);
        $activity_like_count = likes_count_number_of_likes($activity->object_guid);

        $entityString = "";
        $entityTxt = "";
        $icon_url="";
        $img_url="";
        $message_board="";
        $container_entity="";
        $batch_images = array();
        $isObject = false;

        if ($activity->subtype == "tidypics_batch"){
            $isObject = true;
            $batch_images = getBatchImages($activity->object_guid, $user->guid);

            if (sizeof($batch_images) > 1) {
                $entityTxt = "added the photos to the album";

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
                $container_entity = get_entity($entity->container_guid);
                if ($img->title != null) {
                    $entityTxt = "added the photo " . $img->title . " to the album " . $container_entity->title;
                } else {
                    $entityTxt = "added the photo " . $original_file_name . " to the album " . $container_entity->title;
                }

                if ($img->description != null) {
                    if (strlen($img->description) > 300) {
                        $entityString = substr(strip_tags($img->description), 0, 300);
                        $entityString = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';
                    } else {
                        $entityString = strip_tags($img->description);
                    }
                } else {
                    $entityString = '';
                }
            }
        }  else if ($activity->action_type == "create" && $activity->subtype == "album") {
            $isObject = true;

            $album = get_entity($activity->object_guid);
            $entityTxt = "created a new photo album " . $album->title;

            $container_entity = get_entity($entity->container_guid);
            if ($container_entity->type == 'group') {
                $entityTxt = $entityTxt . ' in the group ' . $container_entity->name;
            }

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
            $icon_url = getProfileIcon($friendEntity);
            $icon_url = elgg_format_url($icon_url);
            $img_url = getProfileIcon($friendEntity, 'master');//$friendEntity->getIconURL('master');
            if (strpos($img_url, 'user/defaultmaster.gif') !== false) {
                $img_url = getProfileIcon($friendEntity, 'large');//$friendEntity->getIconURL('large');
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

            $container_entity = get_entity($entity->container_guid);
            if ($container_entity->type == 'group') {
                $entityTxt = 'uploaded the file ' . $entity->title . ' in the group ' . $container_entity->name;
            } else {
                $entityTxt = 'uploaded the file ' . $entity->title;
            }

            if ($entity->description != null) {
                $entityString = $entity->description;
                $entityString = strip_tags($entityString);
            } else {
                $entityString = $entity->title;
            }

            $simpletype = $entity->simpletype;
            if ($simpletype == "image") {
                $activity->type = 'image';
                $icon_url = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $entity->guid . '&size=largethumb';
                $icon_url = elgg_format_url($icon_url);

                $img_url = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $entity->guid . '&size=original';
                $img_url = elgg_format_url($img_url);
            } else {
                $activity->type = 'download';
                $icon_url = getProfileIcon($entity, 'large');//elgg_format_url($entity->getIconURL('large'));
                $img_url = $site_url . 'services/api/rest/json/?method=file.get_post' . '&guid=' . $entity->guid . '&size=' . $entity->originalfilename;
                $img_url = elgg_format_url($img_url);
            }
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
        } else if ($activity->action_type == "create" && $activity->view == 'river/group/create') {
            $isObject = true;
            $entityTxt = 'created the group ' . $entity->name;
            $entityString = strip_tags($entity->description);
        } else if ($activity->action_type == "join" && $activity->view == 'river/relationship/member/create') {
            $isObject = true;
            $entityTxt = 'joined the group ' . $entity->name;
            $entityString = strip_tags($entity->description);
        } else if ($activity->action_type == "messageboard" && $activity->view == 'river/object/messageboard/create') {
            $isObject = true;
            $post_on_entity = get_entity($activity->object_guid);
            $message_board = elgg_get_annotation_from_id($activity->annotation_id);

            $entityTxt = 'posted on ' . $post_on_entity->name . '\'s message board';
            $entityString = strip_tags($message_board->value);

        } else if ($activity->action_type == "create" && $activity->view == 'river/object/blog/create' && $activity->subtype == 'blog') {
            $isObject = true;
            $container_entity = get_entity($entity->container_guid);
            if ($container_entity->type == 'group') {
                $entityTxt = 'published a blog post ' . $entity->title . ' in the group ' . $container_entity->name;
            } else {
                $entityTxt = 'published a blog post ' . $entity->title;
            }

            if ($entity->description != null) {
                if (strlen($entity->description) > 300) {
                    $entityString = substr(strip_tags($entity->description), 0, 300);
                    $entityString = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';
                } else {
                    $entityString = strip_tags($entity->description);
                }
            } else {
                $entityString = '';
            }
        }  else if ($activity->subtype == "bookmarks" && $activity->action_type == "create" && $activity->view == 'river/object/bookmarks/create') {
            $isObject = true;
            $entity = get_entity($activity->object_guid);
            $container_entity = get_entity($entity->container_guid);
            if ($container_entity->type == 'group') {
                $entityTxt = 'bookmarked ' . $entity->title . ' in the group ' . $container_entity->name;
            } else {
                $entityTxt = 'bookmarked ' . $entity->title;
            }

            if ($entity->description != null) {
                $icon_url = getImageLink($entity->description);
                if (strlen($entity->description) > 300) {
                    $entityString = substr(strip_tags($entity->description), 0, 300);
                    $entityString = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';

                } else {
                    $entityString = $entity->description;
                }
            } else {
                $entityString = '';
            }
            $entityString = strip_tags($entityString);
            $img_url = $entity->address;

        } else if ($activity->subtype == "discussion_reply" && $activity->action_type == "reply" && $activity->view == 'river/object/discussion_reply/create') {
            $isObject = true;
            $target_entity = get_entity($activity->target_guid);
            $entityTxt = 'replied on the discussion topic ' . $target_entity->title;
            $entityString = $entity->description;
            $entityString = strip_tags($entityString);
        }  else if ($activity->subtype == "groupforumtopic" && $activity->action_type == "create" && $activity->view == 'river/object/groupforumtopic/create') {
            $isObject = true;
            $container_entity = get_entity($entity->container_guid);
            $entityTxt = 'added a new discussion topic ' . $entity->title . ' in the group ' . $container_entity->name;

            if ($entity->description != null) {
                if (strlen($entity->description) > 300) {
                    $entityString = substr(strip_tags($entity->description), 0, 300);
                    $entityString = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';
                } else {
                    $entityString = $entity->description;
                }
                $entityString = strip_tags($entityString);
            } else {
                $entityString = '';
            }
		// Custome formatting start here -- Rohit Gupta (30th Dec 2016)
        } else if ($activity->view == 'river/object/badge/assign' || $activity->view == 'river/object/badge/award') {
            $isObject = true;
			$performed_by = $activity->getSubjectEntity();
			$performed_on = $activity->getObjectEntity();
			$object = $activity->getObjectEntity();

			if ($guid = $object->badges_badge) {
				$badge = get_entity($guid);
				$badge_url = $badge->badges_url;

				if ($badge_url) {
					$badge_view = "<a href=\"" . $badge_url . "\"><img title=\"" . $badge->title . "\" src=\"" . elgg_add_action_tokens_to_url(elgg_get_site_url() . "action/badges/view?file_guid=" . $badge->guid) . "\"></a>";
				} else {
					$badge_view = "<img title=\"" . $badge->title . "\" src=\"" . elgg_add_action_tokens_to_url(elgg_get_site_url() . "action/badges/view?file_guid=" . $badge->guid) . "\">";
				}

				$url = $performed_by->name;
				$entityString = elgg_echo('badges:river:assigned', array($url, $badge->title)) . "<br>" . $badge_view;
				$entityTxt = 'A new Badge was awarded!';
			}
		} else if ($activity->view == 'river/event_relationship/create') {
					$isObject = true;
					$user = get_entity($activity->subject_guid);
					$event = get_entity($activity->object_guid);

					$subject_url = "<a href='" . $user->getURL() . "'>" . $user->name . "</a>";
					$event_url = "<a href='" . $event->getURL() . "'>" . $event->title . "</a>";

					$relationtype = $event->getRelationshipByUser($user->getGUID()); 
					$entityTxt = "posted a new event!";
					$entityString = elgg_echo("event_manager:river:event_relationship:create:" . $relationtype, array($subject_url, $event_url));
		} else if ($activity->view == 'river/object/event/create') {
					$isObject = true;
					$object = $activity->getObjectEntity();
					$entityString = elgg_get_excerpt($object->description);
					$entityTxt = "created event " . $object->title;
		} else if ($activity->view == 'river/object/questions/create') {
					$isObject = true;
					$object = $activity->getObjectEntity();
					$entityString = elgg_get_excerpt($object->description);
					$entityTxt = "asked a new question!";
		} else if ($activity->view == 'river/relationship/member_of_site/create') {
					$isObject = true;
					$object = $activity->getObjectEntity();
					$entityString = elgg_get_excerpt($object->description);
					$entityTxt = "joined the site!";
					
		} else if ($activity->view == 'river/object/videos/create') {
					$isObject = true;
					$object = $activity->getObjectEntity();
					$excerpt = elgg_get_excerpt($object->description);

					$video_url = $object->video_url;

					$video_url = str_replace("feature=player_embedded&amp;", "", $video_url);
					$video_url = str_replace("feature=player_detailpage&amp;", "", $video_url);
					$video_url = str_replace("http://youtu.be","https://www.youtube.be",$video_url);
					$guid = $object->guid;
					$entityTxt = "posted a video!";
					$entityString = $excerpt . "|" . $video_url;
		} else {
					$isObject = true;
					$object = $activity->getObjectEntity();
					$entityString = elgg_get_excerpt($object->description);
					$entityTxt = "";
		}
		// Custome formatting end here -- Rohit Gupta (30th Dec 2016)

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
            "limit" => 0,
        );

        $comments = get_elgg_comments($options, 'elgg_get_entities_from_metadata');
    } else {
        $comments = elgg_get_entities(array(
            'type' => 'object',
            'subtype' => 'comment',
            'container_guid' => $activity->object_guid,
            "limit" => 0,
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

function getRiverGuidPosition($guid) {
    $notFound = true;
    $offset = 0;
    while($notFound) {
        $options = array(
            'distinct' => false,
            'offset' => $offset,
            'limit' => 1,
        );
        $activity = elgg_get_river($options);

        if (sizeof($activity) > 0) {
            if ($activity[0]->object_guid == $guid) {
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