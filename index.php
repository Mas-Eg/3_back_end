<?php
session_start();

$config = include('db_config.php');
$db = null;

if (!file_exists('db_config.php')) {
    die("Файл db_config.php не найден.");
}
if (!is_array($config) || !isset($config['host'], $config['dbname'], $config['user'], $config['pass'])) {
    die("db_config.php должен возвращать массив с ключами host, dbname, user, pass.");
}

$errors = [];
$name = $tel = $email = $birth_date = $gender = $bio = '';
$languages = [];
$agreement = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['fio'] ?? '');
    $tel = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $bio = trim($_POST['bio'] ?? '');
    $languages = $_POST['languages'] ?? [];
    $agreement = isset($_POST['agreement']);

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

    if (empty($email)) {
        $errors[] = "Поле email пустое";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Почта введена неправильно";
    }

    if (empty($birth_date)) {
        $errors[] = 'Дата рождения обязательна.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$date || $date->format('Y-m-d') !== $birth_date) {
            $errors[] = 'Некорректная дата.';
        } elseif ($date > new DateTime('today')) {
            $errors[] = 'Дата рождения не может быть в будущем.';
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

    if (empty($errors)) {
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


            $languages = array_unique($languages);
            $getLangId = $db->prepare("SELECT L_ID FROM LANGUAGE WHERE LANG = ?");
            $insertConn = $db->prepare("INSERT INTO CONNECT (R_ID, L_ID) VALUES (?, ?)");
            $checkConn = $db->prepare("SELECT COUNT(*) FROM CONNECT WHERE R_ID = ? AND L_ID = ?");

            foreach ($languages as $lang) {
                $getLangId->execute([$lang]);
                $row = $getLangId->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $lId = $row['L_ID'];
                    $checkConn->execute([$requestId, $lId]);
                    if ($checkConn->fetchColumn() == 0) {
                        $insertConn->execute([$requestId, $lId]);
                    }
                } else {
                    error_log("Язык '$lang' не найден в таблице LANGUAGE");
                }
            }

            $db->commit();
            $success = true;

            $name = $tel = $email = $birth_date = $gender = $bio = '';
            $languages = [];
            $agreement = false;
        } catch (PDOException $e) {
            if ($db !== null && $db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форма регистрации</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="content-header">
    <div class="myform">
        <h2 id="form">Форма</h2>

        <?php if (!empty($errors)): ?>
            <div style="color: red; background: #ffe6e6; padding: 10px; margin-bottom: 15px; border: 1px solid red;">
                <strong>Ошибки:</strong><br>
                <?php foreach ($errors as $error): ?>
                    - <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success) && $success): ?>
            <div style="color: green; background: #e6ffe6; padding: 10px; margin-bottom: 15px; border: 1px solid green;">
                <strong>Данные успешно сохранены!</strong>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name-input">ФИО:</label>
                <input id="name-input" name="fio" type="text" value="<?= htmlspecialchars($name) ?>" placeholder="Иванов Иван Иванович" />
            </div>
            <div class="form-group">
                <label for="tel-input">Телефон:</label>
                <input id="tel-input" name="phone" type="tel" value="<?= htmlspecialchars($tel) ?>" placeholder="+7**********" />
            </div>
            <div class="form-group">
                <label for="email-input">email:</label>
                <input id="email-input" name="email" type="email" value="<?= htmlspecialchars($email) ?>" placeholder="Введите вашу почту" />
            </div>
            <div class="form-group">
                <label for="birth_date">Дата рождения:</label>
                <input id="birth_date" name="birth_date" value="<?= htmlspecialchars($birth_date ?: '2026-01-01') ?>" type="date" />
            </div>
            <div class="form-group">
                <span>Пол:</span>
                <label><input type="radio" name="gender" value="M" <?= $gender === 'M' ? 'checked' : '' ?> /> Мужской</label>
                <label><input type="radio" name="gender" value="F" <?= $gender === 'F' ? 'checked' : '' ?> /> Женский</label>
            </div>
            <div class="form-group">
                <label for="lang-select">Ваш любимый язык программирования:</label>
                <select id="lang-select" name="languages[]" multiple>
                    <?php
                    $availableLangs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
                    foreach ($availableLangs as $lang) {
                        $selected = in_array($lang, $languages) ? 'selected' : '';
                        echo "<option value=\"$lang\" $selected>$lang</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="bio-textarea">Биография:</label>
                <textarea id="bio-textarea" name="bio"><?= htmlspecialchars($bio) ?></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="agreement" <?= $agreement ? 'checked' : '' ?> /> с контрактом ознакомлен (-а)</label>
            </div>
            <input type="submit" value="Сохранить" class="knopka" />
        </form>
    </div>
</div>
</body>
</html>
