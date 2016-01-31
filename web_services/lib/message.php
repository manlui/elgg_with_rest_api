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
			$message['user']['avatar_url'] = get_entity_icon_url($user,'small');
			
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
				"Read a sigle message",
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

    $user = get_user_by_username(get_input('username'));
	if (!$user) {
		throw new InvalidParameterException('registration:usernamenotvalid');
		$response['status'] = 1;
		$response['result'] = 'user account not valid';
		return $response;
		exit;
	}

	$params = array(
		'type' => 'object',
		'subtype' => 'messages',
		'metadata_name' => 'toId',
		'metadata_value' => $user->guid,
		'owner_guid' => $user->guid,
        'offset' => (int) max(get_input('offset', 0), 0),
        'limit' => (int) max(get_input('limit', 10), 0),
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
			$message['user']['avatar_url'] = get_entity_icon_url($user,'small');
			
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

	$user = get_loggedin_user();
//    $user = get_user_by_username($username);
	$params = array(
		'type' => 'object',
		'subtype' => 'messages',
		'metadata_name' => 'fromId',
		'metadata_value' => $user->guid,
		'owner_guid' => $user->guid,
        'offset' => (int) max(get_input('offset', 0), 0),
        'limit' => (int) max(get_input('limit', 10), 0),
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
			$message['user']['avatar_url'] = get_entity_icon_url($user,'small');
			
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
		$response['status'] = 1;
		$response['result'] = 'Recipient account not valid';
		return $response;
		exit;
	}

	$recipient_guid = $recipient->guid;
	$result = messages_send($subject, $body, $recipient_guid, 0, $reply);

	if ($result) {
		$response['status'] = 0;
		$response['result'] = $result;
	} else {
		$response['status'] = 1;
		$response['result'] = 'problem send message';
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
 
