<?php
/**
 * Функции безопасности и вспомогательные функции
 * Защита от XSS, CSRF, работа с сессиями
 */

session_start();

// Защита от XSS - очистка выводимых данных
function escape($string) {
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Очистка массива
function escapeArray($array) {
    return array_map('escape', $array);
}

// Генерация CSRF-токена
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Проверка CSRF-токена
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Генерация случайного токена для восстановления пароля
function generate_reset_token($length = 32) {
    return bin2hex(random_bytes($length));
}


function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}


function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Проверка сессии пользователя
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Проверка времени бездействия (30 минут)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// Получение текущего пользователя
function get_current_user() {
    if (!check_auth()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'name' => $_SESSION['name'],
        'role' => $_SESSION['role']
    ];
}

// Проверка роли администратора
function is_admin() {
    $user = get_current_user();
    return $user && $user['role'] === 'admin';
}

// Проверка роли менеджера или администратора
function is_manager() {
    $user = get_current_user();
    return $user && in_array($user['role'], ['admin', 'manager']);
}

// Редирект
function redirect($url) {
    header("Location: $url");
    exit;
}

// Вывод JSON-ответа
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Вывод ошибки
function json_error($message, $status = 400) {
    json_response(['success' => false, 'message' => $message], $status);
}

// Вывод успеха
function json_success($message, $data = []) {
    json_response(['success' => true, 'message' => $message, ...$data]);
}

// Получение IP-адреса
function get_client_ip() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    return $ip;
}

// Получение User Agent
function get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

// Валидация email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Валидация телефона
function validate_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return strlen($phone) >= 11 && strlen($phone) <= 15;
}

// Форматирование телефона
function format_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 11 && $phone[0] === '7') {
        return '+' . $phone;
    }
    return '+' . $phone;
}

// Пагинация
function pagination($current_page, $total_pages, $url_template) {
    $html = '<nav class="pagination"><ul class="pagination-list">';
    
    // Предыдущая страница
    if ($current_page > 1) {
        $html .= '<li><a href="' . str_replace('{page}', $current_page - 1, $url_template) . '" class="pagination-link">&laquo;</a></li>';
    }
    
    // Номера страниц
    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $active = $i === $current_page ? ' active' : '';
        $html .= '<li><a href="' . str_replace('{page}', $i, $url_template) . '" class="pagination-link' . $active . '">' . $i . '</a></li>';
    }
    
    // Следующая страница
    if ($current_page < $total_pages) {
        $html .= '<li><a href="' . str_replace('{page}', $current_page + 1, $url_template) . '" class="pagination-link">&raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// Логирование действий
function log_activity($action, $entity_type = null, $entity_id = null, $details = null) {
    if (!check_auth()) return;
    
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = get_client_ip();
    $user_agent = get_user_agent();
    
    try {
        $db = db();
        $db->insert(
            "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)",
            'ississs',
            $user_id, $action, $entity_type, $entity_id, 
            $details ? json_encode($details) : null,
            $ip, $user_agent
        );
    } catch (Exception $e) {
        error_log("Ошибка логирования: " . $e->getMessage());
    }
}
