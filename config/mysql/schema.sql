-- Initialize database schema

-- =====================================================
-- ROLE-BASED ACCESS CONTROL (RBAC) TABLES
-- =====================================================

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    level INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role-Permission mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    role_id INT NOT NULL DEFAULT 2,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions for remember me functionality
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PAGES TABLE (Updated with ownership)
-- =====================================================

CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    content_type ENUM('html', 'markdown', 'blocks') DEFAULT 'html',
    author_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_created (created_at),
    INDEX idx_author (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT ROLES
-- =====================================================
-- Level determines hierarchy: higher level = more privileges

INSERT INTO roles (name, display_name, description, level) VALUES 
('viewer', 'Viewer', 'Unauthenticated users - can view public content only', 0),
('subscriber', 'Subscriber', 'Authenticated users - can view content and manage own profile', 10),
('writer', 'Writer', 'Can create, edit, and delete owned articles (Feature Coming Soon)', 20),
('page_editor', 'Page Editor', 'Can create, edit, and delete owned pages', 30),
('power_editor', 'Power Editor', 'Can create, edit, and delete all pages', 40),
('admin', 'Administrator', 'Full control of entire site', 100);

-- =====================================================
-- INSERT PERMISSIONS
-- =====================================================

INSERT INTO permissions (name, display_name, description, category) VALUES 
-- Viewing permissions
('view_pages', 'View Pages', 'Can view published pages', 'pages'),
('view_admin', 'View Admin Panel', 'Can access the admin dashboard', 'admin'),

-- Page permissions
('create_pages', 'Create Pages', 'Can create new pages', 'pages'),
('edit_own_pages', 'Edit Own Pages', 'Can edit pages they created', 'pages'),
('edit_all_pages', 'Edit All Pages', 'Can edit any page', 'pages'),
('delete_own_pages', 'Delete Own Pages', 'Can delete pages they created', 'pages'),
('delete_all_pages', 'Delete All Pages', 'Can delete any page', 'pages'),

-- Article permissions (for future use)
('create_articles', 'Create Articles', 'Can create new articles', 'articles'),
('edit_own_articles', 'Edit Own Articles', 'Can edit articles they created', 'articles'),
('edit_all_articles', 'Edit All Articles', 'Can edit any article', 'articles'),
('delete_own_articles', 'Delete Own Articles', 'Can delete articles they created', 'articles'),
('delete_all_articles', 'Delete All Articles', 'Can delete any article', 'articles'),

-- User management
('manage_own_profile', 'Manage Own Profile', 'Can edit own profile settings', 'users'),
('manage_users', 'Manage Users', 'Can create, edit, and delete users', 'users'),
('manage_roles', 'Manage Roles', 'Can create, edit, and delete roles', 'users'),

-- Site management
('manage_settings', 'Manage Settings', 'Can modify site settings', 'settings'),
('manage_uploads', 'Manage Uploads', 'Can upload and manage files', 'uploads');

-- =====================================================
-- ASSIGN PERMISSIONS TO ROLES
-- =====================================================

-- Viewer (Role ID: 1) - Public content viewing only
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE name IN ('view_pages');

-- Subscriber (Role ID: 2) - View + Profile management
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name IN ('view_pages', 'manage_own_profile');

-- Writer (Role ID: 3) - Subscriber + Article management (Future feature)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name IN (
    'view_pages', 'manage_own_profile',
    'create_articles', 'edit_own_articles', 'delete_own_articles'
);

-- Page Editor (Role ID: 4) - Subscriber + Own page management
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name IN (
    'view_pages', 'view_admin', 'manage_own_profile',
    'create_pages', 'edit_own_pages', 'delete_own_pages', 'manage_uploads'
);

-- Power Editor (Role ID: 5) - Page Editor + All pages management
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE name IN (
    'view_pages', 'view_admin', 'manage_own_profile',
    'create_pages', 'edit_own_pages', 'edit_all_pages', 
    'delete_own_pages', 'delete_all_pages', 'manage_uploads'
);

-- Admin (Role ID: 6) - Full access
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions;

-- =====================================================
-- SITE SETTINGS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'text', 'number', 'boolean', 'json', 'color', 'file') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT SETTINGS
-- =====================================================

-- General Settings
INSERT INTO settings (setting_key, setting_value, setting_type, category, display_name, description, display_order) VALUES
('site_title', 'My Website', 'string', 'general', 'Site Title', 'The main title displayed in the browser tab and header', 10),
('site_tagline', 'A Docker-powered website', 'string', 'general', 'Site Tagline', 'A short description or slogan for your site', 20),
('site_favicon', '', 'file', 'general', 'Site Favicon', 'The icon displayed in browser tabs (recommended: 32x32 PNG or ICO)', 30),
('footer_text', '© {year} {site_title}. All rights reserved.', 'string', 'general', 'Footer Text', 'Text displayed in the footer. Use {year} for current year, {site_title} for site name', 40),
('admin_email', 'admin@example.com', 'string', 'general', 'Admin Email', 'Primary contact email for the site', 50);

-- Appearance Settings (Colors)
INSERT INTO settings (setting_key, setting_value, setting_type, category, display_name, description, display_order) VALUES
('color_bg_main', '#000030', 'color', 'appearance', 'Main Background', 'Primary background color (deep navy)', 10),
('color_bg_panel', '#0000C8', 'color', 'appearance', 'Panel Background', 'Card and panel background color (royal blue)', 20),
('color_primary', '#9600E1', 'color', 'appearance', 'Primary Color', 'Primary action buttons and accents (purple)', 30),
('color_secondary', '#CC9CDF', 'color', 'appearance', 'Secondary Color', 'Secondary text and elements (lavender)', 40),
('color_accent', '#FFD700', 'color', 'appearance', 'Accent Color', 'Highlights and headings (gold)', 50),
('color_danger', '#C80000', 'color', 'appearance', 'Danger Color', 'Delete buttons and warnings (red)', 60),
('color_text_main', '#ffffff', 'color', 'appearance', 'Main Text Color', 'Primary text color (white)', 70),
('color_text_muted', '#CC9CDF', 'color', 'appearance', 'Muted Text Color', 'Secondary/muted text color', 80);

-- Feature Settings
INSERT INTO settings (setting_key, setting_value, setting_type, category, display_name, description, display_order) VALUES
('allow_registration', '1', 'boolean', 'features', 'Allow Registration', 'Allow new users to register accounts', 10),
('default_user_role', '2', 'number', 'features', 'Default User Role', 'Role ID assigned to new users (2=Subscriber)', 20),
('require_email_verification', '0', 'boolean', 'features', 'Require Email Verification', 'Require users to verify email before login', 30);

-- =====================================================
-- CREATE DEFAULT ADMIN USER
-- Password: admin123 (CHANGE THIS IN PRODUCTION!)
-- =====================================================

INSERT INTO users (username, email, password_hash, display_name, role_id, is_active, email_verified) VALUES
('admin', 'admin@example.com', '$2y$12$9uM7GiLbp9QmpmZa98.tn.Rs3YfOl7jo/vzgFnkkxHBoeKd90PNxG', 'Administrator', 6, TRUE, TRUE);

-- =====================================================
-- INSERT SAMPLE PAGES (with author)
-- =====================================================

INSERT INTO pages (title, slug, content, content_type, author_id) VALUES 
('Welcome to Your Website', 'home', '<h1>Welcome to Your Website</h1><p>This is your homepage. Use the page editor to customize this content!</p><p>You can create, edit, and delete pages using the built-in editor.</p>', 'html', 1),
('About', 'about', '# About Us\n\nThis is a sample about page written in **Markdown**.\n\n- Easy to use\n- Powerful editing\n- Multiple formats supported', 'markdown', 1);
