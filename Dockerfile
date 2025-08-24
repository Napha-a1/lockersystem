# ใช้ image php เวอร์ชั่น 8.1 ที่มี apache
FROM docker.io/library/php:8.1-apache

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
