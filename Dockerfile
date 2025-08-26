# Start from the base PHP-Apache image.
# We are using version 8.1, which is a stable and widely supported version.
FROM php:8.1-apache

# Step 1: Install PostgreSQL client libraries (libpq-dev).
# This is a critical step. The `pdo_pgsql` extension needs these libraries
# to be built correctly. Without this, the `docker-php-ext-install` command will fail.
# We use apt-get to update the package list and install the necessary package.
RUN apt-get update && apt-get install -y \
    libpq-dev \
    # Clean up the apt cache to reduce the final image size.
    && rm -rf /var/lib/apt/lists/*

# Step 2: Install the PDO PostgreSQL extension for PHP.
# This command builds and enables the actual PHP driver for PostgreSQL.
# This resolves the "could not find driver" error.
RUN docker-php-ext-install pdo_pgsql

# Step 3: Enable Apache's mod_rewrite module.
# This module is often necessary for pretty URLs and routing in web applications.
RUN a2enmod rewrite

# Step 4: Copy all your application files into the container's web root directory.
# This copies all files from your project folder into the /var/www/html directory inside the container.
COPY . /var/www/html

# Step 5: Set file ownership for the application files.
# The Apache web server runs as the `www-data` user and group.
# This command ensures that Apache has the necessary permissions to read and serve your files.
RUN chown -R www-data:www-data /var/www/html

# Step 6: Set specific file permissions for the log file.
# We need to ensure the `auto_return_log.txt` file is writable by the `www-data` user.
# `chmod 775` sets permissions. The `|| true` prevents the build from failing if the file
# doesn't exist yet. `touch` creates the file if it's missing. `chmod 664` sets the final
# read/write permissions for the file itself.
RUN chmod 775 /var/www/html/auto_return_log.txt || true \
    && touch /var/www/html/auto_return_log.txt \
    && chmod 664 /var/www/html/auto_return_log.txt

# Expose port 80 to the outside world.
# This tells Docker that the container will be listening for incoming traffic on this port.
EXPOSE 80

# The final command to run when the container starts.
# This runs the Apache web server in the foreground, which is a standard practice for
# running services in Docker containers.
CMD ["apache2-foreground"]
