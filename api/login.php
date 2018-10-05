<?php

  $info = json_decode(file_get_contents("php://input"), true);

  if ($info != NULL) {
    $email = $info['email'];
    $pass = $info['pass'];

    include_once "encrypt.php";
    include_once "db_conn.php";

    $conn = getConn();
    $conn->begin_transaction();

    $response = AttemptLogin($conn, $email);

    if ($response['emailExists']) {
      $response['validLogin'] = CheckPasswordsMatch($pass, $response['hashedPass']);

      unset($response['hashedPass']);
    }

    if ($response['isOk']) {
      $conn->commit();
    } else {
      $conn->rollback();
    }

    if (isset($response['validLogin']) && !$response['validLogin']) {
      $response['displayError'] = "Password is Incorrect.";
      unset($response['deviceID']);
      unset($response['deviceName']);
      unset($response['devicePass']);
    }

    $conn->close();

    echo json_encode($response);
  }

  function AttemptLogin($conn, $email) {
    $sql = "SELECT device_id, device_name, device_pass, password
      FROM approved_devices
      WHERE email = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);

    $isOk = $stmt->execute();

    if ($isOk) {
      $stmt->store_result();
      $rows = $stmt->num_rows;

      if ($rows) {
        $stmt->bind_result($deviceID, $deviceName, $devicePass, $hashedPass);
        $stmt->fetch();

        $response = [
          'isOk' => true,
          'emailExists' => true,
          'deviceInfo' => [
            'deviceID' => $deviceID,
            'deviceName' => $deviceName,
            'devicePass' => $devicePass
          ],
          'hashedPass' => $hashedPass
        ];

        $stmt->free_result();
      } else {
        $response = [
          'isOk' => true,
          'emailExists' => false,
          'displayError' => "Email Does Not Exist."
        ];
      }
    } else {
      $response = [
        'isOk' => false,
        'displayError' => "Error Logging In"
      ];
    }

    return $response;
  }

  function CheckPasswordsMatch($passEntered, $hashedPass) {
    if (hash_equals($hashedPass, crypt($passEntered, $hashedPass))) {
      return true;
    } else {
      return false;
    }
  }

?>
