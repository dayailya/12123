<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=pc_configurator;charset=utf8", "root", "");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $price = $_POST['price'];
    $specs = $_POST['specs'];

    $stmt = $pdo->prepare("INSERT INTO components (name, type, price, specs) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $type, $price, $specs]);

    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить компонент</title>
</head>
<body>
    <h2>Добавить компонент</h2>
    <form method="post">
        <label>Название: <input name="name" required></label><br><br>
        <label>Тип:
            <select name="type" required>
                <option value="CPU">CPU</option>
                <option value="Motherboard">Motherboard</option>
                <option value="RAM">RAM</option>
                <option value="GPU">GPU</option>
                <option value="PSU">PSU</option>
                <option value="Storage">Storage</option>
                <option value="Case">Case</option>
                <option value="Cooling">Cooling</option>
            </select>
        </label><br><br>
        <label>Цена: <input type="number" name="price" required></label><br><br>
        <label>Характеристики:<br>
            <textarea name="specs" rows="4" cols="50" required></textarea>
        </label><br><br>
        <button type="submit">Сохранить</button>
    </form>
    <p><a href="admin.php">Назад</a></p>
</body>
</html>
