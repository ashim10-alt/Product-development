<?php
/**
 * submit_inquiry.php
 * 
 * Lead capture script for the AI-Solution B2B site.
 * Validates, cleans, and saves form submissions (both regular form-POST and JSON-Fetch requests)
 * to the SQLite database.
 * 
 * Supports:
 * - Customer registration (unique sequential client ID)
 * - Inquiry vs Demo request tracking
 * - 5% Demo deposit computation (£14.95 for basic, £39.95 for standard)
 * - Automatic confirmation email via Gmail SMTP (using mailer.php)
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

require_once 'mailer.php';
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    sendResponse('error', 'Invalid request method.', 405);
}

$is_json = false;
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if (stripos($content_type, 'application/json') !== false) {
    $is_json = true;
    $input_data = json_decode(file_get_contents("php://input"), true);
} else {
    $input_data = $_POST;
}

// Required fields validation
$fields = [
    'full_name'       => 'Full Name',
    'email_address'   => 'Email Address',
    'phone_number'    => 'Phone Number',
    'company_name'    => 'Company Name',
    'country'         => 'Country',
    'job_title'       => 'Job Title',
    'product_name'    => 'Product Name',
    'package_name'    => 'Package Name'
];

$errors = [];
$sanitized = [];

foreach ($fields as $key => $label) {
    $val = isset($input_data[$key]) ? trim($input_data[$key]) : '';
    if ($val === '') {
        $errors[$key] = "$label is required.";
        continue;
    }
    
    if ($key === 'email_address') {
        $sanitized_email = filter_var($val, FILTER_SANITIZE_EMAIL);
        if (!filter_var($sanitized_email, FILTER_VALIDATE_EMAIL)) {
            $errors[$key] = "Please enter a valid email address.";
        } else {
            $sanitized[$key] = $sanitized_email;
        }
    } else {
        $sanitized[$key] = htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8');
    }
}

// Optional fields
$sanitized['request_type'] = htmlspecialchars(strip_tags($input_data['request_type'] ?? 'Inquiry'), ENT_QUOTES, 'UTF-8');
$sanitized['custom_wishes'] = htmlspecialchars(strip_tags($input_data['custom_wishes'] ?? ''), ENT_QUOTES, 'UTF-8');
$sanitized['inquiry_details'] = htmlspecialchars(strip_tags($input_data['inquiry_details'] ?? ''), ENT_QUOTES, 'UTF-8');

if (!empty($errors)) {
    sendResponse('validation_error', 'Please correct the highlighted fields.', 400, $errors);
}

try {
    $pdo = getDbConnection();
    
    // 1. Manage Customer table (Always insert new customer to get a unique sequential client ID per request)
    $cust_ins = $pdo->prepare("INSERT INTO `customers` (`full_name`, `email_address`) VALUES (?, ?)");
    $cust_ins->execute([$sanitized['full_name'], $sanitized['email_address']]);
    $client_id = (int)$pdo->lastInsertId();

    // 2. Compute deposit amount
    $deposit = 0.00;
    if ($sanitized['request_type'] === 'Demo') {
        // Query the catalog database to find the product prices
        $basic_price = 299.00;
        $standard_price = 799.00;
        try {
            $p_stmt = $pdo->prepare("SELECT basic_price, standard_price FROM `products` WHERE name = ?");
            $p_stmt->execute([$sanitized['product_name']]);
            $p_info = $p_stmt->fetch();
            if ($p_info) {
                $basic_price = (float)$p_info['basic_price'];
                $standard_price = (float)$p_info['standard_price'];
            }
        } catch (Exception $e) {
            // Use defaults
        }

        if (stripos($sanitized['package_name'], 'basic') !== false) {
            $deposit = round($basic_price * 0.05, 2);
        } elseif (stripos($sanitized['package_name'], 'standard') !== false) {
            $deposit = round($standard_price * 0.05, 2);
        }
    }

    $payment_status = ($deposit > 0) ? 'Pending' : 'Free';

    // Retrieve initial deal value from catalog
    $deal_value = 0.00;
    try {
        $p_stmt = $pdo->prepare("SELECT basic_price, standard_price FROM `products` WHERE name = ?");
        $p_stmt->execute([$sanitized['product_name']]);
        $p_info = $p_stmt->fetch();
        if ($p_info) {
            if (stripos($sanitized['package_name'], 'basic') !== false) {
                $deal_value = (float)$p_info['basic_price'];
            } elseif (stripos($sanitized['package_name'], 'standard') !== false) {
                $deal_value = (float)$p_info['standard_price'];
            }
        }
    } catch (Exception $e) {
        // Use 0.00
    }

    // 3. Save lead inquiry
    $sql = "INSERT INTO `customer_inquiries` 
            (`customer_id`, `full_name`, `email_address`, `phone_number`, `company_name`, `country`, `job_title`, `request_type`, `product_name`, `package_name`, `deposit_amount`, `payment_status`, `custom_wishes`, `inquiry_details`, `deal_status`, `total_received`, `deal_value`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $client_id,
        $sanitized['full_name'], 
        $sanitized['email_address'], 
        $sanitized['phone_number'], 
        $sanitized['company_name'], 
        $sanitized['country'], 
        $sanitized['job_title'],
        $sanitized['request_type'],
        $sanitized['product_name'],
        $sanitized['package_name'],
        $deposit,
        $payment_status,
        $sanitized['custom_wishes'],
        $sanitized['inquiry_details'],
        'New Lead',
        0.00,
        $deal_value
    ]);
    
    $inquiry_id = $pdo->lastInsertId();

    // 3.5 Log the initial message in customer_conversations
    try {
        $conv_msg = "Hello, I have submitted a " . $sanitized['request_type'] . " request for " . $sanitized['product_name'] . " (" . $sanitized['package_name'] . ").\n\nCustom wishes: " . ($sanitized['custom_wishes'] ?: 'None') . "\nInquiry details: " . ($sanitized['inquiry_details'] ?: 'None');
        $c_stmt = $pdo->prepare("INSERT INTO `customer_conversations` (`inquiry_id`, `sender`, `message`) VALUES (?, 'Customer', ?)");
        $c_stmt->execute([$inquiry_id, $conv_msg]);
    } catch (Exception $e) {
        // Ignore logging failures
    }

    // 4. Send Confirmation Email via SMTP
    $subject = "AI.Solution Request Received: " . $sanitized['product_name'];
    $body = getEmailTemplate(
        $sanitized['full_name'],
        $client_id,
        $sanitized['product_name'],
        $sanitized['package_name'],
        $sanitized['request_type'],
        $sanitized['company_name'],
        ($deposit > 0 ? "£" . number_format($deposit, 2) : "None"),
        $sanitized['custom_wishes'] . " " . $sanitized['inquiry_details']
    );
    
    // Trigger SMTP email sending
    @sendSMTPEmail($sanitized['email_address'], $subject, $body);

    // Return success response to client
    $msg = "Thank you! Your " . $sanitized['request_type'] . " request has been received. (Client ID: #" . $client_id . ")";
    if ($deposit > 0) {
        $msg .= " A 5% demo activation deposit of £" . number_format($deposit, 2) . " is required. Check your email inbox for instructions.";
    }
    
    sendResponse('success', $msg, 200, [], [
        'client_id' => $client_id,
        'inquiry_id' => $inquiry_id,
        'deposit' => $deposit,
        'payment_status' => $payment_status
    ]);

} catch (Exception $e) {
    error_log("AI-Solution DB Error: " . $e->getMessage());
    sendResponse('error', 'We encountered a systems processing issue. Please try again later. Details: ' . $e->getMessage(), 500);
}

/**
 * Utility function to send JSON responses
 */
function sendResponse(string $status, string $message, int $http_code = 200, array $validation_errors = [], array $extra_data = []) {
    global $is_json;
    http_response_code($http_code);
    
    $response = array_merge([
        'status' => $status,
        'message' => $message,
        'errors' => $validation_errors
    ], $extra_data);
    
    if ($is_json || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    } else {
        $redirect_url = 'products.html';
        if ($status === 'success') {
            $redirect_url .= '?success=1&client_id=' . $response['client_id'];
        } else {
            $redirect_url .= '?error=' . urlencode($message);
        }
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Returns premium HTML template
 */
function getEmailTemplate(string $name, int $clientId, string $productName, string $packageName, string $reqType, string $company, string $deposit, string $details) {
    return '
    <div style="font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff; color: #1a202c;">
      <div style="background: linear-gradient(135deg, #070b19 0%, #0f162d 100%); padding: 24px; border-radius: 12px 12px 0 0; text-align: center; color: #ffffff; border-bottom: 3px solid #00f0ff;">
        <h2 style="margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">AI-Solution</h2>
        <p style="margin: 6px 0 0 0; font-size: 13px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1.5px;">B2B Portal Confirmation</p>
      </div>
      <div style="padding: 24px; line-height: 1.6;">
        <p style="font-size: 16px; margin-top: 0;">Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
        <p style="font-size: 14px; color: #4a5568;">We have successfully received your <strong>' . htmlspecialchars($reqType) . '</strong> request for <strong>' . htmlspecialchars($productName) . '</strong>. Our sales engineering team has registered your profile.</p>
        
        <div style="background: #f8fafc; padding: 18px; border-left: 4px solid #00f0ff; border-radius: 8px; margin: 24px 0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
          <h3 style="margin: 0 0 12px 0; font-size: 15px; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">Request Specification</h3>
          <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #334155;">
            <tr><td style="padding: 6px 0; font-weight: 600; width: 140px;">Unique Client ID:</td><td style="padding: 6px 0; font-weight: 700; color: #0f172a;">#' . htmlspecialchars($clientId) . '</td></tr>
            <tr><td style="padding: 6px 0; font-weight: 600;">Product:</td><td style="padding: 6px 0;">' . htmlspecialchars($productName) . '</td></tr>
            <tr><td style="padding: 6px 0; font-weight: 600;">Package level:</td><td style="padding: 6px 0;">' . htmlspecialchars($packageName) . '</td></tr>
            <tr><td style="padding: 6px 0; font-weight: 600;">Request Type:</td><td style="padding: 6px 0; font-weight: 700; color: #0284c7;">' . htmlspecialchars($reqType) . '</td></tr>
            <tr><td style="padding: 6px 0; font-weight: 600;">Organization:</td><td style="padding: 6px 0;">' . htmlspecialchars($company) . '</td></tr>
            <tr><td style="padding: 6px 0; font-weight: 600;">Demo Activation Deposit (5%):</td><td style="padding: 6px 0; font-weight: 700; color: #b45309;">' . htmlspecialchars($deposit) . '</td></tr>
          </table>
        </div>
        
        ' . ($deposit !== 'None' ? '
        <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 8px; padding: 14px; margin-bottom: 24px; font-size: 13px; color: #78350f;">
          <strong>Demo Activation Notice:</strong> To compile and deploy your secure virtual workspace sandbox, a 5% deposit of the product subscription price (' . htmlspecialchars($deposit) . ') must be cleared. Our account executive will send a secure invoice link shortly.
        </div>' : '') . '
        
        <p style="font-size: 14px; color: #4a5568;">Your request details have been registered successfully. Our Sunderland HQ engineers will review your configuration preferences and contact you within 24 hours.</p>
        
        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 28px 0;" />
        <p style="font-size: 11px; color: #94a3b8; text-align: center; margin: 0; line-height: 1.4;">This message was generated automatically by the AI.Solution Lead Ingestion Core.<br>Innovation Hub, Sunderland SR1 1PB, United Kingdom.</p>
      </div>
    </div>';
}
?>
