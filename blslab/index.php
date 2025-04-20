<?php
// index.php - Entry point principale

// Includi la configurazione
require_once 'config/config.php';

// Se l'utente è un amministratore, reindirizza alla dashboard admin
if (User::isAdmin()) {
    header('Location: admin/index.php');
    exit;
}

// Altrimenti reindirizza alla homepage pubblica
header('Location: public/index.php');
exit;