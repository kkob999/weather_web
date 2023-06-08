<?php


    function connect()
    {
        $dbhost = 'localhost';
        $dbuser = 'root';
        $dbpass = '';
        $db = 'forecast';
        $conn = new mysqli($dbhost, $dbuser, $dbpass, $db) or die("Connect failed: %s\n" . $conn->error);
        // if ($conn->connect_errno){
        //     echo "Failed to connect to MySQL: " . $conn->connect_error;
        // }
        // else{
        //     echo "Connected successfully";
        // }

        return $conn;
    }
?>