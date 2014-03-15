<?php

$response = new stdClass();

if (!array_key_exists("table", $_REQUEST)) {
    die('{ "error" : "no request data" }');
}

include "urq.php";

$data = urq::instance("urq.json");

try {
    $record = $data->request();
}
catch (PDOException $err) {
    $response->error = $err->getMessage();
}

$response->row = $record->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($response);
