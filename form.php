<?php
$config = include('db_config.php');
$db = null;

if (!file_exists('db_config.php')) {
    die("Файл db_config.php не найден.");
}
$config = include('db_config.php');
if (!is_array($config) || !isset($config['host'], $config['dbname'], $config['user'], $config['pass'])) {
    die("db_config.php должен возвращать массив с ключами host, dbname, user, pass.");
}

// 2. Получаем данные из $_POST
$name = $_POST['fio'] ?? '';
$tel = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$birth_date = $_POST['birth_date'] ?? '';
$gender = $_POST['gender'] ?? '';
$bio = $_POST['bio'] ?? '';
$languages = $_POST['languages'] ?? [];
$agreement = isset($_POST['agreement']);

$errors = [];

if (empty($name)) {
    $errors[] = "Поле ФИО пустое";
} elseif (strlen($name) > 150) {
    $errors[] = "ФИО слишком длинное";
} elseif (!preg_match('/^[a-zA-Zа-яёА-ЯЁ ]+$/u', $name)) {
    $errors[] = "В поле ФИО могут быть только буквы и пробелы";
}
if (empty($tel)) {
    $errors[] = "Поле 'Телефон' не может быть пустым.";
} elseif (!preg_match('/^\+?[0-9\-]+$/', $tel)) {
    $errors[] = "Телефон введен некорректно.";
} elseif (strlen($tel) < 6 || strlen($tel) > 12) {
    $errors[] = "Телефон должен содержать от 6 до 12 символов.";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Почта введена неправильно";
} elseif (empty($email)) {
    $errors[] = "Поле email пустое";
}
if (empty($birth_date)) {
    $errors['birth_date'] = 'Дата рождения обязательна.';
} else {
    $date = DateTime::createFromFormat('Y-m-d', $birth_date);
    if (!$date || $date->format('Y-m-d') !== $birth_date) {
        $errors['birth_date'] = 'Некорректная дата.';
    } elseif ($date > new DateTime('today')) {
        $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
    }
}

if (empty($gender)) {
    $errors[] = "Поле 'Пол' не может быть пустым.";
} elseif (!in_array($gender, ['M', 'F'])) {
    $errors[] = "Выбран недопустимый пол.";
}

if (empty($languages)) {
    $errors[] = "Необходимо выбрать хотя бы один язык программирования.";
}

if (!$agreement) {
    $errors[] = "Необходимо согласиться с правилами.";
}

if (!is_array($errors)) {
    $errors = [];
}
if (!empty($errors)) {
    echo "<h2>Ошибки:</h2>";
    foreach ($errors as $error) {
        echo "- $error<br>";
    }
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['user'],
        $config['pass']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO REQUEST (FIO, PHONE, E_MAIL, B_DATE, GENDER, BIO) 
                          VALUES (:name, :tel, :email, :birth_date, :gender, :bio)");
    $stmt->execute([
        ':name' => $name,
        ':tel' => $tel,
        ':email' => $email,
        ':birth_date' => $birth_date,
        ':gender' => $gender,
        ':bio' => $bio,
    ]);

    $requestId = $db->lastInsertId();

    $getLangId = $db->prepare("SELECT L_ID FROM LANGUAGE WHERE LANG = ?");
    $insertConn = $db->prepare("INSERT INTO CONNECT (R_ID, L_ID) VALUES (?, ?)");
    echo $requestId;
    echo $getLangId;
    foreach ($languages as $LANG) {
        $getLangId->execute([$LANG]);
        $row = $getLangId->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $insertConn->execute([$requestId, $row['L_ID']]);
        }
    }

    $db->commit();
    echo "<h2>Данные успешно сохранены!</h2>";
} catch (PDOException $e) {
    if ($db !== null && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Ошибка базы данных: " . $e->getMessage();
}
