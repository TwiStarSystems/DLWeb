<?php
/**
 * Registration Page
 */
require_once 'auth.php';
require_once 'settings.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /');
    exit;
}

// Check if registration is allowed
if (!allowRegistration()) {
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';
$formData = [
    'username' => '',
    'email' => '',
    'display_name' => ''
];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!auth()->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $formData['username'] = trim($_POST['username'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['display_name'] = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        if (empty($formData['username']) || empty($formData['email']) || empty($password)) {
            $error = 'Please fill in all required fields';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match';
        } else {
            $result = auth()->register(
                $formData['username'],
                $formData['email'],
                $password,
                $formData['display_name'] ?: null
            );
            
            if ($result['success']) {
                header('Location: /login.php?registered=1');
                exit;
            } else {
                $error = $result['message'];
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
    <title>Register - <?php echo h(siteTitle()); ?></title>
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
                <li><a href="/login.php">Login</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Join us today</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo h($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo h($formData['username']); ?>" 
                           placeholder="Choose a username" required autofocus
                           pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores">
                    <small class="form-hint">3-50 characters, letters, numbers, and underscores only</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo h($formData['email']); ?>" 
                           placeholder="Enter your email address" required>
                </div>
                
                <div class="form-group">
                    <label for="display_name">Display Name</label>
                    <input type="text" id="display_name" name="display_name" class="form-control" 
                           value="<?php echo h($formData['display_name']); ?>" 
                           placeholder="How should we call you?">
                    <small class="form-hint">Optional - defaults to your username</small>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Create a strong password" required minlength="8">
                    <small class="form-hint">Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" 
                           placeholder="Confirm your password" required minlength="8">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="/login.php">Login here</a></p>
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
