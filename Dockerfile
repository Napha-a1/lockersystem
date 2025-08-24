# ใช้ image php เวอร์ชั่น 8.1 ที่มี apache
# เป็น image ที่มาจาก Debian ซึ่งใช้ apt ในการจัดการแพ็คเกจ
FROM docker.io/library/php:8.1-apache@sha256:8ef6d301cf7bc8db84966e6d6e9ae129e9aad8b9caf8b9bcdaa83f0c7593234f

# กำหนด working directory ภายใน container
WORKDIR /var/www/html

# ---
# ติดตั้งแพ็คเกจ postgresql-dev ที่จำเป็นสำหรับการ build pdo_pgsql
# ใช้ apt-get แทน apk เนื่องจาก base image เป็น Debian
RUN apt-get update && apt-get install -y postgresql-dev \
    # ติดตั้งส่วนขยาย pdo_pgsql สำหรับเชื่อมต่อ PostgreSQL
    && docker-php-ext-install pdo_pgsql \
    # ล้างไฟล์ที่ไม่จำเป็นหลังจากการติดตั้งเพื่อลดขนาด image
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# คัดลอกไฟล์ทั้งหมดจากเครื่อง host ไปยัง working directory ใน container
COPY . /var/www/html
