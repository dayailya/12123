<?php
session_start();
require_once 'db_connect.php'; // Подключение к БД

// Если есть авторизация, можно получить $userId из сессии, например:
// $userId = $_SESSION['user_id'] ?? 0;
// Для демонстрации пока просто покажем все сборки

$userId = $_SESSION['user_id'] ?? 0; // заменяй под свою логику

// Получаем сборки текущего пользователя или все (если не авторизован)
if ($userId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM builds WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
} else {
    // Показать все сборки (например, для гостя)
    $stmt = $pdo->query("SELECT * FROM builds ORDER BY created_at DESC");
}

$builds = $stmt->fetchAll();

// Обработка удаления сборки (только если пользователь владелец)
if (isset($_GET['delete_id']) && $userId > 0) {
    $deleteId = (int) $_GET['delete_id'];
    // Проверяем принадлежит ли сборка пользователю
    $stmtCheck = $pdo->prepare("SELECT user_id FROM builds WHERE id = ?");
    $stmtCheck->execute([$deleteId]);
    $owner = $stmtCheck->fetchColumn();

    if ($owner == $userId) {
        $stmtDel = $pdo->prepare("DELETE FROM builds WHERE id = ?");
        $stmtDel->execute([$deleteId]);
    }
    header("Location: builds.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Сохранённые сборки ПК</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #eee; }
        a.delete { color: red; text-decoration: none; }
    </style>
</head>
<body>
<h1>Сохранённые сборки ПК</h1>

<?php if (empty($builds)): ?>
    <p>Сборки ещё не сохранены.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Имя сборки</th>
                <th>Компоненты</th>
                <th>Цена</th>
                <th>Дата создания</th>
                <?php if ($userId > 0): ?>
                <th>Действия</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($builds as $build): ?>
                <tr>
                    <td><?= htmlspecialchars($build['id']) ?></td>
                    <td><?= htmlspecialchars($build['name']) ?></td>
                    <td>
                        <?php
                        $components = json_decode($build['components'], true);
                        if (is_array($components)) {
                            $names = array_map(fn($c) => htmlspecialchars($c['name']), $components);
                            echo implode(', ', $names);
                        }
                        ?>
                    </td>
                    <td><?= number_format($build['price'], 0, '', ' ') ?> ₽</td>
                    <td><?= $build['created_at'] ?></td>
                    <?php if ($userId > 0): ?>
                    <td>
                        <?php if ($build['user_id'] == $userId): ?>
                            <a href="builds.php?delete_id=<?= $build['id'] ?>" class="delete" onclick="return confirm('Удалить сборку «<?= addslashes($build['name']) ?>»?')">Удалить</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="configurator.php">← Вернуться к конфигуратору</a></p>
</body>
</html>
