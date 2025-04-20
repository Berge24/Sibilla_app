<?php
// user/subscription.php

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

// Ottieni tutti gli abbonamenti
$subscriptions = Subscription::getAll();

// Ottieni l'abbonamento attuale dell'utente
$currentSubscription = $user->getSubscription();

// Gestione messaggi di successo e errore
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Processa l'aggiornamento dell'abbonamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['subscription_id'])) {
    $subscriptionId = filter_input(INPUT_POST, 'subscription_id', FILTER_VALIDATE_INT);
    
    if ($subscriptionId && $subscriptionId != $user->getSubscriptionId()) {
        // Imposta data di inizio a oggi e durata di 30 giorni per demo
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+30 days'));
        
        if ($user->updateSubscription($subscriptionId, $startDate, $endDate)) {
            // Reindirizza con messaggio di successo
            header('Location: subscription.php?success=upgrade');
            exit;
        } else {
            $error = 'upgrade';
        }
    }
}

// Includi il template header
$pageTitle = 'Gestione Abbonamento';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/user_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Abbonamento</h1>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna alla Dashboard
                </a>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php if ($success == 'upgrade'): ?>
                        Abbonamento aggiornato con successo. Il nuovo piano è ora attivo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php if ($error == 'upgrade'): ?>
                        Si è verificato un errore durante l'aggiornamento dell'abbonamento. Riprova.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Current Subscription Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Il tuo abbonamento attuale</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header <?php echo ($currentSubscription['price'] == 0) ? 'bg-secondary' : (($currentSubscription['price'] < 15) ? 'bg-primary' : 'bg-danger'); ?> text-white">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($currentSubscription['name']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <span class="h5">
                                            <?php if ($currentSubscription['price'] == 0): ?>
                                                Gratuito
                                            <?php else: ?>
                                                <?php echo number_format($currentSubscription['price'], 2, ',', '.'); ?> €/mese
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <p class="mb-2">
                                        <strong>Data attivazione:</strong> 
                                        <?php echo $user->getSubscriptionStart() ? date('d/m/Y', strtotime($user->getSubscriptionStart())) : 'N/A'; ?>
                                    </p>
                                    
                                    <?php if ($user->getSubscriptionEnd()): ?>
                                        <p class="mb-2">
                                            <strong>Data scadenza:</strong> 
                                            <span class="<?php echo $user->isSubscriptionExpired() ? 'text-danger' : ''; ?>">
                                                <?php echo date('d/m/Y', strtotime($user->getSubscriptionEnd())); ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <ul class="list-group mb-3">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Campionati
                                            <?php if ($currentSubscription['max_championships'] < 0): ?>
                                                <span class="badge bg-primary rounded-pill">Illimitati</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary rounded-pill"><?php echo $currentSubscription['max_championships']; ?></span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Squadre
                                            <?php if ($currentSubscription['max_teams'] < 0): ?>
                                                <span class="badge bg-primary rounded-pill">Illimitate</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary rounded-pill"><?php echo $currentSubscription['max_teams']; ?></span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Pubblicità
                                            <?php if ($currentSubscription['has_ads']): ?>
                                                <span class="badge bg-warning rounded-pill">Sì</span>
                                            <?php else: ?>
                                                <span class="badge bg-success rounded-pill">No</span>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <h5 class="mb-3">Dettagli e vantaggi</h5>
                            
                            <?php if (!empty($currentSubscription['features'])): ?>
                                <h6>Caratteristiche incluse:</h6>
                                <ul class="list-unstyled mb-4">
                                    <?php foreach (explode("\n", $currentSubscription['features']) as $feature): ?>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <?php echo htmlspecialchars($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Se desideri cambiare il tuo piano, scegli tra le opzioni disponibili di seguito.
                            </div>
                            
                            <?php if ($user->isSubscriptionExpired()): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Il tuo abbonamento è scaduto. Ti consigliamo di rinnovarlo o scegliere un nuovo piano.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Available Plans -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Piani disponibili</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($subscriptions as $plan): 
                            // Salta il piano corrente
                            if ($plan['id'] == $user->getSubscriptionId()) continue;
                            
                            $isUpgrade = $plan['price'] > $currentSubscription['price'];
                            $isDowngrade = $plan['price'] < $currentSubscription['price'];
                        ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header <?php echo ($plan['price'] == 0) ? 'bg-secondary' : (($plan['price'] < 15) ? 'bg-primary' : 'bg-danger'); ?> text-white">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($plan['name']); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="h5">
                                                <?php if ($plan['price'] == 0): ?>
                                                    Gratuito
                                                <?php else: ?>
                                                    <?php echo number_format($plan['price'], 2, ',', '.'); ?> €/mese
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <ul class="list-group mb-3">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Campionati
                                                <?php if ($plan['max_championships'] < 0): ?>
                                                    <span class="badge bg-primary rounded-pill">Illimitati</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $plan['max_championships']; ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Squadre
                                                <?php if ($plan['max_teams'] < 0): ?>
                                                    <span class="badge bg-primary rounded-pill">Illimitate</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $plan['max_teams']; ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Pubblicità
                                                <?php if ($plan['has_ads']): ?>
                                                    <span class="badge bg-warning rounded-pill">Sì</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success rounded-pill">No</span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                        
                                        <?php if (!empty($plan['features'])): ?>
                                            <div class="mb-3">
                                                <h6>Caratteristiche:</h6>
                                                <ul class="list-unstyled">
                                                    <?php foreach (explode("\n", $plan['features']) as $feature): ?>
                                                        <li><i class="fas fa-check text-success me-2"></i> <?php echo htmlspecialchars($feature); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                            <input type="hidden" name="subscription_id" value="<?php echo $plan['id']; ?>">
                                            <div class="d-grid">
                                                <?php if ($isUpgrade): ?>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-arrow-up me-1"></i> Passa a <?php echo htmlspecialchars($plan['name']); ?>
                                                    </button>
                                                <?php elseif ($isDowngrade): ?>
                                                    <button type="submit" class="btn btn-outline-secondary">
                                                        <i class="fas fa-arrow-down me-1"></i> Passa a <?php echo htmlspecialchars($plan['name']); ?>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-exchange-alt me-1"></i> Cambia piano
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Disclaimer -->
            <div class="alert alert-light border">
                <h6><i class="fas fa-info-circle me-2"></i> Informazioni sull'abbonamento</h6>
                <p class="small mb-0">
                    I prezzi sono mensili. Quando cambi piano, il nuovo abbonamento inizia immediatamente. 
                    In una versione reale, ci sarebbe un sistema di pagamento integrato e una gestione 
                    completa del ciclo di vita degli abbonamenti. Contatta l'amministratore per assistenza.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>