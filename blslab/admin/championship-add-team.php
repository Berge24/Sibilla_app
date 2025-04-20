<?php
// admin/championship-add-team.php

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

// Carica la squadra
$team = Team::findById($teamId);
if (!$team) {
    header('Location: championship-teams.php?id=' . $championshipId . '&error=team_notfound');
    exit;
}

// Aggiungi la squadra al campionato
if ($championship->addTeam($teamId)) {
    header('Location: championship-teams.php?id=' . $championshipId . '&success=add');
} else {
    header('Location: championship-teams.php?id=' . $championshipId . '&error=add');
}
exit;