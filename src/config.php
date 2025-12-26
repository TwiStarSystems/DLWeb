<?php
// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_USER', getenv('DB_USER') ?: 'webuser');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'webpass');
define('DB_NAME', getenv('DB_NAME') ?: 'webdb');

// Application settings
define('SITE_TITLE', 'My Website');
define('PAGES_DIR', __DIR__ . '/pages');
define('UPLOADS_DIR', __DIR__ . '/uploads');

// Database connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Helper function to sanitize output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Helper function to parse markdown (simple implementation)
function parseMarkdown($text) {
    $lines = explode("\n", $text);
    $html = [];
    $inList = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line)) {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            continue;
        }
        
        // Headers
        if (preg_match('/^### (.+)$/', $line, $matches)) {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            $html[] = '<h3>' . htmlspecialchars($matches[1]) . '</h3>';
        } elseif (preg_match('/^## (.+)$/', $line, $matches)) {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            $html[] = '<h2>' . htmlspecialchars($matches[1]) . '</h2>';
        } elseif (preg_match('/^# (.+)$/', $line, $matches)) {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            $html[] = '<h1>' . htmlspecialchars($matches[1]) . '</h1>';
        }
        // List items
        elseif (preg_match('/^\- (.+)$/', $line, $matches)) {
            if (!$inList) {
                $html[] = '<ul>';
                $inList = true;
            }
            $processed = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $matches[1]);
            $processed = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $processed);
            $processed = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $processed);
            $html[] = '<li>' . $processed . '</li>';
        }
        // Regular text
        else {
            if ($inList) { $html[] = '</ul>'; $inList = false; }
            $processed = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
            $processed = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $processed);
            $processed = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $processed);
            $html[] = '<p>' . $processed . '</p>';
        }
    }
    
    if ($inList) {
        $html[] = '</ul>';
    }
    
    return implode("\n", $html);
}
?>
