<?php
  
class DB_Connect {

    // Connecting to database
    public function connect() {
        global $CONFIG;

        // connecting to mysql
        $con = mysqli_connect($CONFIG->dbhost, $CONFIG->dbuser, $CONFIG->dbpass, $CONFIG->dbname);
        // selecting database
        if (!$con) {
            error_log("Error: Unable to connect to MySQL." . PHP_EOL);
            error_log("Debugging errno: " . mysqli_connect_errno() . PHP_EOL);
            error_log("Debugging error: " . mysqli_connect_error() . PHP_EOL);
            exit;
        }
  
        // return database handler
        return $con;
    }
} 

