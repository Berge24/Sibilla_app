<?php
// admin/calculate-standings.php

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
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'championships.php';

// Calcola le classifiche con gestione degli errori
try {
    if ($championship->calculateStandings()) {
        // Se richiesto, aggiorna anche le probabilità Monte Carlo
        if (isset($_POST['calculate_probabilities']) && $_POST['calculate_probabilities'] == '1') {
            $championship->calculateWinProbabilities();
        }
        
        // Redirect con messaggio di successo
        $successParam = strpos($redirect, '?') !== false ? '&success=standings' : '?success=standings';
        header('Location: ' . $redirect . $successParam);
    } else {
        // Redirect con messaggio di errore
        $errorParam = strpos($redirect, '?') !== false ? '&error=standings' : '?error=standings';
        header('Location: ' . $redirect . $errorParam);
    }
} catch (Exception $e) {
    // Log dell'errore
    error_log("Errore in calculate-standings.php: " . $e->getMessage());
    
    // Redirect con messaggio di errore
    $errorParam = strpos($redirect, '?') !== false ? '&error=standings' : '?error=standings';
    header('Location: ' . $redirect . $errorParam);
}

exit;