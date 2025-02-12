<?php

class TMNOne
{

	private $tmnone_endpoint = 'https://api.tmn.one/api.php';
	private $wallet_endpoint = 'https://api.tmn.one/proxy.dev.php/tmn-mobile-gateway/';
	private $wallet_user_agent = 'tmnApp/truemoney tmnVersion/5.52.0 tmnBuild/771 tmnPlatform/android';
	private $wallet_version = '5.52.0';
	private $tmnone_keyid = 0;
	private $wallet_msisdn, $wallet_login_token, $wallet_tmn_id, $wallet_device_id, $wallet_access_token, $proxy_ip = '', $proxy_username = '', $proxy_password = '', $shield_id;

	private $full_name;

	public function __construct()
	{
	}

	public function setData($tmnone_keyid, $wallet_msisdn, $wallet_login_token, $wallet_tmn_id) {
		$this->tmnone_keyid = $tmnone_keyid;
		$this->wallet_msisdn = $wallet_msisdn;
		$this->wallet_login_token = $wallet_login_token;
		$this->wallet_tmn_id = $wallet_tmn_id;
		$this->wallet_device_id = hash('sha256', $wallet_msisdn);
	}

	public function setProxy($proxy_ip, $proxy_username, $proxy_password) {
		$this->proxy_ip = $proxy_ip;
		$this->proxy_username = $proxy_username;
		$this->proxy_password = $proxy_password;
	}

	public function setDataWithAccessToken($tmnone_keyid, $wallet_access_token, $wallet_login_token, $wallet_device_id) {
		$this->tmnone_keyid = $tmnone_keyid;
		$this->wallet_access_token = $wallet_access_token;
		$this->wallet_login_token = $wallet_login_token;
		$this->wallet_device_id = $wallet_device_id;
	}

	public function getCachedAccessToken()
	{
		$request_body = json_encode(array('scope'=>'text_storage_obj', 'cmd'=>'get'));
		$data = $this->tmnone_connect($request_body)['data'];
		$data = json_decode($data,true);
		$encrypted_access_token = isset($data['access_token']) ? $data['access_token'] : null;
		$this->shield_id = isset($data['shield_id']) ? $data['shield_id'] : null;

		if(!empty($encrypted_access_token))
		{
			$aes_key = hex2bin(substr(hash('sha512', $this->wallet_tmn_id) ,0 ,64));
			$aes_iv = hex2bin(substr($encrypted_access_token, 0, 32));
			$access_token = openssl_decrypt(base64_decode(substr($encrypted_access_token, 32)), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			if(!empty($access_token))
			{
				 $this->wallet_access_token = $access_token;
			}
		}
	}

	public function getShieldID()
	{
		$request_body = json_encode(array('scope'=>'extra', 'cmd'=>'get_shield_id', 'data'=>array('device_id'=>$this->wallet_device_id)));
		$shield_id = $this->tmnone_connect($request_body)['shield_id'];
		return $shield_id;
	}

	public function loginWithPin6($wallet_pin)
	{
		$this->getCachedAccessToken();
		if(!empty($this->wallet_access_token))
		{
			return $this->wallet_access_token;
		}
		if(empty($this->shield_id))
		{
			$this->shield_id = $this->getShieldID();
		}
		$uri = 'mobile-auth-service/v2/pin/login';
		$wallet_pin = hash('sha256', $this->wallet_tmn_id . $wallet_pin);
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' . $this->wallet_login_token . '|' . $this->wallet_version . '|' . $this->wallet_device_id . '|' . $wallet_pin);
		$postdata = array();
		$postdata['device_id'] = $this->wallet_device_id;
		$postdata['pin'] = $wallet_pin;
		$postdata['app_version'] = $this->wallet_version;
		$postdata = json_encode($postdata);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_login_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id), $postdata);
		if(!empty($wallet_response_body['data']['access_token']))
		{
			$this->wallet_access_token = $wallet_response_body['data']['access_token'];
			$aes_key = hex2bin(substr(hash('sha512', $this->wallet_tmn_id) ,0 ,64));
			$aes_iv = openssl_random_pseudo_bytes(16);
			$encrypted_access_token = bin2hex($aes_iv) . base64_encode(openssl_encrypt($this->wallet_access_token, 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv));
			$data = [ 'access_token'=>$encrypted_access_token , 'shield_id'=>$this->shield_id ];
			$request_body = json_encode(array('scope'=>'text_storage_obj', 'cmd'=>'set', 'data'=>json_encode($data)));
			$this->tmnone_connect($request_body);
		}
		 // Store Full_name in the class property
    	$this->full_name = $wallet_response_body['data']['full_name'];
		return $this->wallet_access_token;
	}
	public function getFullName() {
		return $this->full_name;
	}
	public function Re_loginWithPin6($wallet_pin)
	{
		if(empty($this->shield_id))
		{
			$this->shield_id = $this->getShieldID();
		}
		$uri = 'mobile-auth-service/v2/pin/login';
		$wallet_pin = hash('sha256', $this->wallet_tmn_id . $wallet_pin);
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' . $this->wallet_login_token . '|' . $this->wallet_version . '|' . $this->wallet_device_id . '|' . $wallet_pin);
		$postdata = array();
		$postdata['device_id'] = $this->wallet_device_id;
		$postdata['pin'] = $wallet_pin;
		$postdata['app_version'] = $this->wallet_version;
		$postdata = json_encode($postdata);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_login_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id), $postdata);
		if(!empty($wallet_response_body['data']['access_token']))
		{
			$this->wallet_access_token = $wallet_response_body['data']['access_token'];
			$aes_key = hex2bin(substr(hash('sha512', $this->wallet_tmn_id) ,0 ,64));
			$aes_iv = openssl_random_pseudo_bytes(16);
			$encrypted_access_token = bin2hex($aes_iv) . base64_encode(openssl_encrypt($this->wallet_access_token, 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv));
			$data = [ 'access_token'=>$encrypted_access_token , 'shield_id'=>$this->shield_id ];
			$request_body = json_encode(array('scope'=>'text_storage_obj', 'cmd'=>'set', 'data'=>json_encode($data)));
			$this->tmnone_connect($request_body);
		}
		return $this->wallet_access_token;
	}

	public function getBalance()
	{
		$uri = 'user-profile-composite/v1/users/balance/';
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token), '');
		return isset($wallet_response_body['data']['current_balance']) ? $wallet_response_body['data']['current_balance'] : '';
	}

	public function fetchTransactionHistory($start_date, $end_date, $limit=10, $page=1)
	{
		$uri = 'history-composite/v1/users/transactions/history/?start_date=' . $start_date . '&end_date=' . $end_date . '&limit=' . $limit . '&page=' . $page . '&type=&action=';
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id), '');
		return isset($wallet_response_body['data']['activities']) ? $wallet_response_body['data']['activities'] : array();
	}

	public function fetchTransactionInfo($report_id)
	{
		$cache_filename = sys_get_temp_dir() . '/tmn-' . $report_id;
		$aes_key = hex2bin(substr(hash('sha512', $this->wallet_tmn_id) ,0 ,64));
		if(file_exists($cache_filename))
		{
			$wallet_response_body = file_get_contents($cache_filename);
			$aes_iv = hex2bin(substr($wallet_response_body, 0, 32));
			$wallet_response_body = openssl_decrypt(substr($wallet_response_body, 32), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			$wallet_response_body = json_decode($wallet_response_body, true);
			$wallet_response_body['cached'] = true;
			return $wallet_response_body;
		}
		$uri = 'history-composite/v1/users/transactions/history/detail/' . $report_id . '?version=1';
		$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
		$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id), '');
		if(!empty($wallet_response_body['data']))
		{
			$aes_iv = openssl_random_pseudo_bytes(16);
			$encrypted_wallet_response_body = bin2hex($aes_iv) . openssl_encrypt(json_encode($wallet_response_body['data']), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			file_put_contents($cache_filename, $encrypted_wallet_response_body);
		}
		return isset($wallet_response_body['data']) ? $wallet_response_body['data'] : array();
	}

	public function generateVoucher($amount,$detail='')
	{
		try
		{
			$uri = 'transfer-composite/v1/vouchers/';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|R|' . $amount . '|1|' . $detail);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id),
				'{"amount":"' . $amount . '","detail":"' . $detail . '","tmn_id":"' . $this->wallet_tmn_id . '","mobile":"' . $this->wallet_msisdn . '","voucher_type":"R","member":"1"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			//{"code":"TRC-200","data":{"tmn_id":"tmn.xxxxxx","amount":1.00,"link":"0000000000f3453f62bd07185708325c38N","mobile":"0987654321","weight":0.4,"link_voucher":"https://gift.truemoney.com/campaign/?v=0000000000f3453f62bd07185708325c38N/#/voucher_detail/","utiba_id":50020690000000,"type":"R","update_date":1683893000100,"expire_date":1684153000100,"link_redeem":"https://gift.truemoney.com/campaign/?v=0000000000f3453f62bd07185708325c38N","member":1,"voucher_id":299291608745000000,"detail":"TEXT","create_date":1683893000100,"status":"active"}}
			return $wallet_response_body['data'];
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
	}

	public function getRecipientName($payee_wallet_id)
	{
		try
		{
			$amount = '1.00';
			$uri = 'transfer-composite/v2/p2p-transfer/draft-transactions';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $amount . '|' . $payee_wallet_id);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id),
				'{"receiverId":"' . $payee_wallet_id . '","amount":"' . $amount . '"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			return $wallet_response_body['data']['recipient_name'];
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
	}

	public function verifyFace($csid)
	{
		try
		{
			$uri = 'mobile-auth-service/v1/authentications/face';
			$signature = $this->calculate_sign256($this->wallet_access_token . '|' . $csid . '|{"apdidToken":"xbIVBFcxUB5+Glys988DB8mVEE/wnuz8qRY849jaCInMXTOzjQEAAA==","appName":"th.co.truemoney.wallet","appVersion":"' . $this->wallet_version . '","bioMetaInfo":"3.61.0:,;JJBBIBJJIIBIBIA=","buildVersion":"2.0.0.231020141951","deviceModel":"SM-S916B","deviceType":"android","keyHash":"","osVersion":"13.0.1"}|Cherry');
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id, 'CSID: ' . $csid),
				'{"meta_info":"{\"apdidToken\":\"xbIVBFcxUB5+Glys988DB8mVEE/wnuz8qRY849jaCInMXTOzjQEAAA==\",\"appName\":\"th.co.truemoney.wallet\",\"appVersion\":\"' . $this->wallet_version . '\",\"bioMetaInfo\":\"3.61.0:,;JJBBIBJJIIBIBIA=\",\"buildVersion\":\"2.0.0.231020141951\",\"deviceModel\":\"SM-S916B\",\"deviceType\":\"android\",\"keyHash\":\"\",\"osVersion\":\"13.0.1\"}","ui_type":"Cherry"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}

			$transaction_id = $wallet_response_body['data']['transaction_id'];

			$client_cfg = $wallet_response_body['data']['client_cfg'];
			$client_cfg = json_decode($client_cfg,true);
			$zimInitResp = $client_cfg['factorContext']['zimInitResp'];

			$request_body = json_encode(array('scope'=>'extra', 'cmd'=>'face_verify', 'data'=>array('zimInitResp'=>$zimInitResp)));
			$verification_result = $this->tmnone_connect($request_body)['result'];
			$verification_result = json_decode($verification_result,true);

			if($verification_result['productRetCode'] != 1001 || $verification_result['validationRetCode'] != 1000)
			{
				throw new Exception($verification_result['code'] . ' - ' . $verification_result['message']);
			}

			$uri = 'mobile-auth-service/v1/authentications/face/' . $transaction_id . '/status';
			$signature = $this->calculate_sign256($this->wallet_access_token . '|' . $csid . '|/tmn-mobile-gateway/' . $uri);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id, 'CSID: ' . $csid),
				'');
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
		return $wallet_response_body;
	}

	public function transferP2P($payee_wallet_id, $amount, $personal_msg='')
	{
		$transaction_key = md5($payee_wallet_id . $amount . date('YmdHis'));
		$lock_file = sys_get_temp_dir() . '/trx_' . $transaction_key . '.lock';
		
		if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 60) {
			throw new Exception('TRC-888 - Transaction is in progress');
		}
		
		file_put_contents($lock_file, time());
		
		try {
			// เพิ่มการตรวจสอบค่า input
			if (empty($payee_wallet_id) || empty($amount)) {
				throw new Exception('Invalid input parameters');
			}

			$amount = number_format($amount, 2, '.', '');
			
			// Step 1: สร้าง draft transaction พร้อม timeout handling
			$uri = 'transfer-composite/v2/p2p-transfer/draft-transactions';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $amount . '|' . $payee_wallet_id);
			
			// เพิ่ม timeout handling
			$wallet_response_body = $this->wallet_connect($uri, array(
				'Content-Type: application/json', 
				'Authorization: ' . $this->wallet_access_token,
				'signature: ' . $signature,
				'X-Device: ' . $this->wallet_device_id,
				'X-Geo-Location: city=; country=; country_code=',
				'X-Geo-Position: lat=; lng=',
				'X-Shield-Session-Id: ' . $this->shield_id
			), '{"receiverId":"' . $payee_wallet_id . '","amount":"' . $amount . '"}');

			if (empty($wallet_response_body)) {
				throw new Exception('Empty response from server');
			}

			if (!isset($wallet_response_body['code']) || substr($wallet_response_body['code'],-4) != '-200') {
				$error_message = isset($wallet_response_body['message']) ? $wallet_response_body['message'] : 'Unknown error';
				$error_code = isset($wallet_response_body['code']) ? $wallet_response_body['code'] : 'ERROR';
				throw new Exception($error_code . ' - ' . $error_message);
			}

			if (!isset($wallet_response_body['data']['draft_transaction_id']) || !isset($wallet_response_body['data']['reference_key'])) {
				throw new Exception('Invalid response data structure');
			}

			$draft_transaction_id = $wallet_response_body['data']['draft_transaction_id'];
			$reference_key = $wallet_response_body['data']['reference_key'];

			// Step 2: อัปเดต draft transaction
			$uri = 'transfer-composite/v2/p2p-transfer/draft-transactions/' . $draft_transaction_id;
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $personal_msg);
			
			$update_response = $this->wallet_connect($uri, array(
				'Content-Type: application/json',
				'Authorization: ' . $this->wallet_access_token,
				'signature: ' . $signature,
				'X-Device: ' . $this->wallet_device_id,
				'X-Geo-Location: city=; country=; country_code=',
				'X-Geo-Position: lat=; lng=',
				'X-Shield-Session-Id: ' . $this->shield_id
			), '{"personal_message":"' . $personal_msg . '"}', 'PUT');

			if (!isset($update_response['code']) || substr($update_response['code'],-4) != '-200') {
				throw new Exception('Failed to update draft transaction');
			}

			// Step 3: ยืนยัน transaction
			$uri = 'transfer-composite/v2/p2p-transfer/transactions/' . $draft_transaction_id;
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $reference_key);
			
			$confirm_response = $this->wallet_connect($uri, array(
				'Content-Type: application/json',
				'Authorization: ' . $this->wallet_access_token,
				'signature: ' . $signature,
				'X-Device: ' . $this->wallet_device_id,
				'X-Geo-Location: city=; country=; country_code=',
				'X-Geo-Position: lat=; lng=',
				'X-Shield-Session-Id: ' . $this->shield_id
			), '{"reference_key":"' . $reference_key . '"}');

			if (!empty($confirm_response['code']) && substr($confirm_response['code'],-4) == '-428') {
				// ถ้าต้องการ face verification ให้ skip และ return error
				throw new Exception('Face verification required - Transaction cancelled');
			}

			if (!isset($confirm_response['code']) || substr($confirm_response['code'],-4) != '-200') {
				throw new Exception('Transaction confirmation failed');
			}

			$result = isset($confirm_response['data']) ? $confirm_response['data'] : array();
			
			// ลบไฟล์ล็อคเมื่อทำรายการเสร็จ
			@unlink($lock_file);
			
			return $result;

		} catch (Exception $e) {
			// ลบไฟล์ล็อคเมื่อเกิดข้อผิดพลาด
			@unlink($lock_file);
			error_log('TransferP2P Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' of ' . $e->getFile());
			return array('error' => $e->getMessage());
		}
	}

	/*
	$bank_code : SCB,BBL,BAY,KBANK,KTB
	*/
	public function transferBankAC($bank_code,$bank_ac,$amount,$wallet_pin)
	{
		try
		{
			$amount = number_format($amount, 2, '.', '');
			$signature = $this->calculate_sign256($amount . '|' . $bank_code . '|' . $bank_ac);
			$wallet_response_body = $this->wallet_connect('fund-composite/v1/withdrawal/draft-transaction', array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id),
				'{"bank_name":"' . $bank_code . '","bank_account":"' . $bank_ac . '","amount":"' . $amount . '"}');
			if(substr($wallet_response_body['code'],-4) != '-200')
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			$draft_transaction_id = $wallet_response_body['data']['draft_transaction_id'];

			$uri = 'fund-composite/v3/withdrawal/transaction';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri . '|' .  $this->wallet_access_token . '|' . $draft_transaction_id);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id),
				'{"draft_transaction_id":"' . $draft_transaction_id . '"}');
			if(substr($wallet_response_body['code'],-4) != '-428') //{"code":"MAS-428","data":{"csid":"a9d8989b-xxxx-xxxx-xxxx-b4a36a0bfa7d","method":"pin"}}
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
			$csid = $wallet_response_body['data']['csid'];

			$wallet_pin = hash('sha256', $this->wallet_tmn_id . $wallet_pin);
			$signature = $this->calculate_sign256($this->wallet_access_token . '|' . $csid . '|' . $wallet_pin . '|manual_input');
			$wallet_response_body = $this->wallet_connect('mobile-auth-service/v1/authentications/pin', array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id, 'CSID: ' . $csid),
				'{"pin":"' . $wallet_pin . '","method":"manual_input"}');
			if(substr($wallet_response_body['code'],-4) == '-428')
			{
				$csid = $wallet_response_body['data']['csid'];
				$wallet_response_body = $this->verifyFace($csid);
			}

			if(substr($wallet_response_body['code'],-4) != '-200') //{"code":"FNC-200","data":{"withdraw_status":"VERIFIED"}}
			{
				throw new Exception($wallet_response_body['code'] . ' - ' . $wallet_response_body['message']);
			}
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage() . ' (line:' . $e->getLine() . ')');
		}
		return isset($wallet_response_body['data']) ? $wallet_response_body['data'] : array();
	}

	public function getWithdrawalStatus($draft_transaction_id)
	{
		$wallet_response_body = array();
		try
		{
			$uri = 'fund-composite/v1/withdrawal/transaction/' . $draft_transaction_id . '/status';
			$signature = $this->calculate_sign256('/tmn-mobile-gateway/' . $uri);
			$wallet_response_body = $this->wallet_connect($uri, array('Content-Type: application/json', 'Authorization: ' . $this->wallet_access_token , 'signature: ' . $signature , 'X-Device: ' . $this->wallet_device_id, 'X-Geo-Location: city=; country=; country_code=', 'X-Geo-Position: lat=; lng=', 'X-Shield-Session-Id: ' . $this->shield_id),
				'');
		}
		catch (Exception $e)
		{
			return array('error'=>$e->getMessage());
		}
		return $wallet_response_body;
	}

	private function tmnone_connect($request_body)
	{
		$headers = [];
		$aes_key = hex2bin(substr(hash('sha512', $this->wallet_login_token) ,0 ,64));
		$aes_iv = openssl_random_pseudo_bytes(16);
		$request_body = bin2hex($aes_iv) . base64_encode(openssl_encrypt($request_body, 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv));
		$request_body = json_encode(array('encrypted'=>$request_body));
		$curl = curl_init($this->tmnone_endpoint);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-KeyID: ' . $this->tmnone_keyid, 'Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_USERAGENT, 'okhttp/4.4.0/202403042300/' . $this->tmnone_keyid);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_VERBOSE, false);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
		curl_setopt($curl, CURLOPT_HEADERFUNCTION,
			function($curl, $header) use (&$headers)
			{
				$len = strlen($header);
				$header = explode(':', $header, 2);
				if (count($header) < 2) // ignore invalid headers
				{
					return $len;
				}

				$headers[strtolower(trim($header[0]))] = trim($header[1]);

				return $len;
			}
		);
		$response_body = curl_exec($curl);
		curl_close($curl);
		if(!empty($headers['x-wallet-user-agent']))
		{
			$this->wallet_user_agent = $headers['x-wallet-user-agent'];
		}
		$response_body = json_decode($response_body,true);
		if(isset($response_body['encrypted']))
		{
			$response_body = openssl_decrypt(base64_decode($response_body['encrypted']), 'AES-256-CBC', $aes_key,  OPENSSL_RAW_DATA, $aes_iv);
			$response_body = json_decode($response_body,true);
		}
		return $response_body;
	}

	private function wallet_connect_old($uri, $headers, $request_body='', $custom_method=null)
	{
		$ssl_ciphers = array(NULL,'ECDHE-RSA-AES256-GCM-SHA384','ECDHE-RSA-AES128-GCM-SHA256','ECDHE-RSA-CHACHA20-POLY1305-SHA256','ecdhe_rsa_aes_256_gcm_sha_384','ecdhe_rsa_aes_128_gcm_sha_256','ecdhe_rsa_chacha20_poly1305_sha_256');
		foreach($ssl_ciphers as $ssl_cipher)
		{
			$wallet_connect = $this->wallet_connect_curl($uri, $headers, $request_body, $custom_method, $ssl_cipher);
			if(is_array($wallet_connect) || strpos($wallet_connect,'Unknown cipher') === false)
			{
				break;
			}
		}
		return $wallet_connect;
	}

	private function wallet_connect($uri, $headers, $request_body='', $custom_method=null)
	{
		$curl = curl_init($this->wallet_endpoint . $uri);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_VERBOSE, false);
		curl_setopt($curl, CURLOPT_USERAGENT, $this->wallet_user_agent);
		if(!empty($this->proxy_ip))
		{
			curl_setopt($curl, CURLOPT_PROXY, $this->proxy_ip);
			if(!empty($this->proxy_username))
			{
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->proxy_username . ':' . $this->proxy_password);
			}
		}
		if(!empty($request_body))
		{
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
		}
		if(!empty($custom_method))
		{
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $custom_method);
		}
		$response_body = curl_exec($curl);
		if($response_body === false)
		{
			return curl_error($curl);
		}
		curl_close($curl);
		$response_body = json_decode($response_body,true);
		if(empty($response_body))
		{
			return '';
		}
		if(isset($response_body['code']) && $response_body['code'] == 'MAS-401')
		{
			$request_body = json_encode(array('scope'=>'text_storage_obj', 'cmd'=>'set', 'data'=>''));
			$this->tmnone_connect($request_body);
		}
		return $response_body;
	}

	public function calculate_sign256($data)
	{
		$request_body = json_encode(array('cmd'=>'calculate_sign256', 'data'=>array('login_token'=>$this->wallet_login_token, 'device_id'=>$this->wallet_device_id, 'data'=>$data)));
		return isset($this->tmnone_connect($request_body)['signature']) ? $this->tmnone_connect($request_body)['signature'] : '';
	}

}

?>