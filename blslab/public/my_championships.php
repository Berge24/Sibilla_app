<?php
// public/my_championships.php

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

// Ottieni database
$db = Database::getInstance();

// Parametri di filtro
$seasonId = filter_input(INPUT_GET, 'season_id', FILTER_VALIDATE_INT);
$statusFilter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

// Costruisci la query in base ai filtri
$query = "
    SELECT c.*, s.name as season_name,
           (SELECT COUNT(*) FROM championships_teams WHERE championship_id = c.id) as team_count,
           (SELECT COUNT(*) FROM matches WHERE championship_id = c.id) as match_count
    FROM championships c
    JOIN seasons s ON c.season_id = s.id
    WHERE c.user_id = ?
";

$params = [$userId];

if ($seasonId) {
    $query .= " AND c.season_id = ?";
    $params[] = $seasonId;
}

if ($statusFilter == 'active') {
    $query .= " AND c.end_date >= CURRENT_DATE()";
} elseif ($statusFilter == 'completed') {
    $query .= " AND c.end_date < CURRENT_DATE()";
}

if ($search) {
    $query .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$query .= " ORDER BY c.created_at DESC";

// Esegui la query
$userChampionships = $db->fetchAll($query, $params);
$userChampionshipsCount = count($userChampionships);

// Ottieni tutte le stagioni per i filtri
$seasons = Season::getAll();

// Ottieni contatori per i filtri
$activeChampionshipsCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM championships 
    WHERE user_id = ? AND end_date >= CURRENT_DATE()
", [$userId])['count'];

$completedChampionshipsCount = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM championships 
    WHERE user_id = ? AND end_date < CURRENT_DATE()
", [$userId])['count'];

// Includi il template header
$pageTitle = 'I Miei Campionati';
include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $pageTitle; ?></h1>
        
        <?php 
        // Verifica se l'utente può creare campionati in base all'abbonamento
        $canCreateChampionship = $subscriptionPlan && $subscriptionPlan->getCode() !== 'FREE';
        $isChampLimitReached = $champLimit > 0 && $userChampionshipsCount >= $champLimit;
        ?>
        
        <?php if ($canCreateChampionship && !$isChampLimitReached): ?>
            <a href="<?php echo URL_ROOT; ?>/admin/championship_form.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nuovo Campionato
            </a>
        <?php elseif (!$canCreateChampionship): ?>
            <button class="btn btn-secondary" 
                    data-bs-toggle="modal" 
                    data-bs-target="#feature-upgrade-modal" 
                    data-feature="create-championship">
                <i class="fas fa-lock"></i> Funzione Premium
            </button>
        <?php else: ?>
            <button class="btn btn-warning" 
                    data-bs-toggle="modal" 
                    data-bs-target="#limit-reached-modal">
                <i class="fas fa-exclamation-circle"></i> Limite Raggiunto
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Banner abbonamento e limiti -->
    <div class="subscription-banner mb-4">
        <div class="subscription-status">
            <div>
            <h6 class="mb-0">Campionati: <?php echo $userChampionshipsCount; ?> 
                    <?php if ($champLimit > 0): ?>
                        di <?php echo $champLimit; ?>
                    <?php endif; ?>
                </h6>
                <p class="mb-0">
                    <?php if ($subscriptionPlan): ?>
                        Piano: <strong><?php echo htmlspecialchars($subscriptionPlan->getName()); ?></strong>
                        <span class="subscription-badge subscription-badge-<?php echo strtolower($subscriptionPlan->getCode()); ?>">
                            <?php echo strtoupper($subscriptionPlan->getCode()); ?>
                        </span>
                    <?php else: ?>
                        Piano: <strong>Free</strong>
                        <span class="subscription-badge subscription-badge-free">
                            FREE
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <?php if ($champLimit > 0): ?>
            <div class="usage-indicator mb-0" style="width: 200px;">
                <div class="progress">
                    <div class="progress-bar <?php echo ($userChampionshipsCount >= $champLimit) ? 'bg-danger' : 'bg-success'; ?>" 
                         role="progressbar" 
                         style="width: <?php echo min(100, ($userChampionshipsCount / $champLimit) * 100); ?>%" 
                         aria-valuenow="<?php echo $userChampionshipsCount; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="<?php echo $champLimit; ?>">
                        <?php echo $userChampionshipsCount; ?>/<?php echo $champLimit; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$canCreateChampionship || $isChampLimitReached): ?>
            <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary btn-sm">
                Upgrade Piano
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Filtri -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtra Campionati</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-4">
                    <label for="season_id" class="form-label">Stagione</label>
                    <select class="form-select" id="season_id" name="season_id">
                        <option value="">Tutte le Stagioni</option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?php echo $season['id']; ?>" <?php echo ($seasonId == $season['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($season['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="status" class="form-label">Stato</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tutti</option>
                        <option value="active" <?php echo ($statusFilter == 'active') ? 'selected' : ''; ?>>
                            Attivi (<?php echo $activeChampionshipsCount; ?>)
                        </option>
                        <option value="completed" <?php echo ($statusFilter == 'completed') ? 'selected' : ''; ?>>
                            Completati (<?php echo $completedChampionshipsCount; ?>)
                        </option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="search" class="form-label">Cerca</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Nome o descrizione...">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-primary">Filtra</button>
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Lista Campionati -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 font-weight-bold text-primary">
                Campionati
                <?php if (!empty($search)): ?>
                    <span class="badge bg-info ms-2">Ricerca: <?php echo htmlspecialchars($search); ?></span>
                <?php endif; ?>
                
                <?php if ($statusFilter): ?>
                    <span class="badge bg-secondary ms-2">
                        Stato: <?php echo ($statusFilter == 'active') ? 'Attivi' : 'Completati'; ?>
                    </span>
                <?php endif; ?>
            </h5>
            
            <?php if (!empty($userChampionships)): ?>
                <span class="badge bg-primary rounded-pill">
                    <?php echo count($userChampionships); ?> risultati
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($userChampionships)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-trophy fa-4x text-muted mb-3"></i>
                    <h5>Nessun campionato trovato</h5>
                    <p class="text-muted">Non hai ancora creato alcun campionato o nessun campionato corrisponde ai filtri selezionati.</p>
                    
                    <?php if ($canCreateChampionship && !$isChampLimitReached): ?>
                        <a href="<?php echo URL_ROOT; ?>/admin/championship_form.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus-circle"></i> Crea il tuo primo campionato
                        </a>
                    <?php elseif (!$canCreateChampionship): ?>
                        <div class="mt-3">
                            <p>Per creare campionati è necessario un abbonamento.</p>
                            <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary">
                                <i class="fas fa-arrow-circle-up"></i> Upgrade Piano
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($userChampionships as $championship): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 card-championship position-relative">
                                <?php if (!$championship['is_public']): ?>
                                    <span class="position-absolute top-0 end-0 badge bg-danger m-2">
                                        <i class="fas fa-lock"></i> Privato
                                    </span>
                                <?php else: ?>
                                    <span class="position-absolute top-0 end-0 badge bg-success m-2">
                                        <i class="fas fa-globe"></i> Pubblico
                                    </span>
                                <?php endif; ?>
                                
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($championship['name']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <p class="card-text small text-muted">
                                        <i class="fas fa-calendar-alt"></i> Stagione: <?php echo htmlspecialchars($championship['season_name']); ?><br>
                                        <i class="fas fa-tag"></i> Tipo: <?php echo $championship['type']; ?><br>
                                        <i class="fas fa-flag"></i> Stato: 
                                        <?php if (strtotime($championship['end_date']) >= time()): ?>
                                            <span class="badge bg-success">Attivo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Completato</span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-users"></i> <?php echo $championship['team_count']; ?> squadre
                                        </span>
                                        <span class="badge bg-info">
                                            <i class="fas fa-futbol"></i> <?php echo $championship['match_count']; ?> partite
                                        </span>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="<?php echo URL_ROOT; ?>/public/championship.php?id=<?php echo $championship['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Visualizza
                                        </a>
                                        <a href="<?php echo URL_ROOT; ?>/admin/championship_form.php?id=<?php echo $championship['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-edit"></i> Modifica
                                        </a>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="small">
                                        <strong>Periodo:</strong> 
                                        <?php echo date('d/m/Y', strtotime($championship['start_date'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($championship['end_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal per funzionalità premium -->
<div class="modal fade" id="feature-upgrade-modal" tabindex="-1" aria-labelledby="featureUpgradeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="featureUpgradeModalLabel">Funzionalità Premium</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-lock fa-4x text-warning mb-3"></i>
                <h5>Accesso Limitato</h5>
                <p>La creazione di campionati è disponibile solo con abbonamento a pagamento.</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Effettua l'upgrade a un piano Basic o superiore per sbloccare questa funzionalità.
                </div>
                
                <ul class="list-group list-group-flush mt-3 mb-4">
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check text-success me-2"></i>
                        Crea i tuoi campionati personalizzati
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check text-success me-2"></i>
                        Gestisci squadre e partite
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="fas fa-check text-success me-2"></i>
                        Statistiche e classifiche in tempo reale
                    </li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary">
                    <i class="fas fa-arrow-circle-up"></i> Vedi Piani Disponibili
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal per limite raggiunto -->
<div class="modal fade" id="limit-reached-modal" tabindex="-1" aria-labelledby="limitReachedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="limitReachedModalLabel">Limite Raggiunto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                <h5>Hai raggiunto il limite di campionati!</h5>
                <p>Il tuo piano attuale ti permette di creare fino a <?php echo $champLimit; ?> campionati.</p>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Suggerimento:</strong> Puoi effettuare l'upgrade del tuo piano per aumentare il limite o eliminare alcuni campionati esistenti.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-primary">
                    <i class="fas fa-arrow-circle-up"></i> Upgrade Piano
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>