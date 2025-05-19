<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Далее контент страницы для авторизованных пользователей
echo "Привет, " . htmlspecialchars($_SESSION['username']) . "! Ваша роль: " . htmlspecialchars($_SESSION['role']);
?>


require_once 'db_connect.php';

$types = ['CPU', 'Motherboard', 'RAM', 'GPU', 'Storage', 'PSU', 'Case', 'Cooling'];
$componentsByType = [];

// Получаем компоненты из БД
foreach ($types as $type) {
    $stmt = $pdo->prepare("SELECT * FROM components WHERE type = ?");
    $stmt->execute([$type]);
    $componentsByType[$type] = $stmt->fetchAll();
}

$selectedComponents = [];
$totalPrice = 0;
$errors = [];
$saveMessage = '';

// Загрузка сборки по ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_build'])) {
    $buildId = (int)$_POST['load_build'];
    $stmt = $pdo->prepare("SELECT components FROM builds WHERE id = ?");
    $stmt->execute([$buildId]);
    $build = $stmt->fetch();
    if ($build) {
        $loadedComponents = json_decode($build['components'], true);
        foreach ($loadedComponents as $comp) {
            $_POST[$comp['type']] = $comp['id'];
        }
    }
}

// Параметры для проверок совместимости
$cpuSocket = $mbSocket = null;
$ramType = $mbRamType = null;
$psuWatts = $totalPower = 0;
$mbFormFactor = $caseSupport = null;
$coolerSockets = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($types as $type) {
        if (!empty($_POST[$type])) {
            $componentId = (int) $_POST[$type];
            $stmt = $pdo->prepare("SELECT * FROM components WHERE id = ?");
            $stmt->execute([$componentId]);
            $comp = $stmt->fetch();

            if ($comp) {
                $selectedComponents[] = $comp;
                $totalPrice += $comp['price'];

                $specs = $comp['specs'];

                switch ($type) {
                    case 'CPU':
                        if (preg_match('/Сокет:\s*([A-Z0-9]+)/ui', $specs, $m)) $cpuSocket = $m[1];
                        if (preg_match('/Потребление:\s*(\d+)W/ui', $specs, $m)) $totalPower += (int)$m[1];
                        break;
                    case 'Motherboard':
                        if (preg_match('/Сокет:\s*([A-Z0-9]+)/ui', $specs, $m)) $mbSocket = $m[1];
                        if (preg_match('/Поддержка RAM:\s*(DDR[0-9]+)/ui', $specs, $m)) $mbRamType = strtoupper($m[1]);
                        if (preg_match('/Форм-фактор:\s*([A-Z0-9]+)/ui', $specs, $m)) $mbFormFactor = strtoupper($m[1]);
                        break;
                    case 'RAM':
                        if (preg_match('/Тип:\s*(DDR[0-9]+)/ui', $specs, $m)) $ramType = strtoupper($m[1]);
                        break;
                    case 'GPU':
                        if (preg_match('/Потребление:\s*(\d+)W/ui', $specs, $m)) $totalPower += (int)$m[1];
                        break;
                    case 'PSU':
                        if (preg_match('/Мощность:\s*(\d+)W/ui', $specs, $m)) $psuWatts = (int)$m[1];
                        break;
                    case 'Case':
                        if (preg_match('/Поддержка:\s*([A-Za-z0-9,\s]+)/ui', $specs, $m))
                            $caseSupport = array_map('trim', explode(',', strtoupper($m[1])));
                        break;
                    case 'Cooling':
                        if (preg_match('/Сокеты:\s*([A-Za-z0-9,\s]+)/ui', $specs, $m))
                            $coolerSockets = array_map('trim', explode(',', strtoupper($m[1])));
                        break;
                }
            }
        }
    }

    // Проверки совместимости
    if ($cpuSocket && $mbSocket && $cpuSocket !== $mbSocket)
        $errors[] = "❌ Несовместимость: сокет CPU ($cpuSocket) ≠ сокет материнской платы ($mbSocket).";

    if ($ramType && $mbRamType && $ramType !== $mbRamType)
        $errors[] = "❌ Несовместимость: тип RAM ($ramType) ≠ поддержка материнки ($mbRamType).";

    if ($psuWatts > 0 && $totalPower > 0 && $psuWatts < $totalPower + 100)
        $errors[] = "❌ Недостаточная мощность БП: требуется минимум " . ($totalPower + 100) . "W, выбрано $psuWatts W.";

    if ($mbFormFactor && $caseSupport && !in_array($mbFormFactor, $caseSupport))
        $errors[] = "❌ Несовместимость: форм-фактор материнки $mbFormFactor не поддерживается корпусом.";

    if ($cpuSocket && $coolerSockets && !in_array($cpuSocket, $coolerSockets))
        $errors[] = "❌ Несовместимость: кулер не поддерживает сокет CPU ($cpuSocket).";

    // Обработка сохранения сборки - только для авторизованных
    if (isset($_POST['save_build'])) {
        if (!isset($_SESSION['user_id'])) {
            $errors[] = "❌ Для сохранения сборки необходимо войти в аккаунт.";
        } else {
            $buildName = trim($_POST['build_name'] ?? '');
            if ($buildName === '') {
                $errors[] = "❌ Пожалуйста, введите имя сборки для сохранения.";
            }

            if (empty($errors)) {
                $componentsForSave = [];
                foreach ($selectedComponents as $comp) {
                    $componentsForSave[] = [
                        'id' => $comp['id'],
                        'name' => $comp['name'],
                        'type' => $comp['type']
                    ];
                }
                $componentsJson = json_encode($componentsForSave, JSON_UNESCAPED_UNICODE);

                $stmt = $pdo->prepare("INSERT INTO builds (name, components, price, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                try {
                    $stmt->execute([$buildName, $componentsJson, $totalPrice, $_SESSION['user_id']]);
                    $saveMessage = "✅ Сборка \"$buildName\" успешно сохранена!";
                } catch (PDOException $e) {
                    $errors[] = "❌ Ошибка при сохранении сборки: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <title>Конфигуратор ПК</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
        .error { color: red; }
        .success { color: green; }
        label { display: block; margin-top: 10px; }
        select, input[type=text] { width: 250px; padding: 5px; }
        .components { margin-bottom: 20px; }
        .btn { margin-top: 15px; padding: 8px 12px; cursor: pointer; }
        .summary { margin-top: 30px; background: #f4f4f4; padding: 15px; }
    </style>
</head>
<body>
    <h1>Конфигуратор ПК</h1>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err) {
                echo "<p>$err</p>";
            } ?>
        </div>
    <?php endif; ?>

    <?php if ($saveMessage): ?>
        <div class="success"><?= htmlspecialchars($saveMessage) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php foreach ($types as $type): ?>
            <div class="components">
                <label for="<?= $type ?>"><?= $type ?>:</label>
                <select name="<?= $type ?>" id="<?= $type ?>">
                    <option value="">-- Выберите <?= $type ?> --</option>
                    <?php foreach ($componentsByType[$type] as $comp): ?>
                        <option value="<?= $comp['id'] ?>"
                        <?= (isset($_POST[$type]) && $_POST[$type] == $comp['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($comp['name']) ?> — <?= $comp['price'] ?> ₽
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn">Проверить совместимость</button>
    </form>

    <?php if (!empty($selectedComponents)): ?>
        <div class="summary">
            <h2>Выбранные компоненты:</h2>
            <ul>
                <?php foreach ($selectedComponents as $comp): ?>
                    <li><?= htmlspecialchars($comp['type']) ?>: <?= htmlspecialchars($comp['name']) ?> — <?= $comp['price'] ?> ₽</li>
                <?php endforeach; ?>
            </ul>
            <p><strong>Итоговая цена: <?= $totalPrice ?> ₽</strong></p>

            <?php if (empty($errors)): ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="post" action="">
                        <input type="hidden" name="save_build" value="1" />
                        <input type="text" name="build_name" placeholder="Имя сборки" required />
                        <button type="submit" class="btn">Сохранить сборку</button>
                    </form>
                <?php else: ?>
                    <p>Чтобы сохранить сборку, пожалуйста, <a href="login.php">войдите в аккаунт</a>.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <hr />

    <h2>Загрузить сохранённую сборку</h2>
    <form method="post" action="">
        <select name="load_build" required>
            <option value="">-- Выберите сборку --</option>
            <?php
            // Выводим сохранённые сборки пользователя
            if (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT id, name FROM builds WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$_SESSION['user_id']]);
                $builds = $stmt->fetchAll();
                foreach ($builds as $build) {
                    echo "<option value=\"{$build['id']}\">" . htmlspecialchars($build['name']) . "</option>";
                }
            } else {
                echo "<option disabled>Войдите, чтобы увидеть сборки</option>";
            }
            ?>
        </select>
        <button type="submit" class="btn">Загрузить сборку</button>
    </form>

</body>
</html>
