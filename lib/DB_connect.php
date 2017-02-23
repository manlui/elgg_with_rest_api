<?php
  
class DB_Connect {

    // Connecting to database
    public function connect() {
        global $CONFIG;

        // connecting to mysql
        $con = mysqli_connect($CONFIG->dbhost, $CONFIG->dbuser, $CONFIG->dbpass, $CONFIG->dbname);
        // selecting database
        if (!$con) {
            exit;
        }
  
        // return database handler
        return $con;
    }
} 
