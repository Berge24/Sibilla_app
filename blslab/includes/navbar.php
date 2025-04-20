<?php
// includes/navbar.php

// Determina se l'utente è loggato
$isLoggedIn = User::isLoggedIn();
$isAdmin = User::isAdmin();
$currentUser = User::getCurrentUser();
$userName = $isLoggedIn ? $currentUser->getUsername() : '';

// Ottieni l'abbonamento dell'utente
$userSubscription = null;
$subscriptionPlan = null;
$planCode = 'free';

if ($isLoggedIn) {
    $userSubscription = UserSubscription::getByUserId($currentUser->getId());
    if ($userSubscription) {
        $subscriptionPlan = SubscriptionPlan::findById($userSubscription->getPlanId());
        $planCode = $subscriptionPlan ? strtolower($subscriptionPlan->getCode()) : 'free';
    }
}

// Ottieni la stagione corrente
$currentSeason = Season::getCurrentSeason();
$currentSeasonId = $currentSeason ? $currentSeason->getId() : null;
$currentSeasonName = $currentSeason ? $currentSeason->getName() : 'Nessuna stagione attiva';

// Ottieni tutte le stagioni per il dropdown
$seasons = Season::getAll();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo URL_ROOT; ?>/index.php"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo URL_ROOT; ?>/public/index.php">Home</a>
                </li>
                
                <!-- Dropdown Campionati -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navChampionships" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Campionati
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navChampionships">
                        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/public/championships.php">Tutti i Campionati</a></li>
                        <?php if ($isLoggedIn): ?>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/public/my_championships.php">I Miei Campionati</a></li>
                        <?php endif; ?>
                        <?php if ($currentSeasonId): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentSeasonName); ?></h6></li>
                            <?php 
                            // Mostra i campionati della stagione corrente
                            if ($currentSeason) {
                                // Visibilità limitata in base all'utente
                                $query = "
                                    SELECT c.* 
                                    FROM championships c 
                                    WHERE c.season_id = ? AND (c.is_public = 1 OR c.user_id = ?)
                                    ORDER BY c.name
                                ";
                                
                                $db = Database::getInstance();
                                $championships = $db->fetchAll($query, [$currentSeasonId, $isLoggedIn ? $currentUser->getId() : 0]);
                                
                                foreach ($championships as $championship) {
                                    echo '<li><a class="dropdown-item d-flex justify-content-between align-items-center" href="' . URL_ROOT . '/public/championship.php?id=' . $championship['id'] . '">';
                                    echo htmlspecialchars($championship['name']);
                                    
                                    // Mostra distintivo se è un campionato dell'utente
                                    if ($isLoggedIn && $championship['user_id'] == $currentUser->getId()) {
                                        echo '<span class="ownership-badge ownership-badge-owner ms-2"><i class="fas fa-user-edit"></i></span>';
                                    }
                                    
                                    echo '</a></li>';
                                }
                            }
                            ?>
                        <?php endif; ?>
                    </ul>
                </li>
                
                <!-- Dropdown Squadre -->
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo URL_ROOT; ?>/public/teams.php">Squadre</a>
                </li>
                
                <!-- Calendario Partite -->
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo URL_ROOT; ?>/public/matches.php">Calendario</a>
                </li>
                
                <!-- Classifiche -->
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo URL_ROOT; ?>/public/standings.php">Classifiche</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <!-- Dropdown Stagioni -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navSeasons" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo htmlspecialchars($currentSeasonName); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navSeasons">
                        <?php foreach ($seasons as $season): ?>
                            <li>
                                <a class="dropdown-item <?php echo ($currentSeasonId == $season['id']) ? 'active' : ''; ?>" 
                                   href="<?php echo URL_ROOT; ?>/public/season.php?id=<?php echo $season['id']; ?>">
                                    <?php echo htmlspecialchars($season['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                
                <!-- Login/Logout -->
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($userName); ?>
                            <?php if ($subscriptionPlan): ?>
                                <span class="subscription-badge subscription-badge-<?php echo $planCode; ?> ms-1">
                                    <?php echo strtoupper($planCode); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navUser">
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/public/dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/public/profile.php">Profilo</a></li>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/public/subscription_plans.php">
                                Abbonamento 
                                <?php if ($userSubscription && $userSubscription->isExpired()): ?>
                                    <span class="badge bg-danger ms-1">Scaduto</span>
                                <?php endif; ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/admin/index.php">Pannello Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/auth/register.php">Registrati</a>
                    </li>
                <?php endif; ?>
                
                <?php if ($isLoggedIn && $planCode === 'free'): ?>
                    <li class="nav-item">
                        <a class="nav-link btn btn-sm btn-outline-primary mt-1 ms-2" href="<?php echo URL_ROOT; ?>/public/subscription_plans.php">
                            <i class="fas fa-arrow-circle-up me-1"></i> Upgrade
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($isLoggedIn && $userSubscription && $userSubscription->isExpired()): ?>
<!-- Banner abbonamento scaduto -->
<div class="container-fluid bg-danger text-white py-2">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Attenzione:</strong> Il tuo abbonamento è scaduto. Alcune funzionalità potrebbero non essere disponibili.
            </div>
            <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-sm btn-light">Rinnova Ora</a>
        </div>
    </div>
</div>
<?php endif; ?>