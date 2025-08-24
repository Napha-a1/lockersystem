<?php
$plain_password = "password_for_admin"; // <<< เปลี่ยน "password_for_admin" เป็นรหัสผ่านที่คุณต้องการใช้
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
echo "Hashed Password: " . $hashed_password;
?>