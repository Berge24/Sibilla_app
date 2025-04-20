<?php
// auth/logout.php

// Includi la configurazione
require_once '../config/config.php';

// Esegui il logout
$user = User::getCurrentUser();
if ($user) {
    $user->logout();
}

// Reindirizza alla pagina di login
header('Location: login.php');
exit;