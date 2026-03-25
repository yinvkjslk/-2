<?php
/**
 * Установщик базы данных INSIDE360
 * Создаёт таблицы и заполняет начальными данными
 */

session_start();
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/config/functions.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $db = db();
        $conn = $db->getConnection();
        
        if ($_POST['action'] === 'install') {
            // Создание таблиц
            $sql = file_get_contents(__DIR__ . '/db/database.sql');
            
            // Разбиваем на отдельные запросы
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($queries as $query) {
                if (!empty($query) && strpos($query, '--') !== 0) {
                    $conn->query($query);
                }
            }
            
            $message = 'База данных успешно установлена!';
            $success = true;
        }
        
        if ($_POST['action'] === 'test') {
            // Тест подключения
            $conn->ping();
            $message = 'Подключение к базе данных успешно!';
            $success = true;
        }
        
    } catch (Exception $e) {
        $message = 'Ошибка: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка INSIDE360</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: #1e1e2f;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #fff;
            font-size: 28px;
        }
        
        .logo span {
            color: #e63946;
        }
        
        h2 {
            color: #fff;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .info-box {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-box p {
            color: #aaa;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .info-box code {
            background: rgba(0,0,0,0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .btn {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #e63946 0%, #c1121f 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(230,57,70,0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid #444;
            color: #aaa;
        }
        
        .btn-secondary:hover {
            border-color: #666;
            color: #fff;
            box-shadow: none;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        .steps {
            margin-top: 30px;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: #e63946;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-content h4 {
            color: #fff;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .step-content p {
            color: #888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="logo">
            <h1>INSIDE<span>360</span></h1>
            <p style="color: #888; margin-top: 5px;">Установка системы</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h2 style="margin-bottom: 15px;">Настройки подключения</h2>
            <p>Проверьте настройки в файле <code>api/config/database.php</code>:</p>
            <p><strong>Хост:</strong> localhost</p>
            <p><strong>Пользователь:</strong> root (или ваш)</p>
            <p><strong>База данных:</strong> inside360_db</p>
            <p><strong>Пароль:</strong> (ваш пароль)</p>
        </div>
        
        <form method="POST">
            <button type="submit" name="action" value="test" class="btn btn-secondary">
                Проверить подключение
            </button>
            
            <button type="submit" name="action" value="install" class="btn">
                Установить базу данных
            </button>
        </form>
        
        <div class="steps">
            <h2 style="margin-top: 30px;">Инструкция по установке:</h2>
            
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h4>Настройте подключение</h4>
                    <p>Отредактируйте api/config/database.php</p>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h4>Создайте базу данных</h4>
                    <p>Создайте пустую базу данных inside360_db в MySQL</p>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h4>Запустите установку</h4>
                    <p>Нажмите кнопку "Установить базу данных"</p>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h4>Готово!</h4>
                    <p>Система готова к работе</p>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="index.html" style="color: #888; text-decoration: none;">Перейти на сайт</a>
        </div>
    </div>
</body>
</html>
