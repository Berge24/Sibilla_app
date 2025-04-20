<?php
// admin/match-delete.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: matches.php?error=invalid');
    exit;
}

$matchId = intval($_GET['id']);

// Carica la partita
$match = Match::findById($matchId);
if (!$match) {
    header('Location: matches.php?error=notfound');
    exit;
}

// Salva il campionato ID per il redirect
$championshipId = $match->getChampionshipId();

// Tenta l'eliminazione
if ($match->delete()) {
    // Aggiorna la classifica del campionato
    $championship = Championship::findById($championshipId);
    if ($championship) {
        $championship->calculateStandings();
    }
    
    header('Location: matches.php?championship_id=' . $championshipId . '&success=delete');
} else {
    header('Location: matches.php?championship_id=' . $championshipId . '&error=delete');
}
exit;