<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

function get_connection() {
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

try {
    $pdo = get_connection();

    $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['endDate'] ?? date('Y-m-d');

    // ดึงข้อมูลสรุปตามพนักงาน
    $stmt = $pdo->prepare("
        SELECT employee_name, SUM(amount) as total_amount
        FROM tranferlog_ssw
        WHERE date_time BETWEEN :start_date AND :end_date
        GROUP BY employee_name
        ORDER BY total_amount DESC
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $employeeSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูล 100 รายการล่าสุด
    $stmt = $pdo->prepare("
        SELECT *
        FROM tranferlog_ssw
        ORDER BY date_time DESC
        LIMIT 100
    ");
    $stmt->execute();
    $transferLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'employeeSummary' => $employeeSummary,
        'transferLog' => $transferLog
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}