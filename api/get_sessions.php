<?php

  $info = json_decode(file_get_contents("php://input"), true);

  if ($info != NULL) {
    $deviceID = $info['deviceID'];
    $sessionID = $info['lastSessionID'];

    include_once "db_conn.php";

    $conn = getConn();
    $conn->begin_transaction();

    $response = GetSessions($conn, $sessionID, $deviceID);

    if ($response['isOk']) {
      $conn->commit();
    } else {
      $conn->rollback();
    }

    $conn->close();

    echo json_encode($response);
  } else {
    echo json_encode([
      'isOk' => false,
      'displayError' => "No Info Supplied!"
    ]);
  }

  function GetSessions($conn, $sessionID, $deviceID) {
    $sql = "SELECT session_id, session_start, session_end, session_distance
      FROM skate_sessions
      WHERE session_id > ?
      AND fk_device_id = ?
      ORDER BY session_id
      DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $sessionID, $deviceID);

    $isOk = $stmt->execute();

    if ($isOk) {
      $stmt->store_result();
      $rows = $stmt->num_rows;

      $sessions = [];

      if ($rows) {
        $stmt->bind_result($sessionID, $sessionStart, $sessionEnd, $sessionDistance);

        while ($stmt->fetch()) {
          $session = [
            'sessionID' => $sessionID,
            'start' => $sessionStart,
            'end' => $sessionEnd,
            'distance' => $sessionDistance
          ];

          array_push($sessions, $session);
        }

        $response = [
          'isOk' => true,
          'hasSessions' => true,
          'sessions' => $sessions
        ];

        $stmt->free_result();
      } else {
        $response = [
          'isOk' => true,
          'hasSessions' => false
        ];
      }
    } else {
      $response = [
        'isOk' => false,
        'displayError' => "Error Getting Sessions"
      ];
    }

    return $response;
  }

?>
