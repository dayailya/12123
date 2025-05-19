<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=pc_configurator;charset=utf8", "root", "");

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM components WHERE id = ?");
$stmt->execute([$id]);
$component = $stmt->fetch();

if (!$component) {
    die("Компонент не найден");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $price = $_POST['price'];
    $specs = $_POST['specs'];

    $update = $pdo->prepare("UPDATE components SET name=?, type=?, price=?, specs=? WHERE id=?");
    $update->execute([$name, $type, $price, $specs, $id]);

    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать компонент</title>
</head>
<body>
    <h2>Редактировать компонент</h2>
    <form method="post">
        <label>Название: <input name="name" value="<?= htmlspecialchars($component['name']) ?>" required></label><br><br>
        <label>Тип:
            <select name="type" required>
                <?php
                $types = ['CPU','Motherboard','RAM','GPU','PSU','Storage','Case','Cooling'];
                foreach ($types as $t) {
                    $selected = $component['type'] === $t ? 'selected' : '';
                    echo "<option value=\"$t\" $selected>$t</option>";
                }
                ?>
            </select>
        </label><br><br>
        <label>Цена: <input type="number" name="price" value="<?= $component['price'] ?>" required></label><br><br>
        <label>Характеристики:<br>
            <textarea name="specs" rows="4" cols="50" required><?= htmlspecialchars($component['specs']) ?></textarea>
        </label><br><br>
        <button type="submit">Сохранить</button>
    </form>
    <p><a href="admin.php">Назад</a></p>
</body>
</html>
