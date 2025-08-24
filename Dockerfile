# ใช้ PHP base image
FROM php:8.1-apache

# กำหนด working directory
WORKDIR /var/www/html

# คัดลอกไฟล์ทั้งหมดจาก local directory ไปยัง container
COPY . .

# ติดตั้งส่วนขยายที่จำเป็น (เช่น pdo_pgsql สำหรับ PostgreSQL)
RUN docker-php-ext-install pdo pdo_pgsql

# เปิดใช้งาน mod_rewrite (ถ้าจำเป็น)
RUN a2enmod rewrite

# (ถ้ามี composer) ติดตั้ง Composer
# COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
# RUN composer install --no-dev

# เปิดพอร์ตสำหรับเว็บเซิร์ฟเวอร์
EXPOSE 80

# (apache จะ start อัตโนมัติ)
# CMD ["apache2-foreground"]
