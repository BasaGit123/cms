
<?php
/**
 * Файл admin\recovery.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../system/Auth.php';
require_once __DIR__ . '/../system/ClassGlobal.php';

// Генерация CSRF-токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$auth = new Auth();
$ip = GlobalFunctions::getClientIP();
$settingsFile = __DIR__ . '/../data/settings.json';
$resetCodesFile = __DIR__ . '/../data/reset_codes.json';
$logFile = __DIR__ . '/../data/error.log';
$error = '';
$success = '';

if (!$auth->isIPBlocked($ip)) {
    error_log("Access to recovery.php denied: IP $ip is not blocked, User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . ", Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'unknown'), 3, $logFile);
    $_SESSION['error'] = 'Доступ к восстановлению пароля разрешен только для заблокированных IP.';
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Недействительный CSRF-токен';
        error_log("CSRF token validation failed: IP=$ip, User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 3, $logFile);
    } else {
        $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
        $settings = is_array($settings) ? $settings : [];
        $resetCodes = file_exists($resetCodesFile) ? json_decode(file_get_contents($resetCodesFile), true) : [];
        $resetCodes = is_array($resetCodes) ? $resetCodes : [];

        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите корректный email';
        } elseif ($email !== ($settings['admin_email'] ?? '')) {
            $error = 'Email не соответствует администратору';
        } else {
            // Проверка времени последнего запроса
            foreach ($resetCodes as $rc) {
                if ($rc['email'] === $email && (time() - $rc['timestamp']) < 60) {
                    $error = 'Повторный запрос кода возможен через 60 секунд';
                    break;
                }
            }
            if (!$error) {
                // Генерация кода из цифр и спецсимволов длиной 8 символов
                $characters = '0123456789!@#$%^&*';
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $code .= $characters[random_int(0, strlen($characters) - 1)];
                }
                $hashedCode = password_hash($code, PASSWORD_BCRYPT); // Хеширование кода
                $timestamp = time();
                $resetCodes = array_filter($resetCodes, fn($rc) => $rc['email'] !== $email);
                $resetCodes[] = [
                    'email' => $email,
                    'code' => $hashedCode,
                    'timestamp' => $timestamp
                ];
                if (!file_put_contents($resetCodesFile, json_encode($resetCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $error = 'Ошибка: Не удалось сохранить код';
                } else {
                    $subject = 'Код для сброса пароля';
                    $message = "Ваш код для сброса пароля: $code\nКод действителен 5 минут.";
                    $headers = 'From: no-reply@cms.loc' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
                    if (mail($email, $subject, $message, $headers)) {
                        $success = 'Код отправлен на ваш email. Введите его на странице входа.';
                        error_log("Recovery code sent: email=$email, IP=$ip, User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 3, $logFile);
                        header('Location: login.php');
                        exit;
                    } else {
                        $error = 'Ошибка: Не удалось отправить email';
                        error_log("Failed to send recovery email: email=$email, IP=$ip, User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 3, $logFile);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Восстановление пароля</h2>
        <p>Ваш IP заблокирован. Введите email администратора, чтобы получить код для сброса пароля.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Получить код</button>
            <a href="login.php" class="btn btn-link">Назад к входу</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>