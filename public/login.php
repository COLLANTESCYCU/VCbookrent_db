<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';

$auth = Auth::getInstance();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->currentUser();
    if (isset($user['role']) && ($user['role'] === 'admin' || $user['role'] === 'staff')) {
        header('Location: dashboard.php');
    } else {
        header('Location: home.php');
    }
    exit;
}

Flash::init();

$error = '';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $selectedRole = trim($_POST['role'] ?? '');

        // Validation
        if (empty($email)) {
            $errors[] = 'Email address is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($password)) {
            $errors[] = 'Password is required';
        }

        // If validations pass, attempt login
        if (empty($errors)) {
            $authCtrl = new AuthController();
            $user = $authCtrl->login($email, $password);
            
            // Check if selected role matches user's registered role
            $userRole = $user['role'] ?? 'user';
            if (!empty($selectedRole) && $selectedRole !== $userRole) {
                $error = 'Invalid login attempt. The role you selected does not match your account role.';
            } else {
                // Redirect based on role with proper fallback
                if ($userRole === 'admin' || $userRole === 'staff') {
                    header('Location: dashboard.php');
                } else {
                    header('Location: home.php');
                }
                exit;
            }
        } else {
            $error = implode('; ', $errors);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VCBookRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            font-size: 28px;
            font-weight: bold;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            width: 100%;
            margin-top: 10px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .alert {
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: 12px;
            display: block;
            margin-top: -12px;
            margin-bottom: 10px;
        }
        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>📚 VCBookRent</h1>
            <p>Book Rental Management System</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Login Error:</strong><br>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm" onsubmit="return validateLogin()">
            <input type="hidden" name="role" id="roleInput" value="">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" id="loginEmail" required value="<?= htmlspecialchars($email) ?>" placeholder="Enter your email">
                <div class="info-text">Example: admin@bookrent.com</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" id="loginPassword" required placeholder="Enter your password">
                <div class="info-text">Password must be at least 6 characters</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Login As (Optional)</label>
                <select class="form-select" id="roleDisplay" onchange="updateRoleInfo()">
                    <option value="">-- Select to auto-detect --</option>
                    <option value="admin">👤 Admin</option>
                    <option value="staff">👥 Staff</option>
                    <option value="user">📚 User</option>
                </select>
                <div class="info-text" id="roleDescription">System will auto-detect based on your credentials</div>
            </div>
            <button type="submit" class="btn btn-login">Sign In</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Sign up here</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateRoleInfo() {
            const roleSelect = document.getElementById('roleDisplay');
            const roleInput = document.getElementById('roleInput');
            const roleDescription = document.getElementById('roleDescription');
            const role = roleSelect.value;
            
            // Set the hidden input value for form submission
            roleInput.value = role;
            
            const descriptions = {
                'admin': '👤 Admin - Full system access, manage all features',
                'staff': '👥 Staff - Can manage inventory and rentals',
                'user': '📚 User - Can rent books and manage personal account',
                '': 'System will auto-detect based on your credentials'
            };
            
            roleDescription.textContent = descriptions[role] || '';
        }
        
        function validateLogin() {
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;
            
            if (!email) {
                alert('Please enter your email address');
                return false;
            }
            
            if (!email.includes('@')) {
                alert('Please enter a valid email address');
                return false;
            }
            
            if (!password) {
                alert('Please enter your password');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>