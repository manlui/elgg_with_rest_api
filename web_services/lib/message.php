<?php 

/**
 * Web service to read a message
 *
 * @param int $guid
 *
 * @return array $message Array of message content
 */
function messages_read($guid) {	

			$single = get_entity($guid);
			
			$single->readYet = true;
			
			$message['guid'] = $single->guid;
	
			$message[$single->guid]['subject'] = $single->title;
			
			$user = get_entity($single->fromId);
			$message['user']['guid'] = $user->guid;
			$message['user']['name'] = $user->name;
			$message['user']['username'] = $user->username;
			$message['user']['avatar_url'] = getProfileIcon($user); //$user->getIconURL('small');
			
			$message['timestamp'] = time_ago((int)$single->time_created);
			
			$message['description'] = $single->description;
			
			if($single->readYet){
			$message['read'] = "yes";
			}else{
			$message['read'] = "no";
			}
	
			return $message;
}

elgg_ws_expose_function('messages.read',
				"messages_read",
				array(
					  'guid' => array ('type' => 'int', 'required' => true),
					),
				"Read a single message",
				'GET',
				true,
				true);
/**
 * Web service to get a count of the users unread messages
 *
 *
 * @return array $message Array of message content
 */
function messages_count() {	
	$count = (int)messages_count_unread();
	return $count;
}

elgg_ws_expose_function('messages.count',
				"messages_count",
				array(
					),
				"Get a count of the users unread messages",
				'GET',
				true, true);
/**
 * Web service to get messages inbox
 *
 * @param int|string $limit (optional) default 10
 * @param int|string $offset (optional) default 0
 * @return array $message Array of files uploaded
 * @throws InvalidParameterException
 */
function messages_inbox($limit = 20, $offset = 0) {

	$user = elgg_get_logged_in_user_entity();
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}

	$params = array(
		'type' => 'object',
		'subtype' => 'messages',
		'metadata_name' => 'toId',
		'metadata_value' => $user->guid,
		'owner_guid' => $user->guid,
        'offset' => $offset,
        'limit' => $limit,
		'full_view' => false,
    );
	
	$list = elgg_get_entities_from_metadata($params);

	if($list) {
		foreach($list as $single ) {
			$message['guid'] = $single->guid;
			$message['subject'] = $single->title;
			
			$user = get_entity($single->fromId);
			$message['user']['guid'] = $user->guid;
			$message['user']['name'] = $user->name;
			$message['user']['username'] = $user->username;
			$message['user']['avatar_url'] = getProfileIcon($user); //$user->getIconURL('small');
			
			$message['timestamp'] = time_ago((int)$single->time_created);
			
			$message['description'] = $single->description;
			
			if($single->readYet){
			$message['read'] = "yes";
			}else{
			$message['read'] = "no";
			}
			$return[] = $message;
		}
	}
	else {
	 	$msg = elgg_echo('messages:nomessages');
		throw new InvalidParameterException($msg);
	}
	return $return;
}

elgg_ws_expose_function('messages.inbox',
				"messages_inbox",
				array(
					  'limit' => array ('type' => 'int', 'required' => false),
					  'offset' => array ('type' => 'int', 'required' => false),
					),
				"Get messages inbox",
				'GET',
				true,
				true);

/**
 * Web service to get sent messages
 *
 * @param int|string $limit (optional) default 10
 * @param int|string $offset (optional) default 0
 * @return array $mesage Array of files uploaded
 * @throws InvalidParameterException
 */
function messages_sent($limit = 10, $offset = 0) {

	$user = elgg_get_logged_in_user_entity();
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}

	$params = array(
		'type' => 'object',
		'subtype' => 'messages',
		'metadata_name' => 'fromId',
		'metadata_value' => $user->guid,
		'owner_guid' => $user->guid,
        'offset' => $offset,
        'limit' => $limit,
		'full_view' => false,
	);
	
	
	$list = elgg_get_entities_from_metadata($params);
	if($list) {
		foreach($list as $single ) {
			$message['guid'] = $single->guid;
			$message['subject'] = $single->title;
			

			$user = get_entity($single->toId);
			$message['user']['guid'] = $user->guid;
			$message['user']['name'] = $user->name;
			$message['user']['username'] = $user->username;
			$message['user']['avatar_url'] = getProfileIcon($user); //$user->getIconURL('small');
			
			$message['timestamp'] = time_ago((int)$single->time_created);
			
			$message['description'] = $single->description;
			
			if($single->readYet){
			$message['read'] = "yes";
			}else{
			$message['read'] = "no";
			}
			$return[] = $message;
		}
	}
	else {
	 	$msg = elgg_echo('messages:nomessages');
		throw new InvalidParameterException($msg);
	}
	return $return;
}

elgg_ws_expose_function('messages.sent',
				"messages_sent",
				array(
					  'limit' => array ('type' => 'int', 'required' => false),
					  'offset' => array ('type' => 'int', 'required' => false),
					),
				"Get sent",
				'GET',
				true,
				true);

/**
 * Web service to send a message
 *
 * @param string $subject (required)
 * @param string $body (required)
 * @param int $send_to (required)
 * @param int $reply (optional), Default 0
 * @return Success /Fail
 * @throws InvalidParameterException
 */
function message_send($subject,$body, $send_to, $reply = 0) {
	$recipient = get_user_by_username($send_to);
	if (!$recipient) {
		throw new InvalidParameterException('registration:usernamenotvalid');
	}

	$recipient_guid = $recipient->guid;
	$result = messages_send($subject, $body, $recipient_guid, 0, $reply);

	if ($result) {
		$response['guid'] = $result;
	} else {
		$response['guid'] = 0;
	}
		
	return $response;
}

elgg_ws_expose_function('message.send',
				"message_send",
				array(
						'subject' => array ('type' => 'string'),
						'body' => array ('type' => 'string'),
					  	'send_to' => array ('type' => 'string'),
						'reply' => array ('type' => 'int', 'required' => false, 'default'=>0),
					),
				"Send a message",
				'POST',
				true,
				true);


/**
 * @param $guid
 * @return mixed
 */
function messages_delete($guid) {

	$message = get_entity($guid);

	if (!elgg_instanceof($message, 'object', 'messages') || !$message->canEdit()) {
		$return['deleted'] = 0;
		$return['message'] = elgg_echo('messages:error:delete:single');
	}

	if (!$message->delete()) {
		$return['deleted'] = 0;
		$return['message'] = elgg_echo('messages:error:delete:single');
	} else {
		$return['deleted'] = 1;
		$return['message'] = elgg_echo('messages:success:delete:single');
	}

	return $return;
}

elgg_ws_expose_function('messages.delete',
	"messages_delete",
	array(
		'guid' => array ('type' => 'int', 'required' => true),
	),
	"Delete a message",
	'POST',
	true,
	true);

/**
 * @param $guid
 * @return mixed
 */
function messages_mark_as_unread($guid) {
	$message = get_entity($guid);

	if (!elgg_instanceof($message, 'object', 'messages') || !$message->canEdit()) {
		$return['unread'] = -1;
		$return['message'] = elgg_echo('messages:error:delete:single');
	}

    $read = $message->readYet;
    if ($read) {
        $message->readYet = 0;
    }
	if (!$message->readYet) {
		$return['unread'] = 1;
	} else {
		$return['unread'] = 0;
	}

	return $return;
}

elgg_ws_expose_function('messages.mark_as_unread',
	"messages_mark_as_unread",
	array(
		'guid' => array ('type' => 'int', 'required' => true),
	),
	"Unread a message",
	'POST',
	true,
	true);

/**
 * Web service to read a message
 *
 * @param $guidString
 * @return array $message Array of message content
 * @internal param int $guid
 *
 */
function messages_mark_all_as_read($guidString)
{

    $guidArray = string_to_tag_array($guidString);

    foreach ($guidArray as $guid) {

        $single = get_entity($guid);

        if (!elgg_instanceof($single, 'object', 'messages') || !$single->canEdit()) {
            $message['guid'] = $guid;
            $message['message'] = 'fail';
        } else {
            $single->readYet = 1;

            $message['guid'] = $guid;
            $message['message'] = 'success';
        }

        $return[] = $message;
    }

	return $return;
}

elgg_ws_expose_function('messages.mark_all_as_read',
	"messages_mark_all_as_read",
	array(
		'guidString' => array ('type' => 'string', 'required' => true),
	),
	"Mark all select messages as read",
	'POST',
	true,
	true);


/**
 * Web service to read a message
 *
 * @param $guidString
 * @return array $message Array of message content
 * @internal param int $guid
 *
 */
function messages_mark_all_as_unread($guidString)
{

    $guidArray = string_to_tag_array($guidString);

    foreach ($guidArray as $guid) {

        $single = get_entity($guid);

        if (!elgg_instanceof($single, 'object', 'messages') || !$single->canEdit()) {
            $message['guid'] = $guid;
            $message['message'] = 'fail';
        } else {
            $single->readYet = 0;

            $message['guid'] = $guid;
            $message['message'] = 'success';
        }

        $return[] = $message;
    }

    return $return;
}

elgg_ws_expose_function('messages.mark_all_as_unread',
    "messages_mark_all_as_unread",
    array(
        'guidString' => array ('type' => 'string', 'required' => true),
    ),
    "Mark all select messages as unread",
    'POST',
    true,
    true);


function messages_multiple_delete($guidString)
{

    $guidArray = string_to_tag_array($guidString);

    foreach ($guidArray as $guid) {

        $single = get_entity($guid);

        if (!elgg_instanceof($single, 'object', 'messages') || !$single->canEdit()) {
            $message['guid'] = $guid;
            $message['message'] = 'fail';
        } else {
            if (!$single->delete()) {
                $message['guid'] = $guid;
                $message['message'] = 'success';
            } else {
                $message['guid'] = $guid;
                $message['message'] = 'fail';
            }
        }

        $return[] = $message;
    }

    return $return;
}

elgg_ws_expose_function('messages.multiple_delete',
    "messages_multiple_delete",
    array(
        'guidString' => array ('type' => 'string', 'required' => true),
    ),
    "Multiple messages delete",
    'POST',
    true,
    true);