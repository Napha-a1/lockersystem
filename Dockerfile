# ใช้ image php เวอร์ชั่น 8.1 ที่มี apache
# เป็น image ที่มาจาก Debian ซึ่งใช้ apt ในการจัดการแพ็คเกจ
FROM docker.io/library/php:8.1-apache@sha256:8ef6d301cf7bc8db84966e6d6e9ae129e9aad8b9caf8b9bcdaa83f0c7593234f

# กำหนด working directory ภายใน container
WORKDIR /var/www/html

# ---
# ติดตั้งแพ็คเกจที่จำเป็นสำหรับการ build pdo_pgsql
# ใช้ apt-get และเปลี่ยนชื่อแพ็กเกจเป็น libpq-dev
RUN apt-get update && apt-get install -y libpq-dev \
    # ติดตั้งส่วนขยาย pdo_pgsql สำหรับเชื่อมต่อ PostgreSQL
    && docker-php-ext-install pdo_pgsql \
    # ล้างไฟล์ที่ไม่จำเป็นหลังจากการติดตั้งเพื่อลดขนาด image
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# คัดลอกไฟล์ทั้งหมดจากเครื่อง host ไปยัง working directory ใน container
COPY . /var/www/html

# ---
# ตั้งค่า PHP เพื่อส่ง Error Log ไปยัง stderr
# สร้างไฟล์คอนฟิกสำหรับ PHP-FPM
# ไฟล์ www.conf นี้จะถูกโหลดโดย PHP-FPM
COPY .docker/www.conf /etc/php/8.1/fpm/pool.d/www.conf


# เพิ่มการตั้งค่าสำหรับ Apache เพื่อให้ PHP-FPM ทำงานได้
# ใน PHP 8.1-apache image, mod_php ถูกใช้เป็นค่าเริ่มต้น
# เราต้องการใช้ PHP-FPM เพื่อการจัดการ Log ที่ยืดหยุ่นกว่า
# ดังนั้น เราจะเปลี่ยนไปใช้ mod_proxy_fcgi
RUN a2enmod proxy_fcgi setenvif && \
    a2enconf php8.1-fpm && \
    a2dismod php8.1

# กำหนดให้ Apache ส่ง .php requests ไปยัง PHP-FPM โดยใช้คอนฟิกที่กำหนดเอง
COPY .docker/000-default.conf /etc/apache2/sites-available/000-default.conf
