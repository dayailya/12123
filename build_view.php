<?php
session_start();
require_once 'db_connect.php';

$buildId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($buildId <= 0) {
    die("Некорректный ID сборки.");
}

// Получаем сборку из БД
$stmt = $pdo->prepare("SELECT * FROM builds WHERE id = ?");
$stmt->execute([$buildId]);
$build = $stmt->fetch();

if (!$build) {
    die("Сборка не найдена.");
}

// Декодируем компоненты
$components = json_decode($build['components'], true);

// Получаем подробную информацию о компонентах из таблицы components для более полного описания
$componentDetails = [];
if (!empty($components)) {
    $ids = array_column($components, 'id');
    $inQuery = implode(',', array_fill(0, count($ids), '?'));
    $stmt2 = $pdo->prepare("SELECT * FROM components WHERE id IN ($inQuery)");
    $stmt2->execute($ids);
    $componentDetailsRaw = $stmt2->fetchAll();

    foreach ($componentDetailsRaw as $comp) {
        $componentDetails[$comp['id']] = $comp;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Просмотр сборки: <?= htmlspecialchars($build['name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .component { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; }
        .component h3 { margin: 0 0 5px 0; }
        .component p { margin: 5px 0; }
        .price { font-weight: bold; }
        .btn { display: inline-block; padding: 8px 12px; margin: 10px 0; background-color: #007BFF; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <h1>Сборка: <?= htmlspecialchars($build['name']) ?></h1>
    <p><strong>Дата создания:</strong> <?= $build['created_at'] ?></p>
    <p><strong>Общая цена:</strong> <?= number_format($build['price'], 0, '', ' ') ?> ₽</p>

    <?php if (!empty($components)): ?>
        <h2>Компоненты сборки:</h2>
        <?php foreach ($components as $comp): ?>
            <?php
            $detail = $componentDetails[$comp['id']] ?? null;
            ?>
            <div class="component">
                <h3><?= htmlspecialchars($comp['type']) ?>: <?= htmlspecialchars($comp['name']) ?></h3>
                <?php if ($detail): ?>
                    <p><strong>Цена:</strong> <?= number_format($detail['price'], 0, '', ' ') ?> ₽</p>
                    <p><strong>Описание:</strong> <?= nl2br(htmlspecialchars($detail['specs'])) ?></p>
                    <?php if (!empty($detail['image_url'])): ?>
                        <p><img src="<?= htmlspecialchars($detail['image_url']) ?>" alt="<?= htmlspecialchars($comp['name']) ?>" style="max-width:200px;"></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Информация о компоненте отсутствует.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Компоненты не найдены.</p>
    <?php endif; ?>

    <a href="configurator.php?load_build=<?= $build['id'] ?>" class="btn">Загрузить сборку в конфигуратор</a>
    <p><a href="builds.php">← Вернуться к списку сборок</a></p>
</body>
</html>
