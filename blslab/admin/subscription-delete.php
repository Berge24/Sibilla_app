<?php
// admin/subscription-delete.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni ID abbonamento dalla query string
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: subscriptions.php');
    exit;
}

// Non permettere di eliminare l'abbonamento Free (id=1)
if ($id == 1) {
    header('Location: subscriptions.php?error=delete');
    exit;
}

// Carica abbonamento
$subscription = Subscription::findById($id);

if (!$subscription) {
    header('Location: subscriptions.php?error=404');
    exit;
}

// Verifica che non ci siano utenti con questo abbonamento
$userCount = $subscription->getUserCount();
if ($userCount > 0) {
    header('Location: subscriptions.php?error=delete');
    exit;
}

// Elimina abbonamento
if ($subscription->delete()) {
    // Reindirizza con messaggio di successo
    header('Location: subscriptions.php?success=delete');
} else {
    // Reindirizza con messaggio di errore
    header('Location: subscriptions.php?error=delete');
}
exit;