<?php
// config/database.php

// Configurazione database
$db_config = [
    'host'     => 'localhost',
    'username' => 'blslab',
    'password' => 'wDaWe8WQuxBF',
    'dbname'   => 'my_blslab',
    'charset'  => 'utf8mb4'
];

// Connessione al database
function getDbConnection() {
    global $db_config;
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        return new PDO($dsn, $db_config['username'], $db_config['password'], $options);
    } catch (PDOException $e) {
        // In produzione, gestire l'errore in modo appropriato (log, pagina di errore, ecc.)
        die("Errore di connessione al database: " . $e->getMessage());
    }
}