<?php
/**
 * get_reviews.php
 * 
 * Public endpoint to fetch product reviews from SQLite database.
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db_connect.php';

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM `customer_reviews` WHERE `is_verified` = 1 ORDER BY `id` DESC");
    $reviews = [];
    while ($row = $stmt->fetch()) {
        $reviews[] = [
            'id' => (int)$row['id'],
            'name' => $row['reviewer_name'],
            'role' => $row['reviewer_role'],
            'email_address' => $row['email_address'],
            'rating' => (int)$row['rating'],
            'product' => $row['product_name'],
            'date' => $row['review_date'],
            'text' => $row['review_text'],
            'img' => $row['reviewer_img'],
            'is_verified' => (int)$row['is_verified']
        ];
    }
    echo json_encode($reviews);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not fetch reviews: ' . $e->getMessage()]);
    exit;
}
?>
