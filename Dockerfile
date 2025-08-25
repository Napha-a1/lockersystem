# ใช้ image php เวอร์ชั่น 8.1 ที่มี apache
# เป็น image ที่มาจาก Debian ซึ่งใช้ apt ในการจัดการแพ็คเกจ
FROM docker.io/library/php:8.1-apache@sha256:8ef6d301cf7bc8db84966e6d6e9ae129e9aad8b9caf8b9bcdaa83f0c7593234f

# กำหนด working directory ภายใน container
WORKDIR /var/www/html

# ติดตั้งแพ็คเกจที่จำเป็นสำหรับการ build pdo_pgsql
RUN apt-get update && apt-get install -y libpq-dev \
    # ติดตั้งส่วนขยาย pdo_pgsql สำหรับเชื่อมต่อ PostgreSQL
    && docker-php-ext-install pdo_pgsql \
    # ล้างไฟล์ที่ไม่จำเป็นหลังจากการติดตั้งเพื่อลดขนาด image
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# คัดลอกไฟล์ทั้งหมดจากเครื่อง host ไปยัง working directory ใน container
COPY . /var/www/html

# ตั้งค่า PHP-FPM pool (จำเป็นต้องใช้ www.conf)
# ไฟล์ www.conf นี้จะถูกโหลดโดย PHP-FPM
COPY .docker/www.conf /etc/php/8.1/fpm/pool.d/www.conf

# คัดลอกไฟล์ .htaccess เพื่อตั้งค่า PHP ให้แสดง error log
COPY .htaccess /var/www/html/.htaccess

# ตั้งค่า Apache
# คัดลอกไฟล์การตั้งค่า 000-default.conf เพื่อให้ Apache รู้จัก PHP-FPM
COPY .docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# เปิดใช้งานการตั้งค่า Apache
# mod_rewrite เพื่อรองรับ URL rewrites (ถ้ามี)
# headers สำหรับตั้งค่า HTTP headers
# proxy และ proxy_fcgi เพื่อให้ Apache ทำงานร่วมกับ PHP-FPM
# setenvif เพื่อจัดการ environment variables
# และเปิดใช้งานการตั้งค่าจากไฟล์ 000-default.conf
RUN a2enmod rewrite headers proxy proxy_fcgi setenvif && \
    a2ensite 000-default && \
    service apache2 restart

# สั่งให้ container รัน Apache ใน foreground
CMD ["apache2-foreground"]
