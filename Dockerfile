# Start from the base PHP-Apache image.
FROM php:8.1-apache

# Step 1: Install PostgreSQL client libraries (libpq-dev).
RUN apt-get update && apt-get install -y \
    libpq-dev \
    # Clean up the apt cache to reduce the final image size.
    && rm -rf /var/lib/apt/lists/*

# Step 2: Install the PDO PostgreSQL extension for PHP.
RUN docker-php-ext-install pdo_pgsql

# Step 3: Enable Apache's mod_rewrite module.
RUN a2enmod rewrite

# Step 4: Copy all your application files into the container's web root directory.
COPY . /var/www/html

# Step 5: Set file ownership for the application files.
RUN chown -R www-data:www-data /var/www/html

# Step 6: Set specific file permissions for the log file.
RUN chmod 775 /var/www/html/auto_return_log.txt || true \
    && touch /var/www/html/auto_return_log.txt \
    && chmod 664 /var/www/html/auto_return_log.txt

# Expose port 80 to the outside world.
EXPOSE 80

# The final command to run when the container starts.
CMD ["apache2-foreground"]
