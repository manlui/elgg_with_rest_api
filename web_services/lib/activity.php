<?php
/**
 * Created by IntelliJ IDEA.
 * User: mlui
 * Date: 3/10/2016
 * Time: 10:10 AM
 */

function site_river_mine($username, $limit=20, $offset=0) {
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

    $options = array(
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
    ),
    "Read mine latest news feed",
    'GET',
    true,
    true);

function site_river_friends($username, $limit=20, $offset=0) {
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

    $options = array(
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
    ),
    "Read mine latest news feed",
    'GET',
    true,
    true);