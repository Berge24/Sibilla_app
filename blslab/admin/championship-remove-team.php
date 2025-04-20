<?php
// admin/championship-remove-team.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un campionato e una squadra
if (!isset($_POST['championship_id']) || !isset($_POST['team_id']) || 
    !is_numeric($_POST['championship_id']) || !is_numeric($_POST['team_id'])) {
    header('Location: championships.php?error=invalid');
    exit;
}

$championshipId = intval($_POST['championship_id']);
$teamId = intval($_POST['team_id']);

// Carica il campionato
$championship = Championship::findById($championshipId);
if (!$championship) {
    header('Location: championships.php?error=notfound');
    exit;
}

// Verifica che non ci siano partite associate a questa squadra nel campionato
$db = Database::getInstance();
$matchesCount = $db->count('matches', 
    'championship_id = ? AND (home_team_id = ? OR away_team_id = ?)',
    [$championshipId, $teamId, $teamId]
);

if ($matchesCount > 0) {
    header('Location: championship-teams.php?id=' . $championshipId . '&error=has_matches');
    exit;
}

// Rimuovi la squadra dal campionato
if ($championship->removeTeam($teamId)) {
    header('Location: championship-teams.php?id=' . $championshipId . '&success=remove');
} else {
    header('Location: championship-teams.php?id=' . $championshipId . '&error=remove');
}
exit;