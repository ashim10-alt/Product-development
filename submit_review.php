<?php
/**
 * submit_review.php
 * 
 * Endpoint to submit a product review.
 * Validates the reviewer's purchase status. If their email is found in
 * customer_purchases matching the product, the review is marked as verified.
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// Read POST data (handles JSON or form urlencoded)
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true) ?? [];
}

$name = trim($input['reviewer_name'] ?? '');
$email = trim($input['email_address'] ?? '');
$role = trim($input['reviewer_role'] ?? '');
$rating = (int)($input['rating'] ?? 5);
$product = trim($input['product_name'] ?? '');
$text = trim($input['review_text'] ?? '');
$img = trim($input['reviewer_img'] ?? '');

if (empty($name) || empty($email) || empty($product) || empty($text)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

if (empty($img)) {
    // default avatar
    $img = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?q=80&w=100';
}

try {
    $pdo = getDbConnection();
    
    // 1. Check if user exists in customers table
    $cust_stmt = $pdo->prepare("SELECT id FROM `customers` WHERE `email_address` = ?");
    $cust_stmt->execute([$email]);
    $cust = $cust_stmt->fetch();
    
    if (!$cust) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'You have not purchased this product. Reviews are restricted to verified buyers.']);
        exit;
    }
    $is_verified = 1;

    // 2. Insert Review
    $review_date = date('M Y');
    $ins_stmt = $pdo->prepare("INSERT INTO `customer_reviews` 
        (`reviewer_name`, `reviewer_role`, `email_address`, `rating`, `product_name`, `review_date`, `review_text`, `reviewer_img`, `is_verified`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
    $ins_stmt->execute([$name, $role, $email, $rating, $product, $review_date, $text, $img, $is_verified]);
    
    // 3. Update product overall rating and count
    $upd_stmt = $pdo->prepare("UPDATE `products` SET 
        `review_count` = `review_count` + 1,
        `rating` = (SELECT AVG(rating) FROM `customer_reviews` WHERE `product_name` = ?)
        WHERE `name` = ?");
    $upd_stmt->execute([$product, $product]);
        
    echo json_encode([
        'status' => 'success', 
        'message' => 'Review recorded successfully! Thank you for your feedback.', 
        'is_verified' => $is_verified
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not record review: ' . $e->getMessage()]);
    exit;
}
