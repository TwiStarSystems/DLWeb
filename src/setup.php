<?php
/**
 * First-time Setup Page
 * Imports the database schema if tables don't exist
 */

require_once 'config.php';

$message = '';
$messageType = '';
$tablesExist = false;
$schemaFile = '/var/www/html/schema.sql';

// Check if tables already exist (check multiple tables now)
function checkTablesExist($pdo) {
    try {
        // Check for roles, users, pages, and settings tables to verify full schema
        $stmt = $pdo->query("SHOW TABLES LIKE 'roles'");
        $rolesExist = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $usersExist = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'pages'");
        $pagesExist = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        $settingsExist = $stmt->rowCount() > 0;
        
        return $rolesExist && $usersExist && $pagesExist && $settingsExist;
    } catch (PDOException $e) {
        return false;
    }
}

// Check database connection
function checkDatabaseConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return ['success' => true, 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Import schema
function importSchema($pdo, $schemaFile) {
    if (!file_exists($schemaFile)) {
        return ['success' => false, 'error' => "Schema file not found: $schemaFile"];
    }
    
    $sql = file_get_contents($schemaFile);
    if (empty($sql)) {
        return ['success' => false, 'error' => 'Schema file is empty'];
    }
    
    try {
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Check connection status
$dbCheck = checkDatabaseConnection();
$dbConnected = $dbCheck['success'];

if ($dbConnected) {
    $pdo = $dbCheck['pdo'];
    $tablesExist = checkTablesExist($pdo);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'import' && $dbConnected) {
        $result = importSchema($pdo, $schemaFile);
        if ($result['success']) {
            $message = 'Database schema imported successfully! You can now use the application. Default admin credentials: admin / admin123';
            $messageType = 'success';
            $tablesExist = true;
        } else {
            $message = 'Failed to import schema: ' . $result['error'];
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'reset' && $dbConnected) {
        try {
            // Drop tables in correct order (foreign keys)
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DROP TABLE IF EXISTS user_sessions");
            $pdo->exec("DROP TABLE IF EXISTS role_permissions");
            $pdo->exec("DROP TABLE IF EXISTS pages");
            $pdo->exec("DROP TABLE IF EXISTS users");
            $pdo->exec("DROP TABLE IF EXISTS permissions");
            $pdo->exec("DROP TABLE IF EXISTS roles");
            $pdo->exec("DROP TABLE IF EXISTS settings");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $result = importSchema($pdo, $schemaFile);
            if ($result['success']) {
                $message = 'Database reset and schema re-imported successfully! Default admin credentials: admin / admin123';
                $messageType = 'success';
                $tablesExist = true;
            } else {
                $message = 'Failed to import schema after reset: ' . $result['error'];
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Failed to reset database: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Check schema file exists
$schemaExists = file_exists($schemaFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - <?php echo h(SITE_TITLE); ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-container {
            background: linear-gradient(180deg, var(--bg-panel), var(--bg-panel-dark));
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px var(--shadow-color);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .setup-container h1 {
            color: var(--accent);
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: var(--text-muted);
            margin-bottom: 30px;
        }
        .status-label {
            flex: 1;
            color: var(--text-muted);
        }
        .status-value {
            font-weight: 600;
            color: var(--text-main);
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .warning-text {
            color: #ff6b6b;
            font-size: 14px;
            margin-top: 10px;
        }
        .success-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        .success-text {
            color: #90EE90;
            margin-bottom: 15px;
        }
        .muted-text {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 10px;
        }
        .error-text {
            font-size: 12px;
            color: #ff6b6b;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🚀 First-Time Setup</h1>
        <p class="subtitle">Configure your database to get started</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="status-card">
            <div class="status-item">
                <span class="status-icon <?php echo $dbConnected ? 'success' : 'error'; ?>">
                    <?php echo $dbConnected ? '✓' : '✗'; ?>
                </span>
                <span class="status-label">Database Connection</span>
                <span class="status-value"><?php echo $dbConnected ? 'Connected' : 'Failed'; ?></span>
            </div>
            
            <?php if (!$dbConnected): ?>
                <div class="status-item">
                    <span class="status-icon error">!</span>
                    <span class="status-label">Error</span>
                    <span class="status-value error-text">
                        <?php echo h($dbCheck['error'] ?? 'Unknown error'); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <div class="status-item">
                <span class="status-icon <?php echo $schemaExists ? 'success' : 'error'; ?>">
                    <?php echo $schemaExists ? '✓' : '✗'; ?>
                </span>
                <span class="status-label">Schema File</span>
                <span class="status-value"><?php echo $schemaExists ? 'Found' : 'Missing'; ?></span>
            </div>
            
            <div class="status-item">
                <span class="status-icon <?php echo $tablesExist ? 'success' : 'warning'; ?>">
                    <?php echo $tablesExist ? '✓' : '!'; ?>
                </span>
                <span class="status-label">Database Tables</span>
                <span class="status-value"><?php echo $tablesExist ? 'Installed' : 'Not Installed'; ?></span>
            </div>
        </div>
        
        <?php if ($tablesExist): ?>
            <div class="success-actions">
                <p class="success-text">
                    ✅ Your database is ready! You can start using the application.
                </p>
                <div class="actions">
                    <a href="/" class="btn btn-primary">Go to Homepage</a>
                    <a href="/admin/" class="btn btn-secondary">Go to Admin</a>
                </div>
                
                <div style="margin-top: 30px;">
                    <p class="muted-text">
                        Need to reset the database?
                    </p>
                    <form method="post" onsubmit="return confirm('Are you sure? This will delete all pages and data!');">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" class="btn btn-danger">Reset Database</button>
                    </form>
                    <p class="warning-text">⚠️ This will delete all existing data and recreate tables with sample content.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="actions">
                <?php if ($dbConnected && $schemaExists): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="import">
                        <button type="submit" class="btn btn-primary">Import Database Schema</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-primary" disabled>Import Database Schema</button>
                    <p class="warning-text">
                        <?php if (!$dbConnected): ?>
                            Please ensure MySQL is running and connection settings are correct.
                        <?php elseif (!$schemaExists): ?>
                            Schema file not found at <?php echo h($schemaFile); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
