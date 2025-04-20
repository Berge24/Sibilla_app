<?php
// admin/team-delete.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: teams.php?error=invalid');
    exit;
}

$teamId = intval($_GET['id']);

// Carica la squadra
$team = Team::findById($teamId);
if (!$team) {
    header('Location: teams.php?error=notfound');
    exit;
}

// Verifica che la squadra non partecipi a campionati
$championships = $team->getChampionships();
if (!empty($championships)) {
    header('Location: teams.php?error=delete');
    exit;
}

// Tenta l'eliminazione
if ($team->delete()) {
    header('Location: teams.php?success=delete');
} else {
    header('Location: teams.php?error=delete');
}
exit;