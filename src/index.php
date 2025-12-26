<?php
require_once 'config.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page['title']); ?> - <?php echo h(SITE_TITLE); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo"><?php echo h(SITE_TITLE); ?></a>
            <ul class="nav-menu">
                <li><a href="/">Home</a></li>
                <li><a href="?page=about">About</a></li>
                <li><a href="/admin/">Editor</a></li>
            </ul>
        </div>
    </nav>
    
    <main class="container">
        <article class="content">
            <?php echo $content; ?>
        </article>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo h(SITE_TITLE); ?>. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
