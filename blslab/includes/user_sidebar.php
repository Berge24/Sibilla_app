<?php
// includes/user_sidebar.php

// Verifica che l'utente sia loggato
if (!User::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Determina la pagina corrente
$currentPage = basename($_SERVER['PHP_SELF']);

// Ottieni l'utente corrente
$currentUser = User::getCurrentUser();
$userId = $currentUser->getId();

// Ottieni l'abbonamento dell'utente
$userSubscription = UserSubscription::getByUserId($userId);
$subscriptionPlan = null;
$planCode = 'free';

if ($userSubscription) {
    $subscriptionPlan = SubscriptionPlan::findById($userSubscription->getPlanId());
    $planCode = $subscriptionPlan ? strtolower($subscriptionPlan->getCode()) : 'free';
}

// Verifica se l'utente è approvato
$isApproved = $currentUser->isApproved();

// Verifica se l'abbonamento è scaduto
$isExpired = $userSubscription ? $userSubscription->isExpired() : false;

// Ottieni limitazioni abbonamento
$champLimit = ($subscriptionPlan) ? $subscriptionPlan->getChampionshipsLimit() : 0;
$teamsLimit = ($subscriptionPlan) ? $subscriptionPlan->getTeamsLimit() : 0;
$matchesLimit = ($subscriptionPlan) ? $subscriptionPlan->getMatchesLimit() : 0;

// Ottieni utilizzo attuale
$db = Database::getInstance();

$userChampionshipsCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM championships 
    WHERE user_id = ?
", [$userId])['count'];

$userTeamsCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM teams 
    WHERE user_id = ?
", [$userId])['count'];

$userMatchesCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    WHERE c.user_id = ?
", [$userId])['count'];

// Verifica i limiti
$champLimitReached = $champLimit > 0 && $userChampionshipsCount >= $champLimit;
$teamsLimitReached = $teamsLimit > 0 && $userTeamsCount >= $teamsLimit;
$matchesLimitReached = $matchesLimit > 0 && $userMatchesCount >= $matchesLimit;

// Funzioni di disponibilità in base all'abbonamento
$canCreateChampionship = $subscriptionPlan && $planCode !== 'free';
$canCreateTeam = $subscriptionPlan && $planCode !== 'free';
$canUseStatistics = $subscriptionPlan && in_array($planCode, ['premium', 'enterprise']);
$canUsePrivate = $subscriptionPlan && in_array($planCode, ['premium', 'enterprise']);
$canAccessApi = $subscriptionPlan && $planCode === 'enterprise';
?>

<div class="col-md-3 col-lg-2 p-0">
    <div class="list-group sticky-top mt-4">
        <div class="list-group-item bg-primary text-white">
            <h6 class="mb-1">
                <i class="fas fa-user-circle me-2"></i> 
                <?php echo htmlspecialchars($currentUser->getUsername()); ?>
            </h6>
            <div class="small">
                <?php if ($subscriptionPlan): ?>
                    <span class="subscription-badge subscription-badge-<?php echo $planCode; ?>">
                        <?php echo strtoupper($planCode); ?>
                    </span>
                <?php else: ?>
                    <span class="subscription-badge subscription-badge-free">FREE</span>
                <?php endif; ?>
                
                <?php if ($isExpired): ?>
                    <span class="badge bg-danger ms-1">Scaduto</span>
                <?php endif; ?>
            </div>
        </div>
        
        <a href="<?php echo URL_ROOT; ?>/public/dashboard.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        
        <div class="list-group-item list-group-item-light fw-bold text-uppercase small py-2">
            <i class="fas fa-th-list me-2"></i> I Miei Contenuti
        </div>
        
        <a href="<?php echo URL_ROOT; ?>/public/my_championships.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'my_championships.php') ? 'active' : ''; ?> <?php echo (!$isApproved || $isExpired || !$canCreateChampionship) ? 'disabled opacity-75' : ''; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-trophy me-2"></i> Campionati</div>
                <?php if ($champLimit > 0): ?>
                    <span class="badge rounded-pill <?php echo $champLimitReached ? 'bg-danger' : 'bg-primary'; ?>">
                        <?php echo $userChampionshipsCount; ?>/<?php echo $champLimit; ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
        
        <a href="<?php echo URL_ROOT; ?>/public/my_teams.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'my_teams.php') ? 'active' : ''; ?> <?php echo (!$isApproved || $isExpired || !$canCreateTeam) ? 'disabled opacity-75' : ''; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-users me-2"></i> Squadre</div>
                <?php if ($teamsLimit > 0): ?>
                    <span class="badge rounded-pill <?php echo $teamsLimitReached ? 'bg-danger' : 'bg-primary'; ?>">
                        <?php echo $userTeamsCount; ?>/<?php echo $teamsLimit; ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
        
        <a href="<?php echo URL_ROOT; ?>/public/matches.php?user=me" class="list-group-item list-group-item-action <?php echo (($currentPage == 'matches.php' && isset($_GET['user']) && $_GET['user'] == 'me')) ? 'active' : ''; ?> <?php echo (!$isApproved || $isExpired) ? 'disabled opacity-75' : ''; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-futbol me-2"></i> Partite</div>
                <?php if ($matchesLimit > 0): ?>
                    <span class="badge rounded-pill <?php echo $matchesLimitReached ? 'bg-danger' : 'bg-primary'; ?>">
                        <?php echo $userMatchesCount; ?>/<?php echo $matchesLimit; ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
        
        <a href="<?php echo URL_ROOT; ?>/public/standings.php?user=me" class="list-group-item list-group-item-action <?php echo (($currentPage == 'standings.php' && isset($_GET['user']) && $_GET['user'] == 'me')) ? 'active' : ''; ?> <?php echo (!$isApproved || $isExpired) ? 'disabled opacity-75' : ''; ?>">
            <i class="fas fa-table me-2"></i> Classifiche
        </a>
        
        <?php if ($canUseStatistics): ?>
        <a href="<?php echo URL_ROOT; ?>/public/statistics.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'statistics.php') ? 'active' : ''; ?> <?php echo (!$isApproved || $isExpired) ? 'disabled opacity-75' : ''; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-chart-bar me-2"></i> Statistiche</div>
                <?php if (!$isExpired && $canUseStatistics): ?>
                    <span class="badge rounded-pill bg-success">Premium</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endif; ?>
        
        <div class="list-group-item list-group-item-light fw-bold text-uppercase small py-2">
            <i class="fas fa-cog me-2"></i> Account
        </div>
        
        <a href="<?php echo URL_ROOT; ?>/public/profile.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-id-card me-2"></i> Profilo
        </a>
        
        <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'subscription_plans.php') ? 'active' : ''; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-credit-card me-2"></i> Abbonamento</div>
                <?php if ($isExpired): ?>
                    <span class="badge bg-danger rounded-pill">Scaduto</span>
                <?php elseif ($subscriptionPlan): ?>
                    <span class="badge bg-success rounded-pill">Attivo</span>
                <?php else: ?>
                    <span class="badge bg-secondary rounded-pill">Free</span>
                <?php endif; ?>
            </div>
        </a>
        
        <?php if (User::isAdmin()): ?>
            <div class="list-group-item list-group-item-light fw-bold text-uppercase small py-2">
                <i class="fas fa-tools me-2"></i> Area Admin
            </div>
            
            <a href="<?php echo URL_ROOT; ?>/admin/index.php" class="list-group-item list-group-item-action">
                <i class="fas fa-crown me-2"></i> Pannello Admin
            </a>
            
            <a href="<?php echo URL_ROOT; ?>/admin/users.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-cog me-2"></i> Gestione Utenti
            </a>
            
            <a href="<?php echo URL_ROOT; ?>/admin/subscription_plans.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'subscription_plans.php') ? 'active' : ''; ?>">
                <i class="fas fa-tags me-2"></i> Gestione Abbonamenti
            </a>
        <?php endif; ?>
        
        <a href="<?php echo URL_ROOT; ?>/public/index.php" class="list-group-item list-group-item-action">
            <i class="fas fa-home me-2"></i> Home
        </a>
        
        <a href="<?php echo URL_ROOT; ?>/auth/logout.php" class="list-group-item list-group-item-action text-danger">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
    
    <?php if ($isExpired): ?>
        <div class="alert alert-danger mt-3">
            <i class="fas fa-exclamation-circle me-2"></i> Il tuo abbonamento è scaduto.
            <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="alert-link d-block mt-2">Rinnova ora</a>
        </div>
    <?php endif; ?>
    
    <?php if (!$isApproved && !User::isAdmin()): ?>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle me-2"></i> Il tuo account è in attesa di approvazione.
        </div>
    <?php endif; ?>
    
    <?php if ($canAccessApi): ?>
        <div class="card mt-3">
            <div class="card-header bg-purple text-white">
                <h6 class="mb-0">
                    <i class="fas fa-code me-2"></i> Accesso API
                </h6>
            </div>
            <div class="card-body">
                <p class="small">Hai accesso all'API come utente Enterprise.</p>
                <a href="<?php echo URL_ROOT; ?>/api/docs.php" class="btn btn-sm btn-outline-primary w-100">
                    <i class="fas fa-book me-1"></i> Documentazione API
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>