<?php

class DB_Register_Functions {

    private $db;

    // constructor
    function __construct() {
        $path = elgg_get_plugins_path();
        include_once $path . 'elgg_with_rest_api/lib/DB_connect.php';

        $conn = new DB_Connect();
        $this->db = $conn->connect();
    }

    // destructor
    function __destruct() {

    }

    /**
     * Storing new user
     * returns user details
     * @param $name
     * @param $account
     * @param $gcm_regid
     * @param $elgg_post
     * @param $elgg_message
     * @return array|bool|null
     */
    public function storeUser($name, $account, $gcm_regid, $elgg_post, $elgg_message) {
        // insert user into database
        $result = mysqli_query($this->db, "INSERT INTO gcm_users(name, account, gcm_regid, elgg_post, elgg_message, created_at) VALUES('$name', '$account', '$gcm_regid', '$elgg_post', '$elgg_message',NOW())");
        // check for successful store
        if ($result) {
            // get user details
            $id = mysqli_insert_id($this->db); // last inserted id
            $result = mysqli_query($this->db, "SELECT * FROM gcm_users WHERE id = $id");
            // return user details
            if (mysqli_num_rows($result) > 0) {
                return mysqli_fetch_array($result);
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
        $result = mysqli_query($this->db, "select * FROM gcm_users");
        return $result;
    }

    /**
     * @param $name
     * @param $account
     * @param $gcm_regid
     * @param $elgg_post
     * @param $elgg_message
     * @return array|bool|null
     */
    public function updateUser($name, $account, $gcm_regid, $elgg_post, $elgg_message)
    {
        $result = mysqli_query($this->db, "UPDATE gcm_users SET name='$name', account='$account', elgg_post='$elgg_post', elgg_message='$elgg_message' WHERE gcm_regid='$gcm_regid'");

        if (mysqli_num_rows($result) > 0) {
            return mysqli_fetch_array($result);;
        } else {
            return false;
        }
    }

    /**
     * @param $gcm_regid
     * @return bool
     */
    public function checkUser($gcm_regid)
    {
        $result = mysqli_query($this->db, "SELECT * FROM gcm_users WHERE gcm_regid = '$gcm_regid'");
        if (mysqli_num_rows($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getRegId($recipient_username)
    {
        $results = mysqli_query($this->db, "select * FROM gcm_users WHERE account = '$recipient_username'");
        return $results;
    }

    /**
     * @param $regId
     * @return bool|mysqli_result
     */
    public function deleteRegId($regId)
    {
        $result = mysqli_query($this->db, "DELETE FROM gcm_users WHERE gcm_regid = '$regId'");
        return $result;
    }

    /**
     * @param $old_regId
     * @param $new_regId
     * @return bool
     */
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
            $result = mysqli_query($this->db, "UPDATE gcm_users SET gcm_regid='$new_regId' WHERE gcm_regid='$old_regId'");
            if ($result) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @param $regId
     * @return array|bool|null
     */
    public function checkRegId($regId)
    {
        $result = mysqli_query($this->db, "select * FROM gcm_users WHERE gcm_regid = '$regId'");
        if (mysqli_num_rows($result) > 0) {
            return mysqli_fetch_array($result);
        } else {
            return false;
        }
    }

}

