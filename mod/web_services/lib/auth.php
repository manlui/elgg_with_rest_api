<?php


function auth_token_check($token, $username, $password)
{

    $user = get_user_by_username($username);
    if (!$user) {
        throw new InvalidParameterException('registration:usernamenotvalid');
    }

    if (validate_user_token($token, 1) == $user->guid) {
        $return['token'] = 'OK';

    } else {
        $return = auth_gettoken($username, $password);
    }

    return $return;
}

elgg_ws_expose_function('auth.token_check',
    "auth_token_check",
    array( 'token' => array ('type' => 'string', 'required' => true),
        'username' => array ('type' => 'string', 'required' => true),
        'password' => array ('type' => 'string', 'required' => true),
    ),
    "Post a auth token check",
    'POST',
    true,
    false);

function get_api_key() {
    $list = elgg_get_entities(array(
        'type' => 'object',
        'subtype' => 'api_key',
    ));

    $api_key='';
    if ($list) {
        if(sizeof($list) === 1) {
            $entity = get_entity($list[0]->guid);
            $api_key = $entity->public;
        } else {
            foreach($list as $item){
                $entity = get_entity($item->get('guid'));
                if ($entity->title == 'android') {
                    $api_key = $entity->public;
                }
            }
        }

    }
    return $api_key;
}
