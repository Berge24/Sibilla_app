<?php
// admin/user_limits.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni ID utente dalla query string
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    header('Location: users.php');
    exit;
}

// Carica l'utente
$user = User::findById($userId);

if (!$user) {
    header('Location: users.php?error=404');
    exit;
}

// Carica i limiti utente personalizzati
$userLimits = new UserLimits($userId);

// Carica i limiti effettivi (combinando abbonamento e override)
$effectiveLimits = UserLimits::getEffectiveLimits($userId);

// Carica le statistiche utente
$userStats = new UserStats($userId);
$userStats->refresh(); // Aggiorna i conteggi

// Carica l'abbonamento attivo
$activeSubscription = $user->getActiveSubscription();
$subscriptionPlan = null;
if ($activeSubscription) {
    $planId = $activeSubscription->getPlanId();
    $subscriptionPlan = new SubscriptionPlan($planId);
}

// Gestione form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $max_championships = filter_input(INPUT_POST, 'max_championships', FILTER_VALIDATE_INT);
    $max_teams = filter_input(INPUT_POST, 'max_teams', FILTER_VALIDATE_INT);
    $has_ads = isset($_POST['has_ads']) ? 1 : 0;
    $has_predictions = isset($_POST['has_predictions']) ? 1 : 0;
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    // Se il valore è -1, significa che si vuole usare il default del piano (null)
    if ($max_championships == -1) $max_championships = null;
    if ($max_teams == -1) $max_teams = null;
    if (isset($_POST['default_ads'])) $has_ads = null;
    if (isset($_POST['default_predictions'])) $has_predictions = null;
    
    // Validazione
    if ($max_championships !== null && $max_championships < -1) {
        $error = 'Il valore dei campionati deve essere maggiore o uguale a -1';
    } elseif ($max_teams !== null && $max_teams < -1) {
        $error = 'Il valore delle squadre deve essere maggiore o uguale a -1';
    } else {
        // Aggiorna i limiti
        $userLimits->setMaxChampionships($max_championships);
        $userLimits->setMaxTeams($max_teams);
        $userLimits->setHasAds($has_ads);
        $userLimits->setHasPredictions($has_predictions);
        $userLimits->setNotes($notes);
        
        if ($userLimits->save()) {
            $success = 'Limiti utente aggiornati con successo.';
            
            // Ricarica i limiti effettivi
            $effectiveLimits = UserLimits::getEffectiveLimits($userId);
            
            // Se i limiti sono completamente vuoti, possiamo rimuovere il record
            if ($max_championships === null && $max_teams === null && 
                $has_ads === null && $has_predictions === null && empty($notes)) {
                $userLimits->remove();
            }
        } else {
            $error = 'Si è verificato un errore durante il salvataggio dei limiti.';
        }
    }
}

// Includi il template header
$pageTitle = 'Gestione Limiti Utente';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Limiti Utente: <?php echo htmlspecialchars($user->getUsername()); ?></h1>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna agli Utenti
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Info Utente -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 bg-primary text-white">
                            <h6 class="m-0 font-weight-bold">Informazioni Utente</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($user->getUsername()); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user->getEmail()); ?></p>
                            <p><strong>Ruolo:</strong> 
                                <?php if ($user->getRole() == 'admin'): ?>
                                    <span class="badge bg-danger">Amministratore</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Utente</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Stato:</strong> 
                                <?php if ($user->isApproved()): ?>
                                    <span class="badge bg-success">Approvato</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">In attesa</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Registrato il:</strong> <?php echo $user->getFormattedCreatedAt(); ?></p>
                            <p><strong>Ultimo accesso:</strong> <?php echo $user->getFormattedLastLogin(); ?></p>
                            
                            <div class="mt-3">
                                <a href="user_edit.php?id=<?php echo $userId; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-user-edit"></i> Modifica Utente
                                </a>
                                <a href="user_subscriptions.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-history"></i> Storico Abbonamenti
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Piano Abbonamento -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 bg-success text-white">
                            <h6 class="m-0 font-weight-bold">Piano Abbonamento</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($subscriptionPlan): ?>
                                <p><strong>Piano:</strong> <?php echo htmlspecialchars($subscriptionPlan->getName()); ?></p>
                                <p><strong>Prezzo:</strong> <?php echo $subscriptionPlan->getFormattedPrice(); ?></p>
                                
                                <?php if ($activeSubscription): ?>
                                    <p><strong>Inizio:</strong> <?php echo $activeSubscription->getFormattedStartDate(); ?></p>
                                    <p><strong>Scadenza:</strong> <?php echo $activeSubscription->getFormattedEndDate(); ?></p>
                                    <p><strong>Giorni rimanenti:</strong> <?php echo $activeSubscription->getFormattedDaysRemaining(); ?></p>
                                <?php endif; ?>
                                
                                <p><strong>Limiti piano:</strong></p>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-trophy me-2"></i> Campionati: <?php echo $subscriptionPlan->getFormattedMaxChampionships(); ?></li>
                                    <li><i class="fas fa-users me-2"></i> Squadre: <?php echo $subscriptionPlan->getFormattedMaxTeams(); ?></li>
                                    <li><i class="fas fa-ad me-2"></i> Pubblicità: <?php echo $subscriptionPlan->getHasAds() ? 'Sì' : 'No'; ?></li>
                                    <li><i class="fas fa-chart-line me-2"></i> Predizioni: <?php echo $subscriptionPlan->getHasPredictions() ? 'Sì' : 'No'; ?></li>
                                </ul>
                                
                                <div class="mt-3">
                                    <a href="user_subscription_edit.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-edit"></i> Modifica Abbonamento
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Nessun abbonamento attivo.
                                </div>
                                
                                <div class="mt-3">
                                    <a href="user_subscription_add.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> Aggiungi Abbonamento
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiche Utilizzo -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 bg-info text-white">
                            <h6 class="m-0 font-weight-bold">Statistiche Utilizzo</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Campionati:</strong> <?php echo $userStats->getChampionshipsCount(); ?></p>
                            <p><strong>Squadre:</strong> <?php echo $userStats->getTeamsCount(); ?></p>
                            <p><strong>Partite:</strong> <?php echo $userStats->getMatchesCount(); ?></p>
                            
                            <p><strong>Utilizzo campionati:</strong></p>
                            <div class="progress mb-2">
                                <?php 
                                $championshipsPercentage = $userStats->getChampionshipsUsagePercentage(); 
                                if ($championshipsPercentage !== null):
                                    $championshipsBarClass = ($championshipsPercentage >= 90) ? 'bg-danger' : (($championshipsPercentage >= 70) ? 'bg-warning' : 'bg-success');
                                ?>
                                    <div class="progress-bar <?php echo $championshipsBarClass; ?>" role="progressbar" 
                                         style="width: <?php echo $championshipsPercentage; ?>%" 
                                         aria-valuenow="<?php echo $championshipsPercentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo $userStats->getChampionshipsCount(); ?> / <?php echo $effectiveLimits['max_championships']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo $userStats->getChampionshipsCount(); ?> / Illimitati
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <p><strong>Utilizzo squadre:</strong></p>
                            <div class="progress">
                                <?php 
                                $teamsPercentage = $userStats->getTeamsUsagePercentage(); 
                                if ($teamsPercentage !== null):
                                    $teamsBarClass = ($teamsPercentage >= 90) ? 'bg-danger' : (($teamsPercentage >= 70) ? 'bg-warning' : 'bg-success');
                                ?>
                                    <div class="progress-bar <?php echo $teamsBarClass; ?>" role="progressbar" 
                                         style="width: <?php echo $teamsPercentage; ?>%" 
                                         aria-valuenow="<?php echo $teamsPercentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo $userStats->getTeamsCount(); ?> / <?php echo $effectiveLimits['max_teams']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo $userStats->getTeamsCount(); ?> / Illimitate
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-3 text-muted small">
                                Ultimo aggiornamento: <?php echo $userStats->getFormattedLastUpdate(); ?>
                            </div>
                            
                            <div class="mt-3">
                                <a href="user_stats_refresh.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-sync"></i> Aggiorna Statistiche
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Limiti Personalizzati -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Limiti Personalizzati</h6>
                    <?php if (UserLimits::hasCustomLimits($userId)): ?>
                        <a href="user_limits_reset.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-undo"></i> Ripristina Default
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (UserLimits::hasCustomLimits($userId)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Questo utente ha limiti personalizzati che sovrascrivono i limiti del piano di abbonamento.
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?user_id=' . $userId); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_championships" class="form-label">Massimo Campionati</label>
                                    <select class="form-select" id="max_championships" name="max_championships">
                                        <option value="-1" <?php echo ($userLimits->getMaxChampionships() === null) ? 'selected' : ''; ?>>Usa Default del Piano</option>
                                        <option value="1" <?php echo ($userLimits->getMaxChampionships() === 1) ? 'selected' : ''; ?>>1</option>
                                        <option value="3" <?php echo ($userLimits->getMaxChampionships() === 3) ? 'selected' : ''; ?>>3</option>
                                        <option value="5" <?php echo ($userLimits->getMaxChampionships() === 5) ? 'selected' : ''; ?>>5</option>
                                        <option value="10" <?php echo ($userLimits->getMaxChampionships() === 10) ? 'selected' : ''; ?>>10</option>
                                        <option value="999" <?php echo ($userLimits->getMaxChampionships() === 999 || $userLimits->getMaxChampionships() === -1) ? 'selected' : ''; ?>>Illimitati</option>
                                        <option value="<?php echo $userLimits->getMaxChampionships(); ?>" 
                                                <?php echo ($userLimits->getMaxChampionships() !== null && 
                                                        $userLimits->getMaxChampionships() !== -1 && 
                                                        $userLimits->getMaxChampionships() !== 999 && 
                                                        !in_array($userLimits->getMaxChampionships(), [1, 3, 5, 10])) ? 'selected' : ''; ?>>
                                            <?php echo $userLimits->getMaxChampionships(); ?>
                                        </option>
                                    </select>
                                    <div class="form-text">Valore corrente: <?php echo $effectiveLimits['max_championships'] < 0 || $effectiveLimits['max_championships'] >= 999 ? 'Illimitati' : $effectiveLimits['max_championships']; ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_teams" class="form-label">Massimo Squadre</label>
                                    <select class="form-select" id="max_teams" name="max_teams">
                                        <option value="-1" <?php echo ($userLimits->getMaxTeams() === null) ? 'selected' : ''; ?>>
                                        <option value="-1" <?php echo ($userLimits->getMaxTeams() === null) ? 'selected' : ''; ?>>Usa Default del Piano</option>
                                        <option value="10" <?php echo ($userLimits->getMaxTeams() === 10) ? 'selected' : ''; ?>>10</option>
                                        <option value="20" <?php echo ($userLimits->getMaxTeams() === 20) ? 'selected' : ''; ?>>20</option>
                                        <option value="30" <?php echo ($userLimits->getMaxTeams() === 30) ? 'selected' : ''; ?>>30</option>
                                        <option value="50" <?php echo ($userLimits->getMaxTeams() === 50) ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo ($userLimits->getMaxTeams() === 100) ? 'selected' : ''; ?>>100</option>
                                        <option value="999" <?php echo ($userLimits->getMaxTeams() === 999 || $userLimits->getMaxTeams() === -1) ? 'selected' : ''; ?>>Illimitate</option>
                                        <option value="<?php echo $userLimits->getMaxTeams(); ?>" 
                                                <?php echo ($userLimits->getMaxTeams() !== null && 
                                                        $userLimits->getMaxTeams() !== -1 && 
                                                        $userLimits->getMaxTeams() !== 999 && 
                                                        !in_array($userLimits->getMaxTeams(), [10, 20, 30, 50, 100])) ? 'selected' : ''; ?>>
                                            <?php echo $userLimits->getMaxTeams(); ?>
                                        </option>
                                    </select>
                                    <div class="form-text">Valore corrente: <?php echo $effectiveLimits['max_teams'] < 0 || $effectiveLimits['max_teams'] >= 999 ? 'Illimitate' : $effectiveLimits['max_teams']; ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label d-block">Pubblicità</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="default_ads" name="default_ads" 
                                              <?php echo ($userLimits->getHasAds() === null) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="default_ads">Usa Default del Piano</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="has_ads" name="has_ads" 
                                              <?php echo ($userLimits->getHasAds() === 1) ? 'checked' : ''; ?> 
                                              <?php echo ($userLimits->getHasAds() === null) ? 'disabled' : ''; ?>>
                                        <label class="form-check-label" for="has_ads">Mostra Pubblicità</label>
                                    </div>
                                    <div class="form-text">Valore corrente: <?php echo $effectiveLimits['has_ads'] ? 'Sì' : 'No'; ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label d-block">Predizioni</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="default_predictions" name="default_predictions" 
                                              <?php echo ($userLimits->getHasPredictions() === null) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="default_predictions">Usa Default del Piano</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="has_predictions" name="has_predictions" 
                                              <?php echo ($userLimits->getHasPredictions() === 1) ? 'checked' : ''; ?> 
                                              <?php echo ($userLimits->getHasPredictions() === null) ? 'disabled' : ''; ?>>
                                        <label class="form-check-label" for="has_predictions">Abilita Predizioni</label>
                                    </div>
                                    <div class="form-text">Valore corrente: <?php echo $effectiveLimits['has_predictions'] ? 'Sì' : 'No'; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Note</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($userLimits->getNotes()); ?></textarea>
                            <div class="form-text">Note interne visibili solo agli amministratori.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salva Limiti
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Informazioni Limiti Effettivi -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Limiti Effettivi</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Caratteristica</th>
                                    <th>Piano Abbonamento</th>
                                    <th>Override Personalizzato</th>
                                    <th>Valore Effettivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Campionati</strong></td>
                                    <td>
                                        <?php if ($subscriptionPlan): ?>
                                            <?php echo $subscriptionPlan->getFormattedMaxChampionships(); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $userLimits->getFormattedMaxChampionships(); ?>
                                    </td>
                                    <td class="fw-bold">
                                        <?php echo $effectiveLimits['max_championships'] < 0 || $effectiveLimits['max_championships'] >= 999 ? 'Illimitati' : $effectiveLimits['max_championships']; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Squadre</strong></td>
                                    <td>
                                        <?php if ($subscriptionPlan): ?>
                                            <?php echo $subscriptionPlan->getFormattedMaxTeams(); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $userLimits->getFormattedMaxTeams(); ?>
                                    </td>
                                    <td class="fw-bold">
                                        <?php echo $effectiveLimits['max_teams'] < 0 || $effectiveLimits['max_teams'] >= 999 ? 'Illimitate' : $effectiveLimits['max_teams']; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Pubblicità</strong></td>
                                    <td>
                                        <?php if ($subscriptionPlan): ?>
                                            <?php echo $subscriptionPlan->getHasAds() ? 'Sì' : 'No'; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $userLimits->getFormattedHasAds(); ?>
                                    </td>
                                    <td class="fw-bold">
                                        <?php echo $effectiveLimits['has_ads'] ? 'Sì' : 'No'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Predizioni</strong></td>
                                    <td>
                                        <?php if ($subscriptionPlan): ?>
                                            <?php echo $subscriptionPlan->getHasPredictions() ? 'Sì' : 'No'; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $userLimits->getFormattedHasPredictions(); ?>
                                    </td>
                                    <td class="fw-bold">
                                        <?php echo $effectiveLimits['has_predictions'] ? 'Sì' : 'No'; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funzione per gestire la visibilità dei campi has_ads in base al checkbox default_ads
    const defaultAdsCheckbox = document.getElementById('default_ads');
    const hasAdsCheckbox = document.getElementById('has_ads');
    
    defaultAdsCheckbox.addEventListener('change', function() {
        hasAdsCheckbox.disabled = this.checked;
    });
    
    // Funzione per gestire la visibilità dei campi has_predictions in base al checkbox default_predictions
    const defaultPredictionsCheckbox = document.getElementById('default_predictions');
    const hasPredictionsCheckbox = document.getElementById('has_predictions');
    
    defaultPredictionsCheckbox.addEventListener('change', function() {
        hasPredictionsCheckbox.disabled = this.checked;
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>