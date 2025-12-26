FROM ubuntu:22.04

# Prevent interactive prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive

# Install Nginx, PHP-FPM, MySQL client, and required extensions
RUN apt-get update && apt-get install -y \
    nginx \
    php8.1-fpm \
    php8.1-mysql \
    php8.1-mbstring \
    php8.1-xml \
    php8.1-curl \
    php8.1-gd \
    php8.1-zip \
    mysql-client \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP-FPM
RUN sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g' /etc/php/8.1/fpm/php.ini \
    && mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/pages \
    && chown -R www-data:www-data /var/www/html

# Copy Nginx configuration
COPY config/nginx/default.conf /etc/nginx/sites-available/default

# Copy PHP configuration
COPY config/php/php-fpm.conf /etc/php/8.1/fpm/pool.d/www.conf

# Copy supervisor configuration to manage multiple processes
COPY config/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy web application files
COPY src/ /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/pages

# Expose port 80
EXPOSE 80

# Start supervisor to manage nginx and php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
