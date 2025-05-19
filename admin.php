<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // Неавторизованный пользователь — редирект на вход
    header('Location: login.php');
    exit;
}

// Проверка роли администратора (например, для admin.php)
if ($_SESSION['role'] !== 'admin') {
    // Если роль не admin — запрещаем доступ
    http_response_code(403);
    echo "Доступ запрещён.";
    exit;
}


$pdo = new PDO("mysql:host=localhost;dbname=pc_configurator;charset=utf8", "root", "");

$components = $pdo->query("SELECT * FROM components ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        table, th, td { border: 1px solid #000; border-collapse: collapse; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h2>Панель администратора</h2>
    <p><a href="logout.php">Выйти</a> | <a href="add_component.php">Добавить компонент</a></p>

    <table>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Тип</th>
            <th>Цена</th>
            <th>Характеристики</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($components as $c): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['type']) ?></td>
                <td><?= $c['price'] ?> ₽</td>
                <td><?= htmlspecialchars($c['specs']) ?></td>
                <td>
                    <a href="edit_component.php?id=<?= $c['id'] ?>">✏️</a>
                    <a href="delete_component.php?id=<?= $c['id'] ?>" onclick="return confirm('Удалить компонент?');">🗑️</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
