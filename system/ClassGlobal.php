<?php
/**
 * Файл system\ClassGlobal.php
 */
// Класс для глобальных функций и констант
class GlobalFunctions {
    // Получение IP-адреса клиента
    public static function getClientIP() {
        // Проверяем различные заголовки для получения IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        return $ip;
    }

    // Безопасная очистка входных данных
    public static function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }

    // Генерация уникального токена
    public static function generateToken() {
        return bin2hex(random_bytes(32));
    }
}