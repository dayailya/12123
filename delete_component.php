<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=pc_configurator;charset=utf8", "root", "");

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM components WHERE id = ?");
$stmt->execute([$id]);

header("Location: admin.php");
exit;
