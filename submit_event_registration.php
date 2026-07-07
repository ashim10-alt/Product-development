<?php
/**
 * submit_event_registration.php
 * 
 * AJAX endpoint to submit event registration details.
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// Read POST data
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true) ?? [];
}

$name = trim($input['full_name'] ?? '');
$email = trim($input['email_address'] ?? '');
$company = trim($input['company_name'] ?? '');
$event = trim($input['event_title'] ?? '');

if (empty($name) || empty($email) || empty($company) || empty($event)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    $ins = $pdo->prepare("INSERT INTO `event_registrations` (`full_name`, `email_address`, `company_name`, `event_title`) VALUES (?, ?, ?, ?)");
    $ins->execute([$name, $email, $company, $event]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Your reservation pass has been generated! Check your email for confirmations shortly.'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
