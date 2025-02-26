<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Try to register user
        if (registerUser($pdo, $username, $password)) {
            // Redirect to login page with success message
            header('Location: login.php?registered=1');
            exit;
        } else {
            $error = "Username already exists or registration failed";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ConnectHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        body {
            background-color: var(--background-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .signup-card {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .signup-header {
            background-image: linear-gradient(135deg, #4cc9f0 0%, #4361ee 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }
        .signup-header h1 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .signup-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        .signup-body {
            padding: 40px;
            background-color: white;
        }
        .form-floating {
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 10px;
            height: 56px;
            padding: 1rem 0.75rem;
        }
        .form-floating label {
            padding: 1rem 0.75rem;
        }
        .btn-signup {
            height: 56px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 10px;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #6c757d;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider::before {
            margin-right: 10px;
        }
        .divider::after {
            margin-left: 10px;
        }
        .alternative-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }
        .alternative-actions a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .alternative-actions a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        .signup-footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        .signup-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: white;
        }
        .password-strength {
            height: 5px;
            border-radius: 5px;
            background-color: #e9ecef;
            margin-top: 5px;
            overflow: hidden;
        }
        .password-strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }
        .weak { width: 30%; background-color: #dc3545; }
        .medium { width: 70%; background-color: #ffc107; }
        .strong { width: 100%; background-color: #198754; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="signup-card">
                    <div class="signup-header">
                        <i class="fas fa-network-wired signup-icon"></i>
                        <h1>ConnectHub</h1>
                        <p>Join our community and start sharing</p>
                    </div>
                    <div class="signup-body">
                        <h2 class="mb-4 text-center">Create Account</h2>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="signup.php" method="POST" id="signupForm">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                                <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                <div class="password-strength mt-1">
                                    <div class="password-strength-meter" id="strengthMeter"></div>
                                </div>
                                <small id="passwordHelp" class="form-text text-muted">Password must be at least 6 characters long.</small>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-signup">
                                <i class="fas fa-user-plus me-2"></i> Create Account
                            </button>
                        </form>
                        
                        <div class="divider">or</div>
                        
                        <div class="alternative-actions">
                            <a href="login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                            <a href="index.php"><i class="fas fa-user-secret me-1"></i> Continue as Guest</a>
                        </div>
                    </div>
                </div>
                <div class="signup-footer">
                    <p class="mt-3">
                        <a href="index.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Back to Home
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength meter
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const strengthMeter = document.getElementById('strengthMeter');
            const passwordHelp = document.getElementById('passwordHelp');
            const confirmPassword = document.getElementById('confirm_password');
            const form = document.getElementById('signupForm');
            
            passwordInput.addEventListener('input', function() {
                const value = passwordInput.value;
                let strength = 0;
                
                // Length check
                if (value.length >= 6) {
                    strength += 1;
                }
                
                // Contains number check
                if (/\d/.test(value)) {
                    strength += 1;
                }
                
                // Contains special character check
                if (/[!@#$%^&*(),.?":{}|<>]/.test(value)) {
                    strength += 1;
                }
                
                // Update UI
                strengthMeter.className = 'password-strength-meter';
                
                if (value.length === 0) {
                    strengthMeter.style.width = '0%';
                    passwordHelp.textContent = 'Password must be at least 6 characters long.';
                } else if (strength === 1) {
                    strengthMeter.classList.add('weak');
                    passwordHelp.textContent = 'Weak password. Try adding numbers and special characters.';
                } else if (strength === 2) {
                    strengthMeter.classList.add('medium');
                    passwordHelp.textContent = 'Medium strength password.';
                } else {
                    strengthMeter.classList.add('strong');
                    passwordHelp.textContent = 'Strong password!';
                }
            });
            
            // Password match validation
            function validatePasswordMatch() {
                if (passwordInput.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            passwordInput.addEventListener('change', validatePasswordMatch);
            confirmPassword.addEventListener('keyup', validatePasswordMatch);
            
            // Form validation
            form.addEventListener('submit', function(event) {
                if (passwordInput.value.length < 6) {
                    event.preventDefault();
                    passwordHelp.textContent = 'Password must be at least 6 characters long.';
                    passwordHelp.style.color = '#dc3545';
                }
                
                if (passwordInput.value !== confirmPassword.value) {
                    event.preventDefault();
                    confirmPassword.setCustomValidity("Passwords don't match");
                }
            });
        });
    </script>
</body>
</html>