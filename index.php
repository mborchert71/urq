<?php

$response = new stdClass();

if (!array_key_exists("table", $_REQUEST)) {
    die('{ "error" : "no request data" }');
}

include "urq.php";

$data = urq::instance("urq.json");

try {
    $record = $data->request();
    $response->row = $record->fetchAll(PDO::FETCH_ASSOC);

}
catch (PDOException $err) {
    $response->error = $err->getMessage();
}

echo json_encode($response);
