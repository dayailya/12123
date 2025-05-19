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

//для ограничения доступа от обычных пользователей к странице