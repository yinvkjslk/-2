<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'inside360_db';
    
    private function __construct() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $this->connection = new mysqli(
                $this->host, 
                $this->username, 
                $this->password, 
                $this->database
            );
            $this->connection->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            error_log("Ошибка подключения к БД: " . $e->getMessage());
            throw new Exception("Ошибка подключения к базе данных");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    


    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function executePrepared($sql, $types = '', ...$params) {
        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    public function fetchOne($sql, $types = '', ...$params) {
        $stmt = $this->executePrepared($sql, $types, ...$params);
        if (!$stmt) return null;
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }
    
    // Получение всех строк
    public function fetchAll($sql, $types = '', ...$params) {
        $stmt = $this->executePrepared($sql, $types, ...$params);
        if (!$stmt) return [];
        
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
    
    // Вставка с возвратом ID
    public function insert($sql, $types = '', ...$params) {
        $stmt = $this->executePrepared($sql, $types, ...$params);
        if (!$stmt) return false;
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        return $insertId;
    }
    
    // Обновление/удаление
    public function query($sql, $types = '', ...$params) {
        $stmt = $this->executePrepared($sql, $types, ...$params);
        if (!$stmt) return false;
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }
    
    // Начало транзакции
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    // Коммит транзакции
    public function commit() {
        $this->connection->commit();
    }
    
    // Откат транзакции
    public function rollback() {
        $this->connection->rollback();
    }
    
    // Экранирование строки (дополнительная защита)
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Функция для удобного доступа к БД
function db() {
    return Database::getInstance();
}

