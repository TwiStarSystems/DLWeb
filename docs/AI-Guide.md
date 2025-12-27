# AI-Guide: Containerize-Webserver

## Project Overview

**Containerize-Webserver** is a Docker-based web server solution with a built-in page editor for easy website deployment and management. It provides a complete, containerized platform for hosting dynamic websites with minimal configuration overhead.

## Core Services & Technology Stack

### Latest Versions

- **Nginx** - High-performance HTTP server for handling web traffic
- **PHP 8.1 with FPM** - Modern PHP runtime for dynamic content processing
- **MySQL 8.0** - Reliable relational database for data persistence

This combination provides a robust, modern LAMP-like stack optimized for containerized environments.

### Key Architecture Components

```
Docker Compose (Multi-container Orchestration)
├── Webserver Container
│   ├── Nginx (HTTP server)
│   ├── PHP-FPM 8.1 (Application runtime)
│   └── Supervisor (Process management)
└── MySQL Container
    ├── MySQL 8.0 (Database)
    └── Data Volume (Persistent storage)
```

## Built-in Page Editor Features

The project includes a comprehensive admin interface at `/admin/` with three flexible editing modes:

1. **HTML Editor** - WYSIWYG editor with formatting toolbar for direct HTML control
2. **Markdown Editor** - Text-based formatting with live preview capability
3. **Block Editor** - Modern block-based content creation (similar to WordPress Gutenberg)

Users can create, edit, and delete pages through an intuitive web interface without touching code.

## Global Design System

The project uses a cohesive dark theme design system defined in `src/assets/css/global.css` with CSS custom properties for easy customization.

### Color Palette

| Variable | Value | Purpose |
|----------|-------|---------|
| `--bg-main` | `#000030` | Main background (deep navy) |
| `--bg-panel` | `#0000C8` | Panel/card backgrounds (royal blue) |
| `--primary` | `#9600E1` | Primary actions (vibrant purple) |
| `--secondary` | `#CC9CDF` | Secondary text/elements (soft lavender) |
| `--danger` | `#C80000` | Destructive actions (red) |
| `--accent` | `#FFD700` | Highlights & headings (gold) |
| `--text-main` | `#ffffff` | Primary text (white) |
| `--text-muted` | `#CC9CDF` | Muted/secondary text |

### Key Design Components

**Navigation Bar:**
- Purple gradient background (`--primary` → `--primary-dark`)
- Gold logo text
- White nav links with hover effects

**Cards & Panels:**
- Blue gradient backgrounds (`--bg-panel` → darker blue)
- Gold accent headings
- Subtle shadow effects

**Buttons:**
- Rounded pill style (999px border-radius)
- Primary: Purple gradient with glow on hover
- Secondary: Panel blue with border
- Danger: Red with ring effect on hover

**Forms:**
- Semi-transparent dark backgrounds
- Purple focus rings
- Lavender labels

### CSS Files

| File | Purpose |
|------|---------|
| `global.css` | Core design system, shared across all pages |
| `editor.css` | Admin editor-specific overrides |

### Customization

To modify the color scheme, edit the CSS custom properties in `src/assets/css/global.css`:

```css
:root {
    --bg-main: #000030;      /* Change main background */
    --primary: #9600E1;      /* Change primary color */
    --accent: #FFD700;       /* Change accent/highlight color */
    /* ... other variables */
}
```

## Security as a Top Priority

### Production-Ready Security Features

**Input Validation & Protection:**
- Page slugs validated to contain only alphanumeric characters, hyphens, and underscores
- Slug uniqueness enforced at the database level
- HTML output escaped using `htmlspecialchars()` where appropriate
- PDO prepared statements prevent SQL injection vulnerabilities
- Markdown parser includes XSS protection with content escaping

**Access Control:**
- Placeholder for authentication to the `/admin/` path
- Recommended: HTTP Basic Auth via Nginx or PHP session-based authentication
- Admin access should be restricted to trusted users only

**Advanced Security Hardening:**
- HTTPS support via reverse proxy (nginx-proxy, Traefik, or Caddy)
- CSRF protection framework in admin forms
- Rate limiting to prevent abuse
- Content Security Policy (CSP) support
- HTML sanitization library integration (DOMPurify recommended)

**Infrastructure Security:**
- Docker secrets support for credential management
- Environment variable support for sensitive configuration
- Proper file permissions (uploads: 775, files: 755)
- www-data user ownership for web-accessible directories
- Regular security patch updates through Docker image maintenance

### Security Checklist for Deployment

⚠️ **Before Production:**
- [ ] Change default MySQL passwords
- [ ] Implement admin authentication
- [ ] Enable HTTPS with reverse proxy
- [ ] Add CSRF protection tokens
- [ ] Configure rate limiting rules
- [ ] Set up automated backups for MySQL data
- [ ] Apply HTML sanitization to user content
- [ ] Configure firewall rules
- [ ] Keep Docker images updated
- [ ] Validate and sanitize all user input

## Customizability & Configuration

The project is designed to be highly customizable while maintaining security:

### Easy Configuration Points

**Port Management:**
- Modify port mappings in `docker-compose.yml`
- Default: Port 8080 → Container port 80

**Database Credentials:**
```yaml
environment:
  - DB_HOST=mysql
  - DB_USER=webuser
  - DB_PASSWORD=webpass
  - DB_NAME=webdb
```

**Site Customization:**
- Edit `src/config.php` to modify SITE_TITLE
- Customize CSS in `src/assets/css/`
- Extend JavaScript in `src/assets/js/`

**Configuration Files:**
- `config/nginx/default.conf` - Nginx server configuration
- `config/php/php-fpm.conf` - PHP runtime settings
- `config/supervisor/supervisord.conf` - Process management
- `config/mysql/schema.sql` - Database schema initialization

### Environment-Based Configuration

For enhanced security, use `.env` files:
```bash
# Create .env file
echo "DB_USER=customuser" >> .env
echo "DB_PASSWORD=custompass" >> .env
```

Then reference in `docker-compose.yml` for secrets management in production.

## Development & Deployment Workflow

### First-Time Setup

On first deployment, navigate to `/setup.php` to initialize the database:

1. Visit `http://your-server:8080/setup.php`
2. Verify database connection status
3. Click "Import Database Schema" to create tables
4. Once complete, navigate to the homepage or admin panel

### Local Development

```bash
# Build and start
docker-compose up -d

# View logs
docker-compose logs -f webserver

# Access services
# Main site: http://localhost:8080
# Admin: http://localhost:8080/admin/
# Setup: http://localhost:8080/setup.php
```

### Production Deployment

1. **Prepare Environment:**
   - Change all default credentials
   - Create `.env` file with production secrets
   - Set up HTTPS reverse proxy

2. **Deploy Container:**
   - Use Docker secrets or environment variables
   - Mount backup volumes
   - Configure firewall rules
   - Enable log aggregation

3. **Maintenance:**
   - Regular security updates
   - Automated backups
   - Monitor container health
   - Review logs regularly

## Common Use Cases

- **Small Business Websites** - Easy deployment without hosting complexity
- **Blog Platforms** - Built-in editor for content creation
- **Multi-site Hosting** - Run multiple sites in containers
- **Development Sandbox** - Quick testing environment with full stack
- **Educational Projects** - Learn containerization with real application

## File Structure

```
.
├── Dockerfile                          # Container definition
├── docker-compose.yml                  # Orchestration
├── README.md                           # User documentation
├── docs/
│   ├── AI-Guide.md                    # This file
│   └── CSS-colors.txt                 # Design system reference
├── config/
│   ├── nginx/default.conf             # Web server config
│   ├── php/php-fpm.conf               # PHP runtime
│   ├── supervisor/supervisord.conf    # Process manager
│   └── mysql/schema.sql               # DB schema initialization
└── src/
    ├── index.php                       # Homepage
    ├── config.php                      # App configuration
    ├── setup.php                       # First-time setup wizard
    ├── admin/index.php                # Admin interface
    ├── assets/
    │   ├── css/
    │   │   ├── global.css             # Global design system
    │   │   └── editor.css             # Editor-specific styles
    │   └── js/                         # Scripts
    ├── pages/                          # Page storage
    └── uploads/                        # User uploads
```

## Key Resources

- **Docker Documentation:** https://docs.docker.com/
- **Nginx Documentation:** https://nginx.org/en/docs/
- **PHP Documentation:** https://www.php.net/docs.php
- **MySQL Documentation:** https://dev.mysql.com/doc/
- **OWASP Security:** https://owasp.org/

## Support & Troubleshooting

**Database Connection Issues:**
- MySQL takes 30-60 seconds to initialize on first run
- Check logs: `docker-compose logs mysql`

**Permission Problems:**
```bash
docker-compose exec webserver chown -R www-data:www-data /var/www/html
docker-compose exec webserver chmod -R 755 /var/www/html
```

**Port Conflicts:**
- Change port in `docker-compose.yml` if 8080 is in use
- Common alternatives: 8081, 3000, 8888

## Next Steps for Development

1. Review security considerations before production deployment
2. Customize styling in `src/assets/css/`
3. Modify database schema in `config/mysql/init.sql` as needed
4. Add additional PHP libraries as required
5. Implement environment-specific configurations

---

For detailed usage instructions, see [README.md](README.md). For security deployment guidelines, refer to the "Production Deployment" section in the README.
