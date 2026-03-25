-- База данных для рекламного агентства INSIDE360
-- Создание базы данных

CREATE DATABASE IF NOT EXISTS inside360_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE inside360_db;

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('user', 'admin', 'manager') DEFAULT 'user',
    avatar VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица заявок
CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    service VARCHAR(50) NOT NULL,
    message TEXT,
    status ENUM('new', 'processing', 'completed', 'cancelled') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_to INT,
    price DECIMAL(10,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_service (service),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица услуг
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    short_description VARCHAR(255),
    price_from DECIMAL(10,2),
    icon VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица кейсов (портфолио)
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    client_name VARCHAR(100),
    category ENUM('smm', 'sites', 'seo', 'context', 'listing') NOT NULL,
    description TEXT,
    results TEXT,
    image VARCHAR(255),
    gallery JSON,
    is_published TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_category (category),
    INDEX idx_is_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица сообщений/контактов
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    responded TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица настроек сайта
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица логов действий
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Вставка начальных данных

-- Администратор (пароль: Admin@2024!)
INSERT INTO users (username, email, password_hash, name, phone, role) VALUES
('admin', 'admin@inside360.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор', '+7 (924) 000-97-96', 'admin'),
('manager', 'manager@inside360.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Менеджер', '+7 (924) 000-97-97', 'manager');

-- Услуги
INSERT INTO services (name, slug, description, short_description, price_from, icon, sort_order) VALUES
('SMM-продвижение', 'smm', 'Полное продвижение в социальных сетях: ВКонтакте, Telegram, Instagram. Ведение сообществ, таргетированная реклама, контент-маркетинг.', 'Привлечение клиентов через социальные сети', 35000, 'fa-hashtag', 1),
('Создание сайтов', 'sites', 'Разработка сайтов любой сложности: лендинги, корпоративные сайты, интернет-магазины с адаптивным дизайном.', 'Современные продающие сайты', 25000, 'fa-code', 2),
('Контекстная реклама', 'context', 'Настройка и ведение рекламных кампаний в Яндекс.Директ и Google Ads. Мгновенный приток целевых клиентов.', 'Реклама в поиске', 25000, 'fa-bullhorn', 3),
('SEO-продвижение', 'seo', 'Поисковая оптимизация сайтов. Вывод в ТОП-10 Яндекса и Google по целевым запросам.', 'Продвижение в поиске', 25000, 'fa-search', 4),
('Листинг на маркетплейсах', 'marketplace', 'Размещение и продвижение товаров на Wildberries, Ozon, Яндекс.Маркет.', 'Продажи на маркетплейсах', 15000, 'fa-store', 5);

-- Настройки сайта
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'INSIDE360', 'Название сайта'),
('site_title', 'Рекламное агентство INSIDE360 | SMM, сайты, SEO', 'Заголовок сайта'),
('site_description', 'Рекламное агентство во Владивостоке. SMM-продвижение, создание сайтов, контекстная реклама и SEO.', 'Meta description'),
('contact_phone', '+7 (924) 000-97-96', 'Контактный телефон'),
('contact_email', 'info@inside360.ru', 'Контактный email'),
('contact_address', 'г. Владивосток, Океанский проспект, 101а', 'Адрес'),
('vk_link', 'https://vk.com/inside360ru', 'Ссылка ВКонтакте'),
('telegram_link', 'https://t.me/inside360', 'Ссылка Telegram'),
('whatsapp_link', 'https://wa.me/79240009796', 'Ссылка WhatsApp');
