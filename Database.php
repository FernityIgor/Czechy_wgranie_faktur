<?php
/**
 * Database connection handler for SQL Server
 */

require_once __DIR__ . '/app_config.php';

class Database
{
    private static $instance = null;
    private $connection = null;

    private function __construct()
    {
        $config = AppConfig::get()['database'];
        
        try {
            $dsn = "sqlsrv:Server={$config['server']};Database={$config['database']};TrustServerCertificate=yes";
            
            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Execute query and return all results
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            // Dla DDL (CREATE, ALTER, DROP) nie ma wyników do zwrócenia
            if (stripos(trim($sql), 'CREATE') === 0 || 
                stripos(trim($sql), 'ALTER') === 0 || 
                stripos(trim($sql), 'DROP') === 0 ||
                stripos(trim($sql), 'INSERT') === 0 ||
                stripos(trim($sql), 'UPDATE') === 0 ||
                stripos(trim($sql), 'DELETE') === 0) {
                return true;
            }
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Execute query and return single row
     */
    public function queryOne($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
