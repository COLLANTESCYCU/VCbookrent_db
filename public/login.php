<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';

$auth = Auth::getInstance();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->currentUser();
    $redirectTo = 'dashboard.php';
    if ($user['role'] === 'user') {
        $redirectTo = 'books.php';
    }
    header('Location: ' . $redirectTo);
    exit;
}

Flash::init();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $authCtrl = new AuthController();
        $user = $authCtrl->login($_POST['email'] ?? '', $_POST['password'] ?? '');
        
        // Redirect based on role
        if ($user['role'] === 'admin' || $user['role'] === 'staff') {
            header('Location: dashboard.php');
        } else {
            header('Location: books.php');
        }
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
        $email = $_POST['email'] ?? '';
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
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($email) ?>" placeholder="Enter your email">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required placeholder="Enter your password">
            </div>
            <button type="submit" class="btn btn-login">Sign In</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Sign up here</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>