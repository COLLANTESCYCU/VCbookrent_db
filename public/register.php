<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';

$auth = Auth::getInstance();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: books.php');
    exit;
}

Flash::init();

$error = '';
$successMsg = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $contact = $_POST['contact'] ?? '';
        $address = $_POST['address'] ?? '';

        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            throw new Exception('Name, email, and password are required');
        }
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match');
        }
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }

        $authCtrl = new AuthController();
        $userId = $authCtrl->register([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'contact' => $contact,
            'address' => $address
        ]);

        $successMsg = 'Registration successful! Redirecting to login...';
        // Redirect after showing message
        header('refresh:2;url=login.php');
    } catch (Exception $e) {
        $error = $e->getMessage();
        $formData = $_POST;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - VCBookRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .register-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 40px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            color: #333;
            font-size: 28px;
            font-weight: bold;
        }
        .register-header p {
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
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            width: 100%;
            margin-top: 10px;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .alert {
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>📚 VCBookRent</h1>
            <p>Create Your Account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMsg) ?>
        </div>
        <?php else: ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($formData['name'] ?? '') ?>" placeholder="Enter your full name">
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address *</label>
                <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($formData['email'] ?? '') ?>" placeholder="Enter your email">
            </div>

            <div class="form-row">
                <div class="mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" class="form-control" name="contact" value="<?= htmlspecialchars($formData['contact'] ?? '') ?>" placeholder="09XX XXX XXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control" name="password" required placeholder="At least 6 characters">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" rows="2" placeholder="Enter your address"><?= htmlspecialchars($formData['address'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password *</label>
                <input type="password" class="form-control" name="confirm_password" required placeholder="Confirm your password">
            </div>

            <button type="submit" class="btn btn-register">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>

        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>