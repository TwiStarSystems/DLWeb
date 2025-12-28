<?php
require_once '../auth.php';
require_once '../settings.php';

// Require user to have view_admin permission
auth()->requirePermission('view_admin', '/403.php');

$currentUser = currentUser();
$siteTitle = siteTitle();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Verify CSRF token for all POST requests
    if (!auth()->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    $db = getDB();
    
    try {
        switch ($action) {
            case 'save':
                // Check create permission for new pages
                if (empty($_POST['id']) && !hasPermission('create_pages')) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to create pages']);
                    exit;
                }
                
                $id = $_POST['id'] ?? null;
                $title = $_POST['title'] ?? '';
                $slug = $_POST['slug'] ?? '';
                $content = $_POST['content'] ?? '';
                $content_type = $_POST['content_type'] ?? 'html';
                
                // For existing pages, check edit permission
                if ($id) {
                    $stmt = $db->prepare("SELECT author_id FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    $page = $stmt->fetch();
                    
                    if (!$page) {
                        echo json_encode(['success' => false, 'message' => 'Page not found']);
                        exit;
                    }
                    
                    if (!auth()->canEditPage($page['author_id'])) {
                        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this page']);
                        exit;
                    }
                }
                
                if (empty($title) || empty($slug)) {
                    echo json_encode(['success' => false, 'message' => 'Title and slug are required']);
                    exit;
                }
                
                // Validate slug format (alphanumeric, hyphens, underscores only)
                // Convert to lowercase for consistency
                $slug = strtolower($slug);
                if (!preg_match('/^[a-z0-9-_]+$/', $slug)) {
                    echo json_encode(['success' => false, 'message' => 'Slug must contain only letters, numbers, hyphens, and underscores']);
                    exit;
                }
                
                // Check for slug uniqueness
                if ($id) {
                    $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $id]);
                } else {
                    $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ?");
                    $stmt->execute([$slug]);
                }
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'A page with this slug already exists']);
                    exit;
                }
                
                if ($id) {
                    // Update existing page
                    $stmt = $db->prepare("UPDATE pages SET title = ?, slug = ?, content = ?, content_type = ? WHERE id = ?");
                    $stmt->execute([$title, $slug, $content, $content_type, $id]);
                } else {
                    // Create new page with author
                    $stmt = $db->prepare("INSERT INTO pages (title, slug, content, content_type, author_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $slug, $content, $content_type, $currentUser['id']]);
                    $id = $db->lastInsertId();
                }
                
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Page saved successfully']);
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    // Check delete permission
                    $stmt = $db->prepare("SELECT author_id FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    $page = $stmt->fetch();
                    
                    if (!$page) {
                        echo json_encode(['success' => false, 'message' => 'Page not found']);
                        exit;
                    }
                    
                    if (!auth()->canDeletePage($page['author_id'])) {
                        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this page']);
                        exit;
                    }
                    
                    $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true, 'message' => 'Page deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid page ID']);
                }
                break;
                
            case 'load':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $stmt = $db->prepare("SELECT p.*, u.display_name as author_name FROM pages p LEFT JOIN users u ON p.author_id = u.id WHERE p.id = ?");
                    $stmt->execute([$id]);
                    $page = $stmt->fetch();
                    
                    if ($page) {
                        // Add permission info for the frontend
                        $page['can_edit'] = auth()->canEditPage($page['author_id']);
                        $page['can_delete'] = auth()->canDeletePage($page['author_id']);
                    }
                    
                    echo json_encode(['success' => true, 'page' => $page]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid page ID']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get all pages for listing (with author info)
$db = getDB();

// Power editors and admins see all pages, others see only their own
if (hasPermission('edit_all_pages')) {
    $stmt = $db->query("
        SELECT p.id, p.title, p.slug, p.content_type, p.updated_at, p.author_id, 
               u.display_name as author_name
        FROM pages p 
        LEFT JOIN users u ON p.author_id = u.id 
        ORDER BY p.updated_at DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT p.id, p.title, p.slug, p.content_type, p.updated_at, p.author_id,
               u.display_name as author_name
        FROM pages p 
        LEFT JOIN users u ON p.author_id = u.id 
        WHERE p.author_id = ?
        ORDER BY p.updated_at DESC
    ");
    $stmt->execute([$currentUser['id']]);
}
$pages = $stmt->fetchAll();

// Generate CSRF token for JavaScript
$csrfToken = csrfToken();
$favicon = siteFavicon();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Editor - <?php echo h($siteTitle); ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" href="<?php echo h($favicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/editor.css">
    <style><?php echo colorCSS(); ?></style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo"><?php echo h($siteTitle); ?></a>
            <ul class="nav-menu">
                <li><a href="/">View Site</a></li>
                <?php if (hasPermission('manage_settings')): ?>
                <li><a href="/admin/settings.php">Settings</a></li>
                <?php endif; ?>
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
                <h2>Pages</h2>
                <?php if (hasPermission('create_pages')): ?>
                <button class="btn btn-primary" onclick="newPage()">+ New Page</button>
                <?php endif; ?>
            </div>
            <ul class="page-list" id="pageList">
                <?php foreach ($pages as $p): 
                    $canEdit = auth()->canEditPage($p['author_id']);
                    $canDelete = auth()->canDeletePage($p['author_id']);
                ?>
                <li class="page-item" data-id="<?php echo $p['id']; ?>">
                    <div class="page-info">
                        <span class="page-title"><?php echo h($p['title']); ?></span>
                        <span class="page-meta">
                            <span class="page-type"><?php echo h($p['content_type']); ?></span>
                            <?php if ($p['author_name']): ?>
                            <span class="page-author">by <?php echo h($p['author_name']); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="page-actions">
                        <?php if ($canEdit): ?>
                        <button onclick="loadPage(<?php echo $p['id']; ?>)">Edit</button>
                        <?php else: ?>
                        <button onclick="loadPage(<?php echo $p['id']; ?>)">View</button>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                        <button class="btn-danger" onclick="deletePage(<?php echo $p['id']; ?>)">Delete</button>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        
        <main class="editor-main">
            <div id="editorPanel" style="display: none;">
                <div class="editor-header">
                    <input type="hidden" id="pageId">
                    <div class="form-group">
                        <label>Title:</label>
                        <input type="text" id="pageTitle" class="form-control" placeholder="Page Title">
                    </div>
                    <div class="form-group">
                        <label>Slug:</label>
                        <input type="text" id="pageSlug" class="form-control" placeholder="page-url-slug">
                    </div>
                    <div class="form-group">
                        <label>Content Type:</label>
                        <select id="contentType" class="form-control" onchange="switchEditor()">
                            <option value="html">HTML Editor</option>
                            <option value="markdown">Markdown</option>
                            <option value="blocks">Block Editor</option>
                        </select>
                    </div>
                </div>
                
                <div class="editor-content">
                    <!-- HTML Editor -->
                    <div id="htmlEditor" class="editor-type">
                        <div class="toolbar">
                            <button onclick="execCmd('bold')"><b>B</b></button>
                            <button onclick="execCmd('italic')"><i>I</i></button>
                            <button onclick="execCmd('underline')"><u>U</u></button>
                            <button onclick="execCmd('formatBlock', 'h1')">H1</button>
                            <button onclick="execCmd('formatBlock', 'h2')">H2</button>
                            <button onclick="execCmd('formatBlock', 'h3')">H3</button>
                            <button onclick="execCmd('formatBlock', 'p')">P</button>
                            <button onclick="execCmd('insertUnorderedList')">UL</button>
                            <button onclick="execCmd('insertOrderedList')">OL</button>
                            <button onclick="showHTMLSource()">Source</button>
                        </div>
                        <div id="htmlContent" class="editable-content" contenteditable="true"></div>
                        <textarea id="htmlSource" class="html-source" style="display: none;"></textarea>
                    </div>
                    
                    <!-- Markdown Editor -->
                    <div id="markdownEditor" class="editor-type" style="display: none;">
                        <div class="markdown-container">
                            <div class="markdown-input">
                                <textarea id="markdownContent" class="form-control" placeholder="Write markdown here..."></textarea>
                            </div>
                            <div class="markdown-preview">
                                <h4>Preview</h4>
                                <div id="markdownPreview"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Block Editor -->
                    <div id="blockEditor" class="editor-type" style="display: none;">
                        <button class="btn btn-secondary" onclick="addBlock('text')">+ Text Block</button>
                        <button class="btn btn-secondary" onclick="addBlock('heading')">+ Heading</button>
                        <button class="btn btn-secondary" onclick="addBlock('image')">+ Image</button>
                        <div id="blockContent" class="blocks-container"></div>
                    </div>
                </div>
                
                <div class="editor-actions">
                    <button class="btn btn-primary" onclick="savePage()">Save Page</button>
                    <button class="btn btn-secondary" onclick="previewPage()">Preview</button>
                    <button class="btn" onclick="cancelEdit()">Cancel</button>
                </div>
            </div>
            
            <div id="welcomeMessage" class="welcome-message">
                <h2>Welcome to the Page Editor</h2>
                <p>Select a page from the sidebar to edit, or create a new page.</p>
                <p>This editor supports:</p>
                <ul>
                    <li><strong>HTML Editor:</strong> WYSIWYG editor with formatting toolbar</li>
                    <li><strong>Markdown:</strong> Simple text-based formatting with live preview</li>
                    <li><strong>Block Editor:</strong> Modern block-based content creation</li>
                </ul>
            </div>
        </main>
    </div>
    
    <script>
        // CSRF Token for all AJAX requests
        const csrfToken = '<?php echo h($csrfToken); ?>';
        
        // User permissions
        const userPermissions = {
            canCreate: <?php echo hasPermission('create_pages') ? 'true' : 'false'; ?>,
            canEditAll: <?php echo hasPermission('edit_all_pages') ? 'true' : 'false'; ?>,
            canDeleteAll: <?php echo hasPermission('delete_all_pages') ? 'true' : 'false'; ?>
        };
    </script>
    <script src="/assets/js/editor.js"></script>
</body>
</html>
