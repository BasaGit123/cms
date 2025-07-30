
<?php
/**
 * Файл admin\login.php
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
$usersFile = __DIR__ . '/../data/user/users.json';
$logFile = __DIR__ . '/../data/error.log';
$error = $_SESSION['error'] ?? '';
$success = '';
unset($_SESSION['error']);

error_log("Access to login.php: session_user=" . ($_SESSION['user'] ?? 'none') . ", IP=$ip, User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . ", Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'unknown'), 3, $logFile);

if ($auth->isAuthenticated()) {
    error_log("User already authenticated, redirecting to index.php, session_user=" . ($_SESSION['user'] ?? 'none'), 3, $logFile);
    header('Location: index.php');
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

        // Авторизация
        if (isset($_POST['login'])) {
            $username = GlobalFunctions::sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            error_log("Login attempt: username=$username, IP=$ip", 3, $logFile);
            if (empty($username) || empty($password)) {
                $error = 'Логин и пароль обязательны';
            } elseif ($auth->login($username, $password)) {
                error_log("Redirecting to index.php after successful login, session_user=" . ($_SESSION['user'] ?? 'none'), 3, $logFile);
                header('Location: index.php');
                exit;
            } else {
                $error = 'Неверный логин или пароль';
            }
        }

        // Запрос кода сброса
        if (isset($_POST['request_reset_code'])) {
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
                            $success = 'Код отправлен на ваш email';
                            error_log("Recovery code sent: email=$email, IP=$ip", 3, $logFile);
                        } else {
                            $error = 'Ошибка: Не удалось отправить email';
                            error_log("Failed to send recovery email: email=$email, IP=$ip", 3, $logFile);
                        }
                    }
                }
            }
        }

        // Сброс пароля через код
        if (isset($_POST['reset_password'])) {
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $code = GlobalFunctions::sanitizeInput($_POST['code'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            if (empty($email) || empty($code) || empty($new_password) || empty($confirm_password)) {
                $error = 'Все поля обязательны';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Пароли не совпадают';
            } elseif (strlen($new_password) < 8) {
                $error = 'Пароль должен быть не менее 8 символов';
            } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/', $new_password)) {
                $error = 'Пароль должен содержать буквы, цифры и минимум один спецсимвол (!@#$%^&*)';
            } else {
                $validCode = false;
                foreach ($resetCodes as $rc) {
                    if ($rc['email'] === $email && password_verify($code, $rc['code']) && (time() - $rc['timestamp']) < 5 * 60) {
                        $validCode = true;
                        break;
                    }
                }
                if (!$validCode) {
                    $error = 'Неверный или просроченный код';
                } else {
                    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
                    $users = is_array($users) ? $users : [];
                    $adminFound = false;
                    $adminUsername = 'vasa';
                    foreach ($users as &$user) {
                        if ($user['id'] === 1) {
                            $user['password'] = password_hash($new_password, PASSWORD_BCRYPT);
                            $adminUsername = $user['username'];
                            $adminFound = true;
                            break;
                        }
                    }
                    if (!$adminFound) {
                        $users[] = [
                            'id' => 1,
                            'username' => 'vasa',
                            'password' => password_hash($new_password, PASSWORD_BCRYPT)
                        ];
                    }
                    error_log("Reset password: new_password=$new_password, users=" . print_r($users, true), 3, $logFile);
                    if (!file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $error = 'Ошибка: Не удалось сохранить пароль';
                        error_log("Ошибка сохранения users.json: " . print_r($users, true), 3, $logFile);
                    } else {
                        $resetCodes = array_filter($resetCodes, fn($rc) => $rc['email'] !== $email);
                        file_put_contents($resetCodesFile, json_encode($resetCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $auth->unblockIP($ip);
                        $success = 'Пароль успешно изменён. Войдите с новым паролем.';
                    }
                }
            }
        }
    }
}

if ($auth->isIPBlocked($ip)) {
    error_log("IP blocked: $ip, showing reset form", 3, $logFile);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>IP заблокирован</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <h2>Ваш IP заблокирован</h2>
            <p>Обратитесь к администратору для разблокировки.</p>
            <p>Если вы являетесь администратором, введите ваш email для сброса пароля.</p>
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
                <button type="submit" name="request_reset_code" class="btn btn-primary">Получить код</button>
            </form>
            <hr>
            <h4>Введите код из email</h4>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="code" class="form-label">Код</label>
                    <input type="text" class="form-control" id="code" name="code" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">Новый пароль</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Подтвердите пароль</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary">Сбросить пароль</button>
            </form>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Вход в админ-панель</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Логин</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? 'vasa'); ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Пароль</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary">Войти</button>
            <a href="recovery.php" class="btn btn-link">Восстановить пароль</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>