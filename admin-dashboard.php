<?php
/**
 * admin-dashboard.php
 * Full AI-Solution Admin Portal — 6 tabs:
 *   Overview | Inquiries | Customers | Products | Reviews | Analytics
 * Requires active admin session. All AJAX actions handled in this same file.
 */

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

if (is_writable(sys_get_temp_dir())) {
    session_save_path(sys_get_temp_dir());
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

function getAdminAuthSecret() {
    return getenv('ADMIN_AUTH_SECRET') ?: 'AiSolutionAdminSecret2026!';
}

function validateAdminAuthCookie(string $cookieValue) {
    $parts = explode('.', $cookieValue, 2);
    if (count($parts) !== 2) {
        return false;
    }

    $payload = base64_decode($parts[0], true);
    if ($payload === false) {
        return false;
    }

    $expected = hash_hmac('sha256', $payload, getAdminAuthSecret());
    if (!hash_equals($expected, $parts[1])) {
        return false;
    }

    $data = json_decode($payload, true);
    if (!is_array($data) || empty($data['user']) || empty($data['exp'])) {
        return false;
    }

    if ($data['exp'] < time()) {
        return false;
    }

    return $data['user'];
}

session_start();

if ((!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) && !empty($_COOKIE['admin_auth'])) {
    $user = validateAdminAuthCookie($_COOKIE['admin_auth']);
    if ($user !== false) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        $_SESSION['admin_id'] = 1;
        $_SESSION['db_offline'] = true;
    }
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }
    header("Location: admin-login.php");
    exit;
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    setcookie('admin_auth', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

// ─── AJAX Action Handlers ─────────────────────────────────────────────────────
$ajax_action = isset($_POST['ajax_action']) ? $_POST['ajax_action'] : (isset($_GET['ajax_action']) ? $_GET['ajax_action'] : '');

if ($ajax_action) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $pdo = getDbConnection();

        // DELETE INQUIRY
        if ($ajax_action === 'delete_inquiry') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $s = $pdo->prepare("DELETE FROM `customer_inquiries` WHERE id=?");
                $s->execute([$id]);
                echo json_encode(['status'=>'success','message'=>'Inquiry deleted.']);
            } else { echo json_encode(['status'=>'error','message'=>'Invalid ID.']); }
            exit;
        }

        // SEND REPLY EMAIL
        if ($ajax_action === 'send_reply') {
            $to    = trim($_POST['to']    ?? '');
            $subj  = trim($_POST['subject'] ?? 'RE: AI-Solution');
            $body  = trim($_POST['body']  ?? '');
            if ($to && $body) {
                require_once 'mailer.php';
                $html = '<div style="font-family:\'Segoe UI\',sans-serif;max-width:600px;margin:0 auto;padding:24px;background:#fff;border-radius:12px;">
                    <div style="background:linear-gradient(135deg,#070b19,#0f162d);padding:20px;border-radius:8px 8px 0 0;text-align:center;border-bottom:3px solid #00f0ff;">
                      <h2 style="color:#fff;margin:0;font-size:22px;">AI-Solution</h2>
                      <p style="color:rgba(255,255,255,0.7);margin:4px 0 0;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;">Staff Reply</p>
                    </div>
                    <div style="padding:24px;color:#1a202c;line-height:1.7;">
                      <p style="font-size:15px;">' . nl2br(htmlspecialchars($body)) . '</p>
                      <hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">
                      <p style="font-size:11px;color:#94a3b8;text-align:center;">AI-Solution Ltd · Innovation Hub, Sunderland SR1 1PB, UK</p>
                    </div>
                </div>';
                $sent = @sendSMTPEmail($to, $subj, $html);
                echo json_encode(['status'=>$sent?'success':'error','message'=>$sent?'Reply sent successfully.':'SMTP failed — check mailer config.']);
            } else {
                echo json_encode(['status'=>'error','message'=>'Missing email or body.']);
            }
            exit;
        }

        // RECORD PURCHASE
        if ($ajax_action === 'record_purchase') {
            $cid  = (int)($_POST['customer_id'] ?? 0);
            $prod = trim($_POST['product_name'] ?? '');
            if ($cid > 0 && $prod) {
                // Check not already purchased
                $ck = $pdo->prepare("SELECT id FROM `customer_purchases` WHERE customer_id=? AND product_name=?");
                $ck->execute([$cid, $prod]);
                if (!$ck->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO `customer_purchases` (customer_id,product_name) VALUES (?,?)");
                    $ins->execute([$cid, $prod]);
                    
                    // Mark related reviews as verified (SQLite-compatible UPDATE)
                    $upd = $pdo->prepare("UPDATE `customer_reviews` 
                        SET `is_verified` = 1 
                        WHERE `product_name` = ? 
                        AND `email_address` = (SELECT `email_address` FROM `customers` WHERE `id` = ?)");
                    $upd->execute([$prod, $cid]);
                    
                    echo json_encode(['status'=>'success','message'=>'Purchase recorded for Client #'.$cid]);
                } else {
                    echo json_encode(['status'=>'error','message'=>'Already recorded.']);
                }
            } else { echo json_encode(['status'=>'error','message'=>'Invalid data.']); }
            exit;
        }

        // ADD PRODUCT
        if ($ajax_action === 'add_product') {
            $name   = trim($_POST['name']       ?? '');
            $desc   = trim($_POST['description'] ?? '');
            $cat    = trim($_POST['category']   ?? 'new');
            $tags   = trim($_POST['tags']       ?? '');
            $integ  = trim($_POST['integration'] ?? '');
            $dep    = trim($_POST['deployment'] ?? '');
            $rel    = trim($_POST['release_date'] ?? '');
            $basic  = (float)($_POST['basic_price']    ?? 299);
            $std    = (float)($_POST['standard_price'] ?? 799);
            $img    = trim($_POST['image_path'] ?? '');

            // Handle image upload from device
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['image_file']['tmp_name'];
                $fileName = $_FILES['image_file']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                // sanitize file name
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                
                // check if the file extension is allowed
                $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    $uploadFileDir = __DIR__ . '/images/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        $img = 'images/' . $newFileName;
                    } else {
                        echo json_encode(['status'=>'error','message'=>'There was some error moving the file to upload directory.']);
                        exit;
                    }
                } else {
                    echo json_encode(['status'=>'error','message'=>'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions)]);
                    exit;
                }
            }

            if (empty($img)) {
                $img = 'https://images.unsplash.com/photo-1557200134-90327ee9fafa?q=80&w=600';
            }

            if ($name && $desc) {
                $ins = $pdo->prepare("INSERT INTO `products` (name,description,detail_description,category,tags,integration,deployment,release_date,basic_price,standard_price,image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                if ($ins->execute([$name,$desc,$desc,$cat,$tags,$integ,$dep,$rel,$basic,$std,$img])) {
                    echo json_encode(['status'=>'success','message'=>'Product "'.$name.'" added successfully.']);
                } else {
                    echo json_encode(['status'=>'error','message'=>'Could not add product.']);
                }
            } else { echo json_encode(['status'=>'error','message'=>'Name and Description are required.']); }
            exit;
        }

        // EDIT PRODUCT
        if ($ajax_action === 'edit_product') {
            $id     = (int)($_POST['id'] ?? 0);
            $name   = trim($_POST['name']       ?? '');
            $desc   = trim($_POST['description'] ?? '');
            $detail_desc = trim($_POST['detail_description'] ?? '');
            $cat    = trim($_POST['category']   ?? 'new');
            $tags   = trim($_POST['tags']       ?? '');
            $integ  = trim($_POST['integration'] ?? '');
            $dep    = trim($_POST['deployment'] ?? '');
            $rel    = trim($_POST['release_date'] ?? '');
            $basic  = (float)($_POST['basic_price']    ?? 299);
            $std    = (float)($_POST['standard_price'] ?? 799);
            $custom_price = trim($_POST['custom_price'] ?? 'Custom');
            $img    = trim($_POST['image_path'] ?? '');

            if ($id <= 0) {
                echo json_encode(['status'=>'error','message'=>'Invalid Product ID.']);
                exit;
            }

            // Handle image upload from device
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['image_file']['tmp_name'];
                $fileName = $_FILES['image_file']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    $uploadFileDir = __DIR__ . '/images/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $dest_path = $uploadFileDir . $newFileName;
                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        $img = 'images/' . $newFileName;
                    }
                }
            }

            if ($name && $desc) {
                if (empty($img)) {
                    $curr_stmt = $pdo->prepare("SELECT image_path FROM `products` WHERE id=?");
                    $curr_stmt->execute([$id]);
                    $img = $curr_stmt->fetchColumn() ?: 'https://images.unsplash.com/photo-1557200134-90327ee9fafa?q=80&w=600';
                }
                
                $upd = $pdo->prepare("UPDATE `products` SET 
                    `name` = ?, 
                    `description` = ?, 
                    `detail_description` = ?, 
                    `category` = ?, 
                    `tags` = ?, 
                    `integration` = ?, 
                    `deployment` = ?, 
                    `release_date` = ?, 
                    `basic_price` = ?, 
                    `standard_price` = ?, 
                    `custom_price` = ?, 
                    `image_path` = ? 
                    WHERE `id` = ?");
                
                if ($upd->execute([$name, $desc, $detail_desc, $cat, $tags, $integ, $dep, $rel, $basic, $std, $custom_price, $img, $id])) {
                    echo json_encode(['status'=>'success','message'=>'Product "'.$name.'" updated successfully.']);
                } else {
                    echo json_encode(['status'=>'error','message'=>'Could not update product.']);
                }
            } else { 
                echo json_encode(['status'=>'error','message'=>'Name and Description are required.']); 
            }
            exit;
        }

        // DELETE PRODUCT
        if ($ajax_action === 'delete_product') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $s = $pdo->prepare("DELETE FROM `products` WHERE id=?");
                $s->execute([$id]);
                echo json_encode(['status'=>'success','message'=>'Product removed.']);
            } else { echo json_encode(['status'=>'error','message'=>'Invalid ID.']); }
            exit;
        }

        // SEND REGISTRANT REPLY EMAIL
        if ($ajax_action === 'send_registrant_reply') {
            $to      = trim($_POST['to']      ?? '');
            $subject = trim($_POST['subject'] ?? 'RE: Event Registration');
            $message = trim($_POST['message'] ?? '');
            
            if ($to && $message) {
                require_once 'mailer.php';
                $html = '<div style="font-family:\'Segoe UI\',sans-serif;max-width:600px;margin:0 auto;padding:24px;background:#fff;border-radius:12px;">
                    <div style="background:linear-gradient(135deg,#070b19,#0f162d);padding:20px;border-radius:8px 8px 0 0;text-align:center;border-bottom:3px solid #2F58CD;">
                      <h2 style="color:#fff;margin:0;font-size:22px;">AI-Solution</h2>
                      <p style="color:rgba(255,255,255,0.7);margin:4px 0 0;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;">Event Registration Desk</p>
                    </div>
                    <div style="padding:24px;color:#1a202c;line-height:1.7;">
                      <p style="font-size:15px;white-space:pre-line;">' . htmlspecialchars($message) . '</p>
                      <hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">
                      <p style="font-size:11px;color:#94a3b8;text-align:center;">AI-Solution Ltd · Innovation Hub, Sunderland SR1 1PB, UK</p>
                    </div>
                </div>';
                $sent = @sendSMTPEmail($to, $subject, $html);
                echo json_encode(['status'=>$sent?'success':'error','message'=>$sent?'Reply sent successfully.':'SMTP failed — check mailer config.']);
            } else {
                echo json_encode(['status'=>'error','message'=>'Missing email or message body.']);
            }
            exit;
        }

        // GET CONVERSATION AND DEAL DETAILS
        if ($ajax_action === 'get_conversation') {
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM `customer_inquiries` WHERE id=?");
                $stmt->execute([$id]);
                $inq = $stmt->fetch();
                if ($inq) {
                    $c_stmt = $pdo->prepare("SELECT sender, message, created_at FROM `customer_conversations` WHERE inquiry_id=? ORDER BY created_at ASC");
                    $c_stmt->execute([$id]);
                    $messages = $c_stmt->fetchAll();
                    echo json_encode([
                        'status' => 'success',
                        'inquiry' => $inq,
                        'messages' => $messages
                    ]);
                } else { echo json_encode(['status'=>'error','message'=>'Inquiry not found.']); }
            } else { echo json_encode(['status'=>'error','message'=>'Invalid ID.']); }
            exit;
        }

        // UPDATE DEAL DETAILS
        if ($ajax_action === 'update_deal') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM `customer_inquiries` WHERE id=?");
                $stmt->execute([$id]);
                $inq = $stmt->fetch();
                if ($inq) {
                    $status = isset($_POST['deal_status']) ? trim($_POST['deal_status']) : null;
                    
                    // Cancelled trigger logic: Delete immediately
                    if ($status === 'Cancelled') {
                        $del = $pdo->prepare("DELETE FROM `customer_inquiries` WHERE id=?");
                        $del->execute([$id]);
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Inquiry cancelled and deleted permanently.',
                            'deleted' => true
                        ]);
                        exit;
                    }

                    $value = isset($_POST['deal_value']) ? (float)$_POST['deal_value'] : null;
                    $total_received = isset($_POST['total_received']) ? (float)$_POST['total_received'] : null;
                    $payment = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0.0;

                    $new_status = ($status !== null) ? $status : $inq['deal_status'];
                    $new_value = ($value !== null) ? $value : (float)$inq['deal_value'];
                    
                    if ($total_received !== null) {
                        $new_total_received = $total_received;
                    } else {
                        $new_total_received = (float)$inq['total_received'] + $payment;
                    }

                    $payment_status = $inq['payment_status'];
                    if ($payment > 0 || $new_total_received > 0 || $total_received !== null) {
                        $dep = (float)$inq['deposit_amount'];
                        if ($dep > 0 && $new_total_received >= $dep) {
                            $payment_status = 'Paid';
                        } else if ($dep > 0) {
                            $payment_status = 'Pending';
                        } else {
                            $payment_status = 'Free';
                        }
                    }

                    $upd = $pdo->prepare("UPDATE `customer_inquiries` SET `deal_status` = ?, `deal_value` = ?, `total_received` = ?, `payment_status` = ? WHERE id = ?");
                    $upd->execute([$new_status, $new_value, $new_total_received, $payment_status, $id]);

                    // Sold trigger logic: Add to customer purchases and update total amount
                    if ($new_status === 'Sold') {
                        if ($inq['customer_id'] > 0) {
                            $cid = $inq['customer_id'];
                            $prod = $inq['product_name'];
                            
                            $ck = $pdo->prepare("SELECT id FROM `customer_purchases` WHERE customer_id=? AND product_name=?");
                            $ck->execute([$cid, $prod]);
                            if (!$ck->fetch()) {
                                $ins = $pdo->prepare("INSERT INTO `customer_purchases` (customer_id,product_name) VALUES (?,?)");
                                $ins->execute([$cid, $prod]);
                                
                                $updReview = $pdo->prepare("UPDATE `customer_reviews` 
                                    SET `is_verified` = 1 
                                    WHERE `product_name` = ? 
                                    AND `email_address` = (SELECT `email_address` FROM `customers` WHERE `id` = ?)");
                                $updReview->execute([$prod, $cid]);
                            }

                            // Credit user's amount
                            $add_amount = ($new_total_received > 0) ? $new_total_received : $new_value;
                            $updCust = $pdo->prepare("UPDATE `customers` SET `amount` = `amount` + ? WHERE id = ?");
                            $updCust->execute([$add_amount, $cid]);
                        }
                    }

                    if ($payment > 0) {
                        $msg = "System Note: Payment of £" . number_format($payment, 2) . " logged. Total received is now £" . number_format($new_total_received, 2) . ".";
                        $c_stmt = $pdo->prepare("INSERT INTO `customer_conversations` (`inquiry_id`, `sender`, `message`) VALUES (?, 'System', ?)");
                        $c_stmt->execute([$id, $msg]);
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Deal details updated successfully.',
                        'new_total_received' => $new_total_received,
                        'payment_status' => $payment_status
                    ]);
                } else { echo json_encode(['status'=>'error','message'=>'Inquiry not found.']); }
            } else { echo json_encode(['status'=>'error','message'=>'Invalid ID.']); }
            exit;
        }

        // ADD REPLY (AND LOG TO CONVERSATIONS THREAD)
        if ($ajax_action === 'add_reply') {
            $id = (int)($_POST['id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $reply_type = trim($_POST['reply_type'] ?? 'email'); // 'email' or 'message'
            $to = trim($_POST['to'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            if ($id > 0 && $message !== '') {
                $stmt = $pdo->prepare("SELECT email_address, full_name, product_name FROM `customer_inquiries` WHERE id=?");
                $stmt->execute([$id]);
                $inq = $stmt->fetch();
                if ($inq) {
                    $sender = ($reply_type === 'message') ? 'Admin Note' : 'Admin';
                    $c_stmt = $pdo->prepare("INSERT INTO `customer_conversations` (`inquiry_id`, `sender`, `message`) VALUES (?, ?, ?)");
                    $c_stmt->execute([$id, $sender, $message]);

                    $sent = true;
                    if ($reply_type === 'email') {
                        require_once 'mailer.php';
                        $target_email = !empty($to) ? $to : $inq['email_address'];
                        $target_subj = !empty($subject) ? $subject : ("RE: Your AI-Solution Request - " . $inq['product_name']);
                        $html = '<div style="font-family:\'Segoe UI\',sans-serif;max-width:600px;margin:0 auto;padding:24px;background:#fff;border-radius:12px;">
                            <div style="background:linear-gradient(135deg,#070b19,#0f162d);padding:20px;border-radius:8px 8px 0 0;text-align:center;border-bottom:3px solid #00f0ff;">
                              <h2 style="color:#fff;margin:0;font-size:22px;">AI-Solution Staff Response</h2>
                              <p style="color:rgba(255,255,255,0.7);margin:4px 0 0;font-size:12px;text-transform:uppercase;letter-spacing:1.5px;">Corporate Desk</p>
                            </div>
                            <div style="padding:24px;color:#1a202c;line-height:1.7;">
                              <p style="font-size:15px;white-space:pre-line;">' . htmlspecialchars($message) . '</p>
                              <hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">
                              <p style="font-size:11px;color:#94a3b8;text-align:center;">AI-Solution Ltd · Innovation Hub, Sunderland SR1 1PB, UK</p>
                            </div>
                        </div>';
                        $sent = @sendSMTPEmail($target_email, $target_subj, $html);
                    }

                    echo json_encode([
                        'status' => $sent ? 'success' : 'warning',
                        'message' => $sent ? 'Reply registered successfully.' : 'Reply registered, but SMTP failed to deliver email.'
                    ]);
                } else { echo json_encode(['status'=>'error','message'=>'Inquiry not found.']); }
            } else { echo json_encode(['status'=>'error','message'=>'Invalid request parameters.']); }
            exit;
        }

        // UPDATE CUSTOMER AMOUNT
        if ($ajax_action === 'update_customer_amount') {
            $id = (int)($_POST['id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0.0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE `customers` SET `amount` = ? WHERE id = ?");
                if ($stmt->execute([$amount, $id])) {
                    echo json_encode(['status'=>'success','message'=>'Customer amount updated.']);
                } else {
                    echo json_encode(['status'=>'error','message'=>'Could not update customer amount.']);
                }
            } else { echo json_encode(['status'=>'error','message'=>'Invalid ID.']); }
            exit;
        }

        // VERIFY REVIEW
        if ($ajax_action === 'verify_review') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $r_stmt = $pdo->prepare("SELECT product_name FROM `customer_reviews` WHERE id = ?");
                $r_stmt->execute([$id]);
                $rev = $r_stmt->fetch();
                if ($rev) {
                    $stmt = $pdo->prepare("UPDATE `customer_reviews` SET `is_verified` = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Recalculate product rating
                    $p_name = $rev['product_name'];
                    $pdo->exec("UPDATE `products` SET 
                        `rating` = COALESCE((SELECT ROUND(AVG(rating), 2) FROM `customer_reviews` WHERE `product_name` = '{$p_name}'), 5.0),
                        `review_count` = (SELECT COUNT(*) FROM `customer_reviews` WHERE `product_name` = '{$p_name}')
                        WHERE `name` = '{$p_name}'
                    ");
                    echo json_encode(['status'=>'success','message'=>'Review marked as verified.']);
                } else { echo json_encode(['status'=>'error','message'=>'Review not found.']); }
            } else { echo json_encode(['status'=>'error','message'=>'Invalid ID.']); }
            exit;
        }

        // DELETE REVIEW
        if ($ajax_action === 'delete_review') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $r_stmt = $pdo->prepare("SELECT product_name FROM `customer_reviews` WHERE id = ?");
                $r_stmt->execute([$id]);
                $rev = $r_stmt->fetch();
                if ($rev) {
                    $stmt = $pdo->prepare("DELETE FROM `customer_reviews` WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Recalculate product rating
                    $p_name = $rev['product_name'];
                    $pdo->exec("UPDATE `products` SET 
                        `rating` = COALESCE((SELECT ROUND(AVG(rating), 2) FROM `customer_reviews` WHERE `product_name` = '{$p_name}'), 5.0),
                        `review_count` = (SELECT COUNT(*) FROM `customer_reviews` WHERE `product_name` = '{$p_name}')
                        WHERE `name` = '{$p_name}'
                    ");
                    echo json_encode(['status'=>'success','message'=>'Review removed successfully.']);
                } else { echo json_encode(['status'=>'error','message'=>'Review not found.']); }
            } else { echo json_encode(['status'=>'error','message'=>'Invalid ID.']); }
            exit;
        }

        // ADD EVENT
        if ($ajax_action === 'add_event') {
            $title       = trim($_POST['title'] ?? '');
            $badge_text  = trim($_POST['badge_text'] ?? '');
            $badge_class = trim($_POST['badge_class'] ?? 'bg-info text-dark');
            $description = trim($_POST['description'] ?? '');
            $event_date  = trim($_POST['event_date'] ?? '');
            $image_path  = trim($_POST['image_path'] ?? '');

            // Handle optional image file upload
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['image_file']['tmp_name'];
                $fileName = $_FILES['image_file']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    $uploadFileDir = __DIR__ . '/images/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $dest_path = $uploadFileDir . $newFileName;
                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        $image_path = 'images/' . $newFileName;
                    }
                }
            }

            if (empty($image_path)) {
                $image_path = 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?q=80&w=600';
            }

            if ($title && $description && $event_date) {
                $ins = $pdo->prepare("INSERT INTO `events` (`title`, `badge_text`, `badge_class`, `description`, `event_date`, `image_path`) VALUES (?, ?, ?, ?, ?, ?)");
                if ($ins->execute([$title, $badge_text, $badge_class, $description, $event_date, $image_path])) {
                    echo json_encode(['status'=>'success','message'=>'Event "'.$title.'" added successfully.']);
                } else {
                    echo json_encode(['status'=>'error','message'=>'Could not add event.']);
                }
            } else {
                echo json_encode(['status'=>'error','message'=>'Title, Date, and Description are required.']);
            }
            exit;
        }

        // DELETE EVENT
        if ($ajax_action === 'delete_event') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM `events` WHERE id = ?");
                if ($stmt->execute([$id])) {
                    echo json_encode(['status'=>'success','message'=>'Event deleted successfully.']);
                } else {
                    echo json_encode(['status'=>'error','message'=>'Could not delete event.']);
                }
            } else {
                echo json_encode(['status'=>'error','message'=>'Invalid ID.']);
            }
            exit;
        }

        echo json_encode(['status'=>'error','message'=>'Unknown action.']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ─── Fetch all data for Dashboard ───────────────────────────────────────────
$db_connected = false;
$inquiries = $recent_inquiries = $top_countries = $top_companies = $monthly_data = [];
$db_reviews = $customers = $products_list = $purchases = $db_events = $db_registrations = [];
$total_inquiries = $total_customers = $total_products = $total_demo_requests = 0;
$demo_revenue_pending = 0.00;
$total_revenue_received = 0.00;
$total_customer_revenue = 0.00;
$total_inquiry_revenue = 0.00;
$active_pipeline_value = 0.00;

$sort_col = 'created_at'; $sort_order = 'DESC';
$valid_sorts = ['date'=>'created_at','country'=>'country','company'=>'company_name','name'=>'full_name','product'=>'product_name','type'=>'request_type','payment'=>'payment_status','status'=>'deal_status','value'=>'deal_value','received'=>'total_received'];
$valid_orders = ['asc'=>'ASC','desc'=>'DESC'];
if (isset($_GET['sort']) && array_key_exists($_GET['sort'],$valid_sorts)) $sort_col = $valid_sorts[$_GET['sort']];
if (isset($_GET['order']) && array_key_exists($_GET['order'],$valid_orders)) $sort_order = $valid_orders[$_GET['order']];
$currentSortKey   = $_GET['sort']  ?? 'date';
$currentOrderKey  = $_GET['order'] ?? 'desc';

try {
    $pdo = getDbConnection();
    $db_connected = true;

    // Inquiries
    $stmt = $pdo->query("SELECT * FROM `customer_inquiries` ORDER BY $sort_col $sort_order");
    $inquiries = $stmt->fetchAll();
    $total_inquiries = count($inquiries);

    // Demo stats
    $dr = $pdo->query("SELECT COUNT(*) as c, SUM(deposit_amount) as s FROM `customer_inquiries` WHERE request_type='Demo'")->fetch();
    if ($dr) { 
        $total_demo_requests = (int)$dr['c']; 
        $demo_revenue_pending = (float)$dr['s']; 
    }

    // Revenue and Active Pipeline stats
    $rev_stmt = $pdo->query("SELECT SUM(total_received) as tr, SUM(deal_value) as dv FROM `customer_inquiries` WHERE `deal_status` != 'Closed Lost'")->fetch();
    if ($rev_stmt) {
        $total_revenue_received = (float)($rev_stmt['tr'] ?? 0.00);
        $active_pipeline_value = (float)($rev_stmt['dv'] ?? 0.00);
    }
    
    // Split revenues: customer amounts vs inquiry totals
    $total_customer_revenue = (float)$pdo->query("SELECT SUM(amount) FROM `customers`")->fetchColumn();
    $total_inquiry_revenue = (float)$pdo->query("SELECT SUM(total_received) FROM `customer_inquiries`")->fetchColumn();

    // Recent 5 inquiries
    $recent_inquiries = $pdo->query("SELECT full_name,company_name,country,request_type,product_name,created_at FROM `customer_inquiries` ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Customers (with sequential ID & purchase names)
    $cr = $pdo->query("SELECT c.*, (SELECT group_concat(product_name, ', ') FROM customer_purchases WHERE customer_id = c.id) as purchases FROM `customers` c ORDER BY c.id ASC");
    $customers = $cr->fetchAll();
    $total_customers = count($customers);

    // Products list
    $products_list = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM customer_purchases WHERE product_name=p.name) as sold_count FROM `products` p ORDER BY p.id ASC")->fetchAll();
    $total_products = count($products_list);

    // Reviews
    $db_reviews = $pdo->query("SELECT * FROM `customer_reviews` ORDER BY id DESC")->fetchAll();

    // Events
    $db_events = $pdo->query("SELECT * FROM `events` ORDER BY id DESC")->fetchAll();

    // Registrations
    $db_registrations = $pdo->query("SELECT * FROM `event_registrations` ORDER BY id DESC")->fetchAll();

    // Top countries
    $top_countries = $pdo->query("SELECT country, COUNT(*) as count FROM `customer_inquiries` WHERE country!='' GROUP BY country ORDER BY count DESC LIMIT 5")->fetchAll();

    // Top companies
    $top_companies = $pdo->query("SELECT company_name, COUNT(*) as count FROM `customer_inquiries` WHERE company_name!='' GROUP BY company_name ORDER BY count DESC LIMIT 5")->fetchAll();

    // Monthly data (SQLite compatible queries)
    $monthly_raw = $pdo->query("SELECT strftime('%Y-%m', created_at) as month_val, COUNT(*) as count FROM `customer_inquiries` WHERE created_at >= date('now', '-6 month') GROUP BY month_val ORDER BY month_val ASC")->fetchAll();
    foreach ($monthly_raw as $m) {
        $dateObj = DateTime::createFromFormat('!Y-m', $m['month_val']);
        $month_formatted = $dateObj ? $dateObj->format('M Y') : $m['month_val'];
        $monthly_data[] = [
            'month' => $month_formatted,
            'count' => (int)$m['count']
        ];
    }

    // Product sales ranking
    $product_sales = $pdo->query("SELECT product_name, COUNT(*) as sold FROM `customer_purchases` GROUP BY product_name ORDER BY sold DESC")->fetchAll();

    // Revenue by Product query
    $revenue_by_product = $pdo->query("SELECT product_name, SUM(total_received) as revenue FROM `customer_inquiries` WHERE product_name != '' GROUP BY product_name ORDER BY revenue DESC")->fetchAll();

} catch (Exception $e) {
    $db_connected = false;
    error_log("Dashboard connection/query failed: " . $e->getMessage());
}

function sortLink(string $col, string $label, string $cur, string $curOrder) {
    $no = ($cur===$col && $curOrder==='desc') ? 'asc' : 'desc';
    $arrow = $cur===$col ? ($curOrder==='asc'?' ▲':' ▼') : ' ↕';
    return "<a href='admin-dashboard.php?sort={$col}&order={$no}' class='sort-link'>{$label}{$arrow}</a>";
}

$avg_rating = count($db_reviews) > 0 ? round(array_sum(array_column($db_reviews,'rating'))/count($db_reviews),1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AI-Solution</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --pd: #070b19; --sd: #0f162d; --sb: #090d1f;
            --ac: #00f0ff; --ap: #8257e5; --ag: #10b981; --ao: #f59e0b; --ar: #ef4444;
            --tl: #f4f6fd; --tm: #798396; --bd: rgba(255,255,255,0.07);
            --cb: rgba(15,22,45,0.65);
        }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Plus Jakarta Sans',sans-serif; 
            background: radial-gradient(circle at 10% 20%, rgba(130, 87, 229, 0.07) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(0, 240, 255, 0.05) 0%, transparent 40%),
                        var(--pd); 
            color: var(--tl); 
            height: 100vh; 
            overflow: hidden;
            display: flex; 
        }

        /* === SIDEBAR === */
        .sidebar { 
            width:260px; 
            height:100vh; 
            background: linear-gradient(180deg, #090d1f 0%, #050714 100%); 
            border-right: 1px solid rgba(255,255,255,0.08); 
            display:flex; 
            flex-direction:column; 
            position:fixed; 
            top:0; 
            left:0; 
            z-index:200; 
        }
        .sb-brand { padding:1.5rem; border-bottom:1px solid var(--bd); display:flex; align-items:center; gap:10px; }
        .sb-icon { width:36px; height:36px; background:linear-gradient(135deg,rgba(0,240,255,.12),rgba(130,87,229,.12)); border:1px solid rgba(0,240,255,.2); border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--ac); }
        .sb-nav { flex:1; padding:.5rem 0; overflow-y:auto; }
        .sb-label { padding:1.2rem 1.5rem .5rem; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:var(--tm); }
        .nav-btn { display:flex; align-items:center; gap:12px; padding:11px 1.5rem; color:rgba(255,255,255,.5); text-decoration:none; font-size:.88rem; font-weight:500; transition:all .2s; border-left:3px solid transparent; cursor:pointer; background:none; border-top:none; border-right:none; border-bottom:none; width:100%; text-align:left; }
        .nav-btn .ni { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.82rem; background:rgba(255,255,255,.04); flex-shrink:0; transition:all .2s; }
        .nav-btn:hover { color:#fff; background:rgba(255,255,255,.04); border-left-color:rgba(0,240,255,.3); }
        .nav-btn:hover .ni { background:rgba(0,240,255,.1); color:var(--ac); }
        .nav-btn.active { color:var(--ac); background:rgba(0,240,255,.06); border-left-color:var(--ac); }
        .nav-btn.active .ni { background:rgba(0,240,255,.12); color:var(--ac); }
        .nav-badge { margin-left:auto; background:rgba(0,240,255,.1); color:var(--ac); font-size:.68rem; padding:2px 8px; border-radius:20px; font-weight:700; }
        .sb-footer { padding:1rem 1.5rem; border-top:1px solid var(--bd); }
        .u-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--ac),var(--ap)); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.9rem; color:#070b19; flex-shrink:0; }
        .logout-btn { display:flex; align-items:center; gap:8px; color:rgba(239,68,68,.7); text-decoration:none; font-size:.83rem; font-weight:500; transition:color .2s; padding:6px 0; margin-top:.8rem; }
        .logout-btn:hover { color:#ef4444; }

        /* === MAIN === */
        .main { margin-left:260px; flex:1; height:100vh; overflow-y:auto; display:flex; flex-direction:column; }
        .topbar { 
            background: rgba(9, 13, 31, 0.85); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px); 
            border-bottom: 1px solid rgba(255, 255, 255, 0.08); 
            padding: 1.2rem 2rem; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            position: sticky; 
            top: 0; 
            z-index: 100; 
        }
        .page-title { font-family:'Outfit',sans-serif; font-weight:700; font-size:1.2rem; color:#fff; }
        .page-sub { font-size:.78rem; color:var(--tm); margin-top:1px; }
        .content { padding:2rem; flex:1; }
        .tab-panel { display:none; } .tab-panel.active { display:block; }

        /* === BUTTONS === */
        .btn-d { padding:8px 16px; border-radius:8px; font-size:.82rem; font-weight:600; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .2s; font-family:'Plus Jakarta Sans',sans-serif; }
        .btn-p { background:linear-gradient(135deg,var(--ac),#00b4d8); color:#070b19; }
        .btn-p:hover { opacity:.9; transform:translateY(-1px); }
        .btn-o { background:transparent; color:rgba(255,255,255,.6); border:1px solid rgba(255,255,255,.12); }
        .btn-o:hover { background:rgba(255,255,255,.06); color:#fff; }
        .btn-danger { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.2); }
        .btn-danger:hover { background:rgba(239,68,68,.2); color:#ef4444; }
        .btn-green { background:rgba(16,185,129,.12); color:#10b981; border:1px solid rgba(16,185,129,.2); }
        .btn-green:hover { background:rgba(16,185,129,.2); }

        /* === STAT CARDS === */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1.2rem; margin-bottom:2rem; }
        @media(max-width:1200px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
        .stat-card { 
            background: var(--cb); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            border-radius: 16px; 
            padding: 1.5rem; 
            position: relative; 
            overflow: hidden; 
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        .stat-card::before { content:''; position:absolute; top:0;right:0; width:80px;height:80px; border-radius:50%; opacity:.06; transform:translate(20px,-20px); }
        .stat-card:nth-child(1)::before{background:var(--ac);} .stat-card:nth-child(2)::before{background:var(--ap);} .stat-card:nth-child(3)::before{background:var(--ag);} .stat-card:nth-child(4)::before{background:var(--ao);}
        .stat-card:hover { 
            transform: translateY(-4px); 
            border-color: var(--ac); 
            box-shadow: 0 12px 30px rgba(0, 240, 255, 0.15);
        }
        .stat-icon { width:42px;height:42px; border-radius:10px; display:flex;align-items:center;justify-content:center; font-size:1rem; margin-bottom:1rem; }
        .stat-label { font-size:.75rem;color:var(--tm);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-bottom:.4rem; }
        .stat-value { font-family:'Outfit',sans-serif;font-size:2.2rem;font-weight:800;color:#fff;line-height:1; }
        .stat-change { font-size:.75rem;color:var(--ag);margin-top:.4rem; }

        /* === CARDS === */
        .dash-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
        @media(max-width:992px){.dash-grid{grid-template-columns:1fr;}}
        .card { 
            background: var(--cb); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .card:hover {
            border-color: rgba(0, 240, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 240, 255, 0.08);
        }
        .card-header { padding:1.2rem 1.5rem; border-bottom:1px solid var(--bd); display:flex; align-items:center; justify-content:space-between; }
        .card-title { font-family:'Outfit',sans-serif; font-weight:700; font-size:.92rem; color:#fff; display:flex; align-items:center; gap:8px; }
        .card-title i { color:var(--ac); }
        .card-body { padding:1.5rem; }

        /* === TABLE === */
        .tbl-wrap { 
            background: rgba(15, 22, 45, 0.45); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.07); 
            border-radius: 16px; 
            overflow-x: auto; 
            overflow-y: auto;
            max-height: 65vh;
        }
        .tbl { width:100%; border-collapse:collapse; color:var(--tl); }
        .tbl th { 
            background: rgba(255, 255, 255, 0.03); 
            padding: 14px 16px; 
            text-align: left; 
            font-size: 0.72rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: var(--tm); 
            border-bottom: 1px solid rgba(255, 255, 255, 0.08); 
            white-space: nowrap; 
        }
        .tbl td { 
            padding: 14px 16px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.04); 
            font-size: 0.86rem; 
            vertical-align: middle; 
        }
        .tbl tr:last-child td { border-bottom:none; }
        .tbl tr:hover td { background:rgba(255,255,255,.03); }
        .sort-link { color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:color .2s; }
        .sort-link:hover { color:var(--ac); }
        .no-data { text-align:center; padding:3.5rem; color:var(--tm); }
        .no-data i { font-size:2rem; display:block; margin-bottom:1rem; opacity:.4; }

        /* === BADGES === */
        .badge-demo { background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.3);border-radius:20px;padding:3px 10px;font-size:.7rem;font-weight:700;white-space:nowrap; }
        .badge-inquiry { background:rgba(0,240,255,.1);color:var(--ac);border:1px solid rgba(0,240,255,.2);border-radius:20px;padding:3px 10px;font-size:.7rem;font-weight:700;white-space:nowrap; }
        .badge-paid { background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25);border-radius:20px;padding:3px 10px;font-size:.7rem;font-weight:700; }
        .badge-pending { background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.2);border-radius:20px;padding:3px 10px;font-size:.7rem;font-weight:700; }
        .badge-free { background:rgba(255,255,255,.06);color:var(--tm);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:3px 10px;font-size:.7rem;font-weight:700; }
        .badge-verified { background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25);border-radius:12px;padding:3px 10px;font-size:.68rem;font-weight:700;display:inline-flex;align-items:center;gap:4px; }
        .badge-unverified { background:rgba(255,255,255,.05);color:var(--tm);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:3px 10px;font-size:.68rem;font-weight:700; }
        .cid-badge { background:linear-gradient(135deg,rgba(0,240,255,.1),rgba(130,87,229,.1));border:1px solid rgba(0,240,255,.2);color:var(--ac);border-radius:8px;padding:4px 10px;font-size:.8rem;font-weight:700;font-family:'Outfit',sans-serif; }

        /* === TOOLBAR === */
        .toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.2rem; flex-wrap:wrap; gap:.75rem; }
        .search-box { position:relative; flex:1; max-width:300px; }
        .search-box input { background:var(--cb); border:1px solid var(--bd); color:#fff; padding:9px 12px 9px 36px; border-radius:10px; font-size:.86rem; width:100%; transition:border-color .2s; font-family:'Plus Jakarta Sans',sans-serif; }
        .search-box input:focus { outline:none; border-color:var(--ac); }
        .search-box input::placeholder { color:rgba(255,255,255,.3); }
        .search-box i { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--tm);font-size:.82rem; }

        /* === FORM INPUTS === */
        .form-dash { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1); color:#fff; padding:10px 14px; border-radius:10px; font-size:.88rem; font-family:'Plus Jakarta Sans',sans-serif; transition:border-color .2s; width:100%; }
        .form-dash:focus { outline:none; border-color:var(--ac); background:rgba(255,255,255,.06); color:#fff; }
        .form-dash option { color: #000000 !important; background-color: #ffffff !important; }
        .form-dash::placeholder { color:rgba(255,255,255,.3); }
        label.fl { color:rgba(255,255,255,.5); font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.7px; display:block; margin-bottom:.4rem; }

        /* === ACTIVITY === */
        .act-item { display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid var(--bd); }
        .act-item:last-child { border-bottom:none; }
        .act-dot { width:8px;height:8px;border-radius:50%;background:var(--ac);margin-top:6px;flex-shrink:0;box-shadow:0 0 6px rgba(0,240,255,.4); }
        .act-dot.demo { background:var(--ao); box-shadow:0 0 6px rgba(245,158,11,.4); }

        /* === COUNTRY BAR === */
        .cbar-wrap { flex:1; height:6px; background:rgba(255,255,255,.06); border-radius:3px; overflow:hidden; }
        .cbar { height:100%; border-radius:3px; background:linear-gradient(90deg,var(--ac),var(--ap)); }

        /* === CHART === */
        .chart-area { display:flex; align-items:flex-end; gap:8px; height:160px; padding-bottom:1rem; }
        .chart-col { flex:1; display:flex; flex-direction:column; align-items:center; height:100%; justify-content:flex-end; }
        .chart-bar { width:100%; background:linear-gradient(to top,var(--ac),var(--ap)); border-radius:6px 6px 0 0; min-height:4px; transition:all .5s; opacity:.8; }
        .chart-bar:hover { opacity:1; }
        .chart-lbl { font-size:.66rem; color:var(--tm); margin-top:5px; text-align:center; }
        .chart-num { font-size:.66rem; color:var(--ac); margin-bottom:3px; font-weight:700; }

        /* === DB BANNER === */
        .db-banner { background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:12px 16px;color:#fbbf24;font-size:.85rem;display:flex;align-items:center;gap:10px;margin-bottom:1.5rem; }

        /* === PRODUCT CARD (admin) === */
        .prod-admin-card { background:rgba(255,255,255,.02); border:1px solid var(--bd); border-radius:14px; padding:1.2rem; display:flex; align-items:center; gap:14px; transition:border-color .2s; }
        .prod-admin-card:hover { border-color:rgba(0,240,255,.2); }
        .prod-thumb { width:64px; height:48px; border-radius:8px; object-fit:cover; background:var(--pd); flex-shrink:0; }

        /* === TOAST === */
        .adm-toast { position:fixed; bottom:2rem; right:2rem; z-index:9999; background:rgba(15,22,45,.95); border:1px solid rgba(0,240,255,.25); border-radius:14px; padding:1rem 1.4rem; color:#fff; display:flex; align-items:center; gap:12px; font-size:.88rem; box-shadow:0 12px 40px rgba(0,0,0,.5); transform:translateY(120px); transition:transform .4s cubic-bezier(.16,1,.3,1); max-width:350px; }
        .adm-toast.show { transform:translateY(0); }
        .adm-toast.error { border-color:rgba(239,68,68,.3); }
        .adm-toast .ti { font-size:1.1rem; }
        .adm-toast .ti.ok { color:var(--ag); } .adm-toast .ti.err { color:#ef4444; }

        /* === MODAL === */
        .adm-modal-bg { position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:5000; display:flex; align-items:center; justify-content:center; padding:1rem; display:none; }
        .adm-modal-bg.show { display:flex; }
        .adm-modal { background:var(--sd); border:1px solid var(--bd); border-radius:20px; width:100%; max-width:520px; max-height:80vh; overflow-y:auto; box-shadow:0 30px 80px rgba(0,0,0,.6); }
        .adm-modal-lg { max-width:900px; width:95%; max-height:90vh; }
        .chat-thread { background: rgba(0, 0, 0, 0.25); border: 1px solid var(--bd); border-radius: 12px; padding: 1rem; height: 260px; overflow-y: auto; margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.8rem; }
        .chat-thread-msg { margin-bottom: 0; padding: 8px 12px; border-radius: 10px; font-size: 0.85rem; max-width: 85%; word-break: break-word; line-height: 1.4; }
        .chat-thread-msg.customer { background: rgba(0, 240, 255, 0.1); color: #fff; border-left: 3px solid var(--ac); align-self: flex-start; }
        .chat-thread-msg.admin { background: rgba(130, 87, 229, 0.1); color: #fff; border-left: 3px solid var(--ap); align-self: flex-end; margin-left: auto; }
        .chat-thread-msg.system { background: rgba(245, 158, 11, 0.1); color: var(--ao); border-left: 3px solid var(--ao); font-style: italic; align-self: center; margin: 0.5rem auto; text-align: center; max-width: 95%; }
        
        .badge-deal { font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; text-transform: uppercase; display: inline-block; }
        .badge-deal-new-lead { background: rgba(0, 240, 255, 0.12); border: 1px solid rgba(0, 240, 255, 0.25); color: var(--ac); }
        .badge-deal-paid-demo { background: rgba(130, 87, 229, 0.12); border: 1px solid rgba(130, 87, 229, 0.25); color: var(--ap); }
        .badge-deal-demo-active { background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.25); color: var(--ao); }
        .badge-deal-proposal-sent { background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.25); color: #60a5fa; }
        .badge-deal-sold { background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.25); color: var(--ag); }
        .badge-deal-closed-lost { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); color: #f87171; }
        .badge-deal-ongoing { background: rgba(130, 87, 229, 0.12); border: 1px solid rgba(130, 87, 229, 0.25); color: var(--ap); }
        .badge-deal-cancelled { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); color: #f87171; }
        
        .adm-modal-hd { padding:1.4rem 1.6rem; border-bottom:1px solid var(--bd); display:flex; align-items:center; justify-content:space-between; }
        .adm-modal-hd h5 { font-family:'Outfit',sans-serif; font-weight:700; color:#fff; margin:0; }
        .adm-modal-bd { padding:1.6rem; }
        .adm-modal-ft { padding:1rem 1.6rem; border-top:1px solid var(--bd); display:flex; gap:.75rem; justify-content:flex-end; }
        .modal-close { background:none; border:none; color:rgba(255,255,255,.4); font-size:1.4rem; cursor:pointer; line-height:1; transition:color .2s; }
        .modal-close:hover { color:#fff; }

        /* === MISC === */
        .email-lnk { color:var(--ac); text-decoration:none; font-size:.82rem; }
        .email-lnk:hover { text-decoration:underline; }
        .inq-preview { max-width:180px; color:rgba(255,255,255,.45); font-size:.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .review-img { width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--ac); }
        .star-row { color:#ffb800; font-size:.85rem; }

        /* Print */
        @media print { .sidebar,.topbar,.toolbar,.adm-toast,.adm-modal-bg { display:none!important; } .main{margin-left:0;} }

        /* Mobile */
        @media(max-width:768px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .content{padding:1rem;} .stats-grid{grid-template-columns:1fr 1fr;} }

        /* High contrast */
        body.hc { background:#000!important; --cb:#111; --bd:#fff; --sb:#000; --tm:#fff; --ac:#ffff00; }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </div>
        <div>
            <div style="font-family:'Outfit',sans-serif;font-weight:700;font-size:1rem;color:#fff;">AI-Solution</div>
            <div style="font-size:.68rem;color:var(--tm);">Admin Portal</div>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="sb-label">Main Menu</div>
        <button class="nav-btn active" onclick="switchTab('overview',this)" id="tab-overview">
            <div class="ni"><i class="fas fa-th-large"></i></div> Dashboard
        </button>
        <button class="nav-btn" onclick="switchTab('inquiries',this)" id="tab-inquiries">
            <div class="ni"><i class="fas fa-inbox"></i></div> Inquiries
            <span class="nav-badge"><?php echo $total_inquiries; ?></span>
        </button>
        <button class="nav-btn" onclick="switchTab('customers',this)" id="tab-customers">
            <div class="ni"><i class="fas fa-users"></i></div> Customers
            <span class="nav-badge"><?php echo $total_customers; ?></span>
        </button>
        <button class="nav-btn" onclick="switchTab('products',this)" id="tab-products">
            <div class="ni"><i class="fas fa-box-open"></i></div> Products
            <span class="nav-badge"><?php echo $total_products; ?></span>
        </button>
        <button class="nav-btn" onclick="switchTab('reviews',this)" id="tab-reviews">
            <div class="ni"><i class="fas fa-star"></i></div> Reviews
            <span class="nav-badge"><?php echo count($db_reviews); ?></span>
        </button>
        <button class="nav-btn" onclick="switchTab('events',this)" id="tab-events">
            <div class="ni"><i class="far fa-calendar-alt"></i></div> Events
            <span class="nav-badge"><?php echo count($db_events); ?></span>
        </button>
        <button class="nav-btn" onclick="switchTab('registrations',this)" id="tab-registrations">
            <div class="ni"><i class="fas fa-id-badge"></i></div> Registrations
            <span class="nav-badge"><?php echo count($db_registrations); ?></span>
        </button>
        <button class="nav-btn" onclick="switchTab('analytics',this)" id="tab-analytics">
            <div class="ni"><i class="fas fa-chart-bar"></i></div> Analytics
        </button>
        <div class="sb-label">System</div>
        <a class="nav-btn" href="index.html" target="_blank">
            <div class="ni"><i class="fas fa-globe"></i></div> View Website
        </a>
        <a class="nav-btn" href="products.html" target="_blank">
            <div class="ni"><i class="fas fa-shopping-bag"></i></div> View Products
        </a>
        <button class="nav-btn" onclick="window.print()">
            <div class="ni"><i class="fas fa-print"></i></div> Print Report
        </button>
    </nav>

    <div class="sb-footer">
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="u-avatar"><?php echo strtoupper(substr($_SESSION['admin_user'],0,1)); ?></div>
            <div>
                <div style="font-size:.88rem;font-weight:600;color:#fff;"><?php echo htmlspecialchars($_SESSION['admin_user']); ?></div>
                <div style="font-size:.72rem;color:var(--tm);">Administrator</div>
            </div>
        </div>
        <a href="admin-dashboard.php?action=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
</aside>

<!-- ===== MAIN CONTENT ===== -->
<div class="main">

    <div class="topbar">
        <div>
            <div class="page-title" id="topbarTitle">Dashboard Overview</div>
            <div class="page-sub"><?php echo date("l, F j, Y"); ?></div>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;">
            <button class="btn-d btn-o" id="themeToggle"><i class="fas fa-adjust"></i> Contrast</button>
            <button class="btn-d btn-p" onclick="window.print()"><i class="fas fa-download"></i> Export</button>
        </div>
    </div>

    <div class="content">

    <?php if (!$db_connected): ?>
    <div class="db-banner"><i class="fas fa-exclamation-triangle"></i> <span><strong>Database Offline.</strong> Unable to connect to SQLite database. Ensure that the project folder and the database file (`ai_solution_db.sqlite`) are writable by Apache.</span></div>
    <?php endif; ?>

    <!-- ===== TAB: OVERVIEW ===== -->
    <div class="tab-panel active" id="panel-overview">
        <div class="stats-grid" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(0,240,255,.1);color:var(--ac);"><i class="fas fa-inbox"></i></div>
                <div class="stat-label">Total Inquiries</div>
                <div class="stat-value"><?php echo $total_inquiries; ?></div>
                <div class="stat-change"><i class="fas fa-arrow-up me-1"></i>All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(130,87,229,.1);color:var(--ap);"><i class="fas fa-users"></i></div>
                <div class="stat-label">Total Clients</div>
                <div class="stat-value"><?php echo $total_customers; ?></div>
                <div class="stat-change" style="color:var(--ap);">Registered accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--ao);"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-label">Demo Requests</div>
                <div class="stat-value"><?php echo $total_demo_requests; ?></div>
                <div class="stat-change" style="color:var(--ao);">Pending activation</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--ag);"><i class="fas fa-wallet"></i></div>
                <div class="stat-label">Customer Revenue</div>
                <div class="stat-value">£<?php echo number_format($total_customer_revenue, 2); ?></div>
                <div class="stat-change" style="color:var(--ag);">Manual client amounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(52,211,153,.1);color:#34d399;"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="stat-label">Inquiry Revenue</div>
                <div class="stat-value">£<?php echo number_format($total_inquiry_revenue, 2); ?></div>
                <div class="stat-change" style="color:#34d399;">Inquiry payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(96,165,250,.1);color:#60a5fa;"><i class="fas fa-chart-line"></i></div>
                <div class="stat-label">Active Pipeline</div>
                <div class="stat-value">£<?php echo number_format($active_pipeline_value, 2); ?></div>
                <div class="stat-change" style="color:#60a5fa;">Active contract value</div>
            </div>
        </div>

        <div class="dash-grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-bell"></i> Recent Requests</div>
                    <button class="btn-d btn-o" style="font-size:.76rem;padding:5px 12px;" onclick="switchTab('inquiries',document.getElementById('tab-inquiries'))">View All</button>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_inquiries)): ?>
                    <div class="no-data"><i class="fas fa-inbox"></i>No inquiries yet.</div>
                    <?php else: foreach($recent_inquiries as $ri): ?>
                    <div class="act-item">
                        <div class="act-dot <?php echo $ri['request_type']==='Demo'?'demo':''; ?>"></div>
                        <div style="flex:1;">
                            <div style="font-size:.88rem;font-weight:600;color:#fff;"><?php echo htmlspecialchars($ri['full_name']); ?></div>
                            <div style="font-size:.76rem;color:var(--tm);">
                                <?php echo htmlspecialchars($ri['company_name'] ?? ''); ?> ·
                                <span class="<?php echo $ri['request_type']==='Demo'?'badge-demo':'badge-inquiry'; ?>" style="margin-left:4px;"><?php echo htmlspecialchars($ri['request_type']); ?></span>
                            </div>
                        </div>
                        <div style="font-size:.72rem;color:var(--tm);"><?php echo date("M d",strtotime($ri['created_at'])); ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> Monthly Inquiries</div>
                    <span style="font-size:.74rem;color:var(--tm);">Last 6 months</span>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_data)): ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:160px;color:var(--tm);font-size:.85rem;"><i class="fas fa-chart-bar me-2"></i>No data yet.</div>
                    <?php else: $maxC=max(array_column($monthly_data,'count')); ?>
                    <div class="chart-area">
                        <?php foreach($monthly_data as $m): $pct=$maxC>0?round(($m['count']/$maxC)*100):0; ?>
                        <div class="chart-col" title="<?php echo $m['month'].': '.$m['count']; ?>">
                            <div class="chart-num"><?php echo $m['count']; ?></div>
                            <div class="chart-bar" style="height:<?php echo $pct; ?>%;"></div>
                            <div class="chart-lbl"><?php echo explode(' ',$m['month'])[0]; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-globe-europe"></i> Top Countries</div></div>
                <div class="card-body">
                    <?php if (empty($top_countries)): ?>
                    <div class="no-data"><i class="fas fa-globe"></i>No data yet.</div>
                    <?php else: $maxC=max(array_column($top_countries,'count')); foreach($top_countries as $tc): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                        <div style="font-size:.84rem;color:rgba(255,255,255,.7);min-width:120px;"><?php echo htmlspecialchars($tc['country']); ?></div>
                        <div class="cbar-wrap"><div class="cbar" style="width:<?php echo $maxC>0?round(($tc['count']/$maxC)*100):0; ?>%;"></div></div>
                        <div style="font-size:.78rem;color:var(--tm);min-width:20px;text-align:right;"><?php echo $tc['count']; ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-building"></i> Top Organizations</div></div>
                <div class="card-body">
                    <?php if (empty($top_companies)): ?>
                    <div class="no-data"><i class="fas fa-building"></i>No data yet.</div>
                    <?php else: foreach($top_companies as $idx=>$tco): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--bd);">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:26px;height:26px;border-radius:6px;background:rgba(130,87,229,.1);border:1px solid rgba(130,87,229,.2);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--ap);"><?php echo $idx+1; ?></div>
                            <div style="font-size:.86rem;color:rgba(255,255,255,.8);"><?php echo htmlspecialchars($tco['company_name']); ?></div>
                        </div>
                        <span style="background:rgba(0,240,255,.1);border:1px solid rgba(0,240,255,.2);color:var(--ac);border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;"><?php echo $tco['count']; ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div><!-- /overview -->

    <!-- ===== TAB: INQUIRIES ===== -->
    <div class="tab-panel" id="panel-inquiries">
        <div class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="inqSearch" placeholder="Search name, email, product..." oninput="filterTbl('inqBody',this.value)">
            </div>
            <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
                <span class="btn-d btn-o" style="cursor:default;"><?php echo $total_inquiries; ?> Records</span>
                <button class="btn-d btn-o" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="tbl-wrap">
            <table class="tbl" id="inqTable">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th><?php echo sortLink('name','Name',$currentSortKey,$currentOrderKey); ?></th>
                        <th>Email</th>
                        <th><?php echo sortLink('company','Company',$currentSortKey,$currentOrderKey); ?></th>
                        <th><?php echo sortLink('product','Product',$currentSortKey,$currentOrderKey); ?></th>
                        <th><?php echo sortLink('type','Type',$currentSortKey,$currentOrderKey); ?></th>
                        <th>Package</th>
                        <th><?php echo sortLink('status','Deal Status',$currentSortKey,$currentOrderKey); ?></th>
                        <th><?php echo sortLink('value','Deal Value',$currentSortKey,$currentOrderKey); ?></th>
                        <th><?php echo sortLink('received','Total Recv',$currentSortKey,$currentOrderKey); ?></th>
                        <th>Wishes</th>
                        <th><?php echo sortLink('date','Date',$currentSortKey,$currentOrderKey); ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inqBody">
                    <?php if (empty($inquiries)): ?>
                    <tr><td colspan="13"><div class="no-data"><i class="fas fa-inbox"></i>No inquiries yet. Submissions from the Products page appear here.</div></td></tr>
                    <?php else: foreach($inquiries as $inq): ?>
                    <tr>
                        <td><span class="cid-badge">#<?php echo $inq['id']; ?></span></td>
                        <td style="font-weight:600;color:#fff;white-space:nowrap;"><?php echo htmlspecialchars($inq['full_name']); ?></td>
                        <td><a href="mailto:<?php echo htmlspecialchars($inq['email_address']); ?>" class="email-lnk"><?php echo htmlspecialchars($inq['email_address']); ?></a></td>
                        <td style="color:rgba(255,255,255,.7);"><?php echo htmlspecialchars($inq['company_name'] ?? ''); ?></td>
                        <td style="color:rgba(255,255,255,.8);white-space:nowrap;"><?php echo htmlspecialchars($inq['product_name'] ?? '—'); ?></td>
                        <td><span class="<?php echo ($inq['request_type']??'Inquiry')==='Demo'?'badge-demo':'badge-inquiry'; ?>"><?php echo htmlspecialchars($inq['request_type'] ?? 'Inquiry'); ?></span></td>
                        <td style="font-size:.8rem;color:var(--tm);"><?php echo htmlspecialchars($inq['package_name'] ?? '—'); ?></td>
                        <td>
                            <?php 
                            $ds = htmlspecialchars($inq['deal_status'] ?? 'New Lead'); 
                            $dsClass = strtolower(str_replace(' ', '-', $ds));
                            ?>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge-deal badge-deal-<?php echo $dsClass; ?>"><?php echo $ds; ?></span>
                                <button class="btn-d btn-o" style="padding:2px 6px;font-size:0.7rem;" onclick="openEditDealStatus(<?php echo $inq['id']; ?>, '<?php echo htmlspecialchars($inq['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($inq['deal_status'], ENT_QUOTES); ?>')" title="Edit Deal Status"><i class="fas fa-edit"></i></button>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <strong style="color:var(--ac);">£<?php echo number_format($inq['deal_value'] ?? 0.00, 2); ?></strong>
                                <button class="btn-d btn-o" style="padding:2px 6px;font-size:0.7rem;" onclick="openEditDealValue(<?php echo $inq['id']; ?>, '<?php echo htmlspecialchars($inq['full_name'], ENT_QUOTES); ?>', <?php echo (float)($inq['deal_value'] ?? 0.00); ?>)" title="Edit Deal Value"><i class="fas fa-edit"></i></button>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span style="color:var(--ag);">£<?php echo number_format($inq['total_received'] ?? 0.00, 2); ?></span>
                                <button class="btn-d btn-o" style="padding:2px 6px;font-size:0.7rem;" onclick="openEditTotalReceived(<?php echo $inq['id']; ?>, '<?php echo htmlspecialchars($inq['full_name'], ENT_QUOTES); ?>', <?php echo (float)($inq['total_received'] ?? 0.00); ?>)" title="Edit Total Received"><i class="fas fa-edit"></i></button>
                            </div>
                        </td>
                        <td><div class="inq-preview" title="<?php echo htmlspecialchars($inq['custom_wishes']??''); ?>"><?php echo htmlspecialchars($inq['custom_wishes'] ?? ($inq['inquiry_details'] ?? '—')); ?></div></td>
                        <td style="font-size:.78rem;color:var(--tm);white-space:nowrap;"><?php echo date("M d, Y",strtotime($inq['created_at'])); ?></td>
                        <td style="white-space:nowrap;">
                            <button class="btn-d btn-p" style="font-size:.72rem;padding:5px 10px;margin-right:4px;" onclick="openDealPanel(<?php echo $inq['id']; ?>)" title="Manage Deal &amp; Conversations"><i class="fas fa-handshake"></i></button>
                            <button class="btn-d btn-danger" style="font-size:.72rem;padding:5px 10px;" onclick="deleteInquiry(<?php echo $inq['id']; ?>,this)"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /inquiries -->

    <!-- ===== TAB: CUSTOMERS ===== -->
    <div class="tab-panel" id="panel-customers">
        <div class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search customers..." oninput="filterTbl('custBody',this.value)">
            </div>
            <span class="btn-d btn-o" style="cursor:default;"><?php echo $total_customers; ?> Clients</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Client ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Registered</th>
                        <th>Amount</th>
                        <th>Purchases</th>
                        <th>Record Purchase</th>
                    </tr>
                </thead>
                <tbody id="custBody">
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="7"><div class="no-data"><i class="fas fa-users"></i>No registered clients yet.</div></td></tr>
                    <?php else: foreach($customers as $c): ?>
                    <tr>
                        <td><span class="cid-badge">#<?php echo $c['id']; ?></span></td>
                        <td style="font-weight:600;color:#fff;"><?php echo htmlspecialchars($c['full_name']); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <a href="mailto:<?php echo htmlspecialchars($c['email_address']); ?>" class="email-lnk"><?php echo htmlspecialchars($c['email_address']); ?></a>
                                <button class="btn-d btn-o" style="padding:2px 6px;font-size:0.7rem;" onclick="openCustomerEmail('<?php echo htmlspecialchars($c['email_address'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($c['full_name'], ENT_QUOTES); ?>')" title="Send Direct Email"><i class="fas fa-reply"></i></button>
                            </div>
                        </td>
                        <td style="font-size:.78rem;color:var(--tm);"><?php echo date("M d, Y",strtotime($c['created_at'])); ?></td>
                        <td style="font-weight:600;color:var(--ac);white-space:nowrap;">
                            £<?php echo number_format($c['amount'] ?? 0.00, 2); ?>
                            <button class="btn-d btn-o" style="padding:2px 6px;margin-left:6px;font-size:0.7rem;" onclick="openEditAmount(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['full_name'], ENT_QUOTES); ?>', <?php echo (float)($c['amount'] ?? 0.00); ?>)" title="Edit Amount"><i class="fas fa-edit"></i></button>
                        </td>
                        <td style="font-size:.8rem;color:rgba(255,255,255,.6);"><?php echo $c['purchases'] ? htmlspecialchars($c['purchases']) : '<span style="color:var(--tm);">None</span>'; ?></td>
                        <td>
                            <button class="btn-d btn-green" style="font-size:.76rem;padding:6px 12px;" onclick="openRecordPurchase(<?php echo $c['id']; ?>,'<?php echo htmlspecialchars($c['full_name']); ?>')">
                                <i class="fas fa-plus-circle"></i> Record Purchase
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /customers -->

    <!-- ===== TAB: PRODUCTS ===== -->
    <div class="tab-panel" id="panel-products">
        <!-- Add Product Form -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle"></i> Add New Product</div>
            </div>
            <div class="card-body">
                <form id="addProdForm" onsubmit="submitAddProduct(event)">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="fl">Product Name *</label>
                            <input type="text" class="form-dash" name="name" placeholder="e.g. SentimentIQ Pro" required>
                        </div>
                        <div class="col-md-3">
                            <label class="fl">Category</label>
                            <select class="form-dash" name="category">
                                <option value="new">New Release</option>
                                <option value="new analytics">Analytics</option>
                                <option value="new assistant">Assistant</option>
                                <option value="legacy">Legacy</option>
                                <option value="legacy analytics">Legacy Analytics</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="fl">Release Date</label>
                            <input type="text" class="form-dash" name="release_date" placeholder="e.g. July 2025">
                        </div>
                        <div class="col-12">
                            <label class="fl">Description *</label>
                            <textarea class="form-dash" name="description" rows="3" placeholder="Full product description..." required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="fl">Tags (comma separated)</label>
                            <input type="text" class="form-dash" name="tags" placeholder="AI,Automation,Enterprise">
                        </div>
                        <div class="col-md-4">
                            <label class="fl">Integration</label>
                            <input type="text" class="form-dash" name="integration" placeholder="e.g. M365, Salesforce">
                        </div>
                        <div class="col-md-4">
                            <label class="fl">Deployment</label>
                            <input type="text" class="form-dash" name="deployment" placeholder="e.g. Cloud / On-Premise">
                        </div>
                        <div class="col-md-4">
                            <label class="fl">Basic Price (£/mo)</label>
                            <input type="number" class="form-dash" name="basic_price" value="299" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="fl">Standard Price (£/mo)</label>
                            <input type="number" class="form-dash" name="standard_price" value="799" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="fl">Image URL</label>
                            <input type="text" class="form-dash" name="image_path" id="image_path" placeholder="https://... or images/product.png">
                        </div>
                        <div class="col-md-4">
                            <label class="fl">Or Upload Image File</label>
                            <input type="file" class="form-dash" name="image_file" id="image_file" accept="image/*">
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn-d btn-p" id="addProdBtn"><i class="fas fa-plus"></i> Add Product</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Existing Products List -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-box-open"></i> Product Catalog (<?php echo $total_products; ?> products)</div>
            </div>
            <div class="card-body" style="padding:1rem;">
                <?php if (empty($products_list)): ?>
                <div class="no-data"><i class="fas fa-box-open"></i>No products yet. Add one above.</div>
                <?php else: foreach($products_list as $prod): ?>
                <div class="prod-admin-card mb-3" id="prod-row-<?php echo $prod['id']; ?>">
                    <img src="<?php echo htmlspecialchars($prod['image_path']); ?>" class="prod-thumb" alt="<?php echo htmlspecialchars($prod['name']); ?>" onerror="this.src='https://via.placeholder.com/64x48/0f162d/00f0ff?text=AI'">
                    <div style="flex:1;">
                        <div style="font-weight:700;color:#fff;font-family:'Outfit',sans-serif;"><?php echo htmlspecialchars($prod['name']); ?></div>
                        <div style="font-size:.76rem;color:var(--tm);margin-top:2px;"><?php echo htmlspecialchars(substr($prod['description'],0,80)); ?>...</div>
                        <div style="display:flex;gap:.5rem;margin-top:.5rem;flex-wrap:wrap;">
                            <span style="background:rgba(0,240,255,.08);border:1px solid rgba(0,240,255,.15);color:var(--ac);border-radius:6px;padding:2px 8px;font-size:.7rem;font-weight:600;">£<?php echo number_format($prod['basic_price'],0); ?>–£<?php echo number_format($prod['standard_price'],0); ?>/mo</span>
                            <span style="background:rgba(130,87,229,.08);border:1px solid rgba(130,87,229,.15);color:var(--ap);border-radius:6px;padding:2px 8px;font-size:.7rem;font-weight:600;"><?php echo htmlspecialchars($prod['category']); ?></span>
                            <?php if($prod['sold_count']>0): ?><span class="badge-paid" style="font-size:.7rem;"><?php echo $prod['sold_count']; ?> sold</span><?php endif; ?>
                        </div>
                    </div>
                    <button class="btn-d btn-p edit-product-btn" style="flex-shrink:0;font-size:.76rem;padding:6px 12px;margin-right:8px;" data-product='<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>' onclick="openEditProduct(this)"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn-d btn-danger" style="flex-shrink:0;font-size:.76rem;padding:6px 12px;" onclick="deleteProduct(<?php echo $prod['id']; ?>,this)"><i class="fas fa-trash"></i> Remove</button>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div><!-- /products -->

    <!-- ===== TAB: REVIEWS ===== -->
    <div class="tab-panel" id="panel-reviews">
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.5rem;">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(255,184,0,.1);color:#ffb800;"><i class="fas fa-star"></i></div>
                <div class="stat-label">Avg Rating</div>
                <div class="stat-value"><?php echo $avg_rating; ?><span style="font-size:1rem;color:var(--tm);">/5</span></div>
                <div class="stat-change" style="color:#ffb800;"><?php echo str_repeat('★',(int)round($avg_rating)); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(0,240,255,.1);color:var(--ac);"><i class="fas fa-comments"></i></div>
                <div class="stat-label">Total Reviews</div>
                <div class="stat-value"><?php echo count($db_reviews); ?></div>
                <div class="stat-change">Across all products</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--ag);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">Verified Reviews</div>
                <div class="stat-value"><?php echo count(array_filter($db_reviews,fn($r)=>$r['is_verified']==1)); ?></div>
                <div class="stat-change" style="color:var(--ag);">Verified purchase tags</div>
            </div>
        </div>
        <div class="row g-3">
            <?php if (empty($db_reviews)): ?>
            <div class="col-12"><div class="no-data"><i class="fas fa-star"></i>No reviews yet.</div></div>
            <?php else: foreach($db_reviews as $rev): $isV=$rev['is_verified']==1; ?>
            <div class="col-md-6 col-lg-4">
                <div style="background:rgba(255,255,255,.02);border:1px solid var(--bd);<?php echo $isV?'border-left:4px solid var(--ag);':''; ?>border-radius:14px;padding:1.3rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="<?php echo htmlspecialchars($rev['reviewer_img']); ?>" class="review-img" alt="<?php echo htmlspecialchars($rev['reviewer_name']); ?>" onerror="this.src='https://via.placeholder.com/40/0f162d/00f0ff?text=U'">
                            <div>
                                <div style="font-weight:600;font-size:.88rem;color:#fff;"><?php echo htmlspecialchars($rev['reviewer_name']); ?></div>
                                <div style="font-size:.72rem;color:var(--tm);"><?php echo htmlspecialchars($rev['reviewer_role']); ?></div>
                            </div>
                        </div>
                        <?php if($isV): ?><span class="badge-verified"><i class="fas fa-check-circle"></i>Verified</span><?php else: ?><span class="badge-unverified">Unverified</span><?php endif; ?>
                    </div>
                    <div class="star-row mb-2"><?php echo str_repeat('★',(int)$rev['rating']); ?></div>
                    <p style="font-size:.82rem;color:rgba(255,255,255,.6);line-height:1.6;margin-bottom:.8rem;"><?php echo htmlspecialchars($rev['review_text']); ?></p>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;">
                        <span style="background:rgba(0,240,255,.08);border:1px solid rgba(0,240,255,.15);color:var(--ac);font-size:.7rem;font-weight:600;padding:2px 10px;border-radius:20px;"><?php echo htmlspecialchars($rev['product_name']); ?></span>
                        <span style="font-size:.72rem;color:var(--tm);"><?php echo htmlspecialchars($rev['review_date']); ?></span>
                    </div>
                    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                        <?php if(!$isV): ?>
                        <button class="btn-d btn-green" style="font-size:.7rem;padding:4px 10px;" onclick="verifyReview(<?php echo $rev['id']; ?>, this)"><i class="fas fa-check"></i> Verify</button>
                        <?php endif; ?>
                        <button class="btn-d btn-danger" style="font-size:.7rem;padding:4px 10px;" onclick="deleteReview(<?php echo $rev['id']; ?>, this)"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div><!-- /reviews -->

    <!-- ===== TAB: EVENTS ===== -->
    <div class="tab-panel" id="panel-events">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h4 class="text-white fw-bold mb-0" style="font-family:'Outfit',sans-serif;"><i class="far fa-calendar-alt me-2" style="color:var(--ac);"></i>Events Showcase</h4>
            <button class="btn-d btn-p" onclick="openModal('addEventModal')"><i class="fas fa-plus"></i> Add New Event</button>
        </div>

        <!-- Existing Events List -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="far fa-calendar-alt"></i> Active Events (<?php echo count($db_events); ?> events)</div>
            </div>
            <div class="card-body" style="padding:1rem;">
                <?php if (empty($db_events)): ?>
                <div class="no-data"><i class="far fa-calendar-alt"></i>No events yet. Add one above.</div>
                <?php else: foreach($db_events as $evt): ?>
                <div class="prod-admin-card mb-3" id="evt-row-<?php echo $evt['id']; ?>">
                    <img src="<?php echo htmlspecialchars($evt['image_path']); ?>" class="prod-thumb" alt="<?php echo htmlspecialchars($evt['title']); ?>" onerror="this.src='https://via.placeholder.com/64x48/0f162d/00f0ff?text=EVT'">
                    <div style="flex:1;">
                        <div style="font-weight:700;color:#fff;font-family:'Outfit',sans-serif;">
                            <?php echo htmlspecialchars($evt['title']); ?>
                            <span class="badge <?php echo htmlspecialchars($evt['badge_class']); ?> ms-2" style="font-size: 0.7rem;"><?php echo htmlspecialchars($evt['badge_text']); ?></span>
                        </div>
                        <div style="font-size:.76rem;color:var(--tm);margin-top:2px;">Date: <strong class="text-white"><?php echo htmlspecialchars($evt['event_date']); ?></strong></div>
                        <div style="font-size:.76rem;color:rgba(255,255,255,0.7);margin-top:4px;"><?php echo htmlspecialchars($evt['description']); ?></div>
                    </div>
                    <button class="btn-d btn-danger" style="flex-shrink:0;font-size:.76rem;padding:6px 12px;" onclick="deleteEvent(<?php echo $evt['id']; ?>,this)"><i class="fas fa-trash"></i> Remove</button>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div><!-- /events -->

    <!-- ===== TAB: REGISTRATIONS ===== -->
    <div class="tab-panel" id="panel-registrations">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h4 class="text-white fw-bold mb-0" style="font-family:'Outfit',sans-serif;"><i class="fas fa-id-badge me-2" style="color:var(--ac);"></i>Event Registrations</h4>
            <span class="btn-d btn-o" style="cursor:default;"><?php echo count($db_registrations); ?> Registrants</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Attendee Name</th>
                        <th>Email Address</th>
                        <th>Company Name</th>
                        <th>Event Selection</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="regBody">
                    <?php if (empty($db_registrations)): ?>
                    <tr><td colspan="7"><div class="no-data"><i class="fas fa-id-badge"></i>No event registrations yet.</div></td></tr>
                    <?php else: foreach($db_registrations as $reg): ?>
                    <tr>
                        <td><span class="cid-badge">#<?php echo $reg['id']; ?></span></td>
                        <td style="font-weight:600;color:#fff;"><?php echo htmlspecialchars($reg['full_name']); ?></td>
                        <td><a href="mailto:<?php echo htmlspecialchars($reg['email_address']); ?>" class="email-lnk" style="color:var(--ac) !important;text-decoration:none;"><?php echo htmlspecialchars($reg['email_address']); ?></a></td>
                        <td><?php echo htmlspecialchars($reg['company_name']); ?></td>
                        <td style="color:var(--ac);font-weight:600;"><?php echo htmlspecialchars($reg['event_title']); ?></td>
                        <td style="font-size:.78rem;color:var(--tm);"><?php echo date("M d, Y H:i", strtotime($reg['registration_date'])); ?></td>
                        <td>
                            <button class="btn-d btn-p" style="font-size:.74rem;padding:6px 12px;" onclick="openRegReplyModal('<?php echo htmlspecialchars($reg['email_address'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($reg['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($reg['event_title'], ENT_QUOTES); ?>')">
                                <i class="fas fa-reply"></i> Reply via Email
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /registrations -->

    <!-- ===== TAB: ANALYTICS ===== -->
    <div class="tab-panel" id="panel-analytics">
        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(0,240,255,.1);color:var(--ac);"><i class="fas fa-chart-line"></i></div>
                <div class="stat-label">Total Inquiries</div>
                <div class="stat-value"><?php echo $total_inquiries; ?></div>
                <div class="stat-change">All time submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(130,87,229,.1);color:var(--ap);"><i class="fas fa-user-plus"></i></div>
                <div class="stat-label">Registered Clients</div>
                <div class="stat-value"><?php echo $total_customers; ?></div>
                <div class="stat-change" style="color:var(--ap);">Sequential IDs assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--ao);"><i class="fas fa-handshake"></i></div>
                <div class="stat-label">Demo Deposits</div>
                <div class="stat-value">£<?php echo number_format($demo_revenue_pending,0); ?></div>
                <div class="stat-change" style="color:var(--ao);"><?php echo $total_demo_requests; ?> demo requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--ag);"><i class="fas fa-box-open"></i></div>
                <div class="stat-label">Products Listed</div>
                <div class="stat-value"><?php echo $total_products; ?></div>
                <div class="stat-change" style="color:var(--ag);">In catalog</div>
            </div>
        </div>

        <div class="dash-grid">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-globe-europe"></i> Inquiries by Country</div></div>
                <div class="card-body">
                    <?php if(empty($top_countries)): ?>
                    <div class="no-data"><i class="fas fa-globe"></i>Connect database to view analytics.</div>
                    <?php else: $maxC=max(array_column($top_countries,'count')); foreach($top_countries as $idx=>$tc): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                        <div style="font-size:.74rem;color:var(--tm);font-weight:700;min-width:18px;">#<?php echo $idx+1; ?></div>
                        <div style="font-size:.84rem;color:rgba(255,255,255,.7);min-width:110px;"><?php echo htmlspecialchars($tc['country']); ?></div>
                        <div class="cbar-wrap"><div class="cbar" style="width:<?php echo $maxC>0?round(($tc['count']/$maxC)*100):0; ?>%;height:8px;border-radius:4px;"></div></div>
                        <div style="font-size:.8rem;color:var(--ac);font-weight:700;min-width:24px;text-align:right;"><?php echo $tc['count']; ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-trophy"></i> Product Sales Ranking</div></div>
                <div class="card-body">
                    <?php if(empty($product_sales ?? [])): ?>
                    <div class="no-data"><i class="fas fa-shopping-bag"></i>No sales recorded yet. Use the Customers tab to record purchases.</div>
                    <?php else: foreach(($product_sales??[]) as $idx=>$ps): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--bd);">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:28px;height:28px;border-radius:8px;background:<?php echo $idx===0?'rgba(245,158,11,.15)':($idx===1?'rgba(255,255,255,.06)':'rgba(130,87,229,.1)'); ?>;border:1px solid <?php echo $idx===0?'rgba(245,158,11,.3)':($idx===1?'rgba(255,255,255,.1)':'rgba(130,87,229,.2)'); ?>;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:<?php echo $idx===0?'#fbbf24':($idx===1?'#94a3b8':'var(--ap)'); ?>;"><?php echo $idx+1; ?></div>
                            <div style="font-size:.88rem;color:rgba(255,255,255,.8);"><?php echo htmlspecialchars($ps['product_name']); ?></div>
                        </div>
                        <span class="badge-paid"><?php echo $ps['sold']; ?> sold</span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> Revenue by Product</div></div>
                <div class="card-body">
                    <?php if (empty($revenue_by_product)): ?>
                    <div class="no-data"><i class="fas fa-wallet"></i>No revenue records yet.</div>
                    <?php else: $maxRev = max(array_column($revenue_by_product, 'revenue')); foreach ($revenue_by_product as $rp): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                        <div style="font-size:.84rem;color:rgba(255,255,255,.7);min-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($rp['product_name']); ?>"><?php echo htmlspecialchars($rp['product_name']); ?></div>
                        <div class="cbar-wrap" style="height:8px;background:rgba(255,255,255,.06);border-radius:4px;overflow:hidden;">
                            <div class="cbar" style="width:<?php echo $maxRev>0?round(($rp['revenue']/$maxRev)*100):0; ?>%;height:100%;border-radius:4px;background:linear-gradient(90deg,var(--ac),var(--ap));"></div>
                        </div>
                        <div style="font-size:.8rem;color:var(--ag);font-weight:700;min-width:70px;text-align:right;">£<?php echo number_format($rp['revenue'], 2); ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="card" style="grid-column:1/-1;">
                <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> Inquiry Volume — Last 6 Months</div></div>
                <div class="card-body">
                    <?php if(empty($monthly_data)): ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:200px;color:var(--tm);font-size:.85rem;"><i class="fas fa-chart-bar me-2"></i>No monthly data yet.</div>
                    <?php else: $maxC=max(array_column($monthly_data,'count')); ?>
                    <div class="chart-area" style="height:220px;">
                        <?php foreach($monthly_data as $m): $pct=$maxC>0?round(($m['count']/$maxC)*100):0; ?>
                        <div class="chart-col" title="<?php echo $m['month'].': '.$m['count'].' inquiries'; ?>">
                            <div class="chart-num"><?php echo $m['count']; ?></div>
                            <div class="chart-bar" style="height:<?php echo $pct; ?>%;"></div>
                            <div class="chart-lbl"><?php echo $m['month']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div><!-- /analytics -->

    </div><!-- /content -->
</div><!-- /main -->

<!-- ===== MODAL: SEND REPLY ===== -->
<div class="adm-modal-bg" id="replyModal">
    <div class="adm-modal">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-reply me-2" style="color:var(--ac);"></i>Send Email Reply</h5>
            <button class="modal-close" onclick="closeModal('replyModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <div class="mb-3">
                <label class="fl">To (Email)</label>
                <input type="email" class="form-dash" id="replyTo" readonly style="opacity:.6;">
            </div>
            <div class="mb-3">
                <label class="fl">Subject</label>
                <input type="text" class="form-dash" id="replySubject" value="RE: AI-Solution — Your Request">
            </div>
            <div class="mb-3">
                <label class="fl">Message</label>
                <textarea class="form-dash" id="replyBody" rows="5" placeholder="Write your reply here..."></textarea>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('replyModal')">Cancel</button>
            <button class="btn-d btn-p" id="sendReplyBtn" onclick="sendReply()"><i class="fas fa-paper-plane"></i> Send Reply</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: ADD EVENT ===== -->
<div class="adm-modal-bg" id="addEventModal">
    <div class="adm-modal adm-modal-lg">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-calendar-plus me-2" style="color:var(--ac);"></i>Add New Event Showcase</h5>
            <button class="modal-close" onclick="closeModal('addEventModal')">&times;</button>
        </div>
        <form id="addEventForm" onsubmit="submitAddEvent(event)" enctype="multipart/form-data">
            <div class="adm-modal-bd">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fl">Event Title *</label>
                        <input type="text" class="form-dash" name="title" required placeholder="e.g. Sunderland Tech Summit 2026">
                    </div>
                    <div class="col-md-6">
                        <label class="fl">Event Date *</label>
                        <input type="text" class="form-dash" name="event_date" required placeholder="e.g. October 15–16, 2026">
                    </div>
                    <div class="col-md-6">
                        <label class="fl">Badge Text *</label>
                        <input type="text" class="form-dash" name="badge_text" required placeholder="e.g. Live Summit, Webinar">
                    </div>
                    <div class="col-md-6">
                        <label class="fl">Badge Color Style *</label>
                        <select class="form-dash" name="badge_class" required>
                            <option value="bg-info text-dark">Electric Blue (Live Summit)</option>
                            <option value="bg-success text-white">Green (Webinar)</option>
                            <option value="bg-primary text-white">Blue (Technology)</option>
                            <option value="bg-warning text-dark">Yellow (Alert/Important)</option>
                            <option value="bg-danger text-white">Red (Critical)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="fl">Short Description *</label>
                        <textarea class="form-dash" name="description" rows="3" required placeholder="Explain what the event is about..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="fl">Image URL</label>
                        <input type="text" class="form-dash" name="image_path" placeholder="https://... or images/event.png">
                    </div>
                    <div class="col-md-6">
                        <label class="fl">Or Upload Image File</label>
                        <input type="file" class="form-dash" name="image_file" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="adm-modal-ft">
                <button type="button" class="btn-d btn-o" onclick="closeModal('addEventModal')">Cancel</button>
                <button type="submit" class="btn-d btn-p" id="addEvtSubmitBtn"><i class="fas fa-plus"></i> Add Event</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== MODAL: RECORD PURCHASE ===== -->
<div class="adm-modal-bg" id="purchaseModal">
    <div class="adm-modal">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-check-circle me-2" style="color:var(--ag);"></i>Record Customer Purchase</h5>
            <button class="modal-close" onclick="closeModal('purchaseModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <p style="color:rgba(255,255,255,.6);font-size:.86rem;margin-bottom:1rem;">Recording a purchase for <strong id="purchaseClientName" style="color:#fff;"></strong> (Client <span id="purchaseClientId" style="color:var(--ac);"></span>). This will mark their related reviews as "Verified Purchase".</p>
            <div class="mb-3">
                <label class="fl">Select Product *</label>
                <select class="form-dash" id="purchaseProduct">
                    <?php foreach($products_list as $prod): ?>
                    <option value="<?php echo htmlspecialchars($prod['name']); ?>"><?php echo htmlspecialchars($prod['name']); ?></option>
                    <?php endforeach; ?>
                    <?php if(empty($products_list)): ?>
                    <option value="OmniMetrics AI">OmniMetrics AI</option>
                    <option value="Nexus Assist Pro">Nexus Assist Pro</option>
                    <option value="LogicBuilder 3.0">LogicBuilder 3.0</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('purchaseModal')">Cancel</button>
            <button class="btn-d btn-p" id="recordPurchaseBtn" onclick="recordPurchase()"><i class="fas fa-plus-circle"></i> Record Purchase</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: EDIT CUSTOMER AMOUNT ===== -->
<div class="adm-modal-bg" id="editAmountModal">
    <div class="adm-modal">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-edit me-2" style="color:var(--ac);"></i>Edit Customer Amount</h5>
            <button class="modal-close" onclick="closeModal('editAmountModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <p style="color:rgba(255,255,255,.6);font-size:.86rem;margin-bottom:1rem;">Update the manual client amount for <strong id="editAmountClientName" style="color:#fff;"></strong> (Client <span id="editAmountClientId" style="color:var(--ac);"></span>).</p>
            <input type="hidden" id="editAmountInquiryId">
            <div class="mb-3">
                <label class="fl">New Amount (£) *</label>
                <input type="number" step="0.01" class="form-dash" id="editAmountInput" placeholder="0.00" required>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('editAmountModal')">Cancel</button>
            <button class="btn-d btn-p" id="saveAmountBtn" onclick="saveCustomerAmount()"><i class="fas fa-save"></i> Save Amount</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: EDIT DEAL STATUS ===== -->
<div class="adm-modal-bg" id="editDealStatusModal">
    <div class="adm-modal">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-edit me-2" style="color:var(--ac);"></i>Edit Deal Status</h5>
            <button class="modal-close" onclick="closeModal('editDealStatusModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <p style="color:rgba(255,255,255,.6);font-size:.86rem;margin-bottom:1rem;">Update the deal status for <strong id="editStatusClientName" style="color:#fff;"></strong> (Inquiry <span id="editStatusInquiryIdDisplay" style="color:var(--ac);"></span>).</p>
            <input type="hidden" id="editStatusInquiryId">
            <div class="mb-3">
                <label class="fl">Status *</label>
                <select class="form-dash" id="editStatusSelect">
                    <option value="New Lead">New Lead</option>
                    <option value="Demo Active">Demo Active</option>
                    <option value="Paid Demo">Paid Demo</option>
                    <option value="Proposal Sent">Proposal Sent</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Sold">Sold</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Closed Lost">Closed Lost</option>
                </select>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('editDealStatusModal')">Cancel</button>
            <button class="btn-d btn-p" id="saveStatusBtn" onclick="saveDealStatus()"><i class="fas fa-save"></i> Save Status</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: EDIT DEAL VALUE ===== -->
<div class="adm-modal-bg" id="editDealValueModal">
    <div class="adm-modal">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-edit me-2" style="color:var(--ac);"></i>Edit Deal Value</h5>
            <button class="modal-close" onclick="closeModal('editDealValueModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <p style="color:rgba(255,255,255,.6);font-size:.86rem;margin-bottom:1rem;">Update the contract value for <strong id="editValueClientName" style="color:#fff;"></strong> (Inquiry <span id="editValueInquiryIdDisplay" style="color:var(--ac);"></span>).</p>
            <input type="hidden" id="editValueInquiryId">
            <div class="mb-3">
                <label class="fl">Contract Value (£) *</label>
                <input type="number" step="0.01" class="form-dash" id="editValueInput" placeholder="0.00" required>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('editDealValueModal')">Cancel</button>
            <button class="btn-d btn-p" id="saveValueBtn" onclick="saveDealValue()"><i class="fas fa-save"></i> Save Value</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: EDIT TOTAL RECEIVED ===== -->
<div class="adm-modal-bg" id="editTotalReceivedModal">
    <div class="adm-modal">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-edit me-2" style="color:var(--ac);"></i>Edit Total Received</h5>
            <button class="modal-close" onclick="closeModal('editTotalReceivedModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <p style="color:rgba(255,255,255,.6);font-size:.86rem;margin-bottom:1rem;">Update total received for <strong id="editReceivedClientName" style="color:#fff;"></strong> (Inquiry <span id="editReceivedInquiryIdDisplay" style="color:var(--ac);"></span>).</p>
            <input type="hidden" id="editReceivedInquiryId">
            <div class="mb-3">
                <label class="fl">Total Received (£) *</label>
                <input type="number" step="0.01" class="form-dash" id="editReceivedInput" placeholder="0.00" required>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('editTotalReceivedModal')">Cancel</button>
            <button class="btn-d btn-p" id="saveReceivedBtn" onclick="saveTotalReceived()"><i class="fas fa-save"></i> Save Amount</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: DEAL MANAGEMENT & CONVERSATIONS ===== -->
<div class="adm-modal-bg" id="dealModal">
    <div class="adm-modal adm-modal-lg">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-handshake me-2" style="color:var(--ac);"></i>Manage Deal &amp; Conversations</h5>
            <button class="modal-close" onclick="closeModal('dealModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <div class="row g-4">
                <!-- Left Column: Deal Details -->
                <div class="col-md-5 border-end border-secondary" style="border-right: 1px solid rgba(255,255,255,0.08) !important;">
                    <h6 class="text-white fw-bold mb-3"><i class="fas fa-info-circle me-1" style="color:var(--ac);"></i>Deal Information</h6>
                    <input type="hidden" id="dealInquiryId">
                    
                    <div class="client-details-list mb-4" style="display: flex; flex-direction: column; gap: 10px; background: rgba(0,0,0,0.15); border: 1px solid var(--bd); padding: 15px; border-radius: 12px;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px; font-size: 0.82rem;">
                            <span style="color: var(--tm); font-weight: 500;">Client ID:</span>
                            <span style="color: var(--ac); font-weight: 700;" id="dealClientId">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px; font-size: 0.82rem;">
                            <span style="color: var(--tm); font-weight: 500;">Client Name:</span>
                            <span style="color: #fff; font-weight: 600;" id="dealFullName">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px; font-size: 0.82rem;">
                            <span style="color: var(--tm); font-weight: 500;">Phone No:</span>
                            <span style="color: #fff;" id="dealPhone">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px; font-size: 0.82rem;">
                            <span style="color: var(--tm); font-weight: 500;">Email:</span>
                            <span style="color: #fff;" id="dealEmail">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px; font-size: 0.82rem;">
                            <span style="color: var(--tm); font-weight: 500;">Company Name:</span>
                            <span style="color: #fff;" id="dealCompany">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px; font-size: 0.82rem;">
                            <span style="color: var(--tm); font-weight: 500;">Product:</span>
                            <span style="color: #fff;" id="dealProduct">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.82rem;">
                            <span style="color: var(--tm); font-weight: 500;">Package:</span>
                            <span style="color: #fff;" id="dealPackage">—</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fl">Deal Status *</label>
                        <select class="form-dash" id="dealStatusSelect">
                            <option value="Sold">Sold</option>
                            <option value="Proposal Sent">Proposal Sent</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="fl">Deal Contract Value (£) *</label>
                        <input type="number" step="0.01" class="form-dash" id="dealValueInput">
                    </div>

                    <div class="mb-3">
                        <label class="fl">Total Received (£) *</label>
                        <input type="number" step="0.01" class="form-dash" id="dealTotalReceivedInput">
                    </div>

                    <div class="mb-3">
                        <label class="fl">Add Payment Received (£)</label>
                        <div class="d-flex gap-2">
                            <input type="number" step="0.01" class="form-dash" id="dealPaymentInput" placeholder="0.00">
                            <button class="btn-d btn-green" onclick="logPaymentDirect()" style="padding: 10px 14px;" title="Log payment amount"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Conversation Logs -->
                <div class="col-md-7">
                    <h6 class="text-white fw-bold mb-3"><i class="fas fa-comments me-1" style="color:var(--ap);"></i>Conversation Thread</h6>
                    
                    <div class="chat-thread d-flex flex-column" id="dealChatThread">
                        <!-- Filled dynamically -->
                    </div>

                    <div class="mb-3">
                        <label class="fl">Reply Method</label>
                        <div class="d-flex gap-4 align-items-center mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="replyType" id="replyTypeEmail" value="email" checked>
                                <label class="form-check-label text-white small" for="replyTypeEmail">
                                    Send Email &amp; Log
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="replyType" id="replyTypeMsg" value="message">
                                <label class="form-check-label text-white small" for="replyTypeMsg">
                                    Log Message Only
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="emailDetailsSection">
                        <div class="mb-2">
                            <label class="fl">Email To</label>
                            <input type="email" class="form-dash" id="dealReplyEmailTo" placeholder="client@example.com">
                        </div>
                        <div class="mb-2">
                            <label class="fl">Email Subject</label>
                            <input type="text" class="form-dash" id="dealReplySubject" placeholder="RE: Your AI-Solution Request">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="fl">Message / Note Details</label>
                        <textarea class="form-dash" id="dealReplyMessage" rows="3" placeholder="Type message details here..."></textarea>
                    </div>
                    <div class="text-end">
                        <button class="btn-d btn-p w-100" id="dealReplyBtn" onclick="submitDealReply()"><i class="fas fa-paper-plane"></i> Submit Reply</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('dealModal')">Close</button>
            <button class="btn-d btn-p" id="saveDealDetailsBtn" onclick="saveDealDetails()"><i class="fas fa-save"></i> Save Deal Info</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: EDIT PRODUCT ===== -->
<div class="adm-modal-bg" id="editProductModal">
    <div class="adm-modal adm-modal-lg">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-edit me-2" style="color:var(--ac);"></i>Edit Product Details</h5>
            <button class="modal-close" onclick="closeModal('editProductModal')">&times;</button>
        </div>
        <form id="editProdForm" onsubmit="submitEditProduct(event)" enctype="multipart/form-data">
            <input type="hidden" name="id" id="editProdId">
            <div class="adm-modal-bd">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fl">Product Name *</label>
                        <input type="text" class="form-dash" name="name" id="editProdName" required>
                    </div>
                    <div class="col-md-3">
                        <label class="fl">Category</label>
                        <select class="form-dash" name="category" id="editProdCategory">
                            <option value="new">New Release</option>
                            <option value="new analytics">Analytics</option>
                            <option value="new assistant">Assistant</option>
                            <option value="legacy">Legacy</option>
                            <option value="legacy analytics">Legacy Analytics</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="fl">Release Date</label>
                        <input type="text" class="form-dash" name="release_date" id="editProdReleaseDate">
                    </div>
                    <div class="col-12">
                        <label class="fl">Description *</label>
                        <textarea class="form-dash" name="description" id="editProdDescription" rows="2" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="fl">Detail Description *</label>
                        <textarea class="form-dash" name="detail_description" id="editProdDetailDescription" rows="4" required></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="fl">Tags (comma separated)</label>
                        <input type="text" class="form-dash" name="tags" id="editProdTags">
                    </div>
                    <div class="col-md-4">
                        <label class="fl">Integration</label>
                        <input type="text" class="form-dash" name="integration" id="editProdIntegration">
                    </div>
                    <div class="col-md-4">
                        <label class="fl">Deployment</label>
                        <input type="text" class="form-dash" name="deployment" id="editProdDeployment">
                    </div>
                    <div class="col-md-4">
                        <label class="fl">Basic Price (£/mo)</label>
                        <input type="number" class="form-dash" name="basic_price" id="editProdBasicPrice" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="fl">Standard Price (£/mo)</label>
                        <input type="number" class="form-dash" name="standard_price" id="editProdStandardPrice" step="0.01">
                    </div>
                    <div class="col-md-4">
                        <label class="fl">Custom Price Plan text</label>
                        <input type="text" class="form-dash" name="custom_price" id="editProdCustomPrice">
                    </div>
                    <div class="col-md-6">
                        <label class="fl">Image URL</label>
                        <input type="text" class="form-dash" name="image_path" id="editProdImagePath">
                    </div>
                    <div class="col-md-6">
                        <label class="fl">Or Upload Image File</label>
                        <input type="file" class="form-dash" name="image_file" id="editProdImageFile" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="adm-modal-ft">
                <button type="button" class="btn-d btn-o" onclick="closeModal('editProductModal')">Cancel</button>
                <button type="submit" class="btn-d btn-p" id="editProdSubmitBtn"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== MODAL: REPLY REGISTRANT ===== -->
<div class="adm-modal-bg" id="regReplyModal">
    <div class="adm-modal">
        <div class="adm-modal-hd">
            <h5><i class="fas fa-reply me-2" style="color:var(--ac);"></i>Reply to Attendee</h5>
            <button class="modal-close" onclick="closeModal('regReplyModal')">&times;</button>
        </div>
        <div class="adm-modal-bd">
            <div class="mb-3">
                <label class="fl">Email To</label>
                <input type="email" class="form-dash" id="regReplyEmailTo" readonly>
            </div>
            <div class="mb-3">
                <label class="fl">Subject</label>
                <input type="text" class="form-dash" id="regReplySubject" placeholder="Registration Confirmation">
            </div>
            <div class="mb-3">
                <label class="fl">Custom Message</label>
                <textarea class="form-dash" id="regReplyMessage" rows="6" placeholder="Type your custom email message here..."></textarea>
            </div>
        </div>
        <div class="adm-modal-ft">
            <button class="btn-d btn-o" onclick="closeModal('regReplyModal')">Cancel</button>
            <button class="btn-d btn-p" id="regReplyBtn" onclick="submitRegReply()"><i class="fas fa-paper-plane"></i> Send Email</button>
        </div>
    </div>
</div>

<!-- ===== TOAST ===== -->
<div class="adm-toast" id="admToast">
    <span class="ti ok" id="toastIcon"><i class="fas fa-check-circle"></i></span>
    <div>
        <strong style="display:block;color:var(--ac);font-size:.88rem;" id="toastTitle">Done</strong>
        <span style="font-size:.8rem;color:rgba(255,255,255,.7);" id="toastMsg"></span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const TAB_TITLES = { overview:'Dashboard Overview', inquiries:'Inquiries & Requests', customers:'Customer Management', products:'Product Catalog', reviews:'Customer Reviews', events:'Event Highlights', registrations:'Event Registrations', analytics:'Analytics & Insights' };
let _purchaseClientId = null;

function switchTab(name, el) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById('panel-' + name);
    if (panel) panel.classList.add('active');
    if (el) el.classList.add('active');
    document.getElementById('topbarTitle').textContent = TAB_TITLES[name] || name;
}

function filterTbl(tbodyId, q) {
    document.querySelectorAll('#' + tbodyId + ' tr').forEach(row => {
        row.style.display = q==='' || row.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}

function showToast(title, msg, isError=false) {
    const t = document.getElementById('admToast');
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastMsg').textContent = msg;
    document.getElementById('toastIcon').className = 'ti ' + (isError?'err':'ok');
    document.getElementById('toastIcon').innerHTML = isError ? '<i class="fas fa-exclamation-circle"></i>' : '<i class="fas fa-check-circle"></i>';
    t.classList.toggle('error', isError);
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 4000);
}

function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// ─ DELETE INQUIRY
function deleteInquiry(id, btn) {
    if (!confirm('Delete this inquiry? This cannot be undone.')) return;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('admin-dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax_action=delete_inquiry&id='+id })
        .then(r=>r.json()).then(d=>{
            if (d.status==='success') {
                btn.closest('tr').remove();
                showToast('Deleted','Inquiry #'+id+' removed.');
            } else { btn.disabled=false; btn.innerHTML='<i class="fas fa-trash"></i>'; showToast('Error',d.message,true); }
        }).catch(()=>{btn.disabled=false; btn.innerHTML='<i class="fas fa-trash"></i>'; showToast('Error','DB connection failed.',true);});
}

// ─ OPEN REPLY MODAL
function openReply(email, name, product) {
    document.getElementById('replyTo').value = email;
    document.getElementById('replySubject').value = 'RE: AI-Solution — Your '+product+' Request';
    document.getElementById('replyBody').value = 'Dear '+name+',\n\nThank you for your interest in '+product+'. A member of our team will be in touch shortly.\n\nBest regards,\nAI-Solution Sales Team';
    openModal('replyModal');
}

function openCustomerEmail(email, name) {
    document.getElementById('replyTo').value = email;
    document.getElementById('replySubject').value = 'AI-Solution — Account Updates & Support';
    document.getElementById('replyBody').value = 'Dear ' + name + ',\n\nThank you for being a valued customer of AI-Solution.\n\nBest regards,\nAI-Solution Team';
    openModal('replyModal');
}

function sendReply() {
    const to      = document.getElementById('replyTo').value.trim();
    const subject = document.getElementById('replySubject').value.trim();
    const body    = document.getElementById('replyBody').value.trim();
    if (!body) { showToast('Error','Please write a message body.',true); return; }
    const btn = document.getElementById('sendReplyBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';
    const fd = new URLSearchParams({ajax_action:'send_reply',to,subject,body});
    fetch('admin-dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString() })
        .then(r=>r.json()).then(d=>{
            btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Reply';
            closeModal('replyModal');
            showToast(d.status==='success'?'Email Sent':'SMTP Error', d.message, d.status!=='success');
        }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Reply'; showToast('Error','Request failed.',true); });
}

// ─ RECORD PURCHASE
function openRecordPurchase(cid, name) {
    _purchaseClientId = cid;
    document.getElementById('purchaseClientName').textContent = name;
    document.getElementById('purchaseClientId').textContent = '#'+cid;
    openModal('purchaseModal');
}

function recordPurchase() {
    const prod = document.getElementById('purchaseProduct').value;
    if (!prod || !_purchaseClientId) return;
    const btn = document.getElementById('recordPurchaseBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Recording...';
    const fd = new URLSearchParams({ajax_action:'record_purchase',customer_id:_purchaseClientId,product_name:prod});
    fetch('admin-dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString() })
        .then(r=>r.json()).then(d=>{
            btn.disabled=false; btn.innerHTML='<i class="fas fa-plus-circle"></i> Record Purchase';
            closeModal('purchaseModal');
            showToast(d.status==='success'?'Purchase Recorded':d.message, d.status==='success'?'Client #'+_purchaseClientId+' purchased "'+prod+'"':d.message, d.status!=='success');
            if (d.status==='success') setTimeout(()=>location.reload(), 2000);
        }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-plus-circle"></i> Record Purchase'; showToast('Error','Request failed.',true); });
}

function submitAddProduct(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('addProdBtn');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    const fd = new FormData(form);
    fd.append('ajax_action', 'add_product');
    
    fetch('admin-dashboard.php', { 
        method: 'POST', 
        body: fd 
    })
        .then(r=>r.json()).then(d=>{
            btn.disabled=false; btn.innerHTML='<i class="fas fa-plus"></i> Add Product';
            showToast(d.status==='success'?'Product Added':d.message, d.message, d.status!=='success');
            if (d.status==='success') { form.reset(); setTimeout(()=>location.reload(), 2000); }
        }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-plus"></i> Add Product'; showToast('Error','Request failed.',true); });
}

// ─ DELETE PRODUCT
function deleteProduct(id, btn) {
    if (!confirm('Remove this product from the catalog?')) return;
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
    fetch('admin-dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax_action=delete_product&id='+id })
        .then(r=>r.json()).then(d=>{
            if (d.status==='success') { document.getElementById('prod-row-'+id)?.remove(); showToast('Removed','Product deleted from catalog.'); }
            else { btn.disabled=false; btn.innerHTML='<i class="fas fa-trash"></i> Remove'; showToast('Error',d.message,true); }
        }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-trash"></i> Remove'; showToast('Error','Request failed.',true); });
}

// ─ DEAL & CONVERSATIONS MODAL FUNCTIONS
function openDealPanel(id) {
    fetch('admin-dashboard.php?ajax_action=get_conversation&id=' + id)
        .then(r=>r.json()).then(d=>{
            if (d.status === 'success') {
                const inq = d.inquiry;
                document.getElementById('dealInquiryId').value = inq.id;
                document.getElementById('dealClientId').textContent = '#' + (inq.customer_id || '—');
                document.getElementById('dealFullName').textContent = inq.full_name;
                document.getElementById('dealEmail').textContent = inq.email_address;
                document.getElementById('dealPhone').textContent = inq.phone_number || '—';
                document.getElementById('dealCompany').textContent = inq.company_name || '—';
                document.getElementById('dealProduct').textContent = inq.product_name || '—';
                document.getElementById('dealPackage').textContent = inq.package_name || '—';
                
                document.getElementById('dealStatusSelect').value = inq.deal_status || 'Ongoing';
                document.getElementById('dealValueInput').value = parseFloat(inq.deal_value || 0).toFixed(2);
                document.getElementById('dealTotalReceivedInput').value = parseFloat(inq.total_received || 0).toFixed(2);
                document.getElementById('dealPaymentInput').value = '';
                document.getElementById('dealReplyEmailTo').value = inq.email_address || '';
                document.getElementById('dealReplySubject').value = 'RE: Your AI-Solution Request - ' + (inq.product_name || 'Inquiry');
                document.getElementById('replyTypeEmail').checked = true;
                document.getElementById('emailDetailsSection').style.display = 'block';
                
                // Chat Thread
                const thread = document.getElementById('dealChatThread');
                thread.innerHTML = '';
                const messages = d.messages || [];
                if (messages.length === 0) {
                    thread.innerHTML = '<div class="chat-thread-msg system">No communication logs recorded yet.</div>';
                } else {
                    messages.forEach(m => {
                        const div = document.createElement('div');
                        const senderClass = m.sender.toLowerCase();
                        div.className = `chat-thread-msg ${senderClass}`;
                        const messageHtml = (m.message + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
                        div.innerHTML = `<strong>${m.sender}:</strong> ${messageHtml}<br><small style="opacity:0.6;font-size:0.7rem;display:block;margin-top:3px;">${m.created_at}</small>`;
                        thread.appendChild(div);
                    });
                }
                
                openModal('dealModal');
                setTimeout(() => thread.scrollTop = thread.scrollHeight, 100);
            } else {
                showToast('Error', d.message, true);
            }
        }).catch(()=>{ showToast('Error', 'Failed to retrieve conversation details.', true); });
}

function saveDealDetails() {
    const id = document.getElementById('dealInquiryId').value;
    const deal_status = document.getElementById('dealStatusSelect').value;
    const deal_value = document.getElementById('dealValueInput').value;
    const total_received = document.getElementById('dealTotalReceivedInput').value;
    const btn = document.getElementById('saveDealDetailsBtn');
    
    // Cancelled logic check
    if (deal_status === 'Cancelled') {
        if (!confirm('Setting status to Cancelled will delete this inquiry permanently. Proceed?')) return;
    }
    
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    const fd = new URLSearchParams({ajax_action:'update_deal', id, deal_status, deal_value, total_received});
    fetch('admin-dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString() })
        .then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Deal Info';
            if (d.status === 'success') {
                closeModal('dealModal');
                showToast('Saved', d.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('Error', d.message, true);
            }
        }).catch(()=>{ btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Deal Info'; showToast('Error', 'Request failed.', true); });
}

function logPaymentDirect() {
    const id = document.getElementById('dealInquiryId').value;
    const payment_amount = parseFloat(document.getElementById('dealPaymentInput').value || 0);
    if (payment_amount <= 0 || isNaN(payment_amount)) { showToast('Error', 'Please enter a valid positive payment amount.', true); return; }
    
    const deal_status = document.getElementById('dealStatusSelect').value;
    const deal_value = document.getElementById('dealValueInput').value;
    
    const fd = new URLSearchParams({ajax_action:'update_deal', id, deal_status, deal_value, payment_amount});
    fetch('admin-dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString() })
        .then(r=>r.json()).then(d=>{
            if (d.status === 'success') {
                document.getElementById('dealPaymentInput').value = '';
                const newTotal = parseFloat(d.new_total_received);
                document.getElementById('dealTotalReceivedInput').value = newTotal.toFixed(2);
                
                // Append system note dynamically to thread
                const thread = document.getElementById('dealChatThread');
                const div = document.createElement('div');
                div.className = 'chat-thread-msg system';
                div.innerHTML = `<strong>System:</strong> Payment of £${payment_amount.toFixed(2)} logged. Total received: £${newTotal.toFixed(2)}.<br><small style="opacity:0.6;font-size:0.7rem;display:block;margin-top:3px;">Just Now</small>`;
                
                if (thread.innerHTML.includes('No communication logs')) {
                    thread.innerHTML = '';
                }
                
                thread.appendChild(div);
                thread.scrollTop = thread.scrollHeight;
                showToast('Payment Logged', 'Payment of £' + payment_amount.toFixed(2) + ' recorded.');
            } else {
                showToast('Error', d.message, true);
            }
        }).catch(()=>{ showToast('Error', 'Payment log request failed.', true); });
}

function submitDealReply() {
    const id = document.getElementById('dealInquiryId').value;
    const message = document.getElementById('dealReplyMessage').value.trim();
    if (!message) { showToast('Error', 'Please enter a reply message.', true); return; }
    
    const reply_type = document.querySelector('input[name="replyType"]:checked').value;
    const to = document.getElementById('dealReplyEmailTo').value.trim();
    const subject = document.getElementById('dealReplySubject').value.trim();
    
    if (reply_type === 'email' && !to) { showToast('Error', 'Please enter a recipient email address.', true); return; }
    
    const btn = document.getElementById('dealReplyBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    const params = {
        ajax_action: 'add_reply',
        id: id,
        message: message,
        reply_type: reply_type
    };
    if (reply_type === 'email') {
        params.to = to;
        params.subject = subject;
    }
    const fd = new URLSearchParams(params);
    
    fetch('admin-dashboard.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:fd.toString() })
        .then(r=>r.json()).then(d=>{
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Reply';
            if (d.status === 'success' || d.status === 'warning') {
                document.getElementById('dealReplyMessage').value = '';
                
                const thread = document.getElementById('dealChatThread');
                if (thread.innerHTML.includes('No communication logs')) {
                    thread.innerHTML = '';
                }
                
                const div = document.createElement('div');
                const senderName = (reply_type === 'message') ? 'Admin Note' : 'Admin';
                div.className = 'chat-thread-msg admin';
                const messageHtml = message.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
                div.innerHTML = `<strong>${senderName}:</strong> ${messageHtml}<br><small style="opacity:0.6;font-size:0.7rem;display:block;margin-top:3px;">Just Now</small>`;
                thread.appendChild(div);
                thread.scrollTop = thread.scrollHeight;
                showToast(d.status==='success'?'Reply Registered':'Email Warning', d.message, d.status==='warning');
            } else {
                showToast('Error', d.message, true);
            }
        }).catch(()=>{ btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Reply'; showToast('Error', 'Request failed.', true); });
}

function openEditAmount(id, name, currentAmount) {
    document.getElementById('editAmountInquiryId').value = id;
    document.getElementById('editAmountClientName').textContent = name;
    document.getElementById('editAmountClientId').textContent = '#' + id;
    document.getElementById('editAmountInput').value = parseFloat(currentAmount).toFixed(2);
    openModal('editAmountModal');
}

function saveCustomerAmount() {
    const id = document.getElementById('editAmountInquiryId').value;
    const amount = parseFloat(document.getElementById('editAmountInput').value);
    if (isNaN(amount) || amount < 0) {
        showToast('Error', 'Please enter a valid amount.', true);
        return;
    }
    const btn = document.getElementById('saveAmountBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=update_customer_amount&id=${id}&amount=${amount}`
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Amount';
        if (d.status === 'success') {
            closeModal('editAmountModal');
            showToast('Success', 'Customer amount updated.');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Amount';
        showToast('Error', 'Request failed.', true);
    });
}
function openEditDealStatus(id, name, currentStatus) {
    document.getElementById('editStatusInquiryId').value = id;
    document.getElementById('editStatusClientName').textContent = name;
    document.getElementById('editStatusInquiryIdDisplay').textContent = '#' + id;
    document.getElementById('editStatusSelect').value = currentStatus || 'New Lead';
    openModal('editDealStatusModal');
}

function saveDealStatus() {
    const id = document.getElementById('editStatusInquiryId').value;
    const deal_status = document.getElementById('editStatusSelect').value;
    const btn = document.getElementById('saveStatusBtn');
    
    if (deal_status === 'Cancelled') {
        if (!confirm('Setting status to Cancelled will delete this inquiry permanently. Proceed?')) return;
    }
    
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=update_deal&id=${id}&deal_status=${encodeURIComponent(deal_status)}`
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Status';
        if (d.status === 'success') {
            closeModal('editDealStatusModal');
            showToast('Success', d.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Status';
        showToast('Error', 'Request failed.', true);
    });
}

function openEditDealValue(id, name, currentValue) {
    document.getElementById('editValueInquiryId').value = id;
    document.getElementById('editValueClientName').textContent = name;
    document.getElementById('editValueInquiryIdDisplay').textContent = '#' + id;
    document.getElementById('editValueInput').value = parseFloat(currentValue).toFixed(2);
    openModal('editDealValueModal');
}

function saveDealValue() {
    const id = document.getElementById('editValueInquiryId').value;
    const deal_value = parseFloat(document.getElementById('editValueInput').value);
    if (isNaN(deal_value) || deal_value < 0) {
        showToast('Error', 'Please enter a valid deal value.', true);
        return;
    }
    const btn = document.getElementById('saveValueBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=update_deal&id=${id}&deal_value=${deal_value}`
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Value';
        if (d.status === 'success') {
            closeModal('editDealValueModal');
            showToast('Success', 'Deal value updated.');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Value';
        showToast('Error', 'Request failed.', true);
    });
}

function openEditTotalReceived(id, name, currentTotal) {
    document.getElementById('editReceivedInquiryId').value = id;
    document.getElementById('editReceivedClientName').textContent = name;
    document.getElementById('editReceivedInquiryIdDisplay').textContent = '#' + id;
    document.getElementById('editReceivedInput').value = parseFloat(currentTotal).toFixed(2);
    openModal('editTotalReceivedModal');
}

function saveTotalReceived() {
    const id = document.getElementById('editReceivedInquiryId').value;
    const total_received = parseFloat(document.getElementById('editReceivedInput').value);
    if (isNaN(total_received) || total_received < 0) {
        showToast('Error', 'Please enter a valid amount.', true);
        return;
    }
    const btn = document.getElementById('saveReceivedBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=update_deal&id=${id}&total_received=${total_received}`
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Amount';
        if (d.status === 'success') {
            closeModal('editTotalReceivedModal');
            showToast('Success', 'Total received updated.');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Amount';
        showToast('Error', 'Request failed.', true);
    });
}
function verifyReview(id, btn) {
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=verify_review&id=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            showToast('Verified', 'Review marked as verified.');
            setTimeout(() => location.reload(), 1000);
        } else {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Verify';
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Verify';
        showToast('Error', 'Request failed.', true);
    });
}

function deleteReview(id, btn) {
    if (!confirm('Are you sure you want to delete this review?')) return;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=delete_review&id=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            showToast('Deleted', 'Review deleted successfully.');
            setTimeout(() => location.reload(), 1000);
        } else {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Delete';
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Delete';
        showToast('Error', 'Request failed.', true);
    });
}

function submitAddEvent(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('addEvtSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    const fd = new FormData(form);
    fd.append('ajax_action', 'add_event');
    
    fetch('admin-dashboard.php', { 
        method: 'POST', 
        body: fd 
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Add Event';
        showToast(d.status === 'success' ? 'Event Added' : 'Error', d.message, d.status !== 'success');
        if (d.status === 'success') { 
            form.reset(); 
            closeModal('addEventModal');
            setTimeout(() => location.reload(), 1500); 
        }
    })
    .catch(() => { 
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Add Event'; 
        showToast('Error', 'Request failed.', true); 
    });
}

function deleteEvent(id, btn) {
    if (!confirm('Are you sure you want to delete this event?')) return;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    const fd = new URLSearchParams({ ajax_action: 'delete_event', id });
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            document.getElementById('evt-row-' + id)?.remove();
            showToast('Removed', 'Event removed successfully.');
            setTimeout(() => location.reload(), 1000);
        } else {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Remove';
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Remove';
        showToast('Error', 'Request failed.', true);
    });
}

function openEditProduct(btn) {
    const prod = JSON.parse(btn.getAttribute('data-product'));
    document.getElementById('editProdId').value = prod.id;
    document.getElementById('editProdName').value = prod.name;
    document.getElementById('editProdCategory').value = prod.category;
    document.getElementById('editProdReleaseDate').value = prod.release_date || '';
    document.getElementById('editProdDescription').value = prod.description || '';
    document.getElementById('editProdDetailDescription').value = prod.detail_description || '';
    document.getElementById('editProdTags').value = prod.tags || '';
    document.getElementById('editProdIntegration').value = prod.integration || '';
    document.getElementById('editProdDeployment').value = prod.deployment || '';
    document.getElementById('editProdBasicPrice').value = parseFloat(prod.basic_price || 0).toFixed(2);
    document.getElementById('editProdStandardPrice').value = parseFloat(prod.standard_price || 0).toFixed(2);
    document.getElementById('editProdCustomPrice').value = prod.custom_price || 'Custom';
    document.getElementById('editProdImagePath').value = prod.image_path || '';
    document.getElementById('editProdImageFile').value = ''; // Reset file input
    openModal('editProductModal');
}

function submitEditProduct(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('editProdSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    const fd = new FormData(form);
    fd.append('ajax_action', 'edit_product');
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        if (d.status === 'success') {
            closeModal('editProductModal');
            showToast('Success', d.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        showToast('Error', 'Request failed.', true);
    });
}

function openRegReplyModal(email, name, eventTitle) {
    document.getElementById('regReplyEmailTo').value = email;
    document.getElementById('regReplySubject').value = 'RE: Your Reservation for ' + eventTitle;
    document.getElementById('regReplyMessage').value = 'Dear ' + name + ',\n\nThank you for registering to attend ' + eventTitle + '.\n\nWe have reserved your pass. More details and connection instructions will be shared closer to the date.\n\nBest regards,\nAI-Solution Team';
    openModal('regReplyModal');
}

function submitRegReply() {
    const to = document.getElementById('regReplyEmailTo').value;
    const subject = document.getElementById('regReplySubject').value;
    const message = document.getElementById('regReplyMessage').value;
    
    if (!message.trim()) {
        showToast('Error', 'Message cannot be empty.', true);
        return;
    }
    
    const btn = document.getElementById('regReplyBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=send_registrant_reply&to=${encodeURIComponent(to)}&subject=${encodeURIComponent(subject)}&message=${encodeURIComponent(message)}`
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
        if (d.status === 'success') {
            closeModal('regReplyModal');
            showToast('Sent', d.message);
        } else {
            showToast('Error', d.message, true);
        }
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
        showToast('Error', 'Request failed.', true);
    });
}

// ─ High contrast
const themeToggle = document.getElementById('themeToggle');
if (localStorage.getItem('adminContrastMode')==='enabled') document.body.classList.add('hc');
if (themeToggle) themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('hc');
    localStorage.setItem('adminContrastMode', document.body.classList.contains('hc') ? 'enabled' : 'disabled');
});

// ─ Toggle email options inside handshake modal
document.querySelectorAll('input[name="replyType"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
        const emailSection = document.getElementById('emailDetailsSection');
        if (e.target.value === 'email') {
            emailSection.style.display = 'block';
        } else {
            emailSection.style.display = 'none';
        }
    });
});

// ─ URL hash direct tab link
const hash = window.location.hash.replace('#','');
if (hash && TAB_TITLES[hash]) switchTab(hash, document.getElementById('tab-'+hash));
</script>
</body>
</html>
