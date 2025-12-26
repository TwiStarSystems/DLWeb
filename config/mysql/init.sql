-- Initialize database schema
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    content_type ENUM('html', 'markdown', 'blocks') DEFAULT 'html',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample homepage
INSERT INTO pages (title, slug, content, content_type) VALUES 
('Welcome to Your Website', 'home', '<h1>Welcome to Your Website</h1><p>This is your homepage. Use the page editor to customize this content!</p><p>You can create, edit, and delete pages using the built-in editor.</p>', 'html'),
('About', 'about', '# About Us\n\nThis is a sample about page written in **Markdown**.\n\n- Easy to use\n- Powerful editing\n- Multiple formats supported', 'markdown');
