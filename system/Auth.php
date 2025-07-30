
<?php
/**
 * system\Auth.php
 */
class Auth {
    private $usersFile = __DIR__ . '/../data/user/users.json';
    private $ipBlockFile = __DIR__ . '/../data/user/ip_block.json';
    private $logFile = __DIR__ . '/../data/login_attempts.log';
    private $settingsFile = __DIR__ . '/../data/settings.json';
    private $attemptsFile = __DIR__ . '/../data/login_attempts.json';
    private $resetCodesFile = __DIR__ . '/../data/reset_codes.json';

    public function login($username, $password) {
        $settings = file_exists($this->settingsFile) ? json_decode(file_get_contents($this->settingsFile), true) : [];
        $max_attempts = $settings['max_attempts'] ?? 3;
        $block_duration = $settings['block_duration'] ?? 60;
        $ip = $this->getClientIP();

        if ($this->isIPBlocked($ip)) {
            $this->logAttempt($ip, $username, 'FAILED', 'IP заблокирован');
            error_log("Login failed: IP $ip is blocked", 3, $this->logFile);
            return false;
        }

        $attempts = $this->getLoginAttempts($ip);
        if ($attempts >= $max_attempts) {
            $this->blockIP($ip);
            $this->logAttempt($ip, $username, 'FAILED', 'IP заблокирован после превышения попыток');
            error_log("Login failed: IP $ip blocked after $attempts attempts", 3, $this->logFile);
            return false;
        }

        $users = file_exists($this->usersFile) ? json_decode(file_get_contents($this->usersFile), true) : [];
        $users = is_array($users) ? $users : [];

        foreach ($users as $user) {
            if ($user['username'] === $username) {
                $passwordMatch = password_verify($password, $user['password']);
                error_log("Password verify: username=$username, password=$password, stored_hash={$user['password']}, match=$passwordMatch", 3, $this->logFile);
                if ($passwordMatch) {
                    session_regenerate_id(true); // Регенерация ID сессии
                    $_SESSION['user'] = $username;
                    $this->clearLoginAttempts($ip);
                    $this->logAttempt($ip, $username, 'SUCCESS');
                    error_log("Login success: $username from IP $ip, session_user=" . ($_SESSION['user'] ?? 'none'), 3, $this->logFile);
                    return true;
                } else {
                    $this->incrementLoginAttempts($ip);
                    sleep(2); // Задержка 2 секунды для защиты от brute-force
                    $this->logAttempt($ip, $username, 'FAILED', 'Неверный пароль');
                    error_log("Login failed: Invalid password for $username from IP $ip, attempts: " . ($attempts + 1), 3, $this->logFile);
                    return false;
                }
            }
        }

        $this->incrementLoginAttempts($ip);
        sleep(2); // Задержка 2 секунды
        $this->logAttempt($ip, $username, 'FAILED', 'Пользователь не найден');
        error_log("Login failed: Username $username not found from IP $ip, attempts: " . ($attempts + 1), 3, $this->logFile);
        return false;
    }

    public function isAuthenticated() {
        $isAuth = isset($_SESSION['user']);
        error_log("isAuthenticated check: session_user=" . ($_SESSION['user'] ?? 'none') . ", result=" . ($isAuth ? 'true' : 'false'), 3, $this->logFile);
        return $isAuth;
    }

    public function logout() {
        session_unset();
        session_destroy();
        error_log("User logged out, session cleared", 3, $this->logFile);
    }

    public function recoverPassword($email, $code, $newPassword) {
        $settings = file_exists($this->settingsFile) ? json_decode(file_get_contents($this->settingsFile), true) : [];
        $resetCodes = file_exists($this->resetCodesFile) ? json_decode(file_get_contents($this->resetCodesFile), true) : [];
        $resetCodes = is_array($resetCodes) ? $resetCodes : [];

        if ($email !== ($settings['admin_email'] ?? '')) {
            error_log("Recover password failed: Email $email does not match admin_email", 3, $this->logFile);
            return false;
        }

        $validCode = false;
        foreach ($resetCodes as $rc) {
            if ($rc['email'] === $email && password_verify($code, $rc['code']) && (time() - $rc['timestamp']) < 5 * 60) {
                $validCode = true;
                break;
            }
        }
        if (!$validCode) {
            error_log("Recover password failed: Invalid or expired code for email $email", 3, $this->logFile);
            return false;
        }

        $users = file_exists($this->usersFile) ? json_decode(file_get_contents($this->usersFile), true) : [];
        $users = is_array($users) ? $users : [];
        $adminFound = false;
        $adminUsername = 'vasa';
        foreach ($users as &$user) {
            if ($user['id'] === 1) {
                $user['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
                $adminUsername = $user['username'];
                $adminFound = true;
                break;
            }
        }
        if (!$adminFound) {
            $users[] = [
                'id' => 1,
                'username' => 'vasa',
                'password' => password_hash($newPassword, PASSWORD_BCRYPT)
            ];
        }
        if (!file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log("Recover password failed: Unable to write to users.json", 3, $this->logFile);
            return false;
        }
        $resetCodes = array_filter($resetCodes, fn($rc) => $rc['email'] !== $email);
        file_put_contents($this->resetCodesFile, json_encode($resetCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        error_log("Password recovered for email $email, new_password=$newPassword", 3, $this->logFile);
        return true;
    }

    public function isIPBlocked($ip) {
        $blockedIPs = file_exists($this->ipBlockFile) ? json_decode(file_get_contents($this->ipBlockFile), true) : [];
        $blockedIPs = is_array($blockedIPs) ? $blockedIPs : [];
        $settings = file_exists($this->settingsFile) ? json_decode(file_get_contents($this->settingsFile), true) : [];
        $block_duration = ($settings['block_duration'] ?? 60) * 60;

        foreach ($blockedIPs as $blockedIP) {
            if ($blockedIP['ip'] === $ip) {
                $blockTime = strtotime($blockedIP['time']);
                if ((time() - $blockTime) < $block_duration) {
                    error_log("IP $ip is blocked until " . date('Y-m-d H:i:s', $blockTime + $block_duration), 3, $this->logFile);
                    return true;
                } else {
                    $this->unblockIP($ip);
                }
            }
        }
        return false;
    }

    public function unblockIP($ip) {
        $blockedIPs = file_exists($this->ipBlockFile) ? json_decode(file_get_contents($this->ipBlockFile), true) : [];
        $blockedIPs = is_array($blockedIPs) ? $blockedIPs : [];
        $blockedIPs = array_filter($blockedIPs, fn($blockedIP) => $blockedIP['ip'] !== $ip);
        if (file_put_contents($this->ipBlockFile, json_encode(array_values($blockedIPs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log("IP $ip successfully unblocked", 3, $this->logFile);
        } else {
            error_log("Failed to unblock IP $ip: Unable to write to ip_block.json", 3, $this->logFile);
        }
        $this->clearLoginAttempts($ip);
    }

    private function blockIP($ip) {
        $blockedIPs = file_exists($this->ipBlockFile) ? json_decode(file_get_contents($this->ipBlockFile), true) : [];
        $blockedIPs = is_array($blockedIPs) ? $blockedIPs : [];
        $blockedIPs[] = [
            'ip' => $ip,
            'time' => date('Y-m-d H:i:s')
        ];
        if (!file_put_contents($this->ipBlockFile, json_encode($blockedIPs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log("Failed to block IP $ip: Unable to write to ip_block.json", 3, $this->logFile);
        } else {
            error_log("IP $ip blocked", 3, $this->logFile);
        }
    }

    private function getLoginAttempts($ip) {
        $attemptsData = file_exists($this->attemptsFile) ? json_decode(file_get_contents($this->attemptsFile), true) : [];
        $attemptsData = is_array($attemptsData) ? $attemptsData : [];
        foreach ($attemptsData as $entry) {
            if ($entry['ip'] === $ip) {
                $lastAttemptTime = strtotime($entry['last_attempt']);
                if ((time() - $lastAttemptTime) < 15 * 60) {
                    return $entry['attempts'];
                } else {
                    $this->clearLoginAttempts($ip);
                    return 0;
                }
            }
        }
        return 0;
    }

    private function incrementLoginAttempts($ip) {
        $attemptsData = file_exists($this->attemptsFile) ? json_decode(file_get_contents($this->attemptsFile), true) : [];
        $attemptsData = is_array($attemptsData) ? $attemptsData : [];
        $found = false;
        foreach ($attemptsData as &$entry) {
            if ($entry['ip'] === $ip) {
                $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
                $entry['last_attempt'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        if (!$found) {
            $attemptsData[] = [
                'ip' => $ip,
                'attempts' => 1,
                'last_attempt' => date('Y-m-d H:i:s')
            ];
        }
        if (!file_put_contents($this->attemptsFile, json_encode($attemptsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log("Failed to increment login attempts for IP $ip: Unable to write to login_attempts.json", 3, $this->logFile);
        }
    }

    private function clearLoginAttempts($ip) {
        $attemptsData = file_exists($this->attemptsFile) ? json_decode(file_get_contents($this->attemptsFile), true) : [];
        $attemptsData = is_array($attemptsData) ? $attemptsData : [];
        $attemptsData = array_filter($attemptsData, fn($entry) => $entry['ip'] !== $ip);
        if (!file_put_contents($this->attemptsFile, json_encode(array_values($attemptsData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log("Failed to clear login attempts for IP $ip: Unable to write to login_attempts.json", 3, $this->logFile);
        } else {
            error_log("Login attempts cleared for IP $ip", 3, $this->logFile);
        }
    }

    private function logAttempt($ip, $username, $status, $message = '') {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'unknown';
        $logMessage = '[' . date('Y-m-d H:i:s') . "] IP: $ip, Username: $username, Status: $status, User-Agent: $userAgent, Referer: $referer";
        if ($message) {
            $logMessage .= " ($message)";
        }
        $logMessage .= "\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    private function getClientIP() {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
?>