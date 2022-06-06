<?php
include __DIR__ . '/amo_api.php';
date_default_timezone_set('Europe/Moscow');

sleep(5); // АМО ограничение: 7 запросов в секунду

$query = [
  'tid' => isset($_GET['tid']) ? $_GET['tid'] : '',
  'v' => '1',
  'ni' => '0',
  'z' => time(),
  't' => 'event',
  'ec' => isset($_GET['ec']) ? $_GET['ec'] : '',
  'ea' => isset($_GET['ea']) ? $_GET['ea'] : ''
];

$endpoint = 'https://www.google-analytics.com/collect';


// сопоставление полей ga и amo
$fieldsMapGA = [
  'cid' => '2515',
  'utm_source' => '461089',
  'utm_content' => '2489',
  'utm_medium' => '2491',
  'utm_campaign' => '2493',
  'utm_term' => '2497'
];


$api = new AmoApi;
$token = $api->getToken();

$data = [];

if (!empty($_POST['leads']['status'])) {
	$data = $_POST['leads']['status'];
}


if (!empty($data)) {
  $idLead = $data[0]['id'];
  $statusId = $data[0]['status_id'];
  $pipelineId = $data[0]['pipeline_id'];

  $res = $api->getLeadById($idLead);
  
  
  if (isset($res->custom_fields_values)) {
    
    foreach ($res->custom_fields_values as $item) {
      $field_id = $item->field_id;
      $searchRes = array_search($field_id, $fieldsMapGA);
    
      if (empty($searchRes)) {
        continue;
      }

      $value = isset($item->values[0]) ? $item->values[0]->value : '';
      if (empty($value)) {
        continue;
      }

      $query[$searchRes] = $value;
    }

  }

  if (isset($query['cid'])) {
    $query['cd1'] = $query['cid'];
  }

  $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

  $error = checkGaParametr($query);
  if (!empty($error)) {
    $logText = date('Y.m.d h:i:s', time()) . ';' . $error . PHP_EOL;
    file_put_contents('error.txt', $logText, FILE_APPEND);
    http_response_code(200);
    return;
  }

  $headers = [
    'Content-type: application/x-www-form-urlencoded'
  ];

  sendData($endpoint, $queryString, $headers, 'POST');
} else {
  $logText = date('Y.m.d h:i:s', time()) . '; данные от АМО отсутствуют' . PHP_EOL;
  file_put_contents('error.txt', $logText, FILE_APPEND);
}

http_response_code(200);


function sendData($url, $data, $headers, $method = 'GET') {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  
  $certificate_location = __DIR__ . '/cacert-2022-04-26.pem';
  curl_setopt($ch, CURLOPT_CAINFO, $certificate_location);
  curl_setopt($ch, CURLOPT_CAPATH, $certificate_location);

  //curl_setopt($ch, CURLOPT_VERBOSE, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);

  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }
  
  if ($method == 'POST') {
    curl_setopt($ch, CURLOPT_POST, 1);
    if (!empty($data)) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }    
  }

  $output = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($code < 200 || $code > 204) {
    $logText = date('Y.m.d h:i:s', time()) . ';' . $code . ' ' . $output . ' ' . curl_error($ch). PHP_EOL;
    file_put_contents('error.txt', $logText, FILE_APPEND);
  }
  
  curl_close($ch);
  return $output;
}

function checkGaParametr($query) {
  $errorMessaage = '';
  $fields = ['tid', 'ec', 'cid']; // обязательные параметры
  
  foreach ($fields as $field) {
    if (!isset($query[$field])) {
      $errorMessaage .= $field . ' - отсутствует, ';
      continue;
    }

    if (empty($query[$field])) {
      $errorMessaage .= $field . ' - пустой, ';
    }
  }
  return $errorMessaage;
}



?>