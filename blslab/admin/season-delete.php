<?php
// admin/season-delete.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: seasons.php?error=invalid');
    exit;
}

$seasonId = intval($_GET['id']);

// Carica la stagione
$season = Season::findById($seasonId);
if (!$season) {
    header('Location: seasons.php?error=notfound');
    exit;
}

// Verifica che non ci siano campionati associati
$championships = $season->getChampionships();
if (!empty($championships)) {
    header('Location: seasons.php?error=delete');
    exit;
}

// Tenta l'eliminazione
if ($season->delete()) {
    header('Location: seasons.php?success=delete');
} else {
    header('Location: seasons.php?error=delete');
}
exit;