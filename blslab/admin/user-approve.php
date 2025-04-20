<?php
// admin/user-approve.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni ID utente dalla query string
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: users.php');
    exit;
}

// Carica utente
$user = User::findById($id);

if (!$user) {
    header('Location: users.php?error=404');
    exit;
}

// Verifica che l'utente non sia già approvato
if ($user->isApproved()) {
    header('Location: users.php');
    exit;
}

// Processo approvazione
if ($user->approve()) {
    // Reindirizza con messaggio di successo
    header('Location: users.php?success=approve');
} else {
    // Reindirizza con messaggio di errore
    header('Location: users.php?error=approve');
}
exit;