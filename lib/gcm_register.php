<?php
/**
 * Created by PhpStorm.
 * Date: 12/7/2015
 * Time: 12:30 PM
 * @param $regId
 * @param $account
 * @param $name
 * @return mixed
 * @throws InvalidParameterException
 */

function gcm_register($regId, $account, $name) {
    if(!$account) {
        $response['status'] = 1;
        $response['result'] = 'please enter valid user account';
        return $response;
        exit;
    } else {
        $user = get_user_by_username($account);
        if (!$user) {
            throw new InvalidParameterException('registration:usernamenotvalid');
            $response['status'] = 1;
            $response['result'] = 'user account not valid';
            return $response;
            exit;
        }
    }

    // create the tables for API stats
    $path = elgg_get_plugins_path();
    run_sql_script($path . "elgg_with_rest_api/schema/mysql.sql");

    if ($account && $regId) {
        $elgg_post = 1;
        $elgg_message = 1;
        // Store user details in db
        include_once $path . 'elgg_with_rest_api/lib/DB_Register_Functions.php';
        include_once $path . 'elgg_with_rest_api/lib/GCM.php';

        $db = new DB_Register_Functions();
        $gcm = new GCM();

        if ($db->checkUser($regId)) {
            $res = $db->updateUser($name, $account, $regId, $elgg_post, $elgg_message);

            $response['status'] = 0;
            $response['result'] = "success update gcm regId and user info";
        } else {
            $res = $db->storeUser($name, $account, $regId, $elgg_post, $elgg_message);
            $registration_ids = array($regId);
            $message = array("from_name" => "Core Server",
                "subject" => "Campus Karma Notification",
                "message" => "Enable Receive Notification");

            $result = $gcm->send_notification($registration_ids, $message);

            $response['status'] = 0;
            $response['result'] = "success Insert gcm regId and user info";
        }

    } else {
        // user details missing
        $response['status'] = 1;
        $response['result'] = 'Missing name or reg id';
    }

    return $response;
}

elgg_ws_expose_function('gcm.register',
    "gcm_register",
    array(
        'regId' => array ('type' => 'string', 'required' => true),
        'account' => array ('type' => 'string', 'required' => true),
        'name' => array ('type' => 'string', 'required' => true),
    ),
    "GCM a Register for Notification",
    'POST',
    true,
    false);