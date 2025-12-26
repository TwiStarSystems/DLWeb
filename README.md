# Containerize-Webserver

A Docker-based web server solution with a built-in page editor for easy website deployment and management. This container includes Nginx, PHP 8.1, and MySQL, providing a fast, customizable, and robust platform for hosting multiple websites.

## Features

- **Docker containerized** - Easy deployment and scaling
- **Nginx web server** - High-performance HTTP server
- **PHP 8.1 with FPM** - Modern PHP for dynamic content
- **MySQL 8.0 database** - Reliable data storage
- **Built-in Page Editor** with three editing modes:
  - **HTML Editor** - WYSIWYG editor with formatting toolbar
  - **Markdown Editor** - Simple text-based formatting with live preview
  - **Block Editor** - Modern block-based content creation (similar to WordPress Gutenberg)
- **Responsive design** - Works on desktop and mobile
- **Easy content management** - Create, edit, and delete pages through the web interface

## Quick Start

### Prerequisites

- Docker (version 20.10 or higher)
- Docker Compose (version 2.0 or higher)

### Installation

1. Clone this repository:
```bash
git clone https://github.com/TwiStarSystems/Containerize-Webserver.git
cd Containerize-Webserver
```

2. Build and start the containers:
```bash
docker-compose up -d
```

3. Wait for the services to start (about 30 seconds for MySQL to initialize)

4. Access your website:
   - **Main site**: http://localhost:8080
   - **Page editor**: http://localhost:8080/admin/

### Default Configuration

- **Web Port**: 8080 (mapped to container port 80)
- **MySQL Root Password**: rootpass
- **MySQL Database**: webdb
- **MySQL User**: webuser
- **MySQL Password**: webpass

## Usage

### Accessing the Page Editor

1. Navigate to http://localhost:8080/admin/
2. You'll see a list of existing pages in the sidebar
3. Click "New Page" to create a new page or click "Edit" on an existing page

### Creating Pages

1. Click "+ New Page" button
2. Enter a title for your page
3. Enter a URL slug (e.g., "about" will be accessible at `/?page=about`)
4. Choose your preferred editor type:
   - **HTML Editor**: Use the toolbar for formatting and visual editing
   - **Markdown**: Write in markdown syntax with live preview
   - **Block Editor**: Add text, heading, and image blocks
5. Click "Save Page"

### Viewing Pages

- Homepage: http://localhost:8080/
- Other pages: http://localhost:8080/?page=your-slug

## Architecture

```
┌─────────────────────────────────────┐
│         Docker Compose              │
├─────────────────┬───────────────────┤
│   Webserver     │      MySQL        │
│   Container     │     Container     │
├─────────────────┴───────────────────┤
│  - Nginx        │  - MySQL 8.0      │
│  - PHP-FPM 8.1  │  - Data Volume    │
│  - Supervisor   │                   │
└─────────────────┴───────────────────┘
```

## Directory Structure

```
.
├── Dockerfile              # Web server container definition
├── docker-compose.yml      # Multi-container orchestration
├── config/                 # Configuration files
│   ├── nginx/             # Nginx configuration
│   ├── php/               # PHP-FPM configuration
│   ├── supervisor/        # Process manager configuration
│   └── mysql/             # Database initialization scripts
├── src/                   # Web application source
│   ├── index.php          # Main website entry point
│   ├── config.php         # Database and app configuration
│   ├── admin/             # Page editor interface
│   ├── assets/            # CSS and JavaScript files
│   ├── pages/             # Static page storage (optional)
│   └── uploads/           # File uploads directory
└── README.md              # This file
```

## Customization

### Changing Port

Edit `docker-compose.yml` and change the port mapping:
```yaml
ports:
  - "8080:80"  # Change 8080 to your desired port
```

### Environment Variables

You can customize the database credentials by modifying the environment variables in `docker-compose.yml`:

```yaml
environment:
  - DB_HOST=mysql
  - DB_USER=webuser
  - DB_PASSWORD=webpass
  - DB_NAME=webdb
```

### Site Title

Edit `src/config.php` and change the SITE_TITLE constant:
```php
define('SITE_TITLE', 'My Website');
```

## Development

### Viewing Logs

```bash
# View all logs
docker-compose logs

# View web server logs
docker-compose logs webserver

# View MySQL logs
docker-compose logs mysql

# Follow logs in real-time
docker-compose logs -f
```

### Rebuilding Containers

```bash
# Rebuild after making changes to Dockerfile
docker-compose build

# Rebuild and restart
docker-compose up -d --build
```

### Accessing the Database

```bash
# Connect to MySQL from host
docker exec -it mysql mysql -uwebuser -pwebpass webdb

# Or use any MySQL client with:
# Host: localhost
# Port: 3306 (if exposed in docker-compose.yml)
# User: webuser
# Password: webpass
# Database: webdb
```

## Stopping and Removing

```bash
# Stop containers
docker-compose stop

# Stop and remove containers (data persists in volumes)
docker-compose down

# Remove containers and volumes (WARNING: deletes all data)
docker-compose down -v
```

## Production Deployment

For production use, consider:

1. **Change default passwords** in `docker-compose.yml`
2. **Use environment files** for sensitive data (create `.env` file)
3. **Enable HTTPS** with a reverse proxy (nginx-proxy, Traefik, or Caddy)
4. **Set up backups** for the MySQL data volume
5. **Add authentication** to the admin panel
6. **Configure firewall rules** appropriately
7. **Use Docker secrets** for credential management

## Security Considerations

⚠️ **IMPORTANT**: The default configuration is for development only!

### Critical Security Items for Production:

1. **Change default passwords** in `docker-compose.yml` before deployment
2. **Add authentication** to the `/admin/` path (e.g., HTTP Basic Auth via Nginx, or PHP session-based auth)
3. **Use HTTPS** with a reverse proxy (nginx-proxy, Traefik, or Caddy)
4. **Implement CSRF protection** in the admin forms
5. **Add rate limiting** to prevent abuse
6. **Set up backups** for the MySQL data volume
7. **Use Docker secrets** for credential management in production
8. **Keep Docker images updated** with security patches
9. **Configure firewall rules** appropriately
10. **Validate and sanitize all user input** (basic sanitization is included, but review for your use case)

### Input Validation:
- Page slugs are validated to contain only alphanumeric characters, hyphens, and underscores
- Slug uniqueness is enforced at the database level
- HTML output is escaped using `htmlspecialchars()` where appropriate
- PDO prepared statements are used to prevent SQL injection
- Markdown parser includes XSS protection with content escaping
- **HTML Editor Note**: The HTML editor allows direct HTML input for flexibility. In production, consider:
  - Adding admin authentication/authorization
  - Implementing Content Security Policy (CSP)
  - Using a library like DOMPurify for HTML sanitization
  - Restricting HTML editor access to trusted users only

### File Permissions:
- Upload directory has 775 permissions with www-data ownership
- Application files are owned by www-data user
- Sensitive configuration should be moved to environment variables

## Troubleshooting

### MySQL Connection Issues

If you see database connection errors, wait 30-60 seconds for MySQL to fully initialize on first run.

### Permission Issues

```bash
# Fix file permissions
docker-compose exec webserver chown -R www-data:www-data /var/www/html
docker-compose exec webserver chmod -R 755 /var/www/html
```

### Port Already in Use

If port 8080 is already in use, change it in `docker-compose.yml` to an available port.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open source and available under the MIT License.

## Support

For issues and questions, please open an issue on the GitHub repository.