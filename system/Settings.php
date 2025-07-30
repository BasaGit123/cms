
<?php
/**
 * system\Settings.php
 */
require_once __DIR__ . '/ClassGlobal.php';

class Settings {
    private $settingsFile = __DIR__ . '/../data/settings.json';
    private $userFile = __DIR__ . '/../data/user/users.json';
    private $logFile = __DIR__ . '/../data/login_attempts.log';

    public function updateCredentials($newLogin, $newPassword) {
        $users = file_exists($this->userFile) ? json_decode(file_get_contents($this->userFile), true) : [];
        $users = is_array($users) ? $users : [];
        $adminFound = false;
        foreach ($users as &$user) {
            if ($user['id'] === 1) {
                $user['username'] = GlobalFunctions::sanitizeInput($newLogin);
                $user['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
                $adminFound = true;
                break;
            }
        }
        if (!$adminFound) {
            $users[] = [
                'id' => 1,
                'username' => GlobalFunctions::sanitizeInput($newLogin),
                'password' => password_hash($newPassword, PASSWORD_BCRYPT)
            ];
        }
        if (!file_put_contents($this->userFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log("Failed to update credentials: Unable to write to users.json", 3, $this->logFile);
        }
    }

    public function updateBlockSettings($maxAttempts, $blockDuration) {
        $settings = file_exists($this->settingsFile) ? json_decode(file_get_contents($this->settingsFile), true) : [];
        $settings = is_array($settings) ? $settings : [];
        $settings['max_attempts'] = (int)$maxAttempts;
        $settings['block_duration'] = (int)$blockDuration;
        if (!file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log("Failed to update block settings: Unable to write to settings.json", 3, $this->logFile);
        }
    }

    public function getLoginAttempts() {
        $attempts = [];
        if (file_exists($this->logFile)) {
            $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                preg_match('/$$ ([^ $$]+)\] IP: ([^,]+), Username: ([^,]+), Status: ([^\s]+)(\s*$$ (.*) $$)?/', $line, $matches);
                if ($matches) {
                    $attempts[] = [
                        'time' => $matches[1],
                        'ip' => $matches[2],
                        'username' => $matches[3],
                        'status' => trim($matches[4]),
                        'message' => $matches[6] ?? ''
                    ];
                }
            }
        }
        return $attempts;
    }
}
?>