<?php

include "urq.php";


/* create table
$_REQUEST["process"] = "create";
$_REQUEST["table"] = "test";
$record = $data->request();
 */
/* insert into 
$_REQUEST["process"] = "insert";
$_REQUEST["table"] = "test";
$_REQUEST["value"] = "value 1";

$record = $data->request();
print $data->lastInsertId();

$_REQUEST["process"] = "insert";
$_REQUEST["table"] = "test";
$_REQUEST["value"] = "value 2";
$record = $data->request();
print $data->lastInsertId();
*/

/* select */
$_REQUEST["process"] = "select";
$_REQUEST["table"] = "test";
$_REQUEST["limit"] = 1;
$_REQUEST["offset"] = 1;

$data = urq::my($_REQUEST);

$record = $data->request();
print_r($record->fetchAll(PDO::FETCH_ASSOC));
