<?php

  $info = json_decode($HTTP_RAW_POST_DATA, true);

  if ($info != NULL) {
    if (isset($info['API_KEY'])) {  // Check API key exists
      if ($info['API_KEY'] == "**5**") {   // Check API key mathces
        $speeds = $info['speeds'];

        $conn = getConn();
        $conn->begin_transaction();

        $response = SaveSession($conn, GetStartTime($speeds), GetEndTime());

        if ($response['isOk']) {
          $sessionID = $response['sessionID'];

          $response = SaveSpeeds($conn, $speeds, $sessionID);
        }

        if ($response['isOk']) {
          $conn->commit();
        } else {
          $conn->rollback();
        }

        $conn->close();

        SendResponse($response);
      } else {
        // API key supplied does not match
        SendResponse([
          'isOk' => false,
          'displayError' => "Invalid API KEY"
        ]);
      }
    } else {
      // No API key was supplied in JSON
      SendResponse([
        'isOk' => false,
        'displayError' => "Missing API KEY"
      ]);
    }
  } else {
    // No JSON info was supplied
    SendResponse([
      'isOk' => false,
      'displayError' => "No Info Supplied"
    ]);
  }

  function SendResponse($response) {
    echo json_encode($response);
  }

  function SaveSession($conn, $sessionStart, $sessionEnd) {
    $sql = "INSERT INTO skate_sessions
      (session_start, session_end)
      VALUES (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $sessionStart, $sessionEnd);

    $isOk = $stmt->execute();

    if ($isOk) {
      $response = getLastID($conn);

      if ($response['isOk']) {
        $sessionID = $response['lastID'];

        $response = [
          'isOk' => true,
          'sessionID' => $sessionID
        ];
      }
    } else {
      $response = [
        'isOk' => false,
        'displayError' => "Save Failed (1)"
      ];

      break;
    }

    $stmt->close();

    return $response;
  }

  function SaveSpeeds($conn, $speeds, $sessionID) {
    $sql = "INSERT INTO skate_speeds
      (speed_kph, fk_session_id)
      VALUES (?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $speedKPH, $sessionID);

    foreach ($speeds as $speedKPH) {
      $isOk = $stmt->execute();

      if ($isOk) {
        $response = [
          'isOk' => true
        ];
      } else {
        $response = [
          'isOk' => false,
          'displayError' => "Save Failed (2)"
        ];

        break;
      }
    }

    $stmt->close();

    return $response;
  }

      // Get connection to remote MySQL Database
  function getConn() {
    $servername = "**1**";
    $username = "**2**";
    $password = "**3**";
    $dbname = "**4**";

    $conn = new mysqli($servername, $username, $password, $dbname); //Create connection

    if ($conn->connect_error) { //Check connection
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
  }

      // Used to retrieve the skate session ID
  function GetLastID($conn) {
    $sql = "SELECT LAST_INSERT_ID()";

    $stmt = $conn->prepare($sql);

    $isOk = $stmt->execute();

    if ($isOk) {
      $stmt->bind_result($lastID);
      $stmt->fetch();
      $stmt->free_result();

      $response = [
        'isOk' => true,
        'lastID' => $lastID
      ];
    } else {
      $error = $stmt->error;

      $response = [
        'isOk' => false,
        'displayError' => "No Session ID"
      ];
    }

    $stmt->close();

    return $response;
  }

      // Get the time the skate started at by subtracting the amount of seconds
      // that have elapsed from the current time
  function GetStartTime($speeds) {
    $READING_INTERVAL = 2;  // Seconds between each average speed reading

    return date("Y-m-d H:i:s", (time() - (count($speeds) * $READING_INTERVAL)));
  }

      // Get the current date-time
  function GetEndTime() {
    return date("Y-m-d H:i:s");
  }

?>
