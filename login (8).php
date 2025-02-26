<?php
include 'includes/db.php';
include 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (authenticateUser($pdo, $username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ConnectHub</title>
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
        .login-card {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            background-image: linear-gradient(135deg, #4cc9f0 0%, #4361ee 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }
        .login-header h1 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        .login-body {
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
        .btn-login {
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
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
        .login-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-network-wired login-icon"></i>
                        <h1>ConnectHub</h1>
                        <p>Connect with friends and share what matters</p>
                    </div>
                    <div class="login-body">
                        <h2 class="mb-4 text-center">Login</h2>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="login.php" method="POST">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                                <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </button>
                        </form>
                        
                        <div class="divider">or</div>
                        
                        <div class="alternative-actions">
                            <a href="signup.php"><i class="fas fa-user-plus me-1"></i> Sign Up</a>
                            <a href="index.php"><i class="fas fa-user-secret me-1"></i> Continue as Guest</a>
                        </div>
                    </div>
                </div>
                <div class="login-footer">
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
</body>
</html>