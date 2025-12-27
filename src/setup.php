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

// Check if tables already exist
function checkTablesExist($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'pages'");
        return $stmt->rowCount() > 0;
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
            $message = 'Database schema imported successfully! You can now use the application.';
            $messageType = 'success';
            $tablesExist = true;
        } else {
            $message = 'Failed to import schema: ' . $result['error'];
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'reset' && $dbConnected) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS pages");
            $result = importSchema($pdo, $schemaFile);
            if ($result['success']) {
                $message = 'Database reset and schema re-imported successfully!';
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #1a202c;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
        }
        .status-card {
            background: #f7fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
        }
        .status-icon.success {
            background: #c6f6d5;
            color: #22543d;
        }
        .status-icon.error {
            background: #fed7d7;
            color: #822727;
        }
        .status-icon.warning {
            background: #fefcbf;
            color: #744210;
        }
        .status-label {
            flex: 1;
            color: #4a5568;
        }
        .status-value {
            font-weight: 600;
            color: #1a202c;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        .message.error {
            background: #fed7d7;
            color: #822727;
            border: 1px solid #fc8181;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-danger {
            background: #e53e3e;
            color: white;
        }
        .btn-danger:hover {
            background: #c53030;
        }
        .btn-secondary {
            background: #edf2f7;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .warning-text {
            color: #c53030;
            font-size: 14px;
            margin-top: 10px;
        }
        .success-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
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
                    <span class="status-value" style="font-size: 12px; color: #e53e3e;">
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
                <p style="color: #22543d; margin-bottom: 15px;">
                    ✅ Your database is ready! You can start using the application.
                </p>
                <div class="actions">
                    <a href="/" class="btn btn-primary">Go to Homepage</a>
                    <a href="/admin/" class="btn btn-secondary">Go to Admin</a>
                </div>
                
                <div style="margin-top: 30px;">
                    <p style="color: #718096; font-size: 14px; margin-bottom: 10px;">
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
