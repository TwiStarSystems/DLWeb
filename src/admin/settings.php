<?php
/**
 * Settings Page - Admin Only
 * Unified settings management with sidebar navigation
 */
require_once '../auth.php';
require_once '../settings.php';

// Require manage_settings permission
auth()->requirePermission('manage_settings', '/403.php');

$currentUser = currentUser();
$message = '';
$messageType = '';

// Get current section from URL
$section = $_GET['section'] ?? 'general';
$validSections = ['general', 'appearance', 'features', 'users'];
if (!in_array($section, $validSections)) {
    $section = 'general';
}

// Handle file uploads
function handleFileUpload($fieldName, $allowedTypes = ['image/png', 'image/x-icon', 'image/jpeg', 'image/gif']) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    $file = $_FILES[$fieldName];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error']);
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowedTypes));
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size: 2MB');
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'favicon_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/';
    $uploadPath = $uploadDir . $filename;
    
    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    return '/uploads/' . $filename;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth()->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'save_general':
                    $updates = [
                        'site_title' => trim($_POST['site_title'] ?? ''),
                        'site_tagline' => trim($_POST['site_tagline'] ?? ''),
                        'footer_text' => trim($_POST['footer_text'] ?? ''),
                        'admin_email' => trim($_POST['admin_email'] ?? ''),
                    ];
                    
                    // Handle favicon upload
                    $faviconPath = handleFileUpload('site_favicon');
                    if ($faviconPath) {
                        $updates['site_favicon'] = $faviconPath;
                    }
                    
                    // Handle favicon removal
                    if (isset($_POST['remove_favicon']) && $_POST['remove_favicon'] === '1') {
                        $updates['site_favicon'] = '';
                    }
                    
                    settings()->updateBatch($updates);
                    $message = 'General settings saved successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'save_appearance':
                    $colorKeys = [
                        'color_bg_main', 'color_bg_panel', 'color_primary',
                        'color_secondary', 'color_accent', 'color_danger',
                        'color_text_main', 'color_text_muted'
                    ];
                    
                    $updates = [];
                    foreach ($colorKeys as $key) {
                        if (isset($_POST[$key])) {
                            $updates[$key] = $_POST[$key];
                        }
                    }
                    
                    settings()->updateBatch($updates);
                    settings()->clearCache();
                    $message = 'Appearance settings saved successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'save_features':
                    $updates = [
                        'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
                        'default_user_role' => (int) ($_POST['default_user_role'] ?? 2),
                        'require_email_verification' => isset($_POST['require_email_verification']) ? '1' : '0',
                    ];
                    
                    settings()->updateBatch($updates);
                    $message = 'Feature settings saved successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'reset_colors':
                    $defaultColors = [
                        'color_bg_main' => '#000030',
                        'color_bg_panel' => '#0000C8',
                        'color_primary' => '#9600E1',
                        'color_secondary' => '#CC9CDF',
                        'color_accent' => '#FFD700',
                        'color_danger' => '#C80000',
                        'color_text_main' => '#ffffff',
                        'color_text_muted' => '#CC9CDF',
                    ];
                    
                    settings()->updateBatch($defaultColors);
                    settings()->clearCache();
                    $message = 'Colors reset to defaults!';
                    $messageType = 'success';
                    break;
                    
                // User management actions
                case 'create_user':
                    $username = trim($_POST['username'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $displayName = trim($_POST['display_name'] ?? '');
                    $roleId = $_POST['role_id'] ?? 2;
                    
                    if (empty($username) || empty($email) || empty($password)) {
                        throw new Exception('Please fill in all required fields');
                    }
                    
                    $result = auth()->register($username, $email, $password, $displayName);
                    
                    if ($result['success']) {
                        if ($roleId != 2) {
                            $db = getDB();
                            $stmt = $db->prepare("UPDATE users SET role_id = ? WHERE username = ?");
                            $stmt->execute([$roleId, $username]);
                        }
                        $message = 'User created successfully!';
                        $messageType = 'success';
                    } else {
                        throw new Exception($result['message']);
                    }
                    break;
                    
                case 'update_role':
                    $userId = $_POST['user_id'] ?? null;
                    $roleId = $_POST['role_id'] ?? null;
                    
                    if ($userId && $roleId) {
                        $result = auth()->updateUserRole($userId, $roleId);
                        if ($result['success']) {
                            $message = 'User role updated!';
                            $messageType = 'success';
                        } else {
                            throw new Exception($result['message']);
                        }
                    }
                    break;
                    
                case 'toggle_status':
                    $userId = $_POST['user_id'] ?? null;
                    if ($userId) {
                        $result = auth()->toggleUserStatus($userId);
                        if ($result['success']) {
                            $message = 'User status updated!';
                            $messageType = 'success';
                        } else {
                            throw new Exception($result['message']);
                        }
                    }
                    break;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get data for sections
$generalSettings = settings()->getByCategory('general');
$appearanceSettings = settings()->getByCategory('appearance');
$featureSettings = settings()->getByCategory('features');
$users = auth()->getAllUsers();
$roles = auth()->getRoles();
$csrfToken = csrfToken();

// Section titles and icons
$sections = [
    'general' => ['title' => 'General', 'icon' => '⚙️'],
    'appearance' => ['title' => 'Appearance', 'icon' => '🎨'],
    'features' => ['title' => 'Features', 'icon' => '🔧'],
    'users' => ['title' => 'Users', 'icon' => '👥'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo h(siteTitle()); ?></title>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style><?php echo colorCSS(); ?></style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo"><?php echo h(siteTitle()); ?></a>
            <ul class="nav-menu">
                <li><a href="/">View Site</a></li>
                <li><a href="/admin/">Pages</a></li>
                <li><a href="/admin/settings.php" class="active">Settings</a></li>
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
    
    <div class="settings-layout">
        <!-- Sidebar Navigation -->
        <aside class="settings-sidebar">
            <h2>Settings</h2>
            <nav class="settings-nav">
                <?php foreach ($sections as $key => $info): ?>
                <a href="?section=<?php echo $key; ?>" class="settings-nav-item <?php echo $section === $key ? 'active' : ''; ?>">
                    <span class="nav-icon"><?php echo $info['icon']; ?></span>
                    <span class="nav-text"><?php echo $info['title']; ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="settings-content">
            <header class="settings-header">
                <h1><?php echo $sections[$section]['icon']; ?> <?php echo $sections[$section]['title']; ?> Settings</h1>
            </header>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo h($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($section === 'general'): ?>
            <!-- General Settings -->
            <form method="POST" action="?section=general" enctype="multipart/form-data" class="settings-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_general">
                
                <div class="settings-card">
                    <h3>Site Identity</h3>
                    
                    <div class="form-group">
                        <label for="site_title">Site Title</label>
                        <input type="text" id="site_title" name="site_title" class="form-control" 
                               value="<?php echo h(getSetting('site_title', 'My Website')); ?>" required>
                        <small class="form-hint">Displayed in browser tabs and the site header</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_tagline">Site Tagline</label>
                        <input type="text" id="site_tagline" name="site_tagline" class="form-control" 
                               value="<?php echo h(getSetting('site_tagline', '')); ?>">
                        <small class="form-hint">A short description or slogan for your site</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_favicon">Site Favicon</label>
                        <?php $currentFavicon = getSetting('site_favicon'); ?>
                        <?php if ($currentFavicon): ?>
                        <div class="current-favicon">
                            <img src="<?php echo h($currentFavicon); ?>" alt="Current favicon" class="favicon-preview">
                            <label class="checkbox-label">
                                <input type="checkbox" name="remove_favicon" value="1">
                                Remove current favicon
                            </label>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="site_favicon" name="site_favicon" class="form-control" 
                               accept=".ico,.png,.jpg,.jpeg,.gif">
                        <small class="form-hint">Recommended: 32x32 or 64x64 pixel PNG or ICO file</small>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>Footer & Contact</h3>
                    
                    <div class="form-group">
                        <label for="footer_text">Footer Text</label>
                        <input type="text" id="footer_text" name="footer_text" class="form-control" 
                               value="<?php echo h(getSetting('footer_text', '© {year} {site_title}. All rights reserved.')); ?>">
                        <small class="form-hint">Use {year} for current year, {site_title} for site name</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Admin Email</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control" 
                               value="<?php echo h(getSetting('admin_email', '')); ?>">
                        <small class="form-hint">Primary contact email for the site</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save General Settings</button>
                </div>
            </form>
            
            <?php elseif ($section === 'appearance'): ?>
            <!-- Appearance Settings -->
            <form method="POST" action="?section=appearance" class="settings-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_appearance">
                
                <div class="settings-card">
                    <h3>Color Scheme</h3>
                    <p class="card-description">Customize the colors used throughout your site</p>
                    
                    <div class="color-grid">
                        <?php foreach ($appearanceSettings as $setting): ?>
                        <div class="color-item">
                            <label for="<?php echo h($setting['setting_key']); ?>">
                                <?php echo h($setting['display_name']); ?>
                            </label>
                            <div class="color-input-group">
                                <input type="color" 
                                       id="<?php echo h($setting['setting_key']); ?>" 
                                       name="<?php echo h($setting['setting_key']); ?>" 
                                       value="<?php echo h($setting['setting_value']); ?>"
                                       class="color-picker">
                                <input type="text" 
                                       class="color-text form-control" 
                                       value="<?php echo h($setting['setting_value']); ?>"
                                       data-color-for="<?php echo h($setting['setting_key']); ?>"
                                       pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                            <small class="form-hint"><?php echo h($setting['description']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>Preview</h3>
                    <div class="color-preview" id="colorPreview">
                        <div class="preview-navbar">
                            <span class="preview-logo">Site Title</span>
                            <span class="preview-nav">Home | About | Contact</span>
                        </div>
                        <div class="preview-content">
                            <h4>Sample Heading</h4>
                            <p>This is how your content will look with the selected colors.</p>
                            <button class="preview-btn-primary">Primary Button</button>
                            <button class="preview-btn-secondary">Secondary</button>
                            <button class="preview-btn-danger">Danger</button>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Appearance</button>
                    <button type="submit" name="action" value="reset_colors" class="btn btn-secondary"
                            onclick="return confirm('Reset all colors to default values?');">
                        Reset to Defaults
                    </button>
                </div>
            </form>
            
            <?php elseif ($section === 'features'): ?>
            <!-- Feature Settings -->
            <form method="POST" action="?section=features" class="settings-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_features">
                
                <div class="settings-card">
                    <h3>User Registration</h3>
                    
                    <div class="form-group">
                        <label class="checkbox-label toggle-switch">
                            <input type="checkbox" name="allow_registration" value="1" 
                                   <?php echo getSetting('allow_registration', true) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                            <span class="toggle-text">Allow User Registration</span>
                        </label>
                        <small class="form-hint">When disabled, only admins can create new user accounts</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_user_role">Default User Role</label>
                        <select id="default_user_role" name="default_user_role" class="form-control">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" 
                                    <?php echo getSetting('default_user_role', 2) == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo h($role['display_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">Role assigned to newly registered users</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label toggle-switch">
                            <input type="checkbox" name="require_email_verification" value="1"
                                   <?php echo getSetting('require_email_verification', false) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                            <span class="toggle-text">Require Email Verification</span>
                        </label>
                        <small class="form-hint">Users must verify their email before logging in (requires email setup)</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Feature Settings</button>
                </div>
            </form>
            
            <?php elseif ($section === 'users'): ?>
            <!-- User Management -->
            <div class="settings-card">
                <h3>Create New User</h3>
                
                <form method="POST" action="?section=users" class="inline-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="form-row">
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
                        
                        <div class="form-group form-action">
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="settings-card">
                <h3>All Users</h3>
                
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
                                    <form method="POST" action="?section=users" class="inline-form">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo $role['id'] == $user['role_id'] ? 'selected' : ''; ?>>
                                                <?php echo h($role['display_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
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
                                    <form method="POST" action="?section=users" class="inline-form">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm">
                                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="settings-card">
                <h3>Role Reference</h3>
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
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Color picker synchronization
        document.querySelectorAll('.color-picker').forEach(picker => {
            picker.addEventListener('input', function() {
                const textInput = document.querySelector(`[data-color-for="${this.id}"]`);
                if (textInput) {
                    textInput.value = this.value;
                }
                updatePreview();
            });
        });
        
        document.querySelectorAll('.color-text').forEach(input => {
            input.addEventListener('input', function() {
                const colorFor = this.dataset.colorFor;
                const picker = document.getElementById(colorFor);
                if (picker && /^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    picker.value = this.value;
                    updatePreview();
                }
            });
        });
        
        // Live preview update
        function updatePreview() {
            const preview = document.getElementById('colorPreview');
            if (!preview) return;
            
            const getValue = (id) => document.getElementById(id)?.value || '';
            
            preview.style.setProperty('--preview-bg', getValue('color_bg_main'));
            preview.style.setProperty('--preview-panel', getValue('color_bg_panel'));
            preview.style.setProperty('--preview-primary', getValue('color_primary'));
            preview.style.setProperty('--preview-secondary', getValue('color_secondary'));
            preview.style.setProperty('--preview-accent', getValue('color_accent'));
            preview.style.setProperty('--preview-danger', getValue('color_danger'));
            preview.style.setProperty('--preview-text', getValue('color_text_main'));
            preview.style.setProperty('--preview-muted', getValue('color_text_muted'));
        }
        
        // Initialize preview
        updatePreview();
    </script>
</body>
</html>
