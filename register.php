<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Валидация
    if (!$username || !$email || !$password || !$password_confirm) {
        $error = 'Пожалуйста, заполните все поля.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Неверный формат email.';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают.';
    } else {
        // Проверяем, существует ли пользователь с таким username или email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким именем или email уже существует.';
        } else {
            // Хешируем пароль и вставляем нового пользователя
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password_hash]);

            $success = 'Регистрация прошла успешно. Теперь вы можете войти.';
            // Можно сделать редирект, если хочешь:
            // header('Location: login.php');
            // exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Регистрация</title>
</head>
<body>
<h2>Регистрация</h2>

<?php if ($error): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <p style="color: green;"><?= htmlspecialchars($success) ?></p>
    <p><a href="login.php">Войти</a></p>
<?php else: ?>
    <form method="post" action="register.php">
        <label>Имя пользователя:<br>
            <input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required>
        </label><br><br>
        <label>Email:<br>
            <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
        </label><br><br>
        <label>Пароль:<br>
            <input type="password" name="password" required>
        </label><br><br>
        <label>Подтвердите пароль:<br>
            <input type="password" name="password_confirm" required>
        </label><br><br>
        <button type="submit">Зарегистрироваться</button>
    </form>
<?php endif; ?>

<p><a href="login.php">Уже есть аккаунт? Войти</a></p>

</body>
</html>
