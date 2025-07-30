
<?php
/**
 * index.php
 */
require_once __DIR__ . '/system/ClassGlobal.php';

$pagesFile = __DIR__ . '/data/pages.json';
$menusFile = __DIR__ . '/data/menus.json';
$settingsFile = __DIR__ . '/data/settings.json';

// Проверка доступа к файлу pages.json
if (!file_exists($pagesFile)) {
    die('Ошибка: Файл pages.json не найден');
}
if (!is_readable($pagesFile)) {
    die('Ошибка: Файл pages.json недоступен для чтения');
}

$pages = json_decode(file_get_contents($pagesFile), true);
$pages = is_array($pages) ? $pages : [];
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
$menus = is_array($menus) ? $menus : [];
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$settings = is_array($settings) ? $settings : [];

$requestedUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestedUrl = trim($requestedUrl, '/');

$page = array_filter($pages, fn($p) => $p['url'] === $requestedUrl);
$page = !empty($page) ? reset($page) : null;

if (!$page && $requestedUrl === '') {
    $page = [
        'title' => $settings['site_name'] ?? 'Главная',
        'seo_title' => $settings['site_title'] ?? 'Главная страница | Моя CMS',
        'seo_description' => $settings['site_description'] ?? 'Добро пожаловать на главную страницу нашей CMS.',
        'content' => '<h2>Добро пожаловать!</h2><p>Это главная страница вашей CMS.</p>',
        'indexable' => true
    ];
}

// Теперь включаем header, когда $page уже определена
require_once __DIR__ . '/templates/frontend_header.php';
?>
<div class="container mt-4">
    <?php if ($page): ?>
        <h1><?php echo htmlspecialchars($page['title']); ?></h1>
        <div><?php echo $page['content']; ?></div>
    <?php else: ?>
        <h1>404 - Страница не найдена</h1>
        <p>Запрошенная страница не существует.</p>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/templates/frontend_footer.php'; ?>
