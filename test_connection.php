<?php
// เปิดการแสดงข้อผิดพลาดทั้งหมด
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ฟังก์ชันสำหรับการเชื่อมต่อฐานข้อมูล
function get_db_connection() {
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $port = getenv('DB_PORT');

    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";

    try {
        // พยายามสร้างการเชื่อมต่อ
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // ตรวจสอบการเชื่อมต่อด้วยการ query ง่ายๆ
        $stmt = $pdo->query('SELECT 1');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['?column?'] == 1) {
            echo "การเชื่อมต่อสำเร็จ!<br>";
            echo "เวอร์ชัน PostgreSQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
        } else {
            echo "เชื่อมต่อสำเร็จ แต่ไม่สามารถ query ได้<br>";
        }

        // แสดงข้อมูลการเชื่อมต่อ (ระวัง: อย่าแสดงข้อมูลนี้ในสภาพแวดล้อมการผลิต)
        echo "Host: $host<br>";
        echo "Database: $db<br>";
        echo "User: $user<br>";
        echo "Port: $port<br>";

        return $pdo;
    } catch (PDOException $e) {
        // จับข้อผิดพลาดและแสดงรายละเอียด
        die("การเชื่อมต่อล้มเหลว: " . $e->getMessage() . "<br>");
    }
}

// เรียกใช้ฟังก์ชันเชื่อมต่อ
$connection = get_db_connection();

// ถ้าเชื่อมต่อสำเร็จ ทดลอง query ข้อมูล
if ($connection) {
    try {
        // ทดสอบ query ตาราง (แทนที่ 'your_table' ด้วยชื่อตารางจริงในฐานข้อมูลของคุณ)
        $stmt = $connection->query("SELECT * FROM tranferlog_bl_ptop LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "ข้อมูลจากตาราง:<br>";
            foreach ($rows as $row) {
                print_r($row);
                echo "<br>";
            }
        } else {
            echo "ไม่พบข้อมูลในตาราง<br>";
        }
    } catch (PDOException $e) {
        echo "ไม่สามารถ query ข้อมูล: " . $e->getMessage() . "<br>";
    }
}
?>