<?php
// config/config.php

// Informazioni base dell'applicazione
define('APP_NAME', 'Sibilla');
define('APP_VERSION', '1.0.0');

// Impostazioni percorsi
define('BASE_PATH', dirname(__DIR__));
define('URL_ROOT', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' . '://' . $_SERVER['HTTP_HOST']);

// Impostazioni di sessione
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Includi le configurazioni del database
require_once 'database.php';

// Includi constants
require_once 'constants.php';

// Includi utility helper
require_once BASE_PATH . '/utils/helpers.php';

// Gestione errori (in produzione impostare a 0)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Europe/Rome');

// Impostazioni simulazione Monte Carlo
define('MONTE_CARLO_SIMULATIONS', 10000); // Numero di simulazioni da eseguire

// Funzione per il caricamento automatico delle classi
spl_autoload_register(function ($className) {
    $classFile = BASE_PATH . '/classes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});