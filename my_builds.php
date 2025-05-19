<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];

// Получаем сборки пользователя
$stmt = $pdo->prepare("SELECT * FROM builds WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$builds = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8" />
<title>Мои сборки</title>
</head>
<body>
<h1>Мои сборки</h1>

<?php if (empty($builds)): ?>
    <p>У вас пока нет сохранённых сборок.</p>
<?php else: ?>
    <ul>
        <?php foreach ($builds as $build): ?>
            <li>
                <strong><?= htmlspecialchars($build['build_name']) ?></strong> — создано: <?= $build['created_at'] ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p><a href="index.php">Главная</a></p>
<p><a href="logout.php">Выйти</a></p>
</body>
</html>
