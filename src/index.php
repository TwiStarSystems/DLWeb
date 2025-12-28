<?php
require_once 'auth.php';
require_once 'settings.php';

// Get page slug from URL
$slug = isset($_GET['page']) ? $_GET['page'] : 'home';

// Fetch page from database
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    
    if (!$page) {
        $page = [
            'title' => '404 - Page Not Found',
            'content' => '<h1>Page Not Found</h1><p>The page you are looking for does not exist.</p>',
            'content_type' => 'html'
        ];
    }
} catch (PDOException $e) {
    die("Error loading page: " . $e->getMessage());
}

// Process content based on type
$content = $page['content'];
if ($page['content_type'] === 'markdown') {
    $content = parseMarkdown($content);
} elseif ($page['content_type'] === 'blocks') {
    // Decode JSON blocks
    $blocks = json_decode($content, true);
    if ($blocks) {
        $content = '';
        foreach ($blocks as $block) {
            $content .= $block['html'] ?? '';
        }
    }
}

$user = currentUser();
$siteTitle = siteTitle();
$favicon = siteFavicon();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page['title']); ?> - <?php echo h($siteTitle); ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" href="<?php echo h($favicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <style><?php echo colorCSS(); ?></style>
</head>
<body>
    <div class="page-wrapper">
        <nav class="navbar">
            <div class="container">
                <a href="/" class="logo"><?php echo h($siteTitle); ?></a>
                <ul class="nav-menu">
                    <li><a href="/">Home</a></li>
                    <li><a href="?page=about">About</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (hasPermission('view_admin')): ?>
                        <li><a href="/admin/">Editor</a></li>
                        <?php endif; ?>
                        <?php if (hasPermission('manage_settings')): ?>
                        <li><a href="/admin/settings.php">Settings</a></li>
                        <?php endif; ?>
                        <li class="user-info">
                            <span>👤 <?php echo h($user['display_name']); ?></span>
                        </li>
                        <li><a href="/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="/login.php">Login</a></li>
                        <?php if (allowRegistration()): ?>
                        <li><a href="/register.php">Register</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
        
        <main class="container">
            <article class="content">
                <?php echo $content; ?>
            </article>
        </main>
        
        <footer class="footer">
            <p><?php echo h(footerText()); ?></p>
        </footer>
    </div>
</body>
</html>
