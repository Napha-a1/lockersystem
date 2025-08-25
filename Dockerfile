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

# คัดลอกไฟล์ .htaccess เพื่อตั้งค่า PHP ให้แสดง error log
# (ไฟล์ .htaccess จะถูกโหลดโดย Apache โดยอัตโนมัติหาก AllowOverride All ถูกเปิดใช้งาน)
COPY .htaccess /var/www/html/.htaccess

# คัดลอกไฟล์การตั้งค่า Apache
COPY .docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# เปิดใช้งานการตั้งค่า Apache ที่จำเป็น
# mod_rewrite เพื่อรองรับ URL rewrites (ถ้ามี)
# headers สำหรับตั้งค่า HTTP headers
# mod_php8.1 เพื่อให้ Apache ประมวลผล PHP ได้โดยตรง (ควรเปิดอยู่แล้วใน image)
# แก้ไขการเปิดใช้งาน site โดยการสร้าง symlink ด้วยตนเอง แทน a2ensite
RUN a2enmod rewrite headers php8.1 && \
    a2dissite 000-default.conf && \ # ปิด default site เดิมก่อน (ใช้ชื่อไฟล์ .conf)
    ln -s /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf && \ # สร้าง symlink เพื่อเปิดใช้งาน site ของเรา
    service apache2 restart

# สั่งให้ container รัน Apache ใน foreground
CMD ["apache2-foreground"]
