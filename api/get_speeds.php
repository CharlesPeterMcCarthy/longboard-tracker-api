<?php

  $info = json_decode(file_get_contents("php://input"), true);

  if ($info != NULL) {
    $sessionID = $info['sessionID'];

    include_once "db_conn.php";

    $conn = getConn();
    $conn->begin_transaction();

    $response = GetSpeeds($conn, $sessionID);

    if ($response['isOk']) {
      $speeds = $response['speeds'];

      $response = [
        'isOk' => true,
        'speeds' => $speeds
      ];

      $conn->commit();
    } else {
      $conn->rollback();
    }

    $conn->close();

    echo json_encode($response);
  }

  function GetSpeeds($conn, $sessionID) {
    $sql = "SELECT speed_kph
      FROM skate_speeds
      WHERE fk_session_id = ?
      ORDER BY speed_id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sessionID);

    $isOk = $stmt->execute();

    if ($isOk) {
      $speeds = [];

      $stmt->bind_result($speed);

      while ($stmt->fetch()) {

        array_push($speeds, (int) $speed);
      }

      $response = [
        'isOk' => true,
        'speeds' => $speeds
      ];

      $stmt->free_result();
    } else {
      $response = [
        'isOk' => false,
        'displayError' => "Error Getting Speeds."
      ];
    }

    return $response;
  }

?>
