<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Helpers/Flash.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Models/User.php';

$auth = Auth::getInstance();

// Redirect only regular users away from registration (they're already registered)
// Allow admin/staff to access register page
if ($auth->isLoggedIn()) {
    $currentUser = $auth->currentUser();
    if ($currentUser['role'] === 'user') {
        header('Location: index.php');
        exit;
    }
    // Admin/staff can view this page
}

Flash::init();

$error = '';
$errors = [];
$successMsg = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $contact = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // Validation
        if (empty($fullname)) {
            $errors[] = 'Full name is required';
        } elseif (strlen($fullname) < 3) {
            $errors[] = 'Full name must be at least 3 characters';
        } elseif (strlen($fullname) > 100) {
            $errors[] = 'Full name cannot exceed 100 characters';
        }

        if (empty($email)) {
            $errors[] = 'Email address is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } elseif (strlen($email) > 100) {
            $errors[] = 'Email cannot exceed 100 characters';
        } else {
            // Check if email already exists
            $userModel = new User();
            if ($userModel->findByEmail($email)) {
                $errors[] = 'Email address already registered. Please use a different email or login instead';
            }
        }

        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        } elseif (strlen($password) > 50) {
            $errors[] = 'Password cannot exceed 50 characters';
        }

        if (empty($confirmPassword)) {
            $errors[] = 'Please confirm your password';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }

        if (empty($contact)) {
            $errors[] = 'Contact number is required';
        } elseif (!preg_match('/^[0-9\s\-\+\(\)]{7,}$/', $contact)) {
            $errors[] = 'Contact number format is invalid';
        }

        if (empty($address)) {
            $errors[] = 'Address is required';
        } elseif (strlen($address) > 255) {
            $errors[] = 'Address cannot exceed 255 characters';
        }

        // Role validation - public registration is always 'user'
        $role = 'user'; // Public registrations can only be users

        // If no errors, proceed with registration
        if (empty($errors)) {
            $authCtrl = new AuthController();
            $userId = $authCtrl->register([
                'name' => $fullname,
                'email' => $email,
                'password' => $password,
                'contact' => $contact,
                'address' => $address,
                'role' => $role
            ]);

            $successMsg = 'Registration successful! Redirecting to login...';
            // Redirect after showing message
            header('refresh:2;url=login.php');
        } else {
            $error = implode('; ', $errors);
            $formData = $_POST;
        }
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
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            height: 4px;
            border-radius: 2px;
            background: #ddd;
            overflow: hidden;
        }
        .password-strength.weak { background: #dc3545; }
        .password-strength.fair { background: #ffc107; }
        .password-strength.good { background: #17a2b8; }
        .password-strength.strong { background: #28a745; }
        .password-strength-text {
            font-size: 11px;
            margin-top: 3px;
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
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>📚 VCBookRent</h1>
            <p>Create Your Account</p>
            <div style="font-size: 12px; color: #666; margin-top: 10px;">
                <i class="bi bi-info-circle"></i> Public registration for users only. Admin and staff accounts are managed by administrators.
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-exclamation-circle"></i> Registration Error:</strong><br>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-check-circle"></i> Success!</strong><br>
            <?= htmlspecialchars($successMsg) ?>
        </div>
        <?php else: ?>

        <form method="POST" action="register.php" id="registerForm" onsubmit="return validateRegisterForm()">
            <div class="mb-3">
                <label class="form-label">Account Type</label>
                <select class="form-select" name="role" disabled>
                    <option value="user" selected>👤 Regular User</option>
                </select>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    Public registration creates user accounts only. Contact admin for staff/admin access.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Full Name <span style="color: red;">*</span></label>
                <input type="text" class="form-control" id="fullnameInput" name="fullname" required value="<?= htmlspecialchars($formData['fullname'] ?? '') ?>" placeholder="Enter your full name" minlength="3" maxlength="100" oninput="validateField('fullname')">
                <div id="fullnameError" style="font-size: 12px; color: #dc3545; margin-top: 3px;"></div>
                <div style="font-size: 12px; color: #666; margin-top: 3px;">Must be at least 3 characters</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address <span style="color: red;">*</span></label>
                <input type="email" class="form-control" id="emailInput" name="email" required value="<?= htmlspecialchars($formData['email'] ?? '') ?>" placeholder="example@email.com" maxlength="100" oninput="validateField('email')">
                <div id="emailError" style="font-size: 12px; color: #dc3545; margin-top: 3px;"></div>
                <div style="font-size: 12px; color: #666; margin-top: 3px;">We'll use this to send you updates</div>
            </div>

            <div class="form-row">
                <div class="mb-3">
                    <label class="form-label">Contact Number <span style="color: red;">*</span></label>
                    <input type="tel" class="form-control" id="contactInput" name="contact" required value="<?= htmlspecialchars($formData['contact'] ?? '') ?>" placeholder="09XX XXX XXXX" title="Valid phone number format required" oninput="validateField('contact')">
                    <div id="contactError" style="font-size: 12px; color: #dc3545; margin-top: 3px;"></div>
                    <div style="font-size: 12px; color: #666; margin-top: 3px;">Minimum 7 characters</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password <span style="color: red;">*</span></label>
                    <input type="password" class="form-control" id="passwordInput" name="password" required placeholder="At least 6 characters" minlength="6" maxlength="50" oninput="checkPasswordStrength()">
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="password-strength-text" id="passwordStrengthText"></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Address <span style="color: red;">*</span></label>
                <textarea class="form-control" id="addressInput" name="address" required rows="2" placeholder="Enter your address" maxlength="255" oninput="validateField('address')"><?= htmlspecialchars($formData['address'] ?? '') ?></textarea>
                <div id="addressError" style="font-size: 12px; color: #dc3545; margin-top: 3px;"></div>
                <div style="font-size: 12px; color: #666; margin-top: 3px;">Maximum 255 characters</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password <span style="color: red;">*</span></label>
                <input type="password" class="form-control" id="confirmPasswordInput" name="confirm_password" required placeholder="Confirm your password" oninput="checkPasswordMatch()">
                <div id="passwordMatchText" style="font-size: 12px; margin-top: 3px;"></div>
            </div>

            <button type="submit" class="btn btn-register" id="submitBtn">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>

        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateField(fieldName) {
            const errorDiv = document.getElementById(fieldName + 'Error');
            let error = '';
            
            switch(fieldName) {
                case 'fullname':
                    const fullname = document.getElementById('fullnameInput').value.trim();
                    if (fullname.length < 3) {
                        error = 'Full name must be at least 3 characters';
                    } else if (!fullname.match(/^[a-zA-Z\s]+$/)) {
                        error = 'Full name can only contain letters and spaces';
                    } else if (fullname.length > 100) {
                        error = 'Full name cannot exceed 100 characters';
                    }
                    break;
                    
                case 'email':
                    const email = document.getElementById('emailInput').value.trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        error = 'Please enter a valid email address';
                    } else if (email.length > 100) {
                        error = 'Email cannot exceed 100 characters';
                    }
                    break;
                    
                case 'contact':
                    const contact = document.getElementById('contactInput').value.trim();
                    if (!contact) {
                        error = 'Contact number is required';
                    } else if (contact.length < 7) {
                        error = 'Contact number must be at least 7 characters';
                    } else if (!contact.match(/^[0-9\s\-\+\(\)]+$/)) {
                        error = 'Contact number can only contain numbers and common phone characters';
                    }
                    break;
                    
                case 'address':
                    const address = document.getElementById('addressInput').value.trim();
                    if (!address) {
                        error = 'Address is required';
                    } else if (address.length > 255) {
                        error = 'Address cannot exceed 255 characters';
                    }
                    break;
            }
            
            errorDiv.textContent = error;
        }
        
        function validateRegisterForm() {
            // Validate all fields
            validateField('fullname');
            validateField('email');
            validateField('contact');
            validateField('address');
            
            const fullnameError = document.getElementById('fullnameError').textContent;
            const emailError = document.getElementById('emailError').textContent;
            const contactError = document.getElementById('contactError').textContent;
            const addressError = document.getElementById('addressError').textContent;
            const passwordError = document.getElementById('passwordMatchText').textContent;
            
            // Check for any errors
            if (fullnameError || emailError || contactError || addressError) {
                alert('Please fix the errors in the form before submitting');
                return false;
            }
            
            const password = document.getElementById('passwordInput').value;
            const confirmPassword = document.getElementById('confirmPasswordInput').value;
            
            if (!password || !confirmPassword) {
                alert('Password and confirm password are required');
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match. Please check and try again.');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters');
                return false;
            }
            
            return true;
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('passwordInput').value;
            const strengthDiv = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            
            let strength = '';
            let strength_level = 0;
            
            if (password.length >= 6) strength_level++;
            if (password.length >= 8) strength_level++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength_level++;
            if (/[0-9]/.test(password)) strength_level++;
            if (/[!@#$%^&*]/.test(password)) strength_level++;
            
            switch(strength_level) {
                case 0:
                case 1:
                    strength = 'weak';
                    strengthText.textContent = '❌ Weak - Add numbers, uppercase, or special characters';
                    strengthText.style.color = '#dc3545';
                    break;
                case 2:
                    strength = 'fair';
                    strengthText.textContent = '⚠️ Fair - Add uppercase letters or special characters';
                    strengthText.style.color = '#ffc107';
                    break;
                case 3:
                    strength = 'good';
                    strengthText.textContent = '✓ Good - Password is acceptable';
                    strengthText.style.color = '#17a2b8';
                    break;
                case 4:
                case 5:
                    strength = 'strong';
                    strengthText.textContent = '✓✓ Strong - Excellent password!';
                    strengthText.style.color = '#28a745';
                    break;
            }
            
            strengthDiv.className = 'password-strength ' + strength;
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('passwordInput').value;
            const confirmPassword = document.getElementById('confirmPasswordInput').value;
            const matchText = document.getElementById('passwordMatchText');
            
            if (password && confirmPassword) {
                if (password === confirmPassword) {
                    matchText.textContent = '✓ Passwords match';
                    matchText.style.color = '#28a745';
                } else {
                    matchText.textContent = '❌ Passwords do not match';
                    matchText.style.color = '#dc3545';
                }
            } else {
                matchText.textContent = '';
            }
        }
    </script>
</body>
</html>