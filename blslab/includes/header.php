<?php
// includes/header.php

// Titolo della pagina (se non definito, usa quello default)
$pageTitle = isset($pageTitle) ? $pageTitle : APP_NAME;

// Verifica se l'utente è loggato
$isUserLoggedIn = User::isLoggedIn();
$currentUser = $isUserLoggedIn ? User::getCurrentUser() : null;
$userId = $isUserLoggedIn ? $currentUser->getId() : null;

// Ottieni l'abbonamento dell'utente
$userSubscription = null;
$subscriptionPlan = null;
$planCode = 'free';
$isFreePlan = true;

if ($isUserLoggedIn) {
    $userSubscription = UserSubscription::getByUserId($userId);
    if ($userSubscription) {
        $subscriptionPlan = SubscriptionPlan::findById($userSubscription->getPlanId());
        $planCode = $subscriptionPlan ? strtolower($subscriptionPlan->getCode()) : 'free';
        $isFreePlan = ($planCode === 'free');
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sibilla - Sistema di gestione campionati sportivi. Classifiche, risultati e statistiche in tempo reale.">
    <meta name="keywords" content="campionati, sport, gestione, classifiche, risultati, statistiche, tornei">
    <meta name="author" content="Sibilla">
    
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/subscription.css">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo URL_ROOT; ?>/assets/images/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo URL_ROOT; ?>/assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Open Graph Meta Tags per la condivisione sui social media -->
    <meta property="og:title" content="<?php echo $pageTitle; ?> - <?php echo APP_NAME; ?>">
    <meta property="og:description" content="Sistema di gestione campionati sportivi. Classifiche, risultati e statistiche in tempo reale.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo URL_ROOT; ?>">
    <meta property="og:image" content="<?php echo URL_ROOT; ?>/assets/images/og-image.jpg">
</head>
<body>
    <?php include_once 'navbar.php'; ?>
    
    <?php if ($isUserLoggedIn && $isFreePlan): ?>
    <!-- Spazio Pubblicitario (solo per utenti free) -->
    <div class="ad-space container mt-3">
    <p class="ad-space-text">Spazio Pubblicitario</p>
        <div class="text-center">
            <img src="<?php echo URL_ROOT; ?>/assets/images/header-ad-placeholder.png" alt="Advertisement" class="img-fluid">
        </div>
        <p class="small text-center mt-1">
            <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php">Passa a un piano a pagamento</a> per rimuovere le pubblicità.
        </p>
    </div>
    <?php endif; ?>
    
    <main class="container-fluid py-4">