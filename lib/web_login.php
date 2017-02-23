<?php 
/**
 * The aweb_login API.
 * This API call lets a user log in.
 * @param string $username - Username
 * @param string $password - Clear text password
 */
function web_login($username, $password) {
	// check if username is an email address
	if (is_email_address($username)) {
		$users = get_user_by_email($username);

		// check if we have a unique user
		if (is_array($users) && (count($users) == 1)) {
			$username = $users[0]->username;
		}
	}

	// validate username and password
	if (true === elgg_authenticate($username, $password)) {
		$return['status'] = 1;
		$return['message'] = "Login Sucessful!!!";
	} else {
		$return['status'] = 0;
		$return['message'] = "Login Failed";
	}
return $return;
}

elgg_ws_expose_function('web_login',
    "web_login",
    array('username' => array ('type' => 'string', 'required' => true),
        'password' => array ('type' => 'string', 'required' => true),
    ),
    "Check user login for web.",
    'POST',
    false,
    false);
?>