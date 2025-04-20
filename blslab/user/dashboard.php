<?php
// user/dashboard.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia loggato
if (!User::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni l'utente corrente
$user = User::getCurrentUser();

// Verifica che l'utente sia approvato
if (!$user->isApproved() && !User::isAdmin()) {
    header('Location: ../public/pending_approval.php');
    exit;
}

// Ottieni l'abbonamento dell'utente
$subscription = $user->getSubscription();
$subscriptionObj = new Subscription($user->getSubscriptionId());

// Verifica se l'abbonamento è scaduto
$isExpired = $user->isSubscriptionExpired();

// Controlla limiti
$champCount = $user->getChampionshipsCount();
$teamsCount = $user->getTeamsCount();
$champLimit = $subscription['max_championships'];
$teamsLimit = $subscription['max_teams'];
$champLimitReached = $user->hasReachedChampionshipsLimit();
$teamsLimitReached = $user->hasReachedTeamsLimit();

// Includi il template header
$pageTitle = 'Dashboard Utente';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/user_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <h1 class="mb-4">Dashboard Utente</h1>
            
            <?php if ($isExpired): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> Il tuo abbonamento è scaduto. Alcune funzionalità potrebbero essere limitate.
                    <a href="subscription.php" class="ms-2 btn btn-sm btn-outline-warning">Rinnova</a>
                </div>
            <?php endif; ?>
            
            <!-- Informazioni Abbonamento -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Il tuo piano</h6>
                    <a href="subscription.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-arrow-up"></i> Upgrade
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2"><?php echo htmlspecialchars($subscription['name']); ?></h5>
                            
                            <?php if ($subscription['price'] > 0): ?>
                                <p class="mb-2">
                                    <strong>Prezzo:</strong> <?php echo number_format($subscription['price'], 2, ',', '.'); ?> € / mese
                                </p>
                            <?php else: ?>
                                <p class="mb-2">
                                    <strong>Prezzo:</strong> <span class="badge bg-success">Gratuito</span>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($user->getSubscriptionStart()): ?>
                                <p class="mb-2">
                                    <strong>Data attivazione:</strong> <?php echo date('d/m/Y', strtotime($user->getSubscriptionStart())); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($user->getSubscriptionEnd()): ?>
                                <p class="mb-2">
                                    <strong>Data scadenza:</strong> 
                                    <span class="<?php echo $isExpired ? 'text-danger' : ''; ?>">
                                        <?php echo date('d/m/Y', strtotime($user->getSubscriptionEnd())); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            
                            <p class="mb-2">
                                <strong>Pubblicità:</strong> 
                                <?php if ($subscription['has_ads']): ?>
                                    <span class="badge bg-warning">Sì</span>
                                <?php else: ?>
                                    <span class="badge bg-success">No</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2">Caratteristiche</h5>
                            <ul class="list-unstyled">
                                <?php foreach ($subscriptionObj->getFeaturesArray() as $feature): ?>
                                    <li class="mb-1"><i class="fas fa-check text-success me-2"></i> <?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <div class="mt-3">
                                <a href="subscription.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-list"></i> Visualizza tutti i piani
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Utilizzo -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Utilizzo</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Campionati</h5>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-grow-1 me-3">
                                    <div class="progress">
                                        <?php if ($champLimit < 0): // Illimitati ?>
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $champCount; ?> / Illimitati
                                            </div>
                                        <?php else: ?>
                                            <?php 
                                                $champPercentage = ($champLimit > 0) ? min(100, ($champCount / $champLimit) * 100) : 100;
                                                $champBarClass = ($champPercentage >= 90) ? 'bg-danger' : (($champPercentage >= 70) ? 'bg-warning' : 'bg-success');
                                            ?>
                                            <div class="progress-bar <?php echo $champBarClass; ?>" role="progressbar" 
                                                 style="width: <?php echo $champPercentage; ?>%" 
                                                 aria-valuenow="<?php echo $champPercentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $champCount; ?> / <?php echo $champLimit; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($champLimitReached && $champLimit >= 0): ?>
                                    <span class="badge bg-danger">Limite raggiunto</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="../admin/championships.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-trophy me-1"></i> Gestisci Campionati
                                </a>
                                <?php if ($champLimitReached && $champLimit >= 0): ?>
                                    <a href="subscription.php" class="btn btn-warning btn-sm">
                                        <i class="fas fa-arrow-up me-1"></i> Upgrade per più campionati
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Squadre</h5>
                            
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-grow-1 me-3">
                                    <div class="progress">
                                        <?php if ($teamsLimit < 0): // Illimitate ?>
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $teamsCount; ?> / Illimitate
                                            </div>
                                        <?php else: ?>
                                            <?php 
                                                $teamsPercentage = ($teamsLimit > 0) ? min(100, ($teamsCount / $teamsLimit) * 100) : 100;
                                                $teamsBarClass = ($teamsPercentage >= 90) ? 'bg-danger' : (($teamsPercentage >= 70) ? 'bg-warning' : 'bg-success');
                                            ?>
                                            <div class="progress-bar <?php echo $teamsBarClass; ?>" role="progressbar" 
                                                 style="width: <?php echo $teamsPercentage; ?>%" 
                                                 aria-valuenow="<?php echo $teamsPercentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $teamsCount; ?> / <?php echo $teamsLimit; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($teamsLimitReached && $teamsLimit >= 0): ?>
                                    <span class="badge bg-danger">Limite raggiunto</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="../admin/teams.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-users me-1"></i> Gestisci Squadre
                                </a>
                                <?php if ($teamsLimitReached && $teamsLimit >= 0): ?>
                                    <a href="subscription.php" class="btn btn-warning btn-sm">
                                        <i class="fas fa-arrow-up me-1"></i> Upgrade per più squadre
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Azioni Rapide -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Azioni Rapide</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="../admin/season-add.php" class="btn btn-outline-primary w-100 <?php echo $champLimitReached ? 'disabled' : ''; ?>">
                                <i class="fas fa-calendar-plus me-1"></i> Nuova Stagione
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../admin/championship-add.php" class="btn btn-outline-success w-100 <?php echo $champLimitReached ? 'disabled' : ''; ?>">
                                <i class="fas fa-trophy me-1"></i> Nuovo Campionato
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../admin/team-add.php" class="btn btn-outline-info w-100 <?php echo $teamsLimitReached ? 'disabled' : ''; ?>">
                                <i class="fas fa-users me-1"></i> Nuova Squadra
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../admin/match-add.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-futbol me-1"></i> Nuova Partita
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>