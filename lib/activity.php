<?php
/**
 * Created by IntelliJ IDEA.
 * User: mlui
 * Date: 3/10/2016
 * Time: 10:10 AM
 */

function site_river_mine($username, $limit=20, $offset=0, $from_guid) {
    global $jsonexport;

    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
        throw new InvalidParameterException('registration:usernamenotvalid');
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    if ($from_guid > 0) {
        $offset = $offset + getActivityGuidPosition($from_guid, "mine", $user);
    }

    $options = array(
        'distinct' => false,
        'subject_guids' => $user->guid,
        'offset' => $offset,
        'limit' => $limit,
    );

    $activities = elgg_get_river($options);

    $login_user = $user;
    $handle = getRiverActivity($activities, $user, $login_user);

    $jsonexport['activity'] = $handle;

    return $jsonexport['activity'];
}

elgg_ws_expose_function('site.river_mine',
    'site_river_mine',
    array(
        'username' => array ('type' => 'string', 'required' =>true),
        'limit' => array ('type' => 'int', 'required' => false),
        'offset' => array ('type' => 'int', 'required' => false),
        'from_guid' => array ('type' => 'int', 'required' => false, 'default' => 0),
    ),
    "Read mine latest news feed",
    'GET',
    true,
    true);

function site_river_friends($username, $limit=20, $offset=0, $from_guid) {
    global $jsonexport;

    if(!$username) {
        $user = elgg_get_logged_in_user_entity();
        throw new InvalidParameterException('registration:usernamenotvalid');
    } else {
        $user = get_user_by_username($username);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
        }
    }

    if ($from_guid > 0) {
        $offset = $offset + getActivityGuidPosition($from_guid, "friends", $user);
    }

    $options = array(
        'distinct' => false,
        'relationship' => 'friend',
        'relationship_guid' => $user->guid,
        'offset' => $offset,
        'limit' => $limit,
    );

    $activities = elgg_get_river($options);

    $login_user = $user;
    $handle = getRiverActivity($activities, $user, $login_user);

    $jsonexport['activity'] = $handle;

    return $jsonexport['activity'];
}

elgg_ws_expose_function('site.river_friends',
    'site_river_friends',
    array(
        'username' => array ('type' => 'string', 'required' =>true),
        'limit' => array ('type' => 'int', 'required' => false),
        'offset' => array ('type' => 'int', 'required' => false),
        'from_guid' => array ('type' => 'int', 'required' => false, 'default' => 0),
    ),
    "Read friends latest news feed",
    'GET',
    true,
    true);

function getActivityGuidPosition($guid, $context, $loginUser) {
    $notFound = true;
    $offset = 0;
    while($notFound) {
        if ($context == 'mine') {
            $options = array(
                'distinct' => false,
                'subject_guids' => $loginUser->guid,
                'offset' => $offset,
                'limit' => 1,
            );
            $activity = elgg_get_river($options);
        } else if ($context == 'friends') {
            $options = array(
                'distinct' => false,
                'relationship' => 'friend',
                'relationship_guid' => $loginUser->guid,
                'offset' => $offset,
                'limit' => 1,
            );

            $activity = elgg_get_river($options);
        }

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