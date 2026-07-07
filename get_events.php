<?php
/**
 * get_events.php
 * 
 * Public endpoint to fetch upcoming events from SQLite database.
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db_connect.php';

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM `events` ORDER BY `id` ASC");
    $events = [];
    while ($row = $stmt->fetch()) {
        $events[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'badge_text' => $row['badge_text'],
            'badge_class' => $row['badge_class'],
            'description' => $row['description'],
            'event_date' => $row['event_date'],
            'image_path' => $row['image_path']
        ];
    }
    echo json_encode($events);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not fetch events: ' . $e->getMessage()]);
    exit;
}
?>
