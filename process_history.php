<?php
require_once 'TMNOne.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

function process_action($action, $data) {
    switch ($action) {
        case 'fetchTransactionHistory':
            $TMNOne = new TMNOne();
            $TMNOne->setData($data['tmn_key_id'], $data['mobile_number'], $data['login_token'], $data['tmn_id']);
            $TMNOne->loginWithPin6($data['pin']);
            $history = $TMNOne->fetchTransactionHistory($data['start_date'], $data['end_date'], 50, 1);
            return ['success' => true, 'data' => $history];

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