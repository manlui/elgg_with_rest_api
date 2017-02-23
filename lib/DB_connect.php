<?php
  
class DB_Connect {

    // Connecting to database
    public function connect() {
        global $CONFIG;

        // connecting to mysql
        $con = mysqli_connect($CONFIG->dbhost, $CONFIG->dbuser, $CONFIG->dbpass, $CONFIG->dbname);
        // selecting database
        if (!$con) {
            error_log("[".date(DATE_RFC2822)."] Error: Unable to connect to MySQL." . PHP_EOL, 3, "web_error_log");
            error_log("[".date(DATE_RFC2822)."] Debugging errno: " . mysqli_connect_errno() . PHP_EOL, 3, "web_error_log");
            error_log("[".date(DATE_RFC2822)."] Debugging error: " . mysqli_connect_error() . PHP_EOL, 3, "web_error_log");
            exit;
        }
  
        // return database handler
        return $con;
    }
} 
