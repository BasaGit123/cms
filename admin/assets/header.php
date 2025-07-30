
<?php
/**
 * Файл admin\assets\header.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../system/Auth.php';
require_once __DIR__ . '/../../system/ClassGlobal.php';

$auth = new Auth();
$ip = GlobalFunctions::getClientIP();
if ($auth->isIPBlocked($ip)) {
    die('Ваш IP заблокирован');
}
if (!$auth->isAuthenticated()) {
    error_log("Redirecting to login.php: session_user=" . ($_SESSION['user'] ?? 'none'), 3, __DIR__ . '/../../data/error.log');
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/admin/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <button class="hamburger btn btn-light d-md-none" type="button">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="sidebar d-md-block">

        <div class="wrap d-flex flex-column h-100 justify-content-between">
            <ul class="nav flex-column">
                <a class="navbar-brand d-block" href="/admin">Админ-панель</a>
                <li class="nav-item">
                    <a class="nav-link" href="/admin">Главная</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/pages.php">Страницы</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/menus.php">Меню</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/settings.php">Настройки</a>
                </li>
            </ul>

            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="/" target="_blank">Смотреть сайт</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/logout.php">Выход</a>
                </li>
            </ul>
        </div>

    </div>

    <div class="content">