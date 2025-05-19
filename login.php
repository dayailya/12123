<?php
session_start();
require_once 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? ''); // username или email
    $password = $_POST['password'] ?? '';

    if (!$login || !$password) {
        $error = 'Пожалуйста, заполните все поля.';
    } else {
        // Ищем пользователя по username или email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Успешная авторизация
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Перенаправляем на защищённую страницу (например, index.php или dashboard.php)
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверное имя пользователя/email или пароль.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Вход</title>
</head>
<body>
<h2>Вход</h2>

<?php if ($error): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="login.php">
    <label>Имя пользователя или Email:<br>
        <input type="text" name="login" value="<?= htmlspecialchars($login ?? '') ?>" required>
    </label><br><br>
    <label>Пароль:<br>
        <input type="password" name="password" required>
    </label><br><br>
    <button type="submit">Войти</button>
</form>

<p><a href="register.php">Регистрация</a></p>

</body>
</html>
