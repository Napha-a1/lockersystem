# Start from the base PHP-Apache image
FROM php:8.1-apache

# Install mysqli extension for PHP (if needed, replace with your actual needs)
# Example: If you use pdo_pgsql, you might need to install that instead.
# For PDO PostgreSQL, it would be:
# RUN docker-php-ext-install pdo_pgsql

# Enable mod_rewrite for Apache
RUN a2enmod rewrite

# Copy your PHP application files into the container's web root
COPY . /var/www/html

# --- ADD THIS SECTION FOR FILE PERMISSIONS ---
# Set ownership to www-data user/group (Apache's default user)
# This ensures Apache has proper access to your files.
RUN chown -R www-data:www-data /var/www/html

# Set specific write permissions for the log file
# The www-data user needs to be able to create/write to this file.
RUN chmod 775 /var/www/html/auto_return_log.txt || true \
    && touch /var/www/html/auto_return_log.txt \
    && chmod 664 /var/www/html/auto_return_log.txt
# The `|| true` makes sure the command doesn't fail if the file doesn't exist yet.
# `touch` creates the file if it doesn't exist, then `chmod` sets permissions.
# You might need to adjust 775/664 depending on your exact security needs,
# but this should allow the www-data user to write to it.
# --- END ADDITION ---

# Expose port 80 for the web server
EXPOSE 80

# Start Apache web server (default for php-apache image)
CMD ["apache2-foreground"]
