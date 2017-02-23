<?php
/**
 * Created by IntelliJ IDEA.
 * User: mlui
 * Date: 2/12/2016
 * Time: 4:56 PM
 *
 *
 * @param $context
 * @param int $limit
 * @param int $offset
 * @param $username
 * @return array
 * @throws InvalidParameterException
 */

function group_get_list($context,  $limit = 20, $offset = 0, $username, $from_guid) {

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
            'type' => 'group',
            'full_view' => false,
            'no_results' => elgg_echo('groups:none'),
            'distinct' => false,
            'limit' => $limit,
            'offset' => $offset,
        );
        $groups = elgg_get_entities($params);
    } else if ($context == 'mine') {
        $params = array(
            'type' => 'group',
            'container_guid' => $loginUser->guid,
            'full_view' => false,
            'no_results' => elgg_echo('groups:none'),
            'distinct' => false,
            'limit' => $limit,
            'offset' => $offset,
        );
        $groups = elgg_get_entities($params);
    } else if ($context == 'member') {
        $dbprefix = elgg_get_config('dbprefix');

        $groups = elgg_get_entities_from_relationship(array(
            'type' => 'group',
            'relationship' => 'member',
            'relationship_guid' => $loginUser->guid,
            'inverse_relationship' => false,
            'full_view' => false,
            'joins' => array("JOIN {$dbprefix}groups_entity ge ON e.guid = ge.guid"),
            'order_by' => 'ge.name ASC',
            'distinct' => false,
            'limit' => $limit,
            'offset' => $offset,
            'no_results' => elgg_echo('groups:none'),
        ));
    } else {
        $params = array(
            'type' => 'group',
            'full_view' => false,
            'no_results' => elgg_echo('groups:none'),
            'distinct' => false,
            'limit' => $limit,
            'offset' => $offset,
        );
        $groups = elgg_get_entities($params);
    }

    $site_url = get_config('wwwroot');
    if($groups) {
        $return = array();
        foreach($groups as $single ) {
            $group['guid'] = $single->guid;

            if ($single->name != null) {
                $group['title'] = $single->name;
            } else {
                $group['title'] = '';
            }

            $group['time_create'] = time_ago($single->time_created);
            if ($single->description != null) {
                if (strlen($single->description) > 300) {
                    $entityString = substr(strip_tags($single->description), 0, 300);
                    $group['description'] = preg_replace('/\W\w+\s*(\W*)$/', '$1', $entityString) . '...';

                } else {
                    $group['description'] = strip_tags($single->description);
                }
            } else {
                $group['description'] = '';
            }

            $group['access_id'] = $single->access_id;
            $group['members'] = sizeof($single->getMembers());
            $group['is_member'] = $single->isMember($user);
            $group['permission_public'] = $single->isPublicMembership();
            $group['content_access_mode'] = $single->getContentAccessMode();

            $icon = getProfileIcon($single, 'medium'); //$single->getIconURL('medium');
            if (strpos($icon, 'graphics/defaultmedium.gif') !== FALSE) {
                $group['icon'] = $icon;
            } else {
                $group['icon'] = $site_url . 'services/api/rest/json/?method=group.get_icon&guid=' . $single->guid;
            }

            if ($single->getTags() == null) {
                $group['tags'] = '';
            } else {
                $group['tags'] = $single->getTags(); //$single->getTags();
            }




            $group['owner'] = getOwner($single->owner_guid);


            $return[] = $group;
        }
    }
    else {
        $msg = elgg_echo('groups:none');
        throw new InvalidParameterException($msg);
    }


    return $return;
}

elgg_ws_expose_function('group.get_list',
    "group_get_list",
    array(	'context' => array ('type' => 'string'),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
        'username' => array ('type' => 'string', 'required' => false),
        'from_guid' => array ('type' => 'int', 'required' => false, 'default' => 0),
    ),
    "GET all the groups",
    'GET',
    true,
    true);


/**
 * @param $guid
 * @param int $limit
 * @param int $offset
 * @param $username
 * @param $from_guid
 * @return array
 * @throws InvalidParameterException
 */
function group_get_activity($guid, $limit = 20, $offset = 0, $username, $from_guid)
{
    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
        throw new InvalidParameterException('registration:usernamenotvalid');
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    $login_user = elgg_get_logged_in_user_entity();

    $group = get_entity($guid);

    if (!elgg_instanceof($group, 'group')) {
        $return['message'] = elgg_echo('grups:error:group_not_found');
        return $return;
    }

    $db_prefix = elgg_get_config('dbprefix');

    $activities = elgg_get_river(array(
        'limit' => $limit,
        'offset' => $offset,
        'joins' => array(
            "JOIN {$db_prefix}entities e1 ON e1.guid = rv.object_guid",
            "LEFT JOIN {$db_prefix}entities e2 ON e2.guid = rv.target_guid",
        ),
        'wheres' => array(
            "(e1.container_guid = $group->guid OR e2.container_guid = $group->guid)",
        ),
        'no_results' => elgg_echo('groups:activity:none'),
    ));

    $handle = getRiverActivity($activities, $user, $login_user);

    return $handle;
}

elgg_ws_expose_function('group.get_activity',
    "group_get_activity",
    array(
        'guid' => array ('type' => 'string', 'required' => true),
        'limit' => array ('type' => 'int', 'required' => false, 'default' => 20),
        'offset' => array ('type' => 'int', 'required' => false, 'default' => 0),
        'username' => array ('type' => 'string', 'required' => false),
        'from_guid' => array ('type' => 'int', 'required' => false, 'default' => 0),
    ),
    "GET group activity",
    'GET',
    true,
    true);


/**
 * @param $guid
 * @param $size
 * @throws IOException
 * @throws InvalidParameterException
 */
function group_get_icon($guid, $size) {
    /* @var ElggGroup $group */
    $group = get_entity($guid);
    if (!($group instanceof ElggGroup)) {
        header("HTTP/1.1 404 Not Found");
        exit;
    }

// If is the same ETag, content didn't changed.
    $etag = $group->icontime . $guid;
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == "\"$etag\"") {
        header("HTTP/1.1 304 Not Modified");
        exit;
    }

    if (!in_array($size, array('large', 'medium', 'small', 'tiny', 'master', 'topbar')))
        $size = "medium";

    $success = false;

    $filehandler = new ElggFile();
    $filehandler->owner_guid = $group->owner_guid;
    $filehandler->setFilename("groups/" . $group->guid . $size . ".jpg");

    $success = false;
    if ($filehandler->open("read")) {
        if ($contents = $filehandler->read($filehandler->getSize())) {
            $success = true;
        }
    }

    header("Content-type: image/jpeg");
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime("+10 days")), true);
    header("Pragma: public", true);
    header("Cache-Control: public", true);
    header("Content-Length: " . strlen($contents));
    header("ETag: \"$etag\"");

    echo $contents;
    exit;
}

elgg_ws_expose_function('group.get_icon',
    "group_get_icon",
    array(
        'guid' => array ('type' => 'string', 'required' => true),
        'size' => array ('type' => 'string', 'required' => false, 'default' => 'medium'),
    ),
    "GET group icon",
    'GET',
    true,
    true);


/**
 * @param $guid
 * @return mixed
 * @throws InvalidParameterException
 */
function group_join_group($guid) {
    global $CONFIG;

    $user = elgg_get_logged_in_user_entity();
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    /* @var ElggGroup $group */
    $group = get_entity($guid);
    if (!($group instanceof ElggGroup)) {
        $return['joined'] = false;
        $return['message'] = 'Group Not Found';
        exit;
    }
    
    // access bypass for getting invisible group
    $ia = elgg_set_ignore_access(true);
    elgg_set_ignore_access($ia);

    if ($user && ($group instanceof ElggGroup)) {

        // join or request
        $join = false;
        if ($group->isPublicMembership() || $group->canEdit($user->guid)) {
            // anyone can join public groups and admins can join any group
            $join = true;
        } else {
            if (check_entity_relationship($group->guid, 'invited', $user->guid)) {
                // user has invite to closed group
                $join = true;
            }
        }

        if ($join) {
            if (groups_join_group($group, $user)) {
                $return['member'] = 'joined';
                $return['message'] = 'joined';
            } else {
                $isMember = isMemberOf($group, $user);
                if ($isMember) {
                    $return['member'] = 'joined';
                    $return['message'] = 'isMemberOf';
                } else {
                    $return['member'] = 'cantjoin';
                    $return['message'] = 'cantjoin';
                }
            }
        } else {
            add_entity_relationship($user->guid, 'membership_request', $group->guid);

            $owner = $group->getOwnerEntity();

            $url = "{$CONFIG->url}groups/requests/$group->guid";

            $subject = elgg_echo('groups:request:subject', array(
                $user->name,
                $group->name,
            ), $owner->language);

            $body = elgg_echo('groups:request:body', array(
                $group->getOwnerEntity()->name,
                $user->name,
                $group->name,
                $user->getURL(),
                $url,
            ), $owner->language);

            // Notify group owner
            if (notify_user($owner->guid, $user->getGUID(), $subject, $body)) {
                $return['member'] = 'cantjoin';
                $return['message'] = 'joinrequestmade';
            } else {
                $return['member'] = 'cantjoin';
                $return['message'] = 'joinrequestnotmade';
            }
        }
    } else {
        $isMember = isMemberOf($group, $user);
        if ($isMember) {
            $return['member'] = 'joined';
            $return['message'] = 'isMemberOf';
        } else {
            $return['member'] = 'cantjoin';
            $return['message'] = 'cantjoin';
        }
    }

    return $return;
}

elgg_ws_expose_function('group.join_group',
    "group_join_group",
    array(
        'guid' => array ('type' => 'string', 'required' => true),
    ),
    "Join group",
    'POST',
    true,
    true);


/**
 * @param $guid
 * @return mixed
 * @throws InvalidParameterException
 */
function group_leave_group($guid) {
    $user = elgg_get_logged_in_user_entity();
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    /* @var ElggGroup $group */
    $group = get_entity($guid);
    if (!($group instanceof ElggGroup)) {
        $return['member'] = 'left';
        $return['message'] = 'Group Not Found';
        exit;
    }

    if ($user && ($group instanceof ElggGroup)) {
        if ($group->getOwnerGUID() != elgg_get_logged_in_user_guid()) {
            if ($group->leave($user)) {
                $return['member'] = 'left';
                $return['message'] = 'leftGroup';
            } else {
                $return['member'] = 'cantLeaveGroup';
                $return['message'] = 'cantLeaveGroup';
            }
        } else {
            $return['member'] = 'cantLeaveGroup';
            $return['message'] = 'cantLeaveGroup';
        }
    } else {
        $return['member'] = 'cantLeaveGroup';
        $return['message'] = 'cantLeaveGroup';
    }

    return $return;
}

elgg_ws_expose_function('group.leave_group',
    "group_leave_group",
    array(
        'guid' => array ('type' => 'string', 'required' => true),
    ),
    "Leave group",
    'POST',
    true,
    true);


/**
 * @param $group
 * @param $user
 * @return bool
 */
function isMemberOf($group, $user) {
    $members = $group->getMembers();
    $isMember = false;
    foreach ($members as $member) {
        if ($member->guid = $user->guid) {
            $isMember = true;
            return $isMember;
        }
    }
    return $isMember;
}

