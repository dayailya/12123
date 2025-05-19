<?php
// build_detail.php
$pdo = new PDO("mysql:host=localhost;dbname=pc_configurator;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM builds WHERE id = ?");
$stmt->execute([$id]);
$build = $stmt->fetch();

if (!$build) {
    die("Сборка не найдена.");
}

$components = json_decode($build['components'], true); // Массив вида: [['id'=>1, 'name'=>'Intel i5'], ...]
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($build['name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        ul { list-style: none; padding-left: 0; }
    </style>
</head>
<body>

<h1>Сборка: <?= htmlspecialchars($build['name']) ?></h1>
<p><strong>Дата создания:</strong> <?= htmlspecialchars($build['created_at']) ?></p>
<p><strong>Цена:</strong> <?= number_format($build['price'], 0, '', ' ') ?> ₽</p>

<h2>Компоненты:</h2>
<ul>
    <?php foreach ($components as $comp): ?>
        <li><?= htmlspecialchars($comp['name']) ?></li>
    <?php endforeach; ?>
</ul>

<p><a href="builds.php">← Назад к списку</a></p>

</body>
</html>
