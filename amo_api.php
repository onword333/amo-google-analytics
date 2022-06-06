<?php
// Поддомен аккаунта
define('SUBDOMAIN', 'servicesflexeio');
// Client id
define('CLIENT_ID', '97e00cc3-5199-4010-81e1-ce22d85b76bd');
// Secret Key
define('SECRET', 'l4Q8eoKaqpHYqLYnglC67OLaMhfdvX1x8SjtJ9tmlyb7hxdr3MTGvuUmJxGGz5wl');
// Authorization code
define('CODE', 'def50200625c7da7353252d98b36038338e85f9808593a4b03784e3ca672386ac6b6fd1bb484115abeee75de00ea82ad4f5a75d330d5a877301c023151ffc439c1d93f202d1cfb8b7226fc05f2fe922cf3c030ff92dbc5d23a50397c9b710a136cbaaf9ff96d5d12f1427e47d7b6e457b8c01a01d9d9e4e7f51202277f1ec8a64f305e1c472e6efdd1c0995bc7c3b8c2177fe86c86855218fb3316715e41d0480cb09cc00b1be4378fb6922a0e16521042dc33d26343b629d5be63d825863496b12c86f303b696b0b0968245769f9e1987df16a20b8e45988c224464bb82f8024dbcd3acfa67fc62ba40057545d35520f5e9918939f0dba6c252a16f77170b7b069be590ffd2ce10e4533af51de1f12c235de0bafe42b3beb5bb47f85f9b66734cfe99ddbb3c3119e3dfe2fe9c92dd3d3b1df8677ed7d2b585bb7722c19edc0e3deceec152bcf315cec9859c383bf79509b01e42aa1c680153343826b93ad24cea892de4a9d6a8c1971fa11bab0dda1aba03e359061dacd1353bdc6c92adc18db6bc5b7f3b767ed86df9e247c4ffd65cf3cc8318d08a1e180dbbd8ac7c24ac0ea763ed587d32f0a7c0f9150bce8e51b2932efdeef673801edd7e611c');
// Redirect uri
define('REDIRECT_URI', 'https://flexe.io/amocrm/google_analytics/amo_api.php');

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
	file_put_contents(LOG, date('d/m/y H:i:s') . ' ' . $s . PHP_EOL, FILE_APPEND);
	return;
}

?>