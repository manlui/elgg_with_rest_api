<?php
/*
Parameters Used:
$fbData['user_profile']['accessToken'] = (string) $accessToken;
$fbData['user_profile']['id'] = $user_id;
$fbData['user_profile']['name'] = $profile_name;
$fbData['user_profile']['email'] = $user_email;
$fbData['user_profile']['username'] = $user_name;

@return
$return['success'] - true/false;
$return['message'] - Login Status description
$return['user_guid'] = user GUID
$return['user_name'] = Users Profile Name
$return['user_username'] = User Username
$return['user_email'] = User Email
*/

function login_with_fb($accessToken, $user_id, $profile_name, $user_email, $user_name){
	$fbData['user_profile']['accessToken'] = (string) $accessToken;
	$fbData['user_profile']['id'] = $user_id;
	$fbData['user_profile']['name'] = $profile_name;
	$fbData['user_profile']['email'] = $user_email;
	$fbData['user_profile']['username'] = $user_name;
	$fbData['user_profile']['education'] = file_get_contents("https://graph.facebook.com/$user_id?fields=education&access_token=$accessToken");
	
		$options = array(
			'type' => 'user',
			'plugin_user_setting_name_value_pairs' => array(
				'uid' => $fbData['user_profile']['id'],
				'access_token' => $fbData['user_profile']['accessToken'],
			),
			'plugin_user_setting_name_value_pairs_operator' => 'OR',
			'limit' => 0
		);
		$users = elgg_get_entities_from_plugin_user_settings($options);
		
	if ($users) {
		// 1 User Found and it will return a successful return status
			if (count($users) == 1) {
					$return['success'] = true;
					$return['message'] = elgg_echo("Welcome! Facebook user logged in successfully");
					$return['user_guid'] = $users[0]->guid;
					$return['user_name'] = $users[0]->name;
					$return['user_username'] = $users[0]->username;
					$return['user_email'] = $users[0]->email;
					$return['auth_token'] = create_user_token($users[0]->username);
					$return['api_key'] = get_api_key();
					elgg_set_plugin_user_setting('uid', $fbData['user_profile']['id'], $users[0]->guid);
					elgg_set_plugin_user_setting('access_token', $fbData['user_profile']['accessToken'], $users[0]->guid);
					elgg_set_plugin_user_setting('education', $fbData['user_profile']['education'], $users[0]->guid);
					if(empty($users[0]->email)) {
						$user = get_entity($users[0]->guid);
						$user->email = $fbData['user_profile']['email'];
						$user->save();
					}
			} else {
		// More than 1 User Found and it will return an unsuccessful return status
				$return['success'] = false;
				$return['message'] = elgg_echo("Oops! Facebook user not logged in successfully");
			}
		} else {
		// No user was found and it will create a new user based in fbData
		$user = facebook_connect_create_update_user($fbData);
			
		if($user){
			// If the registration was successfully
			$return['success'] = true;
			$return['message'] = elgg_echo("Welcome! Facebook user registered successfully!");
			$return['user_guid'] = $user->guid;
			$return['user_name'] = $user->name;
			$return['user_username'] = $user->username;
			$return['user_email'] = $user->email;
			$return['auth_token'] = create_user_token($user->username);
			$return['api_key'] = get_api_key();
			elgg_set_plugin_user_setting('uid', $fbData['user_profile']['id'], $user->guid);
			elgg_set_plugin_user_setting('access_token', $fbData['user_profile']['accessToken'], $user->guid);
			elgg_set_plugin_user_setting('education', $fbData['user_profile']['education'],$user->guid);
		} else {
			// If the registration was not successful
			$return['success'] = false;
			$return['message'] = elgg_echo("Oops! Facebook user not registered successfully");
		}
	}
	return $return;
}


elgg_ws_expose_function('facebook.user_login',
	"login_with_fb",
	array('accessToken' => array ('type' => 'string'),
		'user_id' => array ('type' => 'string'),
		'profile_name' => array ('type' => 'string'),
		'user_email' => array ('type' => 'string'),
		'user_name' => array ('type' => 'string'),
	),
	"Register user using Facebook",
	'GET',
    true,
    false);
?>
