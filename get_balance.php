<?php
require_once 'TMNOne.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$_TMN = array(
    'tmn_key_id' => $_POST['tmn_key_id'],
    'mobile_number' => $_POST['mobile_number'],
    'login_token' => $_POST['login_token'],
    'pin' => $_POST['pin'],
    'tmn_id' => $_POST['tmn_id']
);

$TMNOne = new TMNOne();
$TMNOne->setData($_TMN['tmn_key_id'], $_TMN['mobile_number'], $_TMN['login_token'], $_TMN['tmn_id']);
$TMNOne->loginWithPin6($_TMN['pin']);

$balance = $TMNOne->getBalance();

echo json_encode(['balance' => $balance]);
?>