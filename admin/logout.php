<?php
/**
 * файл admin\logout.php
 */
session_start();
require_once __DIR__ . '/../system/Auth.php';

$auth = new Auth();
$auth->logout();
header('Location: login.php');
exit;
?>