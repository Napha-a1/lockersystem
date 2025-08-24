#!/usr/bin/env bash

# ตรวจสอบว่าไฟล์ SQL มีอยู่หรือไม่
if [ ! -f "locker_system_web.sql" ]; then
    echo "Error: locker_system_web.sql not found!"
    exit 1
fi

echo "Importing database schema and data..."
# ใช้ psql เพื่อรันคำสั่งจากไฟล์ SQL
psql -v ON_ERROR_STOP=1 -f locker_system_web.sql

if [ $? -eq 0 ]; then
    echo "✅ Database import successful."
else
    echo "❌ Database import failed."
    exit 1
fi
