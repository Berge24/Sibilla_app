<?php
// public/index.php

// Includi la configurazione
require_once '../config/config.php';

// Ottieni la stagione corrente
$currentSeason = Season::getCurrentSeason();
$currentSeasonId = $currentSeason ? $currentSeason->getId() : null;

// Ottieni database
$db = Database::getInstance();

// Verifica se l'utente è loggato
$isLoggedIn = User::isLoggedIn();
$currentUser = $isLoggedIn ? User::getCurrentUser() : null;
$userId = $isLoggedIn ? $currentUser->getId() : null;

// Ottieni l'abbonamento dell'utente
$userSubscription = null;
$subscriptionPlan = null;
$planCode = 'free';

if ($isLoggedIn) {
    $userSubscription = UserSubscription::getByUserId($userId);
    if ($userSubscription) {
        $subscriptionPlan = SubscriptionPlan::findById($userSubscription->getPlanId());
        $planCode = $subscriptionPlan ? strtolower($subscriptionPlan->getCode()) : 'free';
    }
}

// Campionati pubblici e dell'utente (dalla stagione corrente)
$recentChampionships = [];
if ($currentSeasonId) {
    $query = "
        SELECT c.*, 
               (SELECT COUNT(*) FROM championships_teams WHERE championship_id = c.id) as team_count,
               (SELECT COUNT(*) FROM matches WHERE championship_id = c.id) as match_count,
               CASE WHEN c.user_id = ? THEN 1 ELSE 0 END as is_owner
        FROM championships c
        WHERE c.season_id = ? AND (c.is_public = 1 OR c.user_id = ?)
        ORDER BY is_owner DESC, c.start_date DESC
        LIMIT 6
    ";
    
    $recentChampionships = $db->fetchAll($query, [$userId ?? 0, $currentSeasonId, $userId ?? 0]);
}

// Partite recenti (solo pubbliche o dell'utente)
$recentMatches = $db->fetchAll("
    SELECT m.*, 
           c.name as championship_name, c.type as championship_type, c.user_id as championship_user_id,
           home.name as home_team_name, 
           away.name as away_team_name
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    WHERE m.status = ? AND (c.is_public = 1 OR c.user_id = ?)
    ORDER BY m.match_date DESC
    LIMIT 5
", [MATCH_STATUS_COMPLETED, $userId ?? 0]);

// Prossime partite (solo pubbliche o dell'utente)
$upcomingMatches = $db->fetchAll("
    SELECT m.*, 
           c.name as championship_name, c.type as championship_type, c.user_id as championship_user_id,
           home.name as home_team_name, 
           away.name as away_team_name
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    WHERE m.status = ? AND m.match_date >= CURRENT_DATE() AND (c.is_public = 1 OR c.user_id = ?)
    ORDER BY m.match_date ASC
    LIMIT 5
", [MATCH_STATUS_SCHEDULED, $userId ?? 0]);

// Includi il template header
$pageTitle = 'Home';
include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8">
            <!-- Sezione Introduttiva -->
            <div class="jumbotron bg-light p-5 rounded mb-4">
                <h1 class="display-4">Benvenuto su <?php echo APP_NAME; ?></h1>
                <p class="lead">
                    Il sistema completo per la gestione dei campionati sportivi. 
                    Visualizza classifiche in tempo reale, risultati delle partite e probabilità di vittoria.
                </p>
                <?php if (!$isLoggedIn): ?>
                    <hr class="my-4">
                    <p>Accedi o registrati per gestire i tuoi campionati.</p>
                    <div class="d-flex gap-2">
                        <a class="btn btn-primary" href="<?php echo URL_ROOT; ?>/auth/login.php">Accedi</a>
                        <a class="btn btn-outline-primary" href="<?php echo URL_ROOT; ?>/auth/register.php">Registrati</a>
                    </div>
                <?php elseif ($planCode === 'free'): ?>
                    <hr class="my-4">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i> Sblocca tutte le funzionalità!</h5>
                        <p>
                            Il tuo account è attualmente nella modalità <strong>Free</strong>. 
                            Esegui l'upgrade per creare e gestire i tuoi campionati, squadre e molto altro!
                        </p>
                        <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary mt-2">
                            <i class="fas fa-arrow-circle-up me-1"></i> Scopri i Piani di Abbonamento
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!$isLoggedIn || $planCode === 'free'): ?>
                <!-- Banner Promozione Abbonamenti per utenti non loggati o free -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Piani di Abbonamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Basic</h5>
                                    </div>
                                    <div class="card-body">
                                        <h3 class="card-title pricing-card-title">€9,99 <small class="text-muted fw-light">/mese</small></h3>
                                        <ul class="list-unstyled mt-3 mb-4 text-start">
                                            <li><i class="fas fa-check text-success me-2"></i> 3 campionati</li>
                                            <li><i class="fas fa-check text-success me-2"></i> 10 squadre</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Nessuna pubblicità</li>
                                            <li><i class="fas fa-times text-danger me-2"></i> Campionati privati</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Premium</h5>
                                    </div>
                                    <div class="card-body">
                                        <h3 class="card-title pricing-card-title">€19,99 <small class="text-muted fw-light">/mese</small></h3>
                                        <ul class="list-unstyled mt-3 mb-4 text-start">
                                            <li><i class="fas fa-check text-success me-2"></i> 10 campionati</li>
                                            <li><i class="fas fa-check text-success me-2"></i> 40 squadre</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Statistiche avanzate</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Campionati privati</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Enterprise</h5>
                                    </div>
                                    <div class="card-body">
                                        <h3 class="card-title pricing-card-title">€49,99 <small class="text-muted fw-light">/mese</small></h3>
                                        <ul class="list-unstyled mt-3 mb-4 text-start">
                                            <li><i class="fas fa-check text-success me-2"></i> Campionati illimitati</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Squadre illimitate</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Accesso API</li>
                                            <li><i class="fas fa-check text-success me-2"></i> Supporto prioritario</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary">
                                Scopri tutti i dettagli
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Prossime Partite e Ultimi Risultati -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Calendario Partite</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="matchTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="true">Prossime Partite</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent" type="button" role="tab" aria-controls="recent" aria-selected="false">Risultati Recenti</button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3" id="matchTabsContent">
                        <!-- Prossime Partite Tab -->
                        <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                            <?php if (empty($upcomingMatches)): ?>
                                <p class="text-center">Nessuna partita programmata</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-matches">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="match-date">Data</th>
                                                <th>Campionato</th>
                                                <th class="match-teams">Squadre</th>
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
                                                        <a href="championship.php?id=<?php echo $match['championship_id']; ?>">
                                                            <?php echo htmlspecialchars($match['championship_name']); ?>
                                                        </a>
                                                        <br>
                                                        <small class="badge bg-<?php echo ($match['championship_type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                                            <?php echo $match['championship_type']; ?>
                                                        </small>
                                                        <?php if ($isLoggedIn && $match['championship_user_id'] == $userId): ?>
                                                            <span class="ownership-badge ownership-badge-owner">
                                                                <i class="fas fa-user-edit me-1"></i> Tuo
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="match-teams">
                                                        <a href="team.php?id=<?php echo $match['home_team_id']; ?>">
                                                            <?php echo htmlspecialchars($match['home_team_name']); ?>
                                                        </a>
                                                        <span class="text-muted mx-2">vs</span>
                                                        <a href="team.php?id=<?php echo $match['away_team_id']; ?>">
                                                            <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="matches.php" class="btn btn-primary">Vedi Tutte le Partite</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Risultati Recenti Tab -->
                        <div class="tab-pane fade" id="recent" role="tabpanel" aria-labelledby="recent-tab">
                            <?php if (empty($recentMatches)): ?>
                                <p class="text-center">Nessun risultato disponibile</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-matches">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="match-date">Data</th>
                                                <th>Campionato</th>
                                                <th class="match-teams">Squadre</th>
                                                <th class="match-score">Risultato</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentMatches as $match): ?>
                                                <tr>
                                                    <td class="match-date">
                                                        <?php echo date('d/m/Y', strtotime($match['match_date'])); ?>
                                                    </td>
                                                    <td>
                                                        <a href="championship.php?id=<?php echo $match['championship_id']; ?>">
                                                            <?php echo htmlspecialchars($match['championship_name']); ?>
                                                        </a>
                                                        <br>
                                                        <small class="badge bg-<?php echo ($match['championship_type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                                            <?php echo $match['championship_type']; ?>
                                                        </small>
                                                        <?php if ($isLoggedIn && $match['championship_user_id'] == $userId): ?>
                                                            <span class="ownership-badge ownership-badge-owner">
                                                                <i class="fas fa-user-edit me-1"></i> Tuo
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="match-teams">
                                                        <a href="team.php?id=<?php echo $match['home_team_id']; ?>">
                                                            <?php echo htmlspecialchars($match['home_team_name']); ?>
                                                        </a>
                                                        <span class="text-muted mx-2">vs</span>
                                                        <a href="team.php?id=<?php echo $match['away_team_id']; ?>">
                                                            <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="match-score">
                                                        <a href="match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                            <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="matches.php?status=completed" class="btn btn-primary">Vedi Tutti i Risultati</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <?php if ($isLoggedIn && $planCode === 'free'): ?>
                <!-- Spazio Pubblicitario (solo per utenti free) -->
                <div class="ad-space mb-4">
                    <p class="ad-space-text">Spazio Pubblicitario</p>
                    <div class="text-center">
                        <img src="<?php echo URL_ROOT; ?>/assets/images/ad-placeholder.png" alt="Advertisement" class="img-fluid">
                    </div>
                    <p class="small text-center mt-2">
                        <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php">Upgrade a un piano a pagamento</a> per rimuovere le pubblicità.
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($isLoggedIn): ?>
                <!-- Dashboard utente (solo per utenti loggati) -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0">Il tuo account</h5>
                    </div>
                    <div class="card-body">
                        <h6>Benvenuto, <?php echo htmlspecialchars($currentUser->getUsername()); ?></h6>
                        <p>
                            <span class="badge bg-<?php echo ($planCode === 'free') ? 'secondary' : 'primary'; ?>">
                                Piano <?php echo ($subscriptionPlan) ? htmlspecialchars($subscriptionPlan->getName()) : 'Free'; ?>
                            </span>
                        </p>
                        
                        <div class="list-group mt-3">
                            <a href="<?php echo URL_ROOT; ?>/public/dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            
                            <a href="<?php echo URL_ROOT; ?>/public/my_championships.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-trophy me-2"></i> I miei campionati
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            
                            <a href="<?php echo URL_ROOT; ?>/public/profile.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-user me-2"></i> Profilo
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            
                            <?php if ($planCode === 'free'): ?>
                                <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center bg-light">
                                    <div>
                                        <i class="fas fa-arrow-circle-up me-2"></i> <strong>Upgrade Piano</strong>
                                    </div>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Campionati Attuali -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0">Campionati Attuali</h5>
                    <?php if ($isLoggedIn): ?>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-light active" id="btn-all-championships">Tutti</button>
                            <button type="button" class="btn btn-light" id="btn-my-championships">I miei</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($recentChampionships)): ?>
                        <p class="text-center">Nessun campionato attivo</p>
                    <?php else: ?>
                        <div class="list-group" id="championships-list">
                            <?php foreach ($recentChampionships as $championship): ?>
                                <a href="championship.php?id=<?php echo $championship['id']; ?>" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($isLoggedIn && $championship['is_owner']) ? 'championship-owned' : 'championship-public'; ?>">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($championship['name']); ?></h6>
                                        <div class="small">
                                            <span class="badge bg-<?php echo ($championship['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                                <?php echo $championship['type']; ?>
                                            </span>
                                            
                                            <?php if ($isLoggedIn && $championship['is_owner']): ?>
                                                <span class="ownership-badge ownership-badge-owner ms-1">
                                                    <i class="fas fa-user-edit me-1"></i> Tuo
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!$championship['is_public']): ?>
                                                <span class="ownership-badge ownership-badge-private ms-1">
                                                    <i class="fas fa-lock me-1"></i> Privato
                                                </span>
                                            <?php else: ?>
                                                <span class="ownership-badge ownership-badge-public ms-1">
                                                    <i class="fas fa-globe me-1"></i> Pubblico
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?php echo $championship['team_count']; ?> squadre</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="championships.php" class="btn btn-success">Vedi Tutti i Campionati</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Collegamenti Rapidi -->
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="m-0">Collegamenti Rapidi</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="teams.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i> Tutte le Squadre
                        </a>
                        <a href="standings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-table me-2"></i> Classifiche
                        </a>
                        <a href="matches.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar me-2"></i> Calendario Partite
                        </a>
                        <?php if (User::isAdmin()): ?>
                            <a href="<?php echo URL_ROOT; ?>/admin/index.php" class="list-group-item list-group-item-action bg-light">
                                <i class="fas fa-cog me-2"></i> Pannello Amministrazione
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Script per filtrare i campionati nella sidebar (tutti/miei)
document.addEventListener('DOMContentLoaded', function() {
    const btnAllChampionships = document.getElementById('btn-all-championships');
    const btnMyChampionships = document.getElementById('btn-my-championships');
    const championshipsList = document.getElementById('championships-list');
    
    if (btnAllChampionships && btnMyChampionships && championshipsList) {
        btnAllChampionships.addEventListener('click', function() {
            btnAllChampionships.classList.add('active');
            btnMyChampionships.classList.remove('active');
            
            const items = championshipsList.querySelectorAll('.list-group-item');
            items.forEach(item => {
                item.style.display = 'flex';
            });
        });
        
        btnMyChampionships.addEventListener('click', function() {
            btnMyChampionships.classList.add('active');
            btnAllChampionships.classList.remove('active');
            
            const items = championshipsList.querySelectorAll('.list-group-item');
            items.forEach(item => {
                if (item.classList.contains('championship-owned')) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>