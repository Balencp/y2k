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
    $chat_id = "-4686242496";
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

function check_recent_transfer($bank_account_no, $amount) {
    $conn = get_connection();
    $stmt = $conn->prepare("
        SELECT * FROM tranferlog_y2k_ptop 
        WHERE bankAccountNo = :bank_account_no 
        AND amount = :amount 
        AND date_time >= :date_time
    ");
    $stmt->execute([
        'bank_account_no' => $bank_account_no,
        'amount' => $amount,
        'date_time' => date('Y-m-d H:i:s', strtotime('-1 minute'))
    ]);
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);
    return $recent;
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
            try {
                 // ตรวจสอบ Content-Type
                $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
                if (strpos($contentType, 'multipart/form-data') !== false) {
                    // ข้อมูลมาจาก multipart/form-data
                    $data = $_POST;
                } else {
                    // ข้อมูลมาจาก application/x-www-form-urlencoded หรืออื่นๆ
                    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
                }
                // เชื่อมต่อ DB และเริ่ม transaction
                $conn = get_connection();
                $conn->beginTransaction();

                // ตรวจสอบ Request ID
                $request_id = $_POST['request_id'] ?? null;
                if (!$request_id) {
                    throw new Exception('Missing request ID');
                }

                // ตรวจสอบการทำรายการซ้ำจาก Request ID
                $stmt = $conn->prepare("
                    SELECT * FROM transfer_requests 
                    WHERE request_id = :request_id 
                    AND created_at >= NOW() - INTERVAL '10 minutes'
                ");
                $stmt->execute(['request_id' => $request_id]);
                if ($existing_request = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($existing_request['status'] === 'completed') {
                        return [
                            'success' => true,
                            'message' => 'รายการนี้ทำการโอนสำเร็จแล้ว',
                            'new_balance' => $existing_request['final_balance']
                        ];
                    }
                    throw new Exception('พบรายการที่กำลังดำเนินการอยู่ กรุณารอสักครู่');
                }

                // บันทึก request เริ่มต้น
                $stmt = $conn->prepare("
                    INSERT INTO transfer_requests (
                        request_id, wallet_id, amount, status, created_at
                    ) VALUES (
                        :request_id, :wallet_id, :amount, 'pending', NOW()
                    )
                ");
                $stmt->execute([
                    'request_id' => $request_id,
                    'wallet_id' => $data['payee_wallet_id'],
                    'amount' => $data['amount']
                ]);

                // ตรวจสอบพนักงาน
                $employee = check_employee($data['employee_id']);
                if (!$employee) {
                    throw new Exception('รหัสพนักงานไม่ถูกต้อง');
                }

                // ตรวจสอบการโอนซ้ำในช่วง 30 วินาที
                $recent = check_recent_transfer($data['payee_wallet_id'], $data['amount']);
                if ($recent) {
                    throw new Exception('พบรายการโอนซ้ำในช่วง 30 วินาที กรุณารอสักครู่');
                }

                // สร้าง instance TMNOne และ login
                $TMNOne = new TMNOne();
                $TMNOne->setData($data['tmn_key_id'], $data['mobile_number'], $data['login_token'], $data['tmn_id']);
                $TMNOne->loginWithPin6($data['pin']);
                
                // ดึงข้อมูลผู้รับ
                $recipientName = $TMNOne->getRecipientName($data['payee_wallet_id']);
                
                // หน่วงเวลาก่อนโอน 1 วินาที
                sleep(1);
                
                // ทำการโอนเงิน
                $result = $TMNOne->transferP2P($data['payee_wallet_id'], $data['amount'], '');
                
                // ตรวจสอบผลการโอน
                if (!isset($result['transfer_status']) || $result['transfer_status'] !== 'PROCESSING') {
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

                // เชื่อมต่อฐานข้อมูลและเตรียมข้อมูล
                $date_time = date('Y-m-d H:i:s');
                
                // เตรียมข้อความแจ้งเตือน
                $telegram_message = "<b>True Wallet โอนเงินสำเร็จ!</b>\n"
                                . "Request ID: {$request_id}\n"
                                . "เบอร์โทรศัพท์: {$data['payee_wallet_id']}\n"
                                . "ชื่อลูกค้า: {$recipientName}\n"
                                . "จำนวนเงิน: {$data['amount']} บาท\n"
                                . "พนักงาน: {$employee['employee_name']}\n"
                                . "เวลา: {$date_time}";
                
                // ส่งการแจ้งเตือน
                sendTelegramNotify($telegram_message);
                
                try {
                    // บันทึก tranferlog_bl_ptop
                    $stmt = $conn->prepare("
                        INSERT INTO tranferlog_bl_ptop (
                            amount, date_time, bankAccountNo
                        ) VALUES (
                            :amount, :date_time, :bank_account_no
                        )
                    ");
                    
                    $stmt->execute([
                        'amount' => $data['amount'],
                        'date_time' => $date_time,
                        'bank_account_no' => $data['payee_wallet_id']
                    ]);

                    // บันทึก tranferlog_bl
                    $stmt = $conn->prepare("
                        INSERT INTO tranferlog_bl (
                            phonenumber, amount, date_time, 
                            employee_name, employee_id
                        ) VALUES (
                            :phonenumber, :amount, :date_time, 
                            :employee_name, :employee_id
                        )
                    ");
                    
                    $stmt->execute([
                        'phonenumber' => $data['payee_wallet_id'],
                        'amount' => $data['amount'],
                        'date_time' => $date_time,
                        'employee_name' => $employee['employee_name'],
                        'employee_id' => $data['employee_id']
                    ]);

                    // บันทึก tranferlogall
                    $stmt = $conn->prepare("
                        INSERT INTO tranferlogall (
                            phonenumber, customername, amount, date_time,
                            employee_name, employee_id, prefix_wep, datalog
                        ) VALUES (
                            :phonenumber, :customername, :amount, :date_time,
                            :employee_name, :employee_id, :prefix_wep, :datalog
                        )
                    ");
                    
                    $stmt->execute([
                        'phonenumber' => $data['payee_wallet_id'],
                        'customername' => $recipientName,
                        'amount' => $data['amount'],
                        'date_time' => $date_time,
                        'employee_name' => $employee['employee_name'],
                        'employee_id' => $data['employee_id'],
                        'prefix_wep' => 'bl',
                        'datalog' => json_encode($result)
                    ]);

                    // อัพเดทสถานะ request
                    $new_balance = $TMNOne->getBalance();
                    $stmt = $conn->prepare("
                        UPDATE transfer_requests 
                        SET status = 'completed',
                            completed_at = NOW(),
                            final_balance = :final_balance,
                            result = :result
                        WHERE request_id = :request_id
                    ");
                    
                    $stmt->execute([
                        'final_balance' => $new_balance,
                        'result' => json_encode($result),
                        'request_id' => $request_id
                    ]);

                    // Commit transaction
                    $conn->commit();
                    
                } catch (Exception $e) {
                    // Rollback เมื่อเกิดข้อผิดพลาด
                    $conn->rollBack();
                    throw $e;
                }

                // ส่งผลลัพธ์กลับ
                return [
                    'success' => true,
                    'message' => 'โอนเงินสำเร็จ',
                    'new_balance' => $new_balance
                ];

            } catch (Exception $e) {
                if (isset($conn)) {
                    $conn->rollBack();
                }
                throw $e;
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