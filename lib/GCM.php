<?php
 
class GCM {
 
    //put your code here
    // constructor
    function __construct() {
        $path = elgg_get_plugins_path();
        include_once $path.'elgg_with_rest_api/lib/DB_Register_Functions.php';
    }

    public function setup_message($sender_name, $sender_username, $recipient_name, $recipient_username, $message_sent_title, $message_sent_description)
    {

        $db = new DB_Register_Functions();

        $results = $db->getRegId($recipient_username);
        $body = strip_tags($message_sent_description);

        while ($row = mysqli_fetch_array($results)) {
            $gcm_regid = $row['gcm_regid'];
            if ($gcm_regid) {
                $registatoin_ids = array($gcm_regid);
                $message = array( "from_name" => $sender_name,
                                  "from_username" => $sender_username,
                                  "subject" => $message_sent_title,
                                  "message" => $body,
                                  "recipient_username" => $recipient_username
                               );
                $response = $this->send_notification($registatoin_ids, $message);

                foreach ($response['results'] as $k => $val) {
                    if (isset($val['registration_id'])) {
                        $this->updateRegId($gcm_regid, $val['registration_id']);
                    } else if (isset($val['error'])) {
                        if ($val['error'] === 'NotRegistered') {
                            $this->removeOldRegId($gcm_regid);
                        }
                    }
                }

            }

        }
        return true;
    }

    /**
     * Sending Push Notification
     * @param $registration_ids
     * @param $message
     * @return bool|mixed
     */
    public function send_notification($registration_ids, $message) {

        if ($message) {
            // include config
            $GOOGLE_API_KEY = elgg_get_plugin_setting('google_api_key', 'elgg_with_rest_api');

            // Set POST variables
            $url = 'https://fcm.googleapis.com/fcm/send';

            $fields = array(
                'registration_ids' => $registration_ids,
                'data' => $message,
            );

            $headers = array(
                'Authorization: key=' . $GOOGLE_API_KEY,
                'Content-Type: application/json'
            );
            // Open connection
            $ch = curl_init();

            // Set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Disabling SSL Certificate support temporarly
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

            // Execute post
            $result = curl_exec($ch);
            error_log("[".date(DATE_RFC2822). " " . $result . PHP_EOL, 3, "web_error_log");
            if ($result === FALSE) {
                die('Curl failed: ' . curl_error($ch));
                return false;
            } else {
                return json_decode($result, true);
            }

            // Close connection
            curl_close($ch);
        }
    }

    function removeOldRegId($regId)
    {
        $db = new DB_Register_Functions();
        $result = $db->deleteRegId($regId);
        if ($result) {
            error_log("[".date(DATE_RFC2822)."] Success removed RegId". PHP_EOL, 3, "web_error_log");
        } else {
            error_log("[".date(DATE_RFC2822)."] Fail to remove RedId". PHP_EOL, 3, "web_error_log");
        }
    }

    public function updateRegId($old_regId, $new_regId)
    {
        $db = new DB_Register_Functions();
        $result = $db->updateNewRegId($old_regId, $new_regId);
        if ($result) {
            error_log("[".date(DATE_RFC2822)."] Success updated RegId". PHP_EOL, 3, "web_error_log");
        } else {
            error_log("[".date(DATE_RFC2822)."] Fail to update RedId". PHP_EOL, 3, "web_error_log");
        }
    }
}
