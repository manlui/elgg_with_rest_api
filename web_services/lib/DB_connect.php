<?php
  
class DB_Connect {
  
    // constructor
    function __construct() {
  
    }
  
    // destructor
    function __destruct() {
        // $this->close();
    }
  
    // Connecting to database
    public function connect() {
        global $CONFIG;

        // connecting to mysql
        $con = mysqli_connect($CONFIG->dbhost, $CONFIG->dbuser, $CONFIG->dbpass, $CONFIG->dbname);
        // selecting database
        //mysql_select_db($CONFIG->dbname);
        if (!$con) {
            echo "Error: Unable to connect to MySQL." . PHP_EOL;
            echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
            echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
            exit;
        }
  
        // return database handler
        return $con;
    }
  
    // Closing database connection
    public function close() {
        mysql_close();
    }
  
} 

