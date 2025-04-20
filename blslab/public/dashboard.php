<?php
// public/dashboard.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia loggato
if (!User::isLoggedIn()) {
    header('Location: ' . URL_ROOT . '/auth/login.php');
    exit;
}

// Ottieni l'utente corrente
$currentUser = User::getCurrentUser();
$userId = $currentUser->getId();

// Ottieni l'abbonamento dell'utente
$userSubscription = UserSubscription::getByUserId($userId);
$subscriptionPlan = null;

if ($userSubscription) {
    $subscriptionPlan = SubscriptionPlan::findById($userSubscription->getPlanId());
}

// Ottieni limitazioni abbonamento
$champLimit = ($subscriptionPlan) ? $subscriptionPlan->getChampionshipsLimit() : 0;
$teamsLimit = ($subscriptionPlan) ? $subscriptionPlan->getTeamsLimit() : 0;
$matchesLimit = ($subscriptionPlan) ? $subscriptionPlan->getMatchesLimit() : 0;

// Ottieni database
$db = Database::getInstance();

// Campionati dell'utente
$userChampionships = $db->fetchAll("
    SELECT c.*, s.name as season_name,
           (SELECT COUNT(*) FROM championships_teams WHERE championship_id = c.id) as team_count,
           (SELECT COUNT(*) FROM matches WHERE championship_id = c.id) as match_count
    FROM championships c
    JOIN seasons s ON c.season_id = s.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
", [$userId]);

$userChampionshipsCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM championships 
    WHERE user_id = ?
", [$userId])['count'];

// Squadre dell'utente
$userTeams = $db->fetchAll("
    SELECT t.*, 
           (SELECT COUNT(*) FROM championships_teams WHERE team_id = t.id) as championship_count
    FROM teams t
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 5
", [$userId]);

$userTeamsCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM teams 
    WHERE user_id = ?
", [$userId])['count'];

// Partite recenti dei campionati dell'utente
$recentMatches = $db->fetchAll("
    SELECT m.*, 
           c.name as championship_name, c.type as championship_type,
           home.name as home_team_name, 
           away.name as away_team_name
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    WHERE c.user_id = ? AND m.status = ?
    ORDER BY m.match_date DESC
    LIMIT 5
", [$userId, MATCH_STATUS_COMPLETED]);

// Prossime partite dei campionati dell'utente
$upcomingMatches = $db->fetchAll("
    SELECT m.*, 
           c.name as championship_name, c.type as championship_type,
           home.name as home_team_name, 
           away.name as away_team_name
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    WHERE c.user_id = ? AND m.status = ? AND m.match_date >= CURRENT_DATE()
    ORDER BY m.match_date ASC
    LIMIT 5
", [$userId, MATCH_STATUS_SCHEDULED]);

$userMatchesCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    WHERE c.user_id = ?
", [$userId])['count'];

// Utenti attivi recenti (solo per admin)
$recentUsers = [];
if (User::isAdmin()) {
    $recentUsers = $db->fetchAll("
        SELECT u.*, s.name as subscription_name
        FROM users u
        LEFT JOIN user_subscriptions us ON u.id = us.user_id
        LEFT JOIN subscription_plans s ON us.plan_id = s.id
        ORDER BY u.last_login DESC
        LIMIT 5
    ");
}

// Includi il template header
$pageTitle = 'Dashboard';
include_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Banner abbonamento -->
    <div class="subscription-banner mb-4">
        <div class="subscription-status">
            <div>
                <h5 class="mb-0">Benvenuto, <?php echo htmlspecialchars($currentUser->getUsername()); ?></h5>
                <p class="mb-0">
                    <?php if ($subscriptionPlan): ?>
                        Abbonamento: <strong><?php echo htmlspecialchars($subscriptionPlan->getName()); ?></strong>
                        <span class="subscription-badge subscription-badge-<?php echo strtolower($subscriptionPlan->getCode()); ?>">
                            <?php echo htmlspecialchars($subscriptionPlan->getCode()); ?>
                        </span>
                    <?php else: ?>
                        Abbonamento: <strong>Free</strong>
                        <span class="subscription-badge subscription-badge-free">
                            Free
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="subscription-actions">
            <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary btn-sm">
                <?php if ($subscriptionPlan && $subscriptionPlan->getCode() !== 'FREE'): ?>
                    Gestisci Abbonamento
                <?php else: ?>
                    Upgrade
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Statistiche Principali -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="dashboard-card card bg-primary text-white">
                <div class="card-body">
                    <i class="fas fa-trophy card-icon"></i>
                    <div class="card-value"><?php echo $userChampionshipsCount; ?></div>
                    <div class="card-title">Campionati</div>
                    <div class="card-subtitle">
                        <?php if ($champLimit > 0): ?>
                            su <?php echo $champLimit; ?> disponibili
                        <?php else: ?>
                            Limite non impostato
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($champLimit > 0): ?>
                        <div class="usage-indicator mt-2">
                            <div class="progress">
                                <div class="progress-bar bg-light text-dark" role="progressbar" 
                                     style="width: <?php echo min(100, ($userChampionshipsCount / $champLimit) * 100); ?>%" 
                                     aria-valuenow="<?php echo $userChampionshipsCount; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="<?php echo $champLimit; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="dashboard-card card bg-success text-white">
                <div class="card-body">
                    <i class="fas fa-users card-icon"></i>
                    <div class="card-value"><?php echo $userTeamsCount; ?></div>
                    <div class="card-title">Squadre</div>
                    <div class="card-subtitle">
                        <?php if ($teamsLimit > 0): ?>
                            su <?php echo $teamsLimit; ?> disponibili
                        <?php else: ?>
                            Limite non impostato
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($teamsLimit > 0): ?>
                        <div class="usage-indicator mt-2">
                            <div class="progress">
                                <div class="progress-bar bg-light text-dark" role="progressbar" 
                                     style="width: <?php echo min(100, ($userTeamsCount / $teamsLimit) * 100); ?>%" 
                                     aria-valuenow="<?php echo $userTeamsCount; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="<?php echo $teamsLimit; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="dashboard-card card bg-info text-white">
                <div class="card-body">
                    <i class="fas fa-futbol card-icon"></i>
                    <div class="card-value"><?php echo $userMatchesCount; ?></div>
                    <div class="card-title">Partite</div>
                    <div class="card-subtitle">
                        <?php if ($matchesLimit > 0): ?>
                            su <?php echo $matchesLimit; ?> disponibili
                        <?php else: ?>
                            Limite non impostato
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($matchesLimit > 0): ?>
                        <div class="usage-indicator mt-2">
                            <div class="progress">
                                <div class="progress-bar bg-light text-dark" role="progressbar" 
                                     style="width: <?php echo min(100, ($userMatchesCount / $matchesLimit) * 100); ?>%" 
                                     aria-valuenow="<?php echo $userMatchesCount; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="<?php echo $matchesLimit; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Funzionalità rapide -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-light">
                    <h5 class="m-0 font-weight-bold">Funzionalità Rapide</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        // Definisci le funzionalità disponibili in base all'abbonamento
                        $canCreateChampionship = $subscriptionPlan && $subscriptionPlan->getCode() !== 'FREE';
                        $canCreateTeam = $subscriptionPlan && $subscriptionPlan->getCode() !== 'FREE';
                        $isChampLimitReached = $champLimit > 0 &&$isChampLimitReached = $champLimit > 0 && $userChampionshipsCount >= $champLimit;
                        $isTeamLimitReached = $teamsLimit > 0 && $userTeamsCount >= $teamsLimit;
                        ?>
                        
                        <div class="col-md-3 mb-3">
                            <a href="<?php echo URL_ROOT; ?>/public/my_championships.php" class="btn btn-primary w-100 py-3">
                                <i class="fas fa-trophy fa-2x mb-2"></i>
                                <div>I Miei Campionati</div>
                            </a>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <?php if ($canCreateChampionship && !$isChampLimitReached): ?>
                                <a href="<?php echo URL_ROOT; ?>/admin/championship_form.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                    <div>Nuovo Campionato</div>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary w-100 py-3 feature-locked" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#feature-upgrade-modal" 
                                        <?php echo ($isChampLimitReached) ? 'data-feature="limit-championships"' : 'data-feature="create-championship"'; ?>>
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                    <div>Nuovo Campionato</div>
                                    <div class="feature-locked-text">
                                        <?php echo ($isChampLimitReached) ? 'Limite raggiunto' : 'Funzione premium'; ?>
                                    </div>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <?php if ($canCreateTeam && !$isTeamLimitReached): ?>
                                <a href="<?php echo URL_ROOT; ?>/admin/team_form.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-users-cog fa-2x mb-2"></i>
                                    <div>Nuova Squadra</div>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary w-100 py-3 feature-locked" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#feature-upgrade-modal" 
                                        <?php echo ($isTeamLimitReached) ? 'data-feature="limit-teams"' : 'data-feature="create-team"'; ?>>
                                    <i class="fas fa-users-cog fa-2x mb-2"></i>
                                    <div>Nuova Squadra</div>
                                    <div class="feature-locked-text">
                                        <?php echo ($isTeamLimitReached) ? 'Limite raggiunto' : 'Funzione premium'; ?>
                                    </div>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <a href="<?php echo URL_ROOT; ?>/public/standings.php" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <div>Classifiche</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Campionati recenti -->
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">I Miei Campionati</h5>
                    <a href="<?php echo URL_ROOT; ?>/public/my_championships.php" class="btn btn-sm btn-primary">
                        Vedi Tutti
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($userChampionships)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                            <p>Non hai ancora creato alcun campionato.</p>
                            <?php if ($canCreateChampionship && !$isChampLimitReached): ?>
                                <a href="<?php echo URL_ROOT; ?>/admin/championship_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i> Crea il primo
                                </a>
                            <?php elseif (!$canCreateChampionship): ?>
                                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#feature-upgrade-modal" data-feature="create-championship">
                                    <i class="fas fa-lock"></i> Funzione premium
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($userChampionships as $championship): ?>
                                <a href="<?php echo URL_ROOT; ?>/public/championship.php?id=<?php echo $championship['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($championship['name']); ?>
                                            <?php if ($championship['is_public']): ?>
                                                <span class="ownership-badge ownership-badge-public">
                                                    <i class="fas fa-globe-europe me-1"></i> Pubblico
                                                </span>
                                            <?php else: ?>
                                                <span class="ownership-badge ownership-badge-private">
                                                    <i class="fas fa-lock me-1"></i> Privato
                                                </span>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="small text-muted">
                                            <span class="badge bg-<?php echo ($championship['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                                <?php echo $championship['type']; ?>
                                            </span>
                                            <span class="ms-2"><?php echo htmlspecialchars($championship['season_name']); ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end text-nowrap">
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $championship['team_count']; ?> squadre
                                        </span>
                                        <span class="badge bg-secondary rounded-pill ms-1">
                                            <?php echo $championship['match_count']; ?> partite
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($canCreateChampionship && !$isChampLimitReached): ?>
                            <div class="text-center mt-3">
                                <a href="<?php echo URL_ROOT; ?>/admin/championship_form.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus-circle"></i> Nuovo Campionato
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Prossime partite -->
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">Prossime Partite</h5>
                    <a href="<?php echo URL_ROOT; ?>/public/matches.php" class="btn btn-sm btn-primary">
                        Vedi Tutte
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingMatches)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                            <p>Non hai partite programmate.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-matches">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Campionato</th>
                                        <th>Squadre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingMatches as $match): ?>
                                        <tr>
                                            <td class="match-date">
                                                <?php echo date('d/m/Y', strtotime($match['match_date'])); ?><br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($match['match_date'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="<?php echo URL_ROOT; ?>/public/championship.php?id=<?php echo $match['championship_id']; ?>">
                                                    <?php echo htmlspecialchars($match['championship_name']); ?>
                                                </a>
                                            </td>
                                            <td class="match-teams">
                                                <a href="<?php echo URL_ROOT; ?>/public/team.php?id=<?php echo $match['home_team_id']; ?>">
                                                    <?php echo htmlspecialchars($match['home_team_name']); ?>
                                                </a>
                                                <span class="text-muted mx-2">vs</span>
                                                <a href="<?php echo URL_ROOT; ?>/public/team.php?id=<?php echo $match['away_team_id']; ?>">
                                                    <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Risultati recenti -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">Risultati Recenti</h5>
                    <a href="<?php echo URL_ROOT; ?>/public/matches.php?status=<?php echo MATCH_STATUS_COMPLETED; ?>" class="btn btn-sm btn-primary">
                        Vedi Tutti
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentMatches)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <p>Non ci sono risultati recenti.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-matches">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Campionato</th>
                                        <th>Squadre</th>
                                        <th class="text-center">Risultato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentMatches as $match): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($match['match_date'])); ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo URL_ROOT; ?>/public/championship.php?id=<?php echo $match['championship_id']; ?>">
                                                    <?php echo htmlspecialchars($match['championship_name']); ?>
                                                </a>
                                            </td>
                                            <td class="match-teams">
                                                <a href="<?php echo URL_ROOT; ?>/public/team.php?id=<?php echo $match['home_team_id']; ?>">
                                                    <?php echo htmlspecialchars($match['home_team_name']); ?>
                                                </a>
                                                <span class="text-muted mx-2">vs</span>
                                                <a href="<?php echo URL_ROOT; ?>/public/team.php?id=<?php echo $match['away_team_id']; ?>">
                                                    <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <a href="<?php echo URL_ROOT; ?>/public/match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Area Admin (solo per admin) -->
    <?php if (User::isAdmin()): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold">Area Amministratore</h5>
                    <a href="<?php echo URL_ROOT; ?>/admin/index.php" class="btn btn-sm btn-light">
                        Pannello Admin
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Utenti recenti</h6>
                            <?php if (empty($recentUsers)): ?>
                                <p>Nessun utente disponibile.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Abbonamento</th>
                                                <th>Ultimo Accesso</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentUsers as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <?php if ($user['subscription_name']): ?>
                                                            <span class="badge bg-success"><?php echo htmlspecialchars($user['subscription_name']); ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Free</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Strumenti rapidi</h6>
                            <div class="list-group">
                                <a href="<?php echo URL_ROOT; ?>/admin/users.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-users-cog me-2"></i> Gestione Utenti
                                </a>
                                <a href="<?php echo URL_ROOT; ?>/admin/subscription_plans.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-tags me-2"></i> Gestione Piani Abbonamento
                                </a>
                                <a href="<?php echo URL_ROOT; ?>/admin/user_approve.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-user-check me-2"></i> Approvazione Utenti
                                </a>
                                <a href="<?php echo URL_ROOT; ?>/admin/user_limits.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-sliders-h me-2"></i> Gestione Limiti Utente
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal per funzionalità premium -->
<div class="modal fade" id="feature-upgrade-modal" tabindex="-1" aria-labelledby="featureUpgradeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-upgrade">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="featureUpgradeModalLabel">Funzionalità Premium</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-lock fa-4x text-warning mb-3"></i>
                <h5>Accesso limitato</h5>
                <p>La funzionalità <span class="feature-title fw-bold">selezionata</span> è disponibile solo con abbonamento <span class="feature-plan fw-bold">Premium</span>.</p>
                
                <div class="upgrade-features">
                    <div class="upgrade-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Crea campionati illimitati</span>
                    </div>
                    <div class="upgrade-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Gestisci più squadre</span>
                    </div>
                    <div class="upgrade-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Statistiche avanzate</span>
                    </div>
                </div>
                
                <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary btn-lg mt-3">
                    Scopri i piani di abbonamento
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal per limite raggiunto -->
<div class="modal fade" id="limit-reached-modal" tabindex="-1" aria-labelledby="limitReachedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-upgrade">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="limitReachedModalLabel">Limite Raggiunto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                <h5>Hai raggiunto il limite!</h5>
                <p>Hai raggiunto il limite massimo di <span class="resource-title fw-bold">risorse</span> disponibili con il tuo piano attuale.</p>
                
                <div class="card bg-light my-3 p-3">
                    <div class="card-body">
                        <h6 class="card-title">Piano Attuale</h6>
                        <p class="card-text">
                            <?php if ($subscriptionPlan): ?>
                                <strong><?php echo htmlspecialchars($subscriptionPlan->getName()); ?></strong>
                            <?php else: ?>
                                <strong>Piano Free</strong>
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($subscriptionPlan): ?>
                            <ul class="list-group list-group-flush text-start small">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Campionati
                                    <span class="badge bg-primary rounded-pill"><?php echo $champLimit; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Squadre
                                    <span class="badge bg-primary rounded-pill"><?php echo $teamsLimit; ?></span>
                                </li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary btn-lg mt-3">
                    Upgrade Piano
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>