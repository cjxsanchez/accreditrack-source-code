<?php
/**
 * Base Model Class
 * Provides common database operations for all models
 */

class BaseModel {
    protected $db;
    protected $table;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Find by ID
     */
    public function find($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find all
     */
    public function all($limit = null, $offset = 0) {
        $query = "SELECT * FROM {$this->table}";
        if ($limit) {
            $query .= " LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$limit, $offset]);
        } else {
            $stmt = $this->db->prepare($query);
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    /**
     * Find by condition
     */
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $query = "SELECT * FROM {$this->table} WHERE $column $operator ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$value]);
        return $stmt->fetch();
    }

    /**
     * Find all by condition
     */
    public function whereAll($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $query = "SELECT * FROM {$this->table} WHERE $column $operator ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    /**
     * Create
     */
    public function create($data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";

        $stmt = $this->db->prepare($query);
        if ($stmt->execute(array_values($data))) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Update
     */
    public function update($id, $data) {
        $columns = implode(' = ?, ', array_keys($data)) . ' = ?';
        $query = "UPDATE {$this->table} SET $columns WHERE id = ?";

        $values = array_values($data);
        $values[] = $id;

        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }

    /**
     * Delete
     */
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id]);
    }

    /**
     * Count records
     */
    public function count($column = '*', $value = null) {
        if ($value === null) {
            $query = "SELECT COUNT($column) as total FROM {$this->table}";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
        } else {
            $query = "SELECT COUNT($column) as total FROM {$this->table} WHERE $column = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$value]);
        }
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
}

?>
