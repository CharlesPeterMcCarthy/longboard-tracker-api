<?php

  $info = json_decode(file_get_contents("php://input"), true);

  $isAllOk = true;

  if (!CheckDataExists($info)) {
    // No JSON info was supplied
    $response = [
      'isOk' => false,
      'displayError' => "No Info Supplied"
    ];
    $isAllOk = false;
  }

  if ($isAllOk && !CheckAPIKeyExists($info)) {
    // No API key was supplied in JSON
    $response = [
      'isOk' => false,
      'displayError' => "Missing API KEY"
    ];
    $isAllOk = false;
  }

  if ($isAllOk && !CheckAPIKey($info['API_KEY'])) {
    // API key supplied does not match
    $response = [
      'isOk' => false,
      'displayError' => "Invalid API KEY"
    ];
    $isAllOk = false;
  }

  if ($isAllOk && !CheckDeviceDetailsExist($info)) {
    // Device name and/or device password was not supplied
    $response = [
      'isOk' => false,
      'displayError' => "Missing Device Credentials"
    ];
    $isAllOk = false;
  }

  if ($isAllOk) {
    $response = CheckDeviceDetails($info['deviceName'], $info['devicePass']);
    $deviceID = $response['deviceID'];
    $email = $response['email'];

    $isAllOk = $response['isOk'];
  }

  if ($isAllOk && !CheckAllDataIsSupplied($info)) {
    // Some data is missing
    $response = [
      'isOk' => false,
      'displayError' => "Some Data Is Missing"
    ];
    $isAllOk = false;
  }

  if ($isAllOk) {
    $speeds = $info['speeds'];
    $distance = $info['distance'];
    $deviceName = $info['deviceName'];
    $sessionStart = GetStartTime($speeds);
    $sessionEnd = GetEndTime();

    include_once "db_conn.php";

    $conn = getConn();
    $conn->begin_transaction();

    $response = SaveSession($conn, $sessionStart, $sessionEnd, $distance, $deviceID);

    if ($response['isOk']) {
      $sessionID = $response['sessionID'];

      $response = SaveSpeeds($conn, $speeds, $sessionID);
    }

    if ($response['isOk']) {
      $response = GetResponseData($speeds, $sessionStart, $sessionEnd, $sessionID);
      SendAlertEmail($email, $sessionID, $deviceName, $deviceID, $distance,
        $response['sessionLength'], $response['averageSpeed'], $response['highestSpeed']);

      $conn->commit();
    } else {
      $conn->rollback();
    }

    $conn->close();
  }

  SendResponse($response);

  function SendResponse($response) {    // Return response data to Calling Script (Shell / Arduino)
    echo json_encode($response);
  }

      // Save the parent skate session 'object'
  function SaveSession($conn, $sessionStart, $sessionEnd, $distance, $deviceID) {
    $sql = "INSERT INTO skate_sessions
      (session_start, session_end, session_distance, fk_device_id)
      VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdi", $sessionStart, $sessionEnd, $distance, $deviceID);

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
    }

    $stmt->close();

    return $response;
  }

      // Save the individual skate logs relating to the skate session
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

    // Send alert email to user
  function SendAlertEmail($email, $sessionID, $deviceName, $deviceID, $distance, $sessionLength, $averageSpeed, $highestSpeed) {
    $BASE_URL = "{{BASE_URL}}";    // Place URL to skate_sessions.php page here
    $URL = $BASE_URL . "?sessionID=$sessionID&deviceID=$deviceID";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: 'IoT Arduino Skate App' <{{SENDER_EMAIL}}>";

    $subject = "Skate Session # $sessionID";

    $message = "<div style='background-color:#eee;padding:10px 10px 5px 10px'>";
      $message .= "<div style='font-family:Verdana;font-size:14px;background-color:#a2c2d8;padding:30px;border-radius:4px;'>";
        $message .= "<h1 style='text-align:center'>Your new skate session data from $deviceName is ready.</h1><br>";
        $message .= "<div style='width:100%;height:2px;background-color:#555;margin-bottom:20px;'></div>";
        $message .= "Here is a quick run down on the stats from your skate:<br>";
        $message .= "<ul>";
          $message .= "<li>Total Time: $sessionLength Seconds</li>";
          $message .= "<li>Total Distance: $distance KM</li>";
          $message .= "<li>Average Speed: $averageSpeed KM/H</li>";
          $message .= "<li>Highest Speed: $highestSpeed KM/H</li>";
        $message .= "</ul>";
        $message .= "Click the following link to view your full skate data.<br><br>";
        $message .= "<div style='text-align:center'>";
          $message .= "<a href='$URL' style='background-color:#555;padding:8px 12px;border-radius:4px;color:white;
            text-decoration:none;font-weight:bold;font-size:18px;display:block'>Skate Session #$sessionID</a>";
        $message .= "</div>";
      $message .= "</div>";
      $message .= "<div style='text-align:center;color:#555;margin-top:20px;'>";
        $message .= "<small>Arduino Longboard Project</small>";
      $message .= "</div>";
    $message .= "</div>";

    mail($email, $subject, $message, $headers); // Send email
  }

  function GetResponseData($speeds, $sessionStart, $sessionEnd, $sessionID) {
    return [
      'sessionID' => $sessionID,
      'sessionLength' => GetSessionLength($sessionStart, $sessionEnd),
      'averageSpeed' => GetAverageSpeed($speeds),
      'highestSpeed' => GetHighestSpeed($speeds)
    ];
  }

      // Get the time in seconds between the start and the end of the skate session
  function GetSessionLength($sessionStart, $sessionEnd) {
    return (int) date(strtotime($sessionEnd) - strtotime($sessionStart));
  }

      // Get the average speed from the skate session
  function GetAverageSpeed($speeds) {
    $total = 0;

    foreach ($speeds as $speed) {
      $total += $speed;
    }

    return (float) number_format((float) ($total / count($speeds)), 2, '.', '');
  }

      // Get the highest speed from the skate session
  function GetHighestSpeed($speeds) {
    $highestSpeed = 0;

    foreach ($speeds as $speed) {
      if ($speed > $highestSpeed) {
        $highestSpeed = $speed;
      }
    }

    return $highestSpeed;
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

  function CheckDataExists($info) {
    return $info != NULL;
  }

  function CheckAPIKeyExists($info) {
    return isset($info['API_KEY']);
  }

  function CheckAPIKey($apiKey) {
    return $apiKey == "{{API_KEY}}";   // Place your API Key here
  }

  function CheckDeviceDetailsExist($info) {
    return isset($info['deviceName']) && isset($info['devicePass']);
  }

      // Find matching device details in DB
  function CheckDeviceDetails($deviceName, $devicePass) {
    include_once "db_conn.php";

    $conn = getConn();
    $conn->begin_transaction();

    $sql = "SELECT device_id, email
      FROM approved_devices
      WHERE device_name = ?
      AND device_pass = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $deviceName, $devicePass);
    $isOk = $stmt->execute();

    if ($isOk) {
      $stmt->bind_result($deviceID, $email);
      $stmt->fetch();
      $stmt->free_result();

      $response = $email !== "" ? [
        'isOk' => true,
        'deviceID' => $deviceID,
        'email' => $email
      ] : [
        'isOk' => false,
        'displayError' => "Invalid Device Credentials"
      ];
    } else {
      $response = [
        'isOk' => false,
        'displayError' => "Error Checking Stored Device Credentials"
      ];
    }

    $conn->close();

    return $response;
  }

  function CheckAllDataIsSupplied($info) {
    return isset($info['speeds']) && isset($info['distance']);
  }

?>
