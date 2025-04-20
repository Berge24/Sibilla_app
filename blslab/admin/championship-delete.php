<?php
// admin/championship-delete.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: championships.php?error=invalid');
    exit;
}

$championshipId = intval($_GET['id']);

// Carica il campionato
$championship = Championship::findById($championshipId);
if (!$championship) {
    header('Location: championships.php?error=notfound');
    exit;
}

// Verifica che non ci siano partite associate
$db = Database::getInstance();
$matchCount = $db->count('matches', 'championship_id = ?', [$championshipId]);

if ($matchCount > 0) {
    header('Location: championships.php?error=delete');
    exit;
}

// Tenta l'eliminazione
if ($championship->delete()) {
    header('Location: championships.php?success=delete');
} else {
    header('Location: championships.php?error=delete');
}
exit;