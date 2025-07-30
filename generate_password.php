<?php
/**
 * Сброс пароля
 */
$newPassword = '123456'; // Укажите новый пароль
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
echo $hashedPassword;
?>