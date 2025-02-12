<?php
require_once 'TMNOne.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Thailand
date_default_timezone_set('Asia/Bangkok');


function sendTelegramNotify($message) {
    $token = "8134810874:AAEClDIW1U90KpjssRYCG0IypWrSdYmWyPA";
    $chat_id = "-1002448690478";
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}


function sendLineNotifylog($messagelog)
{
    $token = "rsJwXz45KBr4LS99ebTYat4CgSfc05KQu4vPD9EOJwa"; // ใส่ Token ที่สร้างไว้
    $data = array(
        'message' => $messagelog
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // แปลงข้อมูลให้เป็นรูปแบบ URL encoded
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded', 
        'Authorization: Bearer ' . $token
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;


}

function sendLineNotify($message)
{
    $token = "O9gMo6cHmEnO6bCIbZCCv7EJ5B8q9blMNeLfu0X4Cy0"; // ใส่ Token ที่สร้างไว้
    $data = array(
        'message' => $message
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // แปลงข้อมูลให้เป็นรูปแบบ URL encoded
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded', 
        'Authorization: Bearer ' . $token
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;


}



function get_connection() {
    // $host = "dpg-cqofhjggph6c73b8ohng-a.singapore-postgres.render.com";
    // // $host = "dpg-cqofhjggph6c73b8ohng-a";
    // $db   = "truewallet_db";
    // $user = "truewallet_db_user";
    // $pass = "x7liT4P9dZS58ESjbimpct3H5ARCAeel";
    // $port = "5432";

    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $port = getenv('DB_PORT');
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        throw new Exception("Connection failed: " . $e->getMessage());
    }
    return $pdo;
}

function check_employee($employee_id) {
    $conn = get_connection();
    $stmt = $conn->prepare("SELECT * FROM employee_y2k WHERE employee_id = :employee_id");
    $stmt->execute(['employee_id' => $employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    return $employee;
}

function check_duplicate_transfer($bank_account_no, $amount) {
    $conn = get_connection();
    $stmt = $conn->prepare("SELECT * FROM tranferlog_y2k_ptop 
        WHERE bankAccountNo = :bank_account_no AND amount = :amount AND date_time >= :date_time");
    $stmt->execute([
        'bank_account_no' => $bank_account_no,
        'amount' => $amount,
        'date_time' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
    ]);
    $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
    return $duplicate;
}

function process_action($action, $data) {
    switch ($action) {
        case 'getRecipientName':
            $TMNOne = new TMNOne();
            $TMNOne->setData($data['tmn_key_id'], $data['mobile_number'], $data['login_token'], $data['tmn_id']);
            $TMNOne->loginWithPin6($data['pin']);
            $recipientName = $TMNOne->getRecipientName($data['payee_wallet_id']);
            return ['success' => true, 'recipient_name' => $recipientName];

        case 'getBalance':
            $TMNOne = new TMNOne();
            $TMNOne->setData($data['tmn_key_id'], $data['mobile_number'], $data['login_token'], $data['tmn_id']);
            $TMNOne->loginWithPin6($data['pin']);
            $balance = $TMNOne->getBalance();
            return ['success' => true, 'balance' => $balance];

        case 'transfer':
            $employee = check_employee($data['employee_id']);
            if (!$employee) {
                // $message = 'รหัสพนักงานไม่ถูกต้อง';
                // sendLineNotify($message);
                throw new Exception('รหัสพนักงานไม่ถูกต้อง');
            }

            $duplicate = check_duplicate_transfer($data['payee_wallet_id'], $data['amount']);
            if ($duplicate) {
                // $message = "พบรายการถอนซ้ำ!\nเบอร์โทรศัพท์: {$data['payee_wallet_id']}\nจำนวนเงิน: {$data['amount']} \nเวลาที่แจ้งถอนบิลก่อนหน้า:\n {$duplicate['date_time']}";
                // sendLineNotify($message);
                throw new Exception('พบรายการถอนซ้ำ');
            }

            $TMNOne = new TMNOne();
            $TMNOne->setData($data['tmn_key_id'], $data['mobile_number'], $data['login_token'], $data['tmn_id']);
            $TMNOne->loginWithPin6($data['pin']);
            $result = $TMNOne->transferP2P($data['payee_wallet_id'], $data['amount'], '');
            $messagelog = "NEXT : {$result['transfer_status']}\nเบอร์โทรศัพท์: {$data['payee_wallet_id']}\nจำนวนเงิน: {$data['amount']} บาท";
            // sendLineNotifylog($messagelog);
            if (isset($result['transfer_status']) && $result['transfer_status'] === 'PROCESSING') {
                
                
                $conn = get_connection();
                $date_time = date('Y-m-d H:i:s');
                $recipientName = $TMNOne->getRecipientName($data['payee_wallet_id']);
                $message = "การโอนเงินสำเร็จ!\nเบอร์โทรศัพท์: {$data['payee_wallet_id']}\nชื่อลูกค้า: {$recipientName}\nจำนวนเงิน: {$data['amount']} บาท\nพนักงาน: {$employee['employee_name']}\nเวลา: {$date_time}";
                // sendLineNotify($message);
                // Insert into tranferlog_bl_ptop

                // ส่งแจ้งเตือน Telegram แบบ HTML formatting
                $telegram_message = "<b>True Wallet โอนเงินสำเร็จ!</b>\n"
                                . "เบอร์โทรศัพท์: {$data['payee_wallet_id']}\n"
                                . "ชื่อลูกค้า: {$recipientName}\n"
                                . "จำนวนเงิน: {$data['amount']} บาท\n"
                                . "พนักงาน: {$employee['employee_name']}\n"
                                . "เวลา: {$date_time}";
                sendTelegramNotify($telegram_message);


                $stmt = $conn->prepare("INSERT INTO tranferlog_y2k_ptop (amount, date_time, bankAccountNo) VALUES (:amount, :date_time, :bank_account_no)");
                $stmt->execute([
                    'amount' => $data['amount'],
                    'date_time' => $date_time,
                    'bank_account_no' => $data['payee_wallet_id']
                ]);

                // Insert into tranferlog_bl
                $stmt = $conn->prepare("INSERT INTO tranferlog_y2k (phonenumber, amount, date_time, employee_name, employee_id) VALUES (:phonenumber, :amount, :date_time, :employee_name, :employee_id)");
                $stmt->execute([
                    'phonenumber' => $data['payee_wallet_id'],
                    'amount' => $data['amount'],
                    'date_time' => $date_time,
                    'employee_name' => $employee['employee_name'],
                    'employee_id' => $data['employee_id']
                ]);
                // $message = "การโอนเงินสำเร็จ!\nเบอร์โทรศัพท์: {$data['mobile_number']}\nจำนวนเงิน: {$data['amount']}\nพนักงาน: {$employee['employee_name']}\nเวลา: {$date_time}";
                // sendLineNotify($message);

                $stmt = $conn->prepare("INSERT INTO tranferlogall (phonenumber, customername, amount, date_time, employee_name, employee_id, prefix_wep,datalog) VALUES (:phonenumber, :customername, :amount, :date_time, :employee_name, :employee_id, :prefix_wep,:datalog)");
                $stmt->execute([
                    'phonenumber' => $data['payee_wallet_id'],
                    'customername' => $recipientName,
                    'amount' => $data['amount'],
                    'date_time' => $date_time,
                    'employee_name' => $employee['employee_name'],
                    'employee_id' => $data['employee_id'],
                    'prefix_wep' => 'next',
                    'datalog' => ''
                ]);
                return [
                    'success' => true,
                    'message' => 'โอนเงินสำเร็จ',
                    'new_balance' => $TMNOne->getBalance()
                ];
            } else {
                $error_message = isset($result['error']) ? $result['error'] : 'เกิดข้อผิดพลาดในการโอนเงิน';
                if (strpos($error_message, 'TRC-4011') !== false) {
                    throw new Exception('Error: Invalid phone number or Wallet ID.');
                } elseif (strpos($error_message, 'TRC-1001') !== false) {
                    throw new Exception('Error: Insufficient balance / ยอดคงเหลือไม่พอ.');
                } elseif (strpos($error_message, 'TRC-55408') !== false) {
                    throw new Exception('ไม่สามารถทำรายการได้ในณะนี้ <br> Sorry,transaction right now. (R) <br> ต้องเว้นระยะถอนไปก่อน Wallet โดนทรูระงับการโอนชั่วคราว <br><b>รบกวนน้องแอดมินทำรายการถอนมือไปก่อน </b><br>ต้องเว้นการถอนผ่านระบบ ขั้นต่ำ 1 ชั่วโมง <br> ขออภัยในความไม่สะดวกครับ ');
                } elseif (strpos($error_message, 'TRC-888') !== false) {
                    throw new Exception('Error: รายการถอนซ้ำ');
                } else {
                    throw new Exception($error_message);
                }
            }

        default:
            throw new Exception('Invalid action');
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';
    $response = process_action($action, $_POST);
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>