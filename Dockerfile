# Start from the base PHP-Apache image
FROM php:8.1-apache

# Install PostgreSQL client libraries (libpq-dev)
# This is necessary for pdo_pgsql extension to be built successfully.
# It also includes cleaning up apt lists to keep the image size down.
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PDO PostgreSQL extension for PHP
# This is crucial for connecting to PostgreSQL databases from PHP.
RUN docker-php-ext-install pdo_pgsql

# Enable mod_rewrite for Apache. This is often needed for clean URLs in PHP apps.
RUN a2enmod rewrite

# Copy your PHP application files into the container's web root.
# All your project files (PHP scripts, Dockerfile itself, etc.) will be placed here.
COPY . /var/www/html

# Set ownership of all files in the web root to www-data user/group.
# This ensures Apache (which runs as www-data) has proper read/write access to your application files.
RUN chown -R www-data:www-data /var/www/html

# Set specific write permissions for the auto_return_log.txt file.
# The `|| true` prevents the command from failing if the file doesn't exist yet.
# `touch` creates the file if it doesn't exist.
# `chmod 664` grants read/write permissions for the owner (www-data) and group (www-data),
# and read-only for others. This should allow auto_return.php to write to the log.
RUN chmod 775 /var/www/html/auto_return_log.txt || true \
    && touch /var/www/html/auto_return_log.txt \
    && chmod 664 /var/www/html/auto_return_log.txt

# Expose port 80, which is the default port for HTTP traffic.
# This tells Docker that the container listens on this port.
EXPOSE 80

# Define the command to run when the container starts.
# `apache2-foreground` runs Apache in the foreground, essential for Docker containers.
CMD ["apache2-foreground"]
