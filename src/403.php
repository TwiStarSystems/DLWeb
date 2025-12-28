<?php
/**
 * 403 Forbidden Page
 */
require_once 'auth.php';
require_once 'settings.php';

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?php echo h(siteTitle()); ?></title>
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
                <?php if (isLoggedIn()): ?>
                <li><a href="/logout.php">Logout</a></li>
                <?php else: ?>
                <li><a href="/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    
    <div class="auth-container">
        <div class="auth-card error-card">
            <div class="error-icon">🚫</div>
            <h1>Access Denied</h1>
            <p>Sorry, you don't have permission to access this page.</p>
            
            <?php if (isLoggedIn()): ?>
            <p class="error-detail">
                You are logged in as <strong><?php echo h(currentUser()['display_name']); ?></strong> 
                with the role <strong><?php echo h(currentUser()['role_display_name']); ?></strong>.
            </p>
            <p>If you believe you should have access, please contact an administrator.</p>
            <?php else: ?>
            <p>Please <a href="/login.php">log in</a> to access this content.</p>
            <?php endif; ?>
            
            <div class="error-actions">
                <a href="/" class="btn btn-primary">Go Home</a>
                <?php if (isLoggedIn()): ?>
                <a href="/logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                <a href="/login.php" class="btn btn-secondary">Login</a>
                <?php endif; ?>
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
