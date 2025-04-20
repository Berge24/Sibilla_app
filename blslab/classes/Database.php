<?php
// classes/Database.php

class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Costruttore privato per impedire l'istanziazione diretta (pattern Singleton)
     */
    private function __construct() {
        $this->connection = getDbConnection();
    }
    
    /**
     * Ottiene l'istanza della classe Database (Singleton)
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Esegue una query con parametri preparati
     * @param string $query Query SQL da eseguire
     * @param array $params Parametri per la query preparata
     * @return PDOStatement
     */
    public function query($query, $params = []) {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Ottiene un singolo record
     * @param string $query Query SQL
     * @param array $params Parametri
     * @return array|false Record come array associativo o false se non trovato
     */
    public function fetchOne($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }
    
    /**
     * Ottiene tutti i record
     * @param string $query Query SQL
     * @param array $params Parametri
     * @return array Array di record
     */
    public function fetchAll($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Ottiene il valore di una singola colonna
     * @param string $query Query SQL
     * @param array $params Parametri
     * @param int $column Indice colonna (default 0)
     * @return mixed Valore della colonna
     */
    public function fetchColumn($query, $params = [], $column = 0) {
        $stmt = $this->query($query, $params);
        return $stmt->fetchColumn($column);
    }
    
    /**
     * Inserisce un record e restituisce l'id generato
     * @param string $table Nome tabella
     * @param array $data Dati da inserire (chiave => valore)
     * @return int|false ID ultimo inserimento o false in caso di errore
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($query, array_values($data));
        
        return $this->connection->lastInsertId();
    }
    
    /**
     * Aggiorna un record
     * @param string $table Nome tabella
     * @param array $data Dati da aggiornare (chiave => valore)
     * @param string $where Condizione WHERE
     * @param array $whereParams Parametri per la condizione WHERE
     * @return int Numero di righe aggiornate
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $setClause = implode(', ', $setParts);
        $query = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $stmt = $this->query($query, array_merge($params, $whereParams));
        return $stmt->rowCount();
    }
    
    /**
     * Elimina record
     * @param string $table Nome tabella
     * @param string $where Condizione WHERE
     * @param array $params Parametri per la condizione WHERE
     * @return int Numero di righe eliminate
     */
    public function delete($table, $where, $params = []) {
        $query = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($query, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Conta il numero di record
     * @param string $table Nome tabella
     * @param string $where Condizione WHERE opzionale
     * @param array $params Parametri per la condizione WHERE
     * @return int Numero di record
     */
    public function count($table, $where = '', $params = []) {
        $query = "SELECT COUNT(*) FROM {$table}";
        
        if (!empty($where)) {
            $query .= " WHERE {$where}";
        }
        
        return $this->fetchColumn($query, $params);
    }
    
    /**
     * Inizia una transazione
     */
    public function beginTransaction() {
        $this->connection->beginTransaction();
    }
    
    /**
     * Conferma una transazione
     */
    public function commit() {
        $this->connection->commit();
    }
    
    /**
     * Annulla una transazione
     */
    public function rollback() {
        $this->connection->rollBack();
    }
    
    /**
     * Ottiene l'istanza PDO
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Previene la clonazione (pattern Singleton)
     */
    private function __clone() {}
}