# ใช้ PHP base image พร้อม Apache
FROM php:8.1-apache

# ตั้ง working directory
WORKDIR /var/www/html

# ติดตั้งแพ็คเกจ postgresql-dev ที่จำเป็นสำหรับการ build pdo_pgsql
RUN apk add --no-cache postgresql-dev

# ติดตั้งส่วนขยาย pdo_pgsql สำหรับเชื่อมต่อ PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

# เปิดใช้งาน mod_rewrite (ถ้าจำเป็นสำหรับ .htaccess)
RUN a2enmod rewrite

# คัดลอกไฟล์ทั้งหมดในโปรเจกต์ไปยัง container
COPY . /var/www/html/

# เซิร์ฟเวอร์ Apache จะรันอัตโนมัติเมื่อ Container เริ่มทำงาน
