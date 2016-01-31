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
        $con = mysql_connect($CONFIG->dbhost, $CONFIG->dbname, $CONFIG->dbpass);
        // selecting database
        mysql_select_db($CONFIG->dbname);
  
        // return database handler
        return $con;
    }
  
    // Closing database connection
    public function close() {
        mysql_close();
    }
  
} 
?>
