<?php
/**
 * get_products.php
 * 
 * Public endpoint to fetch the product catalog.
 * Connects to the SQLite database and returns the products as JSON.
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db_connect.php';

try {
    $pdo = getDbConnection();
    
    // Fetch products from database
    $products = [];
    $stmt = $pdo->query("SELECT * FROM `products` ORDER BY `id` ASC");
    
    while ($row = $stmt->fetch()) {
        $row['id'] = (int)$row['id'];
        $row['rating'] = (float)$row['rating'];
        $row['review_count'] = (int)$row['review_count'];
        $row['basic_price'] = (float)$row['basic_price'];
        $row['standard_price'] = (float)$row['standard_price'];
        $products[] = $row;
    }
    
    echo json_encode($products);
    exit;

} catch (Exception $e) {
    error_log("get_products.php error: " . $e->getMessage());
    // Fallback to local default mock products on database issues
    echo json_encode(getDefaultProductsFallback());
    exit;
}

/**
 * Returns default products array as fallback
 */
function getDefaultProductsFallback() {
    return [
        [
            'id' => 1,
            'name' => 'OmniMetrics AI',
            'description' => 'Advanced predictive analytics dashboard that automatically identifies workflow bottlenecks across your enterprise architecture with real-time insights.',
            'category' => 'new analytics',
            'tags' => 'AI-Powered,Predictive,Enterprise',
            'integration' => 'M365, Salesforce, SAP',
            'deployment' => 'Cloud / On-Premise',
            'release_date' => 'October 2023',
            'rating' => 4.90,
            'review_count' => 6,
            'image_path' => 'images/dashboard.png',
            'basic_price' => 299.00,
            'standard_price' => 799.00,
            'custom_price' => 'Custom'
        ],
        [
            'id' => 2,
            'name' => 'Nexus Assist Pro',
            'description' => 'Our flagship virtual assistant deeply integrated with M365 and Google Workspace to automate routine employee inquiries around the clock.',
            'category' => 'new assistant',
            'tags' => 'Virtual Assistant,M365,Google WS',
            'integration' => 'M365, Google Workspace',
            'deployment' => 'Cloud / On-Premise',
            'release_date' => 'October 2023',
            'rating' => 5.00,
            'review_count' => 4,
            'image_path' => 'images/hero.png',
            'basic_price' => 299.00,
            'standard_price' => 799.00,
            'custom_price' => 'Custom'
        ],
        [
            'id' => 3,
            'name' => 'LogicBuilder 3.0',
            'description' => 'Rapid prototyping solution for IT departments to visually construct and deploy custom AI logic trees without writing a single line of code.',
            'category' => 'new assistant analytics',
            'tags' => 'No-Code,Workflow,Automation',
            'integration' => 'Zero-Code Setup',
            'deployment' => '200+ integrations',
            'release_date' => 'September 2023',
            'rating' => 4.70,
            'review_count' => 8,
            'image_path' => 'images/workflow.png',
            'basic_price' => 299.00,
            'standard_price' => 799.00,
            'custom_price' => 'Custom'
        ]
    ];
}
