<?php
// admin/calculate-probabilities.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un campionato
if (!isset($_POST['championship_id']) || !is_numeric($_POST['championship_id'])) {
    header('Location: championships.php?error=invalid');
    exit;
}

$championshipId = intval($_POST['championship_id']);

// Carica il campionato
$championship = Championship::findById($championshipId);
if (!$championship) {
    header('Location: championships.php?error=notfound');
    exit;
}

// Ottieni l'URL di provenienza per il redirect
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'championship-edit.php?id=' . $championshipId;

// Calcola prima le classifiche aggiornate se richiesto
if (isset($_POST['update_standings']) && $_POST['update_standings'] == '1') {
    $championship->calculateStandings();
}

// Calcola le probabilità di vittoria con gestione degli errori
try {
    // Prima calcola le probabilità del campionato
    if ($championship->calculateWinProbabilities()) {
        // Poi calcola le probabilità delle singole partite
        $championship->calculateMatchProbabilities();
        
        // Redirect con messaggio di successo
        $successParam = strpos($redirect, '?') !== false ? '&success=probabilities' : '?success=probabilities';
        header('Location: ' . $redirect . $successParam);
    } else {
        // Redirect con messaggio di errore
        $errorParam = strpos($redirect, '?') !== false ? '&error=probabilities' : '?error=probabilities';
        header('Location: ' . $redirect . $errorParam);
    }
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in calculate-probabilities.php: " . $e->getMessage());
    
    // Redirect con messaggio di errore
    $errorParam = strpos($redirect, '?') !== false ? '&error=probabilities' : '?error=probabilities';
    header('Location: ' . $redirect . $errorParam);
}

exit;
?>