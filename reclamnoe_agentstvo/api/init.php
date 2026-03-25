<?php
/**
 * Инициализация сессии и CSRF токена
 * Подключить этот файл в начале каждого PHP файла
 */

session_start();

// Генерация CSRF токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Установка CSRF токена в cookie для JavaScript
$csrfCookieName = 'csrf_token';
$csrfCookieValue = $_SESSION['csrf_token'];
$csrfCookieExpire = time() + 86400; // 24 часа
$csrfCookiePath = '/';
$csrfCookieDomain = $_SERVER['HTTP_HOST'] ?? '';
$csrfCookieSecure = isset($_SERVER['HTTPS']);
$csrfCookieHttpOnly = false; // Доступно для JavaScript

setcookie($csrfCookieName, $csrfCookieValue, $csrfCookieExpire, $csrfCookiePath, $csrfCookieDomain, $csrfCookieSecure, $csrfCookieHttpOnly);

// Функция для проверки AJAX запроса
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Функция для получения CSRF токена
function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

// Функция для проверки CSRF токена
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
