
<?php
/**
 * admin\settings.php
 */
session_start();
require_once __DIR__ . '/assets/header.php';
require_once __DIR__ . '/../system/ClassGlobal.php';
require_once __DIR__ . '/../system/Auth.php';

$settingsFile = __DIR__ . '/../data/settings.json';
$usersFile = __DIR__ . '/../data/user/users.json';
$ipBlockFile = __DIR__ . '/../data/user/ip_block.json';
$logFile = __DIR__ . '/../data/login_attempts.log';
$message = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

error_log("Access to settings.php: session_user=" . ($_SESSION['user'] ?? 'none'), 3, __DIR__ . '/../data/error.log');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
    $settings = is_array($settings) ? $settings : [];
    $users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
    $users = is_array($users) ? $users : [];

    if (isset($_POST['update_settings'])) {
        $site_name = GlobalFunctions::sanitizeInput($_POST['site_name']);
        $site_title = GlobalFunctions::sanitizeInput($_POST['site_title']);
        $site_description = GlobalFunctions::sanitizeInput($_POST['site_description']);
        
        $settings['site_name'] = $site_name;
        $settings['site_title'] = $site_title;
        $settings['site_description'] = $site_description;
        
        if (!file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $message = 'Ошибка: Не удалось сохранить настройки';
        } else {
            $message = 'Настройки обновлены';
        }
        $activeTab = 'general';
    } elseif (isset($_POST['update_security'])) {
        $max_attempts = (int)$_POST['max_attempts'];
        $block_duration = (int)$_POST['block_duration'];
        $admin_email = filter_var($_POST['admin_email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if ($max_attempts < 1) {
            $message = 'Ошибка: Максимальное количество попыток должно быть не менее 1';
        } elseif ($block_duration < 1) {
            $message = 'Ошибка: Длительность блокировки должна быть не менее 1 минуты';
        } elseif (!empty($admin_email) && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Ошибка: Некорректный email';
        } else {
            $settings['max_attempts'] = $max_attempts;
            $settings['block_duration'] = $block_duration;
            $settings['admin_email'] = $admin_email;
            if (!file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $message = 'Ошибка: Не удалось сохранить настройки безопасности';
            } else {
                $message = 'Настройки безопасности обновлены';
            }
            $activeTab = 'security';
        }
    } elseif (isset($_POST['update_admin'])) {
        $new_username = GlobalFunctions::sanitizeInput($_POST['new_username'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_username) || empty($new_password) || empty($confirm_password)) {
            $message = 'Ошибка: Все поля обязательны';
        } elseif ($new_password !== $confirm_password) {
            $message = 'Ошибка: Пароли не совпадают';
        } elseif (strlen($new_password) < 6) {
            $message = 'Ошибка: Пароль должен быть не менее 6 символов';
        } else {
            $usernameExists = false;
            foreach ($users as $user) {
                if ($user['username'] === $new_username && $user['id'] !== 1) {
                    $usernameExists = true;
                    break;
                }
            }
            if ($usernameExists) {
                $message = 'Ошибка: Логин уже используется';
            } else {
                $adminFound = false;
                foreach ($users as &$user) {
                    if ($user['id'] === 1) {
                        $user['username'] = $new_username;
                        $user['password'] = password_hash($new_password, PASSWORD_BCRYPT);
                        $_SESSION['user'] = $new_username;
                        $adminFound = true;
                        break;
                    }
                }
                if (!$adminFound) {
                    $users[] = [
                        'id' => 1,
                        'username' => $new_username,
                        'password' => password_hash($new_password, PASSWORD_BCRYPT)
                    ];
                    $_SESSION['user'] = $new_username;
                }
                if (!file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $message = 'Ошибка: Не удалось сохранить данные администратора';
                } else {
                    $message = 'Данные администратора обновлены';
                }
            }
            $activeTab = 'security';
        }
    } elseif (isset($_POST['clear_log'])) {
        if (file_exists($logFile) && is_writable($logFile)) {
            file_put_contents($logFile, '');
            $message = 'Лог очищен';
        } else {
            $message = 'Ошибка: Не удалось очистить лог';
        }
        $activeTab = 'logs';
    } elseif (isset($_POST['unblock_ip'])) {
        $ip = GlobalFunctions::sanitizeInput($_POST['ip']);
        $blockedIPs = file_exists($ipBlockFile) ? json_decode(file_get_contents($ipBlockFile), true) : [];
        $blockedIPs = is_array($blockedIPs) ? $blockedIPs : [];
        $blockedIPs = array_filter($blockedIPs, fn($blockedIP) => $blockedIP['ip'] !== $ip);
        if (file_put_contents($ipBlockFile, json_encode(array_values($blockedIPs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $message = 'IP разблокирован';
        } else {
            $message = 'Ошибка: Не удалось разблокировать IP';
        }
        $activeTab = 'logs';
    }
}

$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$settings = is_array($settings) ? $settings : [];
$site_name = $settings['site_name'] ?? 'Моя CMS';
$site_title = $settings['site_title'] ?? 'Главная страница | Моя CMS';
$site_description = $settings['site_description'] ?? 'Добро пожаловать на главную страницу нашей CMS.';
$max_attempts = $settings['max_attempts'] ?? 3;
$block_duration = $settings['block_duration'] ?? 60;
$admin_email = $settings['admin_email'] ?? '';
$admin_username = $_SESSION['user'] ?? 'vasa'; // Используем текущий логин из сессии
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$users = is_array($users) ? $users : [];
foreach ($users as $user) {
    if ($user['id'] === 1) {
        $admin_username = $user['username'];
        break;
    }
}
$blockedIPs = file_exists($ipBlockFile) ? json_decode(file_get_contents($ipBlockFile), true) : [];
$blockedIPs = is_array($blockedIPs) ? $blockedIPs : [];
$logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
?>
<div class="container-fluid">
    <h2>Настройки</h2>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'general' ? 'active' : ''; ?>" href="?tab=general">Общие настройки</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'security' ? 'active' : ''; ?>" href="?tab=security">Безопасность</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'logs' ? 'active' : ''; ?>" href="?tab=logs">Логи</a>
        </li>
    </ul>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($activeTab === 'general'): ?>
        <h3>Общие настройки</h3>
        <form method="post">
            <div class="mb-3">
                <label for="site_name" class="form-label">Название сайта</label>
                <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
            </div>
            <div class="mb-3">
                <label for="site_title" class="form-label">SEO Заголовок главной страницы</label>
                <input type="text" class="form-control" id="site_title" name="site_title" value="<?php echo htmlspecialchars($site_title); ?>" required>
            </div>
            <div class="mb-3">
                <label for="site_description" class="form-label">SEO Описание главной страницы</label>
                <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($site_description); ?></textarea>
            </div>
            <button type="submit" name="update_settings" class="btn btn-primary">Сохранить</button>
        </form>
    <?php endif; ?>

    <?php if ($activeTab === 'security'): ?>
        <h3>Безопасность</h3>
        <h4 class="mt-4">Настройки входа</h4>
        <form method="post">
            <div class="mb-3">
                <label for="max_attempts" class="form-label">Максимальное количество попыток входа</label>
                <input type="number" class="form-control" id="max_attempts" name="max_attempts" value="<?php echo htmlspecialchars($max_attempts); ?>" min="1" required>
            </div>
            <div class="mb-3">
                <label for="block_duration" class="form-label">Длительность блокировки (в минутах)</label>
                <input type="number" class="form-control" id="block_duration" name="block_duration" value="<?php echo htmlspecialchars($block_duration); ?>" min="1" required>
            </div>
            <div class="mb-3">
                <label for="admin_email" class="form-label">Email администратора (для сброса пароля)</label>
                <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($admin_email); ?>">
            </div>
            <button type="submit" name="update_security" class="btn btn-primary">Сохранить</button>
        </form>

        <h4 class="mt-4">Смена данных администратора</h4>
        <form method="post">
            <div class="mb-3">
                <label for="new_username" class="form-label">Новый логин администратора</label>
                <input type="text" class="form-control" id="new_username" name="new_username" value="<?php echo htmlspecialchars($admin_username); ?>" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">Новый пароль</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Подтвердите пароль</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="update_admin" class="btn btn-primary">Сохранить</button>
        </form>
    <?php endif; ?>

    <?php if ($activeTab === 'logs'): ?>
        <h3>Логи</h3>
        <h4 class="mt-4">Очистка лога</h4>
        <form method="post">
            <button type="submit" name="clear_log" class="btn btn-warning">Очистить лог</button>
        </form>

        <h4 class="mt-4">Разблокировка IP</h4>
        <form method="post">
            <div class="mb-3">
                <label for="ip" class="form-label">IP адрес</label>
                <input type="text" class="form-control" id="ip" name="ip" placeholder="Введите IP" required>
            </div>
            <button type="submit" name="unblock_ip" class="btn btn-danger">Разблокировать</button>
        </form>

        <h4 class="mt-4">Заблокированные IP</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>IP</th>
                    <th>Время блокировки</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blockedIPs as $blockedIP): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($blockedIP['ip']); ?></td>
                        <td><?php echo htmlspecialchars($blockedIP['time']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h4 class="mt-4">Лог попыток входа</h4>
        <pre><?php echo htmlspecialchars($logContent); ?></pre>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/assets/footer.php'; ?>