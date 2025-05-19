<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    $pdo = new PDO("mysql:host=localhost;dbname=pc_configurator;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->prepare("DELETE FROM builds WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: builds.php");
    exit;
} else {
    die("Неверный запрос.");
}
