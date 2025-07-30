
<?php
require_once __DIR__ . '/assets/header.php';
require_once __DIR__ . '/../system/ClassGlobal.php';

$menusFile = __DIR__ . '/../data/menus.json';
$pagesFile = __DIR__ . '/../data/pages.json';
$message = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'list'; // По умолчанию вкладка "Список меню"

$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$pages = is_array($pages) ? $pages : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
    $menus = is_array($menus) ? $menus : [];

    if (isset($_POST['add_menu'])) {
        $title = GlobalFunctions::sanitizeInput($_POST['title']);
        $url = GlobalFunctions::sanitizeInput($_POST['url'] === 'custom' ? $_POST['url_custom'] : $_POST['url']);
        if (empty($title) || empty($url)) {
            $message = 'Ошибка: Название и URL обязательны';
        } else {
            $id = !empty($menus) ? max(array_column($menus, 'id')) + 1 : 1;
            $menus[] = ['id' => $id, 'title' => $title, 'url' => $url];
            file_put_contents($menusFile, json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = 'Пункт меню добавлен';
            $activeTab = 'list'; // После добавления переключаемся на список
        }
    } elseif (isset($_POST['edit_menu'])) {
        $id = (int)$_POST['id'];
        $title = GlobalFunctions::sanitizeInput($_POST['title']);
        $url = GlobalFunctions::sanitizeInput($_POST['url'] === 'custom' ? $_POST['url_custom'] : $_POST['url']);
        if (empty($title) || empty($url)) {
            $message = 'Ошибка: Название и URL обязательны';
        } else {
            foreach ($menus as &$menu) {
                if ($menu['id'] === $id) {
                    $menu['title'] = $title;
                    $menu['url'] = $url;
                    break;
                }
            }
            file_put_contents($menusFile, json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $message = 'Пункт меню обновлен';
            $activeTab = 'list'; // После редактирования переключаемся на список
        }
    } elseif (isset($_POST['delete_menu'])) {
        $id = (int)$_POST['id'];
        $menus = array_filter($menus, fn($menu) => $menu['id'] !== $id);
        $menus = array_values($menus);
        file_put_contents($menusFile, json_encode($menus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = 'Пункт меню удален';
        $activeTab = 'list';
    }
}

$menus = file_exists($menusFile) ? json_decode(file_get_contents($menusFile), true) : [];
$menus = is_array($menus) ? $menus : [];
$editMenu = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editMenu = array_filter($menus, fn($menu) => $menu['id'] === $editId);
    $editMenu = !empty($editMenu) ? reset($editMenu) : null;
    $activeTab = 'add'; // При редактировании открываем вкладку "Добавить пункт меню"
}
?>
<div class="container-fluid">
    <h2>Управление меню</h2>

    <!-- Горизонтальное меню вкладок -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'list' ? 'active' : ''; ?>" href="?tab=list">Список меню</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'add' ? 'active' : ''; ?>" href="?tab=add">Добавить пункт меню</a>
        </li>
    </ul>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($activeTab === 'add' || $editMenu): ?>
        <!-- Форма добавления/редактирования пункта меню -->
        <h3><?php echo $editMenu ? 'Редактировать пункт меню' : 'Добавить пункт меню'; ?></h3>
        <form method="post">
            <?php if ($editMenu): ?>
                <input type="hidden" name="id" value="<?php echo $editMenu['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="title" class="form-label">Название пункта</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo $editMenu ? htmlspecialchars($editMenu['title']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="url" class="form-label">URL (выберите страницу или введите вручную)</label>
                <select class="form-control" id="url" name="url" required>
                    <option value="">Выберите страницу</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo htmlspecialchars($page['url']); ?>" <?php echo $editMenu && $editMenu['url'] === $page['url'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($page['title']); ?> (<?php echo htmlspecialchars($page['url']); ?>)
                        </option>
                    <?php endforeach; ?>
                    <option value="custom" <?php echo $editMenu && !in_array($editMenu['url'], array_column($pages, 'url')) ? 'selected' : ''; ?>>Пользовательский URL</option>
                </select>
                <input type="text" class="form-control mt-2" id="custom_url" name="url_custom" placeholder="Введите пользовательский URL" value="<?php echo $editMenu && !in_array($editMenu['url'], array_column($pages, 'url')) ? htmlspecialchars($editMenu['url']) : ''; ?>">
            </div>
            <button type="submit" name="<?php echo $editMenu ? 'edit_menu' : 'add_menu'; ?>" class="btn btn-primary">
                <?php echo $editMenu ? 'Сохранить изменения' : 'Добавить пункт'; ?>
            </button>
            <?php if ($editMenu): ?>
                <a href="?tab=list" class="btn btn-secondary">Отмена</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php if ($activeTab === 'list'): ?>
        <!-- Список пунктов меню -->
        <h3>Список пунктов меню</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>URL</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menus as $menu): ?>
                    <tr>
                        <td><?php echo $menu['id']; ?></td>
                        <td><?php echo htmlspecialchars($menu['title']); ?></td>
                        <td><?php echo htmlspecialchars($menu['url']); ?></td>
                        <td>
                            <a href="?edit=<?php echo $menu['id']; ?>&tab=add" class="btn btn-primary btn-sm">Редактировать</a>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $menu['id']; ?>">
                                <button type="submit" name="delete_menu" class="btn btn-danger btn-sm" onclick="return confirm('Удалить пункт меню?');">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<script>
    document.getElementById('url').addEventListener('change', function() {
        document.getElementById('custom_url').style.display = this.value === 'custom' ? 'block' : 'none';
        if (this.value !== 'custom') {
            document.getElementById('custom_url').value = '';
        }
    });
    document.getElementById('custom_url').style.display = document.getElementById('url').value === 'custom' ? 'block' : 'none';
</script>
<?php require_once __DIR__ . '/assets/footer.php'; ?>
