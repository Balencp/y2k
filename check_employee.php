<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

error_reporting(E_ALL);
ini_set('display_errors', 1);

function get_connection() {
    $connection_string = "host=dpg-cqofhjggph6c73b8ohng-a.singapore-postgres.render.com " .
        "user=truewallet_db_user " .
        "password=x7liT4P9dZS58ESjbimpct3H5ARCAeel " .
        "dbname=truewallet_db " .
        "port=5432";
    
    $connection = pg_connect($connection_string);
    
    if (!$connection) {
        throw new Exception("Database connection failed: " . pg_last_error());
    }
    
    return $connection;
}

function check_employee($employee_id) {
    try {
        $conn = get_connection();
        $result = pg_query_params($conn, "SELECT employee_name FROM employee_bl WHERE employee_id = $1", array($employee_id));
        
        if (!$result) {
            throw new Exception("Query failed: " . pg_last_error($conn));
        }
        
        $employee = pg_fetch_assoc($result);
        pg_close($conn);
        
        return $employee !== false;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? '';

    try {
        if (check_employee($employee_id)) {
            echo json_encode(['success' => true, 'message' => 'รหัสพนักงานถูกต้อง']);
        } else {
            echo json_encode(['success' => false, 'message' => 'รหัสพนักงานไม่ถูกต้อง']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>