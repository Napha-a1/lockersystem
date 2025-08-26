# Start from the base PHP-Apache image
FROM php:8.1-apache

# Install PostgreSQL client libraries (libpq-dev)
# This is necessary for pdo_pgsql extension to be built successfully.
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PDO PostgreSQL extension for PHP
# This is crucial for connecting to PostgreSQL databases from PHP
RUN docker-php-ext-install pdo_pgsql

# Enable mod_rewrite for Apache
RUN a2enmod rewrite

# Copy your PHP application files into the container's web root
COPY . /var/www/html

# Set ownership to www-data user/group (Apache's default user)
# This ensures Apache has proper access to your files.
RUN chown -R www-data:www-data /var/www/html

# Set specific write permissions for the log file
# The www-data user needs to be able to create/write to this file.
RUN chmod 775 /var/www/html/auto_return_log.txt || true \
    && touch /var/www/html/auto_return_log.txt \
    && chmod 664 /var/www/html/auto_return_log.txt

# Expose port 80 for the web server
EXPOSE 80

# Start Apache web server (default for php-apache image)
CMD ["apache2-foreground"]
