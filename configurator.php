<?php
session_start();

// Подключение к БД
$host = 'localhost';
$db   = 'pc_configurator';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Типы комплектующих
$types = ['CPU', 'Motherboard', 'RAM', 'GPU', 'Storage', 'PSU', 'Case', 'Cooling'];
$componentsByType = [];

// Загружаем компоненты по типам
foreach ($types as $type) {
    $stmt = $pdo->prepare("SELECT * FROM components WHERE type = ?");
    $stmt->execute([$type]);
    $componentsByType[$type] = $stmt->fetchAll();
}

// Инициализация выбранных компонентов в сессии
if (!isset($_SESSION['selectedComponents'])) {
    $_SESSION['selectedComponents'] = [];
}

$selectedComponents = [];
$totalPrice = 0;
$errors = [];
$saveMessage = '';

// Переменные для проверок совместимости
$cpuSocket = $mbSocket = null;
$ramType = $mbRamType = null;
$psuWatts = $totalPower = 0;
$mbFormFactor = $caseSupport = null;
$coolerSockets = [];

// Если загрузка сборки через GET-параметр (например, из просмотра сборки)
if (isset($_GET['load_build'])) {
    $loadBuildId = (int)$_GET['load_build'];
    $stmt = $pdo->prepare("SELECT components FROM builds WHERE id = ?");
    $stmt->execute([$loadBuildId]);
    $buildData = $stmt->fetchColumn();

    if ($buildData) {
        $loadedComponents = json_decode($buildData, true);
        if (is_array($loadedComponents)) {
            // Нам нужно загрузить компоненты и сохранить в сессию
            $newSelection = [];
            foreach ($loadedComponents as $compId) {
                // Определяем тип компонента из БД
                $stmt2 = $pdo->prepare("SELECT type FROM components WHERE id = ?");
                $stmt2->execute([$compId]);
                $typeFound = $stmt2->fetchColumn();
                if ($typeFound && in_array($typeFound, $types)) {
                    $newSelection[$typeFound] = $compId;
                }
            }
            $_SESSION['selectedComponents'] = $newSelection;
            // Чтобы после загрузки сборки отобразить её сразу, перенаправим на эту же страницу без параметра
            header("Location: configurator.php");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Собираем выбранные ID компонентов из формы
    $selectedComponentIds = [];
    foreach ($types as $type) {
        if (!empty($_POST[$type])) {
            $componentId = (int) $_POST[$type];
            $selectedComponentIds[$type] = $componentId;
        }
    }
    // Сохраняем выбор в сессию
    $_SESSION['selectedComponents'] = $selectedComponentIds;

    // Загружаем данные выбранных компонентов и суммируем цену
    foreach ($selectedComponentIds as $type => $componentId) {
        $stmt = $pdo->prepare("SELECT * FROM components WHERE id = ?");
        $stmt->execute([$componentId]);
        $comp = $stmt->fetch();

        if ($comp) {
            $selectedComponents[] = $comp;
            $totalPrice += $comp['price'];

            $specs = $comp['specs'];

            // Анализируем характеристики для проверок совместимости
            switch ($type) {
                case 'CPU':
                    if (preg_match('/Сокет:\s*([A-Z0-9]+)/ui', $specs, $m)) {
                        $cpuSocket = strtoupper($m[1]);
                    }
                    if (preg_match('/Потребление:\s*(\d+)W/ui', $specs, $m)) {
                        $totalPower += (int)$m[1];
                    }
                    break;

                case 'Motherboard':
                    if (preg_match('/Сокет:\s*([A-Z0-9]+)/ui', $specs, $m)) {
                        $mbSocket = strtoupper($m[1]);
                    }
                    if (preg_match('/Поддержка RAM:\s*(DDR[0-9]+)/ui', $specs, $m)) {
                        $mbRamType = strtoupper($m[1]);
                    }
                    if (preg_match('/Форм-фактор:\s*([A-Z0-9]+)/ui', $specs, $m)) {
                        $mbFormFactor = strtoupper($m[1]);
                    }
                    break;

                case 'RAM':
                    if (preg_match('/Тип:\s*(DDR[0-9]+)/ui', $specs, $m)) {
                        $ramType = strtoupper($m[1]);
                    }
                    break;

                case 'GPU':
                    if (preg_match('/Потребление:\s*(\d+)W/ui', $specs, $m)) {
                        $totalPower += (int)$m[1];
                    }
                    break;

                case 'PSU':
                    if (preg_match('/Мощность:\s*(\d+)W/ui', $specs, $m)) {
                        $psuWatts = (int)$m[1];
                    }
                    break;

                case 'Case':
                    if (preg_match('/Поддержка:\s*([A-Za-z0-9,\s]+)/ui', $specs, $m)) {
                        $caseSupport = array_map('trim', explode(',', strtoupper($m[1])));
                    }
                    break;

                case 'Cooling':
                    if (preg_match('/Сокеты:\s*([A-Za-z0-9,\s]+)/ui', $specs, $m)) {
                        $coolerSockets = array_map('trim', explode(',', strtoupper($m[1])));
                    }
                    break;
            }
        }
    }

    // Проверки совместимости
    if ($cpuSocket && $mbSocket && $cpuSocket !== $mbSocket) {
        $errors[] = "❌ Несовместимость: сокет CPU ($cpuSocket) ≠ сокет материнской платы ($mbSocket).";
    }

    if ($ramType && $mbRamType && $ramType !== $mbRamType) {
        $errors[] = "❌ Несовместимость: тип RAM ($ramType) ≠ поддержка материнки ($mbRamType).";
    }

    if ($psuWatts > 0 && $totalPower > 0 && $psuWatts < $totalPower + 100) {
        $errors[] = "❌ Недостаточная мощность БП: требуется минимум " . ($totalPower + 100) . "W, выбрано $psuWatts W.";
    }

    if ($mbFormFactor && $caseSupport && !in_array($mbFormFactor, $caseSupport)) {
        $errors[] = "❌ Несовместимость: форм-фактор материнки $mbFormFactor не поддерживается корпусом.";
    }

    if ($cpuSocket && $coolerSockets && !in_array($cpuSocket, $coolerSockets)) {
        $errors[] = "❌ Несовместимость: кулер не поддерживает сокет CPU ($cpuSocket).";
    }

    // Обработка сохранения сборки
    if (isset($_POST['save_build'])) {
        $buildName = trim($_POST['build_name'] ?? '');

        if ($buildName === '') {
            $errors[] = "❌ Пожалуйста, введите имя сборки для сохранения.";
        }

        if (empty($errors)) {
            // Для сохранения нужно сохранить массив объектов с id, name и type
            $componentsToSave = [];
            foreach ($selectedComponents as $comp) {
                $componentsToSave[] = [
                    'id' => $comp['id'],
                    'name' => $comp['name'],
                    'type' => $comp['type'],
                ];
            }

            $componentsJson = json_encode($componentsToSave, JSON_UNESCAPED_UNICODE);

            $stmt = $pdo->prepare("INSERT INTO builds (user_id, name, components, price, created_at) VALUES (?, ?, ?, ?, NOW())");
            try {
                // user_id = 0, так как авторизация не реализована
                $stmt->execute([0, $buildName, $componentsJson, $totalPrice]);
                $saveMessage = "✅ Сборка \"$buildName\" успешно сохранена!";
            } catch (PDOException $e) {
                $errors[] = "❌ Ошибка при сохранении сборки: " . $e->getMessage();
            }
        }
    }
} else {
    // При GET подгружаем выбранные компоненты из сессии
    if (!empty($_SESSION['selectedComponents'])) {
        $selectedComponentIds = $_SESSION['selectedComponents'];
        foreach ($selectedComponentIds as $type => $componentId) {
            $stmt = $pdo->prepare("SELECT * FROM components WHERE id = ?");
            $stmt->execute([$componentId]);
            $comp = $stmt->fetch();
            if ($comp) {
                $selectedComponents[] = $comp;
                $totalPrice += $comp['price'];
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
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 700px; margin: auto; }
        select, button, input[type=text] { padding: 5px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        .block { margin-bottom: 20px; }
        .error { color: red; font-weight: bold; }
        .summary { margin-top: 20px; background: #f9f9f9; padding: 15px; border-radius: 5px; }
        .success { color: green; font-weight: bold; margin-bottom: 20px; }
        label { font-weight: bold; }
    </style>
</head>
<body>

<h1>Конфигуратор ПК</h1>

<form method="POST" autocomplete="off">

    <?php foreach ($componentsByType as $type => $components): ?>
        <div class="block">
            <label for="<?= $type ?>"><?= htmlspecialchars($type) ?>:</label><br />
            <select name="<?= $type ?>" id="<?= $type ?>">
                <option value="">-- Выберите <?= htmlspecialchars($type) ?> --</option>
                <?php foreach ($components as $comp): ?>
                    <option value="<?= $comp['id'] ?>"
                        <?= (isset($selectedComponentIds[$type]) && $selectedComponentIds[$type] == $comp['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($comp['name']) ?> (<?= number_format($comp['price'], 0, '', ' ') ?> ₽)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>

    <div class="block">
        <label for="build_name">Имя сборки:</label><br />
        <input
            type="text"
            name="build_name"
            id="build_name"
            maxlength="100"
            placeholder="Введите имя сборки"
            value="<?= htmlspecialchars($_POST['build_name'] ?? '') ?>"
        />
    </div>

    <button type="submit" name="assemble">Собрать</button>
    <button type="submit" name="save_build">Сохранить сборку</button>
</form>

<?php if (!empty($errors)): ?>
    <div class="error">
        <h3>Найдены ошибки совместимости или заполнения:</h3>
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($saveMessage): ?>
    <div class="success"><?= htmlspecialchars($saveMessage) ?></div>
<?php endif; ?>

<?php if (!empty($selectedComponents)): ?>
    <div class="summary">
        <h2>Выбранные компоненты:</h2>
        <ul>
            <?php foreach ($selectedComponents as $comp): ?>
                <li>
                    <?= htmlspecialchars($comp['type']) ?>: <?= htmlspecialchars($comp['name']) ?>
                    — <?= number_format($comp['price'], 0, '', ' ') ?> ₽
                </li>
            <?php endforeach; ?>
        </ul>
        <p><strong>Общая цена:</strong> <?= number_format($totalPrice, 0, '', ' ') ?> ₽</p>
    </div>
<?php endif; ?>

</body>
</html>
