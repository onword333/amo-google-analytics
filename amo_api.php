<?php
// Поддомен аккаунта
define('SUBDOMAIN', '');
// Client id
define('CLIENT_ID', '');
// Secret Key
define('SECRET', '');
// Authorization code
define('CODE', '');
// Redirect uri
define('REDIRECT_URI', '');

define('KEYS', __DIR__ . '/amo_keys.txt');	// Ключи доступа
define('JSON_NICE', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/php_errors');
define('LOG', __DIR__ . '/log.txt');

/*
if (isset($_GET['client_id']) && $_GET['client_id'] == CLIENT_ID) {
	$keys = new AMO_KEYS;
	say($keys->getToken());
}*/

class AmoApi {
	private $keys;

	
	public function __construct() {
		$this->keys = (object)[];
		if (file_exists(KEYS)) {
			$this->keys = json_decode(file_get_contents(KEYS));
			if ($this->keys->expires_in <= time()) {
				$this->refreshToken();
			}
		} else {
			$this->getAccessKey();
		}
	}


	public function getToken() {
		return $this->keys->access_token;
	}


	private function getAccessKey() {
		$p = [
			'client_id' => CLIENT_ID,
			'client_secret' => SECRET,
			'grant_type' => 'authorization_code',
			'code' => CODE,
			'redirect_uri' => REDIRECT_URI
		];
		$res = $this->callApi('oauth2/access_token', $p);
		if (isset($res->access_token)) {
			$this->saveKeys($res);
			mylog('Файл ключей обновлён.');
		} else {
			mylog('ERR: Ошибка получения АПИ-ключей!');
			say('ERR: Ошибка получения АПИ-ключей!', false);
			exit;
		}
	}


	private function refreshToken() {
		$p = [
		  'client_id' => CLIENT_ID,
			'client_secret' => SECRET,
			'grant_type' => 'refresh_token',
			'refresh_token' => $this->keys->refresh_token,
		  'redirect_uri' => REDIRECT_URI
		];
		$res = $this->callApi('oauth2/access_token', $p);
		if (isset($res->access_token)) {
			$this->saveKeys($res);
		} else {
			mylog('ERR: ошибка обновления АПИ-ключа!');
			say('ERR: ошибка обновления АПИ-ключа!', false);
			exit;
		}
	}


	private function saveKeys($data) {
		foreach($data as $k => $v)
			$this->keys->{$k} = $v
		;
		$this->keys->expires_in += (time() - 100);
		file_put_contents(KEYS, json_encode($this->keys, JSON_NICE));
	}


	private function callApi($method, $data = [], $type = null) {
		//mylog("CURL\r\n$method\r\n" . json_encode($data, JSON_NICE));
		$url = 'https://' . SUBDOMAIN . '.amocrm.ru/' . $method;
		$curl = curl_init();		

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
		curl_setopt($curl, CURLOPT_URL, $url);
		$h = ['Content-Type: application/json'];
		if (substr($method, 0, 3) == 'api') 
			$h[] = 'Authorization: Bearer ' . $this->keys->access_token
		;
		curl_setopt($curl, CURLOPT_HTTPHEADER, $h);
		curl_setopt($curl, CURLOPT_HEADER, false);
		if (!empty($data)) {
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type ?? 'POST');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		}

		$certificate_location = __DIR__ . '/cacert-2022-04-26.pem';
  	curl_setopt($curl, CURLOPT_CAINFO, $certificate_location);
  	curl_setopt($curl, CURLOPT_CAPATH, $certificate_location);

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		$out = curl_exec($curl);
		$code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
	  
		if ($code < 200 || $code > 204) {
			mylog("ERR CODE: $code\r\n$out");
	  }
		
		$res = json_decode($out);
		if ($res === null) {
			mylog("ERR: неверный ответ АМО || NULL.\r\n$out");
			say("ERR: неверный ответ АМО || NULL.\r\n$out", false);
			exit;
		} else {
			//mylog("AMO ANSWER:\r\n" . json_encode($res, JSON_NICE))
		}
		return $res;
	}


	public function getLeadById($id) {
		$res = $this->callApi('api/v4/leads/' . $id);
		return $res;
	}
}


function say($s, $isOK = true) {
	$p = [
		'status' => $isOK ? 'ok' : 'error',
		'token' => $s
	];
	echo json_encode($p, JSON_NICE);
}


function mylog($s) {
	file_put_contents(LOG, date('Y.m.d H:i:s') . ';' . $s . PHP_EOL, FILE_APPEND);
	return;
}

?>