<?php
/**
 * API: Управление заявками
 * Защищённые маршруты с проверкой сессий
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
    case 'list':
        get_requests_list();
        break;
    case 'get':
        get_request();
        break;
    case 'create':
        create_request();
        break;
    case 'update':
        update_request();
        break;
    case 'delete':
        delete_request();
        break;
    case 'stats':
        get_stats();
        break;
    case 'my':
        get_my_requests();
        break;
    default:
        json_error('Неизвестное действие', 404);
}

/**
 * Получение списка заявок (только для менеджеров и админов)
 */
function get_requests_list() {
    if (!is_manager()) {
        json_error('Доступ запрещён', 403);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $status = $_GET['status'] ?? '';
    $service = $_GET['service'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    try {
        $db = db();
        
        // Построение запроса с фильтрами
        $where = [];
        $params = [];
        $types = '';
        
        if (!empty($status)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($service)) {
            $where[] = 'r.service = ?';
            $params[] = $service;
            $types .= 's';
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Получение общего количества
        $count_sql = "SELECT COUNT(*) as total FROM requests r $where_sql";
        $total = $db->fetchOne($count_sql, $types, ...$params);
        $total = $total['total'] ?? 0;
        
        // Получение заявок
        $sql = "SELECT r.*, u.name as user_name, u.email as user_email, 
                       a.name as assigned_name
                FROM requests r
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN users a ON r.assigned_to = a.id
                $where_sql
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $requests = $db->fetchAll($sql, $types, ...$params);
        
        json_success('Заявки получены', [
            'requests' => $requests,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Ошибка получения заявок: " . $e->getMessage());
        json_error('Ошибка получения заявок');
    }
}

/**
 * Получение одной заявки
 */
function get_request() {
    if (!check_auth()) {
        json_error('Требуется авторизация', 401);
    }
    
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        json_error('Укажите ID заявки');
    }
    
    try {
        $db = db();
        $user = get_current_user();
        
        // Если не админ - показываем только свои заявки
        $where = $user['role'] === 'admin' || $user['role'] === 'manager' 
            ? 'r.id = ?' 
            : 'r.id = ? AND r.user_id = ?';
        
        $types = $user['role'] === 'admin' || $user['role'] === 'manager' ? 'i' : 'ii';
        $params = $user['role'] === 'admin' || $user['role'] === 'manager' ? [$id] : [$id, $user['id']];
        
        $request = $db->fetchOne(
            "SELECT r.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
                    a.name as assigned_name
             FROM requests r
             LEFT JOIN users u ON r.user_id = u.id
             LEFT JOIN users a ON r.assigned_to = a.id
             WHERE $where",
            $types, ...$params
        );
        
        if (!$request) {
            json_error('Заявка не найдена', 404);
        }
        
        json_success('Заявка получена', ['request' => $request]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения заявки');
    }
}

/**
 * Создание заявки (публичный метод)
 */
function create_request() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service = $_POST['service'] ?? '';
    $message = $_POST['message'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    // Валидация
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Введите имя';
    }
    
    if (!validate_phone($phone)) {
        $errors[] = 'Введите корректный телефон';
    }
    
    if (!validate_email($email)) {
        $errors[] = 'Введите корректный email';
    }
    
    if (empty($service)) {
        $errors[] = 'Выберите услугу';
    }
    
    if (!empty($errors)) {
        json_error(implode('. ', $errors));
    }
    
    try {
        $db = db();
        
        // Получаем user_id если пользователь авторизован
        $user_id = check_auth() ? $_SESSION['user_id'] : null;
        
        $request_id = $db->insert(
            "INSERT INTO requests (user_id, name, phone, email, service, message, status, priority) 
             VALUES (?, ?, ?, ?, ?, ?, 'new', 'medium')",
            'isssss',
            $user_id, $name, format_phone($phone), $email, $service, $message
        );
        
        // Логирование
        if ($user_id) {
            log_activity('request_create', 'request', $request_id);
        }
        
        json_success('Заявка создана! Наш менеджер свяжется с вами в ближайшее время.', [
            'request_id' => $request_id
        ]);
        
    } catch (Exception $e) {
        error_log("Ошибка создания заявки: " . $e->getMessage());
        json_error('Ошибка отправки заявки. Попробуйте позже.');
    }
}

/**
 * Обновление заявки (только для менеджеров)
 */
function update_request() {
    if (!is_manager()) {
        json_error('Доступ запрещён', 403);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Метод не разрешён', 405);
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? null;
    $price = $_POST['price'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    if ($id <= 0) {
        json_error('Укажите ID заявки');
    }
    
    // Валидация статуса
    $valid_statuses = ['new', 'processing', 'completed', 'cancelled'];
    if (!empty($status) && !in_array($status, $valid_statuses)) {
        json_error('Неверный статус');
    }
    
    // Валидация приоритета
    $valid_priorities = ['low', 'medium', 'high'];
    if (!empty($priority) && !in_array($priority, $valid_priorities)) {
        json_error('Неверный приоритет');
    }
    
    try {
        $db = db();
        
        $updates = [];
        $params = [];
        $types = '';
        
        if (!empty($status)) {
            $updates[] = 'status = ?';
            $params[] = $status;
            $types .= 's';
            
            if ($status === 'completed') {
                $updates[] = 'completed_at = NOW()';
            }
        }
        
        if (!empty($priority)) {
            $updates[] = 'priority = ?';
            $params[] = $priority;
            $types .= 's';
        }
        
        if ($assigned_to !== null) {
            $updates[] = 'assigned_to = ?';
            $params[] = (int)$assigned_to;
            $types .= 'i';
        }
        
        if ($price !== null) {
            $updates[] = 'price = ?';
            $params[] = (float)$price;
            $types .= 'd';
        }
        
        if (!empty($notes)) {
            $updates[] = 'notes = ?';
            $params[] = $notes;
            $types .= 's';
        }
        
        if (empty($updates)) {
            json_error('Нечего обновлять');
        }
        
        $params[] = $id;
        $types .= 'i';
        
        $db->query(
            "UPDATE requests SET " . implode(', ', $updates) . " WHERE id = ?",
            $types, ...$params
        );
        
        log_activity('request_update', 'request', $id, ['status' => $status, 'priority' => $priority]);
        
        json_success('Заявка обновлена');
        
    } catch (Exception $e) {
        error_log("Ошибка обновления заявки: " . $e->getMessage());
        json_error('Ошибка обновления заявки');
    }
}

/**
 * Удаление заявки (только для админа)
 */
function delete_request() {
    if (!is_admin()) {
        json_error('Доступ запрещён', 403);
    }
    
    $id = (int)($_GET['id'] ?? 0);
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!verify_csrf($csrf_token)) {
        json_error('Ошибка безопасности', 403);
    }
    
    if ($id <= 0) {
        json_error('Укажите ID заявки');
    }
    
    try {
        $db = db();
        
        $db->query("DELETE FROM requests WHERE id = ?", 'i', $id);
        
        log_activity('request_delete', 'request', $id);
        
        json_success('Заявка удалена');
        
    } catch (Exception $e) {
        json_error('Ошибка удаления заявки');
    }
}

/**
 * Получение статистики (для админа)
 */
function get_stats() {
    if (!is_manager()) {
        json_error('Доступ запрещён', 403);
    }
    
    try {
        $db = db();
        
        // Всего заявок
        $total = $db->fetchOne("SELECT COUNT(*) as count FROM requests");
        
        // По статусам
        $by_status = $db->fetchAll(
            "SELECT status, COUNT(*) as count FROM requests GROUP BY status"
        );
        
        // По услугам
        $by_service = $db->fetchAll(
            "SELECT service, COUNT(*) as count FROM requests GROUP BY service"
        );
        
        // За сегодня
        $today = $db->fetchOne(
            "SELECT COUNT(*) as count FROM requests WHERE DATE(created_at) = CURDATE()"
        );
        
        // За неделю
        $week = $db->fetchOne(
            "SELECT COUNT(*) as count FROM requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Новые заявки
        $new = $db->fetchOne(
            "SELECT COUNT(*) as count FROM requests WHERE status = 'new'"
        );
        
        json_success('Статистика получена', [
            'stats' => [
                'total' => $total['count'] ?? 0,
                'today' => $today['count'] ?? 0,
                'week' => $week['count'] ?? 0,
                'new' => $new['count'] ?? 0,
                'by_status' => $by_status,
                'by_service' => $by_service
            ]
        ]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения статистики');
    }
}

/**
 * Получение своих заявок (для обычного пользователя)
 */
function get_my_requests() {
    if (!check_auth()) {
        json_error('Требуется авторизация', 401);
    }
    
    $user = get_current_user();
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    try {
        $db = db();
        
        // Общее количество
        $total = $db->fetchOne(
            "SELECT COUNT(*) as total FROM requests WHERE user_id = ?",
            'i', $user['id']
        );
        
        // Заявки
        $requests = $db->fetchAll(
            "SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            'iii', $user['id'], $limit, $offset
        );
        
        json_success('Заявки получены', [
            'requests' => $requests,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total['total'] ?? 0,
                'pages' => ceil(($total['total'] ?? 0) / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        json_error('Ошибка получения заявок');
    }
}
