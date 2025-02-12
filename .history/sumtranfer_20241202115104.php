<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
// function get_connection() {
//     $host = "dpg-cqofhjggph6c73b8ohng-a.singapore-postgres.render.com";
//     $dbname = "truewallet_db";
//     $user = "truewallet_db_user";
//     $pass = "x7liT4P9dZS58ESjbimpct3H5ARCAeel";
//     $port = "5432";

//     try {
//         $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
//         $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
//         return $pdo;
//     } catch (PDOException $e) {
//         throw new Exception("Connection failed: " . $e->getMessage());
//     }
// }
try {
    $pdo = get_connection();
    
    $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['endDate'] ?? date('Y-m-d');
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
    $offset = $page * 100;

    // ดึงข้อมูลสรุปตามพนักงาน
    $stmt = $pdo->prepare("
        SELECT employee_name, SUM(amount) as total_amount
        FROM tranferlogall
        WHERE prefix_wep = 'next' 
        AND date_time BETWEEN :start_date AND :end_date
        GROUP BY employee_name
        ORDER BY total_amount DESC
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $employeeSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $phoneNumber = $_GET['phoneNumber'] ?? '';

    // Add phone number to base condition if provided
    if ($phoneNumber) {
        $baseCondition .= " AND phonenumber LIKE :phone_number";
        $baseParams['phone_number'] = "%$phoneNumber%";
    }


    // ดึงข้อมูลแบบแบ่งหน้า
    $stmt = $pdo->prepare("
        SELECT phonenumber, customername, amount, date_time, employee_name
        FROM tranferlogall
        WHERE prefix_wep = 'next'
        AND date_time BETWEEN :start_date AND :end_date
        ORDER BY date_time DESC
        LIMIT 100 OFFSET :offset
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transferLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // นับจำนวนรายการทั้งหมด
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM tranferlogall
        WHERE prefix_wep = 'next'
        AND date_time BETWEEN :start_date AND :end_date
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'employeeSummary' => $employeeSummary,
        'transferLog' => $transferLog,
        'totalPages' => ceil($totalRecords / 100),
        'currentPage' => $page
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}