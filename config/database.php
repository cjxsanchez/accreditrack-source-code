<?php
/**
 * Database Connection Class
 * Handles all database operations with PDO
 */

class Database {
    private $connection;
    private $host;
    private $db_name;
    private $user;
    private $password;
    private $port;
    private $charset;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->user = DB_USER;
        $this->password = DB_PASS;
        $this->port = DB_PORT;
        $this->charset = 'utf8mb4';
    }

    /**
     * Connect to database
     * @return PDO
     */
    public function connect() {
        $this->connection = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=" . $this->charset;

            $this->connection = new PDO($dsn, $this->user, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            logMessage('Database connected successfully', 'info');
            return $this->connection;

        } catch (PDOException $e) {
            logMessage('Database connection error: ' . $e->getMessage(), 'error');
            die('Database connection failed. Please check your configuration.');
        }
    }

    /**
     * Get connection
     * @return PDO
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Prepare a query
     * @param string $query
     * @return PDOStatement
     */
    public function prepare($query) {
        try {
            return $this->getConnection()->prepare($query);
        } catch (PDOException $e) {
            logMessage('Prepare error: ' . $e->getMessage(), 'error', ['query' => $query]);
            throw new Exception('Database prepare failed');
        }
    }

    /**
     * Execute a query with parameters
     * @param string $query
     * @param array $params
     * @return bool
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            logMessage('Execute error: ' . $e->getMessage(), 'error');
            throw new Exception('Database execute failed');
        }
    }

    /**
     * Fetch single row
     * @param string $query
     * @param array $params
     * @return array|false
     */
    public function fetchOne($query, $params = []) {
        try {
            $stmt = $this->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            logMessage('Fetch error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Fetch all rows
     * @param string $query
     * @param array $params
     * @return array
     */
    public function fetchAll($query, $params = []) {
        try {
            $stmt = $this->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            logMessage('Fetch all error: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get last insert ID
     * @return string
     */
    public function lastInsertId() {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Get row count from last query
     * @param PDOStatement $stmt
     * @return int
     */
    public function rowCount($stmt = null) {
        if ($stmt === null) {
            return 0;
        }
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     * @return bool
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     * @return bool
     */
    public function commit() {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     * @return bool
     */
    public function rollback() {
        return $this->getConnection()->rollback();
    }
}

?>
