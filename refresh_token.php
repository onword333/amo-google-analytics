<?php
include __DIR__ . '/amo_api.php';
date_default_timezone_set('Europe/Moscow');

$api = new AmoApi;
$token = $api->getToken();

?>