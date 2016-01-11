<?php

class DB_Register_Functions {

    private $db;

    //put your code here
    // constructor
    function __construct() {
        $path = elgg_get_plugins_path();
        include_once $path . 'web_services/lib/DB_connect.php';
//        $path = elgg_get_plugins_path();
//        include_once $path.'gcm/DB_connect.php';
        // connecting to database
        $this->db = new DB_Connect();
        $this->db->connect();
    }

    // destructor
    function __destruct() {

    }

    /**
     * Storing new user
     * returns user details
     */
    public function storeUser($name, $account, $gcm_regid, $elgg_post, $elgg_message) {
        // insert user into database
        $result = mysql_query("INSERT INTO gcm_users(name, account, gcm_regid, elgg_post, elgg_message, created_at) VALUES('$name', '$account', '$gcm_regid', '$elgg_post', '$elgg_message',NOW())");
        // check for successful store
        if ($result) {
            // get user details
            $id = mysql_insert_id(); // last inserted id
            $result = mysql_query("SELECT * FROM gcm_users WHERE id = $id") or die(mysql_error());
            // return user details
            if (mysql_num_rows($result) > 0) {
                return mysql_fetch_array($result);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Getting all users
     */
    public function getAllUsers() {
        $result = mysql_query("select * FROM gcm_users");
        return $result;
    }

    public function updateUser($name, $account, $gcm_regid, $elgg_post, $elgg_message)
    {
        $result = mysql_query("UPDATE gcm_users SET name='$name', account='$account', elgg_post='$elgg_post', elgg_message='$elgg_message' WHERE gcm_regid='$gcm_regid'");

        if (mysql_num_rows($result) > 0) {
            return mysql_fetch_array($result);;
        } else {
            return false;
        }
    }

    public function checkUser($gcm_regid)
    {
        $result = mysql_query("SELECT * FROM gcm_users WHERE gcm_regid = '$gcm_regid'") or die(mysql_error());
        if (mysql_num_rows($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getRegId($recipient_username)
    {
        $results = mysql_query("select * FROM gcm_users WHERE account = '$recipient_username'");
        return $results;
//        while ($rows[] = mysql_fetch_array($results, MYSQL_ASSOC));
//        return $rows;
    }

    public function deleteRegId($regId)
    {
        $result = mysql_query("DELETE FROM gcm_users WHERE gcm_regid = '$regId'");
        return $result;
    }

    public function updateNewRegId($old_regId, $new_regId)
    {
        $result = $this->checkRegId($new_regId);
        if ($result) {
            $deleteResult = $this->deleteRegId($old_regId);
            if ($deleteResult) {
                return true;
            } else {
                return false;
            }
        } else {
            $result = mysql_query("UPDATE gcm_users SET gcm_regid='$new_regId' WHERE gcm_regid='$old_regId'");
            if ($result) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function checkRegId($regId)
    {
        $result = mysql_query("select * FROM gcm_users WHERE gcm_regid = '$regId'");
        if (mysql_num_rows($result) > 0) {
            return mysql_fetch_array($result);
        } else {
            return false;
        }
    }

}

?>
