
<?php
$menusFile = __DIR__ . '/../data/menus.json';
$settingsFile = __DIR__ . '/../data/settings.json';
$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
$menus = is_array($menus) ? $menus : [];
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$settings = is_array($settings) ? $settings : [];

// Проверка, определена ли переменная $page
if (!isset($page) || !is_array($page)) {
    $page = [
        'title' => $settings['site_name'] ?? 'Моя CMS',
        'seo_title' => $settings['site_title'] ?? 'Моя CMS',
        'seo_description' => $settings['site_description'] ?? 'Добро пожаловать в нашу CMS',
        'indexable' => true
    ];
}

// Установка SEO-параметров
$seo_title = !empty($page['seo_title']) ? htmlspecialchars($page['seo_title']) : htmlspecialchars($page['title'] ?? $settings['site_name'] ?? 'Моя CMS');
$seo_description = !empty($page['seo_description']) ? htmlspecialchars($page['seo_description']) : ($settings['site_description'] ?? 'Добро пожаловать в нашу CMS');
$indexable = isset($page['indexable']) ? $page['indexable'] : true;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $seo_title; ?></title>
    <meta name="description" content="<?php echo $seo_description; ?>">
    <meta name="robots" content="<?php echo $indexable ? 'index, follow' : 'noindex, nofollow'; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="/"><?php echo htmlspecialchars($settings['site_name'] ?? 'Моя CMS'); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <?php foreach ($menus as $menu): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/<?php echo htmlspecialchars($menu['url']); ?>">
                                <?php echo htmlspecialchars($menu['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
