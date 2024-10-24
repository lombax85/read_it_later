FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    libsqlite3-dev \
    ffmpeg

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install OpenSSL for certificate generation
RUN apt-get update && apt-get install -y openssl

# Generate self-signed certificate
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/ssl-cert-snakeoil.key -out /etc/ssl/certs/ssl-cert-snakeoil.pem -subj "/C=IT/ST=State/L=City/O=Organization/OU=Unit/CN=localhost"

# Enable Apache SSL module
RUN a2enmod ssl

# Ensure the DocumentRoot is set correctly for HTTP
RUN sed -i 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Configure Apache for HTTP and HTTPS
RUN sed -i 's!^</VirtualHost>!</VirtualHost>\n\n<VirtualHost *:443>\n\tServerAdmin webmaster@localhost\n\tDocumentRoot /var/www/html/public\n\tSSLEngine on\n\tSSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem\n\tSSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key\n\t<Directory /var/www/html/public>\n\t\tOptions Indexes FollowSymLinks\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>\n</VirtualHost>!' /etc/apache2/sites-available/000-default.conf


# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
# RUN composer install --no-interaction --no-dev --prefer-dist

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/public/podcasts \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/public/podcasts

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Disable PHP deprecation warnings
RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" > /usr/local/etc/php/conf.d/error_reporting.ini

# Install XDebug
RUN pecl install xdebug

# Copy XDebug configuration file
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini.disabled

# Expose port 80 and 443 for HTTPS
EXPOSE 80 443

# Start Apache
CMD ["apache2-foreground"]
