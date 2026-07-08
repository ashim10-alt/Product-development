<?php
/**
 * setup_database.php
 * 
 * Web-based SQLite database setup wizard.
 * Access via: setup_database.php
 */

header('Content-Type: text/html; charset=utf-8');

$status = [];

function log_status(string $message, string $type = 'info') {
    global $status;
    $status[] = ['message' => $message, 'type' => $type];
    
    $colorClass = 'log-info';
    if ($type === 'success') $colorClass = 'log-success';
    if ($type === 'error') $colorClass = 'log-error';
    if ($type === 'warning') $colorClass = 'log-warning';
    
    echo "<div class='{$colorClass}'>" . htmlspecialchars($message) . "</div>\n";
}

// Start output
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Solution SQLite Database Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #070b19 0%, #0f162d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #070b19;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .log {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            color: #333;
        }
        .log-info { color: #0066cc; }
        .log-success { color: #00aa00; font-weight: bold; }
        .log-error { color: #cc0000; font-weight: bold; }
        .log-warning { color: #ff8800; }
        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        button {
            flex: 1;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #00f0ff;
            color: #070b19;
        }
        .btn-primary:hover {
            background: #00d9e8;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        .status-box {
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            font-size: 14px;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ SQLite Database Setup Wizard</h1>
        <p class="subtitle">AI-Solution Portal - SQLite Configuration</p>
        
        <div class="log">
            <div class="log-info">[Setup Started]</div>
HTML;

try {
    $db_file = __DIR__ . '/ai_solution_db.sqlite';
    if (file_exists($db_file)) {
        log_status('Resetting existing database file for a clean install...', 'warning');
        @unlink($db_file);
    }

    log_status('Connecting and initializing SQLite database...', 'info');
    
    // Require db_connect which creates tables and seeds default records
    require_once __DIR__ . '/db_connect.php';
    
    log_status('✓ SQLite connection established successfully', 'success');
    log_status('✓ Database tables verified/created', 'success');
    log_status('✓ Default admin user credentials set: admin / AdminSecure2026!', 'success');
    log_status('✓ Default product catalogs seeded', 'success');
    log_status('✓ Default customer reviews seeded', 'success');
    
    // Verify database file
    $db_file = __DIR__ . '/ai_solution_db.sqlite';
    if (file_exists($db_file)) {
        $size = filesize($db_file);
        log_status("✓ Database file verified at: ai_solution_db.sqlite (" . number_format($size) . " bytes)", 'success');
    } else {
        throw new Exception("Database file was not created.");
    }
    
    log_status('[Setup Completed]', 'info');
    
    echo <<<HTML
        </div>
        
        <div class="status-box status-success">
            <strong>✓ SQLite Database Setup Complete!</strong><br>
            Your embedded SQLite database is ready. No MySQL server or configuration is required.
            <ul style="margin-top: 10px; margin-left: 20px;">
                <li>Admin Portal: <strong>admin-login.php</strong></li>
                <li>Username: <strong>admin</strong></li>
                <li>Password: <strong>AdminSecure2026!</strong></li>
            </ul>
        </div>
        
        <div class="buttons">
            <button class="btn-primary" onclick="location.href='index.html'">Go to Portal</button>
            <button class="btn-secondary" onclick="location.href='admin-login.php'">Admin Login</button>
        </div>
    </div>
</body>
</html>
HTML;

} catch (Exception $e) {
    log_status('✗ Setup Failed: ' . $e->getMessage(), 'error');
    echo <<<HTML
        </div>
        
        <div class="status-box status-error">
            <strong>✗ SQLite Setup Failed</strong><br>
            Make sure the folder is writable and PHP has PDO SQLite extension enabled.
        </div>
        
        <div class="buttons">
            <button class="btn-secondary" onclick="location.reload()">Try Again</button>
        </div>
    </div>
</body>
</html>
HTML;
}
?>
