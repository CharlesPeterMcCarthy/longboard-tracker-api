<?php

  function getConn() {
    $servername = "{{SERVER_NAME}}";
    $username = "{{USER_NAME}}";
    $password = "{{PASSWORD}}";
    $dbname = "{{DB_NAME}}";
    
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    return $conn;
  }

?>
