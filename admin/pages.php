
<?php

require_once __DIR__ . '/assets/header.php';
require_once __DIR__ . '/../system/ClassGlobal.php';

$pagesFile = __DIR__ . '/../data/pages.json';
$message = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'list'; // По умолчанию вкладка "Список страниц"

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
    $pages = is_array($pages) ? $pages : [];

    if (isset($_POST['add_page'])) {
        $title = GlobalFunctions::sanitizeInput($_POST['title']);
        $content = GlobalFunctions::sanitizeInput($_POST['content']);
        $url = GlobalFunctions::sanitizeInput($_POST['url']);
        $seo_title = GlobalFunctions::sanitizeInput($_POST['seo_title']);
        $seo_description = GlobalFunctions::sanitizeInput($_POST['seo_description']);
        $indexable = isset($_POST['indexable']) && $_POST['indexable'] === 'yes' ? true : false;
        $url = preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($url)));
        
        $urlExists = array_filter($pages, fn($page) => $page['url'] === $url);
        if (!empty($urlExists)) {
            $message = 'Ошибка: URL уже существует';
        } elseif (empty($title) || empty($url)) {
            $message = 'Ошибка: Заголовок и URL обязательны';
        } else {
            $id = !empty($pages) ? max(array_column($pages, 'id')) + 1 : 1;
            $pages[] = [
                'id' => $id,
                'title' => $title,
                'content' => $content,
                'url' => $url,
                'seo_title' => $seo_title,
                'seo_description' => $seo_description,
                'indexable' => $indexable
            ];
            file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = 'Страница добавлена';
            $activeTab = 'list'; // После добавления переключаемся на список
        }
    } elseif (isset($_POST['edit_page'])) {
        $id = (int)$_POST['id'];
        $title = GlobalFunctions::sanitizeInput($_POST['title']);
        $content = GlobalFunctions::sanitizeInput($_POST['content']);
        $url = GlobalFunctions::sanitizeInput($_POST['url']);
        $seo_title = GlobalFunctions::sanitizeInput($_POST['seo_title']);
        $seo_description = GlobalFunctions::sanitizeInput($_POST['seo_description']);
        $indexable = isset($_POST['indexable']) && $_POST['indexable'] === 'yes' ? true : false;
        $url = preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($url)));

        $urlExists = array_filter($pages, fn($page) => $page['url'] === $url && $page['id'] !== $id);
        if (!empty($urlExists)) {
            $message = 'Ошибка: URL уже существует';
        } elseif (empty($title) || empty($url)) {
            $message = 'Ошибка: Заголовок и URL обязательны';
        } else {
            foreach ($pages as &$page) {
                if ($page['id'] === $id) {
                    $page['title'] = $title;
                    $page['content'] = $content;
                    $page['url'] = $url;
                    $page['seo_title'] = $seo_title;
                    $page['seo_description'] = $seo_description;
                    $page['indexable'] = $indexable;
                    break;
                }
            }
            file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = 'Страница обновлена';
            $activeTab = 'list'; // После редактирования переключаемся на список
        }
    } elseif (isset($_POST['delete_page'])) {
        $id = (int)$_POST['id'];
        $pages = array_filter($pages, fn($page) => $page['id'] !== $id);
        $pages = array_values($pages);
        file_put_contents($pagesFile, json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = 'Страница удалена';
        $activeTab = 'list';
    }
}

$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$pages = is_array($pages) ? $pages : [];
$editPage = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editPage = array_filter($pages, fn($page) => $page['id'] === $editId);
    $editPage = !empty($editPage) ? reset($editPage) : null;
    $activeTab = 'add'; // При редактировании открываем вкладку "Добавить страницу"
}
?>
<div class="container-fluid">
    <h2>Управление страницами</h2>

    <!-- Горизонтальное меню вкладок -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'list' ? 'active' : ''; ?>" href="?tab=list">Список страниц</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'add' ? 'active' : ''; ?>" href="?tab=add">Добавить страницу</a>
        </li>
    </ul>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($activeTab === 'add' || $editPage): ?>
        <!-- Форма добавления/редактирования страницы -->
        <h3><?php echo $editPage ? 'Редактировать страницу' : 'Добавить страницу'; ?></h3>
        <form method="post">
            <?php if ($editPage): ?>
                <input type="hidden" name="id" value="<?php echo $editPage['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="title" class="form-label">Заголовок</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo $editPage ? htmlspecialchars($editPage['title']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="content" class="form-label">Контент (HTML)</label>
                <textarea class="form-control" id="content" name="content" rows="5"><?php echo $editPage ? htmlspecialchars($editPage['content']) : ''; ?></textarea>
            </div>
            <div class="mb-3">
                <label for="url" class="form-label">URL (например, about-us)</label>
                <input type="text" class="form-control" id="url" name="url" value="<?php echo $editPage ? htmlspecialchars($editPage['url']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="seo_title" class="form-label">SEO Заголовок</label>
                <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?php echo $editPage ? htmlspecialchars($editPage['seo_title'] ?? '') : ''; ?>">
            </div>
            <div class="mb-3">
                <label for="seo_description" class="form-label">SEO Описание</label>
                <textarea class="form-control" id="seo_description" name="seo_description" rows="3"><?php echo $editPage ? htmlspecialchars($editPage['seo_description'] ?? '') : ''; ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Индексировать страницу</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="indexable" id="indexable_yes" value="yes" <?php echo $editPage && ($editPage['indexable'] ?? true) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="indexable_yes">Да</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="indexable" id="indexable_no" value="no" <?php echo $editPage && !($editPage['indexable'] ?? true) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="indexable_no">Нет</label>
                </div>
            </div>
            <button type="submit" name="<?php echo $editPage ? 'edit_page' : 'add_page'; ?>" class="btn btn-primary">
                <?php echo $editPage ? 'Сохранить изменения' : 'Добавить страницу'; ?>
            </button>
            <?php if ($editPage): ?>
                <a href="?tab=list" class="btn btn-secondary">Отмена</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php if ($activeTab === 'list'): ?>
        <!-- Список страниц -->
        <h3>Список страниц</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Заголовок</th>
                    <th>URL</th>
                    <th>SEO Заголовок</th>
                    <th>Индексируется</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><?php echo $page['id']; ?></td>
                        <td><?php echo htmlspecialchars($page['title']); ?></td>
                        <td><?php echo htmlspecialchars($page['url']); ?></td>
                        <td><?php echo htmlspecialchars($page['seo_title'] ?? ''); ?></td>
                        <td><?php echo ($page['indexable'] ?? true) ? 'Да' : 'Нет'; ?></td>
                        <td>
                            <a href="?edit=<?php echo $page['id']; ?>&tab=add" class="btn btn-primary btn-sm">Редактировать</a>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $page['id']; ?>">
                                <button type="submit" name="delete_page" class="btn btn-danger btn-sm" onclick="return confirm('Удалить страницу?');">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/assets/footer.php'; ?>
```