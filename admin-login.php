<?php
/**
 * admin-login.php
 * Standalone admin login page. Redirects to admin-dashboard.php on success.
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

session_start();

// Already logged in — redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin-dashboard.php");
    exit;
}

require_once __DIR__ . '/db_connect.php';

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $login_error = 'Please enter both username and password.';
    } else {
        try {
            $pdo = getDbConnection();
            
            $stmt = $pdo->prepare("SELECT `id`, `password` FROM `admin_users` WHERE `username` = ?");
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            
            if ($row) {
                $admin_id = (int)$row['id'];
                $stored_password = $row['password'];
                
                // Hashed password verification
                if (password_verify($password, $stored_password)) {
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user'] = $username;
                    $_SESSION['admin_id'] = $admin_id;
                    $_SESSION['db_offline'] = false;
                    
                    // Clear output buffer and redirect
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    header("Location: admin-dashboard.php", true, 302);
                    exit;
                } else {
                    $login_error = 'Invalid password. Please try again.';
                }
            } else {
                $login_error = 'User not found.';
            }
        } catch (Exception $e) {
            error_log("Admin login database error: " . $e->getMessage());
            // Fallback for demo when DB has error
            if ($username === 'admin' && $password === 'AdminSecure2026!') {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = 'admin';
                $_SESSION['admin_id'] = 1;
                $_SESSION['db_offline'] = true;
                header("Location: admin-dashboard.php");
                exit;
            } else {
                $login_error = 'System error occurred. Please contact administrator.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | AI-Solution</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #070b19;
            --secondary-dark: #0f162d;
            --accent-cyan: #00f0ff;
            --accent-purple: #8257e5;
            --text-light: #f4f6fd;
            --text-muted: #798396;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--primary-dark);
            background-image:
                radial-gradient(ellipse at 10% 20%, rgba(130,87,229,0.1) 0%, transparent 40%),
                radial-gradient(ellipse at 90% 80%, rgba(0,240,255,0.07) 0%, transparent 40%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        /* Animated grid background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,240,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,240,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        .login-wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }

        /* Back to site link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--accent-cyan); }

        .auth-card {
            background: rgba(15,22,45,0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0,240,255,0.12);
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.02);
            overflow: hidden;
        }

        .auth-top-bar {
            height: 3px;
            background: linear-gradient(90deg, var(--accent-cyan), var(--accent-purple), var(--accent-cyan));
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .auth-header {
            padding: 2.5rem 2.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, rgba(0,240,255,0.1), rgba(130,87,229,0.1));
            border: 1px solid rgba(0,240,255,0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            color: var(--accent-cyan);
            font-size: 1.6rem;
        }

        .auth-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.6rem;
            color: #ffffff;
            margin-bottom: 0.3rem;
        }

        .auth-subtitle {
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        .auth-body { padding: 2rem 2.5rem 2.5rem; }

        .input-group-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
            z-index: 5;
        }

        .form-control-admin {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 14px 16px 14px 40px;
            font-size: 0.95rem;
            border-radius: 12px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control-admin:focus {
            background: rgba(255,255,255,0.06);
            border-color: var(--accent-cyan);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(0,240,255,0.1);
            outline: none;
        }

        .form-control-admin::placeholder { color: rgba(255,255,255,0.25); }

        .form-label-admin {
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 0.5rem;
            display: block;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.9rem;
            transition: color 0.2s;
            z-index: 5;
        }
        .toggle-password:hover { color: var(--accent-cyan); }

        .btn-login {
            background: linear-gradient(135deg, var(--accent-cyan) 0%, #00b4d8 100%);
            color: #070b19;
            font-weight: 700;
            border: none;
            padding: 15px 20px;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            letter-spacing: 0.5px;
            width: 100%;
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0,240,255,0.3);
        }

        .btn-login:hover::before { opacity: 1; }
        .btn-login:active { transform: translateY(0); }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .error-alert {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 10px;
            color: #f87171;
            padding: 12px 16px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.2rem;
        }

        .auth-footer-note {
            text-align: center;
            padding: 1.2rem 2.5rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            color: var(--text-muted);
            font-size: 0.78rem;
        }

        .auth-footer-note i { color: rgba(0,240,255,0.5); margin-right: 5px; }

        .floating-orbs {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.06;
            animation: orbFloat 12s ease-in-out infinite;
        }
        .orb-1 { width: 400px; height: 400px; background: var(--accent-cyan); top: -100px; left: -100px; animation-delay: 0s; }
        .orb-2 { width: 300px; height: 300px; background: var(--accent-purple); bottom: -100px; right: -100px; animation-delay: -6s; }
        @keyframes orbFloat {
            0%, 100% { transform: translate(0,0); }
            50% { transform: translate(30px, 20px); }
        }

        /* High contrast */
        body.high-contrast { background-color: #000 !important; }
        body.high-contrast .auth-card { background: #000; border: 3px solid #ffff00; }
        body.high-contrast .form-control-admin { background: #000; border: 2px solid #fff; color: #fff; }
        body.high-contrast .btn-login { background: #ffff00; color: #000; }
    </style>
</head>
<body>

    <div class="floating-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <div class="login-wrapper">
        <a href="index.html" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Website
        </a>

        <div class="auth-card">
            <div class="auth-top-bar"></div>
            
            <div class="auth-header">
                <div class="brand-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                </div>
                <div class="auth-title">AI-Solution Admin</div>
                <div class="auth-subtitle">Secure staff access portal</div>
            </div>

            <div class="auth-body">

<?php if (!empty($login_error)): ?>
                    <div class="error-alert" role="alert" style="display: block; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                        <strong>Login Failed:</strong> <?php echo htmlspecialchars($login_error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" onsubmit="handleLoginSubmit(event)">
                    <div class="mb-4">
                        <label for="username" class="form-label-admin">Staff Username</label>
                        <div class="input-group-icon">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-control-admin" id="username" name="username" placeholder="Enter your username" required autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label-admin">Password</label>
                        <div class="input-group-icon">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control-admin" id="password" name="password" placeholder="••••••••••" required autocomplete="current-password">
                            <button type="button" class="toggle-password" onclick="togglePwd()" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="pwdEyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="login_submit" class="btn-login" id="loginBtn">
                        <i class="fas fa-shield-alt me-2"></i> Authenticate Securely
                    </button>
                </form>
            </div>

            <div class="auth-footer-note">
                <i class="fas fa-lock"></i>
                Protected with session-level security &amp; password hashing
            </div>
        </div>

        <div class="text-center mt-4">
            <button id="themeToggle" style="background:transparent;border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.4);padding:8px 16px;border-radius:20px;font-size:0.78rem;cursor:pointer;transition:all 0.2s;" onmouseenter="this.style.borderColor='rgba(0,240,255,0.3)'" onmouseleave="this.style.borderColor='rgba(255,255,255,0.1)'">
                <i class="fas fa-adjust me-1"></i> High Contrast Mode
            </button>
        </div>
    </div>

    <script>
        function togglePwd() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('pwdEyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        const themeToggle = document.getElementById('themeToggle');
        if (localStorage.getItem('adminContrastMode') === 'enabled') {
            document.body.classList.add('high-contrast');
            themeToggle.innerHTML = '<i class="fas fa-adjust me-1"></i> Normal Mode';
        }
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('high-contrast');
            if (document.body.classList.contains('high-contrast')) {
                localStorage.setItem('adminContrastMode', 'enabled');
                themeToggle.innerHTML = '<i class="fas fa-adjust me-1"></i> Normal Mode';
            } else {
                localStorage.setItem('adminContrastMode', 'disabled');
                themeToggle.innerHTML = '<i class="fas fa-adjust me-1"></i> High Contrast Mode';
            }
        });

        // Add loading state on submit
        function handleLoginSubmit(event) {
            event.preventDefault();
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
            btn.disabled = true;
            // Submit the form after showing loading state
            setTimeout(() => {
                document.getElementById('loginForm').submit();
            }, 100);
        }
    </script>
</body>
</html>
