<?php
/**
 * User Management Page (Admin Only)
 */
require_once '../auth.php';

// Require manage_users permission
auth()->requirePermission('manage_users', '/403.php');

$currentUser = currentUser();
$message = '';
$messageType = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (!auth()->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_role':
            $userId = $_POST['user_id'] ?? null;
            $roleId = $_POST['role_id'] ?? null;
            
            if ($userId && $roleId) {
                $result = auth()->updateUserRole($userId, $roleId);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            break;
            
        case 'toggle_status':
            $userId = $_POST['user_id'] ?? null;
            
            if ($userId) {
                $result = auth()->toggleUserStatus($userId);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    if (!auth()->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_user') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $displayName = trim($_POST['display_name'] ?? '');
            $roleId = $_POST['role_id'] ?? 2;
            
            if (empty($username) || empty($email) || empty($password)) {
                $message = 'Please fill in all required fields';
                $messageType = 'error';
            } else {
                // Register user
                $result = auth()->register($username, $email, $password, $displayName);
                
                if ($result['success']) {
                    // Update role if not default subscriber
                    if ($roleId != 2) {
                        $db = getDB();
                        $stmt = $db->prepare("UPDATE users SET role_id = ? WHERE username = ?");
                        $stmt->execute([$roleId, $username]);
                    }
                    $message = 'User created successfully!';
                    $messageType = 'success';
                } else {
                    $message = $result['message'];
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get all users and roles
$users = auth()->getAllUsers();
$roles = auth()->getRoles();
$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo h(SITE_TITLE); ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/editor.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo"><?php echo h(SITE_TITLE); ?></a>
            <ul class="nav-menu">
                <li><a href="/">View Site</a></li>
                <li><a href="/admin/">Pages</a></li>
                <li><a href="/admin/users.php" class="active">Users</a></li>
                <li class="user-menu">
                    <span class="user-badge">
                        <?php echo h($currentUser['display_name']); ?>
                        <small>(<?php echo h($currentUser['role_display_name']); ?>)</small>
                    </span>
                    <a href="/logout.php" class="btn btn-sm">Logout</a>
                </li>
            </ul>
        </div>
    </nav>
    
    <div class="editor-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Create User</h2>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" style="margin: 1rem;">
                <?php echo h($message); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" style="padding: 1rem;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="display_name">Display Name</label>
                    <input type="text" id="display_name" name="display_name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="role_id">Role</label>
                    <select id="role_id" name="role_id" class="form-control">
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo $role['name'] === 'subscriber' ? 'selected' : ''; ?>>
                            <?php echo h($role['display_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create User</button>
            </form>
        </aside>
        
        <main class="editor-main" style="padding: 2rem;">
            <h1 style="color: var(--accent); margin-bottom: 1.5rem;">User Management</h1>
            
            <div class="card">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?php echo $user['id']; ?>">
                            <td>
                                <strong><?php echo h($user['display_name'] ?: $user['username']); ?></strong>
                                <br><small style="color: var(--text-muted);">@<?php echo h($user['username']); ?></small>
                            </td>
                            <td><?php echo h($user['email']); ?></td>
                            <td>
                                <?php if ($user['id'] == $currentUser['id']): ?>
                                    <span class="role-<?php echo h($user['role_name']); ?>">
                                        <?php echo h($user['role_display_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <select class="role-select" data-user-id="<?php echo $user['id']; ?>" onchange="updateRole(this)">
                                        <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" <?php echo $role['id'] == $user['role_id'] ? 'selected' : ''; ?>>
                                            <?php echo h($role['display_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $user['is_active'] ? '● Active' : '○ Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <?php echo date('M j, Y H:i', strtotime($user['last_login'])); ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['id'] != $currentUser['id']): ?>
                                    <button class="btn btn-sm" onclick="toggleStatus(<?php echo $user['id']; ?>)">
                                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card" style="margin-top: 2rem;">
                <h3 style="color: var(--accent); margin-bottom: 1rem;">Role Permissions</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Level</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><strong class="role-<?php echo h($role['name']); ?>"><?php echo h($role['display_name']); ?></strong></td>
                            <td><?php echo $role['level']; ?></td>
                            <td><?php echo h($role['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
        const csrfToken = '<?php echo h($csrfToken); ?>';
        
        function updateRole(select) {
            const userId = select.dataset.userId;
            const roleId = select.value;
            
            fetch('/admin/users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=1&action=update_role&user_id=${userId}&role_id=${roleId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Role updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                    location.reload();
                }
            })
            .catch(error => {
                alert('Error: ' + error);
                location.reload();
            });
        }
        
        function toggleStatus(userId) {
            if (!confirm('Are you sure you want to change this user\'s status?')) {
                return;
            }
            
            fetch('/admin/users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=1&action=toggle_status&user_id=${userId}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
    </script>
</body>
</html>
