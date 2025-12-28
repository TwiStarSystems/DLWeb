<?php
/**
 * Login Page
 */
require_once 'auth.php';
require_once 'settings.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = $_SESSION['redirect_after_login'] ?? '/';
    unset($_SESSION['redirect_after_login']);
    header("Location: $redirect");
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($usernameOrEmail) || empty($password)) {
        $error = 'Please enter both username/email and password';
    } else {
        $result = auth()->login($usernameOrEmail, $password, $remember);
        
        if ($result['success']) {
            $redirect = $_SESSION['redirect_after_login'] ?? '/';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Check for success message from registration
if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please log in.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo h(siteTitle()); ?></title>
    <?php if (siteFavicon()): ?>
    <link rel="icon" href="<?php echo h(siteFavicon()); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <style><?php echo colorCSS(); ?></style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo"><?php echo h(siteTitle()); ?></a>
            <ul class="nav-menu">
                <li><a href="/">Home</a></li>
                <li><a href="/register.php">Register</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Login</h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo h($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo h($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo h($_POST['username'] ?? ''); ?>" 
                           placeholder="Enter your username or email" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <div class="form-group form-check">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span class="checkmark"></span>
                        Remember me for 30 days
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="/register.php">Register here</a></p>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <p><?php echo h(footerText()); ?></p>
        </div>
    </footer>
</body>
</html>
