<?php
/**
 * API: Админ-панель
 * Управление пользователями, заявками, контентом
 */

require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'users':
        get_users();
        break;
    case 'user':
        get_user();
        break;
    case 'create_user':
        create_user();
        break;
    case 'update_user':
        update_user();
        break;
    case 'delete_user':
        delete_user();
        break;
    case 'messages':
        get_messages();
        break;
    case 'message':
        get_message();
        break;
    case 'respond_message':
        respond_message();
        break;
    case 'settings':
        get_settings();
        break;
    case 'update_settings':
        update_settings();
        break;
    case 'logs':
        get_logs();
        break;
    default:
        json_error('Неизвестное действие', 404);
}

/**
 * Получение списка пользователей
 */
function get_users() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $role = $_GET['role'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    try {
        $db = db();
        
        $where = [];
        $params = [];
        $types = '';
        
        if (!empty($role)) {
            $where[] = 'role = ?';
            $params[] = $role;
            $types .= 's';
        }
        
        if (!empty($search)) {
            $where[] = '(username LIKE ? OR email LIKE ? OR name LIKE ?)';
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'sss';
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Общее количество
        $count = $db->fetchOne("SELECT COUNT(*) as total FROM users $where_sql", $types, ...$params);
        
        // Список пользователей
        $users = $db->fetchAll(
            "SELECT id, username, email, name, phone, role, is_active, created_at, last_login 
             FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $types . 'ii', ...$params, $limit, $offset
        );
        
        json_success('Пользователи получены', [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $count['total'] ?? 0,
                'pages' => ceil(($count['total'] ?? 0) / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения пользователей');
    }
}

/**
 * Получение одного пользователя
 */
function get_user() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    $id = (int)($_GET['id'] ?? 0);
    
    try {
        $db = db();
        
        $user = $db->fetchOne(
            "SELECT id, username, email, name, phone, role, is_active, created_at, last_login 
             FROM users WHERE id = ?",
            'i', $id
        );
        
        if (!$user) {
            json_error('Пользователь не найден', 404);
        }
        
        // Количество заявок пользователя
        $requests_count = $db->fetchOne(
            "SELECT COUNT(*) as count FROM requests WHERE user_id = ?",
            'i', $id
        );
        
        $user['requests_count'] = $requests_count['count'] ?? 0;
        
        json_success('Пользователь получен', ['user' => $user]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения пользователя');
    }
}

/**
 * Создание пользователя (админ)
 */
function create_user() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    // Валидация
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
    
    if (!in_array($role, ['user', 'manager', 'admin'])) {
        $errors[] = 'Неверная роль';
    }
    
    if (!empty($errors)) {
        json_error(implode('. ', $errors));
    }
    
    try {
        $db = db();
        
        // Проверка уникальности
        if ($db->fetchOne("SELECT id FROM users WHERE username = ?", 's', $username)) {
            json_error('Пользователь с таким логином уже существует');
        }
        
        if ($db->fetchOne("SELECT id FROM users WHERE email = ?", 's', $email)) {
            json_error('Пользователь с таким email уже существует');
        }
        
        $password_hash = hash_password($password);
        
        $user_id = $db->insert(
            "INSERT INTO users (username, email, password_hash, name, phone, role) VALUES (?, ?, ?, ?, ?, ?)",
            'ssssss', $username, $email, $password_hash, $name, $phone, $role
        );
        
        log_activity('user_create', 'user', $user_id);
        
        json_success('Пользователь создан', ['user_id' => $user_id]);
        
    } catch (Exception $e) {
        json_error('Ошибка создания пользователя');
    }
}

/**
 * Обновление пользователя
 */
function update_user() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $is_active = $_POST['is_active'] ?? null;
    $new_password = $_POST['new_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    if ($id <= 0) {
        json_error('Укажите пользователя');
    }
    
    try {
        $db = db();
        
        $updates = [];
        $params = [];
        $types = '';
        
        if (!empty($name)) {
            $updates[] = 'name = ?';
            $params[] = $name;
            $types .= 's';
        }
        
        if (!empty($email)) {
            // Проверка уникальности
            $exists = $db->fetchOne(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                'si', $email, $id
            );
            if ($exists) {
                json_error('Email уже используется');
            }
            $updates[] = 'email = ?';
            $params[] = $email;
            $types .= 's';
        }
        
        if (!empty($phone)) {
            $updates[] = 'phone = ?';
            $params[] = $phone;
            $types .= 's';
        }
        
        if (!empty($role) && in_array($role, ['user', 'manager', 'admin'])) {
            $updates[] = 'role = ?';
            $params[] = $role;
            $types .= 's';
        }
        
        if ($is_active !== null) {
            $updates[] = 'is_active = ?';
            $params[] = (int)$is_active;
            $types .= 'i';
        }
        
        if (!empty($new_password)) {
            $updates[] = 'password_hash = ?';
            $params[] = hash_password($new_password);
            $types .= 's';
        }
        
        if (empty($updates)) {
            json_error('Нечего обновлять');
        }
        
        $params[] = $id;
        $types .= 'i';
        
        $db->query("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", $types, ...$params);
        
        log_activity('user_update', 'user', $id);
        
        json_success('Пользователь обновлён');
        
    } catch (Exception $e) {
        json_error('Ошибка обновления пользователя');
    }
}

/**
 * Удаление пользователя
 */
function delete_user() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    $id = (int)($_GET['id'] ?? 0);
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    if ($id <= 0) {
        json_error('Укажите пользователя');
    }
    
    // Нельзя удалить самого себя
    if ($id == $_SESSION['user_id']) {
        json_error('Нельзя удалить свой аккаунт');
    }
    
    try {
        $db = db();
        
        $db->query("DELETE FROM users WHERE id = ?", 'i', $id);
        
        log_activity('user_delete', 'user', $id);
        
        json_success('Пользователь удалён');
        
    } catch (Exception $e) {
        json_error('Ошибка удаления пользователя');
    }
}

/**
 * Получение сообщений
 */
function get_messages() {
    if (!is_manager()) {
        json_error('Доступ запрещён', 403);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $is_read = $_GET['is_read'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    try {
        $db = db();
        
        $where = '';
        $params = [];
        $types = '';
        
        if ($is_read !== '') {
            $where = 'WHERE is_read = ?';
            $params[] = (int)$is_read;
            $types .= 'i';
        }
        
        $count = $db->fetchOne("SELECT COUNT(*) as total FROM messages $where", $types, ...$params);
        
        $messages = $db->fetchAll(
            "SELECT * FROM messages $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $types . 'ii', ...$params, $limit, $offset
        );
        
        json_success('Сообщения получены', [
            'messages' => $messages,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $count['total'] ?? 0,
                'pages' => ceil(($count['total'] ?? 0) / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения сообщений');
    }
}

/**
 * Получение одного сообщения
 */
function get_message() {
    if (!is_manager()) {
        json_error('Доступ запрещён', 403);
    }
    
    $id = (int)($_GET['id'] ?? 0);
    
    try {
        $db = db();
        
        $message = $db->fetchOne("SELECT * FROM messages WHERE id = ?", 'i', $id);
        
        if (!$message) {
            json_error('Сообщение не найдено', 404);
        }
        
        // Отметить как прочитанное
        $db->query("UPDATE messages SET is_read = 1 WHERE id = ?", 'i', $id);
        
        json_success('Сообщение получено', ['message' => $message]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения сообщения');
    }
}

/**
 * Отметить сообщение как отвеченное
 */
function respond_message() {
    if (!is_manager()) {
        json_error('Доступ запрещён', 403);
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    try {
        $db = db();
        
        $db->query("UPDATE messages SET responded = 1 WHERE id = ?", 'i', $id);
        
        log_activity('message_respond', 'message', $id);
        
        json_success('Сообщение отмечено как отвеченное');
        
    } catch (Exception $e) {
        json_error('Ошибка');
    }
}

/**
 * Получение настроек
 */
function get_settings() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    try {
        $db = db();
        
        $settings = $db->fetchAll("SELECT * FROM settings");
        
        $result = [];
        foreach ($settings as $s) {
            $result[$s['setting_key']] = $s['setting_value'];
        }
        
        json_success('Настройки получены', ['settings' => $result]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения настроек');
    }
}

/**
 * Обновление настроек
 */
function update_settings() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    $settings = $_POST['settings'] ?? [];
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    if (!is_array($settings) || empty($settings)) {
        json_error('Укажите настройки');
    }
    
    try {
        $db = db();
        
        foreach ($settings as $key => $value) {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                'sss', $key, $value, $value
            );
        }
        
        log_activity('settings_update', 'settings');
        
        json_success('Настройки сохранены');
        
    } catch (Exception $e) {
        json_error('Ошибка сохранения настроек');
    }
}

/**
 * Получение логов активности
 */
function get_logs() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $user_id = (int)($_GET['user_id'] ?? 0);
    $action = $_GET['action'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    try {
        $db = db();
        
        $where = [];
        $params = [];
        $types = '';
        
        if ($user_id > 0) {
            $where[] = 'user_id = ?';
            $params[] = $user_id;
            $types .= 'i';
        }
        
        if (!empty($action)) {
            $where[] = 'action = ?';
            $params[] = $action;
            $types .= 's';
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $count = $db->fetchOne("SELECT COUNT(*) as total FROM activity_logs $where_sql", $types, ...$params);
        
        $logs = $db->fetchAll(
            "SELECT l.*, u.username, u.name as user_name 
             FROM activity_logs l
             LEFT JOIN users u ON l.user_id = u.id
             $where_sql
             ORDER BY l.created_at DESC
             LIMIT ? OFFSET ?",
            $types . 'ii', ...$params, $limit, $offset
        );
        
        json_success('Логи получены', [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $count['total'] ?? 0,
                'pages' => ceil(($count['total'] ?? 0) / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения логов');
    }
}
