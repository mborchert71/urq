<?php

$response = new stdClass();

if (!array_key_exists("table", $_REQUEST)) {
    die('{ "error" : "no request data" }');
}

include "urq.php";

$data = urq::instance("urq.json");

try {
    $record = $data->request();
    $response->rows = $record->fetchAll(PDO::FETCH_ASSOC);
    $response->insert_id = $data->lastInsertId();
}
catch (PDOException $err) {
    $response->error = $err->getMessage();
}
catch (Exception $err) {
    $response->error = $err->getMessage();
}

echo json_encode($response);
