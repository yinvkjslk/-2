<?php
require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        register_user();
        break;
    case 'login':
        login_user();
        break;
    case 'logout':
        logout_user();
        break;
    case 'check':
        check_session();
        break;
    case 'profile':
        get_profile();
        break;
    case 'update_profile':
        update_profile();
        break;
    default:
        json_error('Неизвестное действие', 404);
}

/**
 * Регистрация нового пользователя
 */
function register_user() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    // Получение и очистка данных
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Валидация CSRF
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности. Обновите страницу.', 403);
    }
    
    // Валидация данных
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Логин должен содержать минимум 3 символа';
    }
    
    if (!validate_email($email)) {
        $errors[] = 'Введите корректный email';
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов';
    }
    
    if (empty($name)) {
        $errors[] = 'Введите ваше имя';
    }
    
    if (!empty($errors)) {
        json_error(implode('. ', $errors));
    }
    
    try {
        $db = db();
        
        // Проверка уникальности username
        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE username = ?",
            's', $username
        );
        
        if ($existing) {
            json_error('Пользователь с таким логином уже существует');
        }
        
        // Проверка уникальности email
        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            's', $email
        );
        
        if ($existing) {
            json_error('Пользователь с таким email уже существует');
        }
        
        // Хеширование пароля
        $password_hash = hash_password($password);
        
        // Вставка пользователя
        $user_id = $db->insert(
            "INSERT INTO users (username, email, password_hash, name, phone, role) VALUES (?, ?, ?, ?, ?, 'user')",
            'sssss',
            $username, $email, $password_hash, $name, $phone
        );
        
        // Логирование
        log_activity('register', 'user', $user_id);
        
        // Создание сессии
        create_user_session($user_id, $username, $email, $name, 'user');
        
        json_success('Регистрация успешна!', [
            'user' => [
                'id' => $user_id,
                'username' => $username,
                'email' => $email,
                'name' => $name,
                'role' => 'user'
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Ошибка регистрации: " . $e->getMessage());
        json_error('Ошибка при регистрации. Попробуйте позже.');
    }
}

/**
 * Вход пользователя
 */
function login_user() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Валидация CSRF
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности. Обновите страницу.', 403);
    }
    
    // Валидация данных
    if (empty($username) || empty($password)) {
        json_error('Введите логин и пароль');
    }
    
    try {
        $db = db();
        
        // Получение пользователя
        $user = $db->fetchOne(
            "SELECT id, username, email, password_hash, name, phone, role, is_active FROM users WHERE username = ? OR email = ?",
            'ss', $username, $username
        );
        
        if (!$user) {
            json_error('Неверный логин или пароль');
        }
        
        // Проверка активности
        if (!$user['is_active']) {
            json_error('Аккаунт заблокирован. Свяжитесь с поддержкой.');
        }
        
        // Проверка пароля
        if (!verify_password($password, $user['password_hash'])) {
            // Логирование неудачной попытки
            log_activity('login_failed', 'user', $user['id'], ['reason' => 'wrong_password']);
            json_error('Неверный логин или пароль');
        }
        
        // Обновление времени последнего входа
        $db->query(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            'i', $user['id']
        );
        
        // Создание сессии
        create_user_session(
            $user['id'],
            $user['username'],
            $user['email'],
            $user['name'],
            $user['role']
        );
        
        // Логирование
        log_activity('login', 'user', $user['id']);
        
        json_success('Вход выполнен успешно!', [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role']
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Ошибка входа: " . $e->getMessage());
        json_error('Ошибка при входе. Попробуйте позже.');
    }
}

/**
 * Выход пользователя
 */
function logout_user() {
    log_activity('logout', 'user', $_SESSION['user_id'] ?? null);
    
    // Очистка сессии
    session_unset();
    session_destroy();
    
    json_success('Выход выполнен');
}

/**
 * Проверка сессии
 */
function check_session() {
    $user = get_current_user();
    
    if ($user) {
        json_success('Сессия активна', ['user' => $user]);
    } else {
        json_error('Сессия не активна', 401);
    }
}

/**
 * Получение профиля пользователя
 */
function get_profile() {
    if (!check_auth()) {
        json_error('Требуется авторизация', 401);
    }
    
    $user = get_current_user();
    
    try {
        $db = db();
        $profile = $db->fetchOne(
            "SELECT id, username, email, name, phone, role, created_at, last_login FROM users WHERE id = ?",
            'i', $user['id']
        );
        
        if ($profile) {
            json_success('Профиль получен', ['user' => $profile]);
        } else {
            json_error('Пользователь не найден');
        }
        
    } catch (Exception $e) {
        json_error('Ошибка получения профиля');
    }
}

/**
 * Обновление профиля
 */
function update_profile() {
    if (!check_auth()) {
        json_error('Требуется авторизация', 401);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    $user = get_current_user();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    if (empty($name)) {
        json_error('Введите ваше имя');
    }
    
    try {
        $db = db();
        
        $db->query(
            "UPDATE users SET name = ?, phone = ? WHERE id = ?",
            'ssi', $name, $phone, $user['id']
        );
        
        // Обновление данных сессии
        $_SESSION['name'] = $name;
        $_SESSION['phone'] = $phone;
        
        log_activity('profile_update', 'user', $user['id']);
        
        json_success('Профиль обновлён');
        
    } catch (Exception $e) {
        json_error('Ошибка обновления профиля');
    }
}

/**
 * Создание сессии пользователя
 */
function create_user_session($id, $username, $email, $name, $role) {
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['name'] = $name;
    $_SESSION['role'] = $role;
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = get_client_ip();
}
