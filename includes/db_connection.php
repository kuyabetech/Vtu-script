<?php
// includes/db_connection.php - FIXED VERSION

require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $connected = false;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
            $this->connected = true;
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        // Check if connection is still alive before returning
        if (!$this->connected || !$this->connection) {
            $this->__construct(); // Reconnect if needed
        }
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->getConnection()->prepare($sql);
    }
    
    public function query($sql) {
        return $this->getConnection()->query($sql);
    }
    
    public function escape($string) {
        return $this->getConnection()->real_escape_string($string);
    }
    
    public function insertId() {
        return $this->getConnection()->insert_id;
    }
    
    public function affectedRows() {
        return $this->getConnection()->affected_rows;
    }
    
    public function beginTransaction() {
        $this->getConnection()->begin_transaction();
    }
    
    public function commit() {
        $this->getConnection()->commit();
    }
    
    public function rollback() {
        $this->getConnection()->rollback();
    }
    
    /**
     * Manual close method - use only when absolutely necessary
     */
    public function close() {
        if ($this->connected && $this->connection) {
            @$this->connection->close();
            $this->connected = false;
        }
    }
    
    /**
     * FIXED: Destructor now checks if connection exists before closing
     * and suppresses any errors
     */
    public function __destruct() {
        // Only try to close if we have a valid connection
        if ($this->connected && $this->connection) {
            try {
                @$this->connection->close();
            } catch (Exception $e) {
                // Silently ignore - connection might already be closed
                // This prevents the "mysqli object is already closed" error
            }
        }
    }
}

// Helper function to get database connection
function db() {
    return Database::getInstance()->getConnection();
}

// Optional: Helper function to safely close connection
function closeDb() {
    // This is optional - only use if you have a specific reason
    // Database::getInstance()->close();
}