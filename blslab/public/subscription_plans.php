<?php
// public/subscription_plans.php

// Includi la configurazione
require_once '../config/config.php';

// Ottieni tutti i piani di abbonamento
$subscriptionPlans = SubscriptionPlan::getAll();

// Ottieni l'utente corrente e il suo abbonamento se è loggato
$currentUser = User::isLoggedIn() ? User::getCurrentUser() : null;
$currentSubscription = null;
$currentPlanId = null;

if ($currentUser) {
    $currentSubscription = UserSubscription::getByUserId($currentUser->getId());
    if ($currentSubscription) {
        $currentPlanId = $currentSubscription->getPlanId();
    }
}

// Processa il form di upgrade/downgrade
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id']) && $currentUser) {
    $newPlanId = intval($_POST['plan_id']);
    
    // Verifica che il piano esista
    $newPlan = SubscriptionPlan::findById($newPlanId);
    
    if ($newPlan) {
        // Se l'utente ha già un abbonamento, aggiornalo
        if ($currentSubscription) {
            $result = $currentSubscription->updatePlan($newPlanId);
        } else {
            // Altrimenti crea un nuovo abbonamento
            $subscription = new UserSubscription();
            $subscription->setUserId($currentUser->getId());
            $subscription->setPlanId($newPlanId);
            $subscription->setStartDate(date('Y-m-d H:i:s'));
            $subscription->setEndDate(date('Y-m-d H:i:s', strtotime('+1 month')));
            $subscription->setStatus('active');
            
            $result = $subscription->save();
        }
        
        if ($result) {
            $message = 'Abbonamento aggiornato con successo!';
            $messageType = 'success';
            // Aggiorna la variabile per mostrare il nuovo piano come corrente
            $currentPlanId = $newPlanId;
        } else {
            $message = 'Si è verificato un errore durante l\'aggiornamento dell\'abbonamento.';
            $messageType = 'danger';
        }
    } else {
        $message = 'Piano di abbonamento non valido.';
        $messageType = 'danger';
    }
}

// Includi il template header
$pageTitle = 'Piani di Abbonamento';
include_once '../includes/header.php';
?>

<!-- Includi CSS per gli abbonamenti -->
<link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/subscription.css">

<div class="container py-4">
    <h1 class="mb-4">Piani di Abbonamento</h1>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
        </div>
    <?php endif; ?>
    
    <!-- Introduzione -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <h5 class="card-title">Scegli il Piano Perfetto per Te</h5>
            <p class="card-text">
                Sia che tu stia gestendo un singolo campionato locale o diversi tornei, abbiamo un piano adatto alle tue esigenze.
                Ogni piano include funzionalità specifiche per aiutarti a gestire al meglio i tuoi campionati.
            </p>
            
            <?php if (!User::isLoggedIn()): ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Nota:</strong> È necessario <a href="<?php echo URL_ROOT; ?>/auth/login.php" class="alert-link">accedere</a> o 
                    <a href="<?php echo URL_ROOT; ?>/auth/register.php" class="alert-link">registrarsi</a> per sottoscrivere un abbonamento.
                </div>
            <?php elseif ($currentSubscription): ?>
                <div class="subscription-banner">
                    <div class="subscription-status">
                        <div>
                            <h6 class="mb-0">Il Tuo Abbonamento Attuale</h6>
                            <?php 
                            $plan = SubscriptionPlan::findById($currentPlanId);
                            $planName = $plan ? $plan->getName() : 'Free';
                            $planCode = $plan ? strtolower($plan->getCode()) : 'free';
                            ?>
                            <p class="mb-0">
                                Piano: <strong><?php echo htmlspecialchars($planName); ?></strong>
                                <span class="subscription-badge subscription-badge-<?php echo $planCode; ?>">
                                    <?php echo strtoupper($planCode); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="subscription-info">
                        <p class="small mb-0">
                            <strong>Stato:</strong> 
                            <?php if ($currentSubscription->isActive()): ?>
                                <span class="text-success">Attivo</span>
                            <?php else: ?>
                                <span class="text-danger">Scaduto</span>
                            <?php endif; ?>
                            &nbsp;|&nbsp;
                            <strong>Scadenza:</strong> <?php echo date('d/m/Y', strtotime($currentSubscription->getEndDate())); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Tabella comparativa dei piani -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">Confronto Piani</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="pricing-table">
                    <thead>
                        <tr>
                            <th class="border-0"></th>
                            <th class="border-0 text-center">
                                <div class="plan-title">Free</div>
                                <div class="plan-price">€0</div>
                                <div class="plan-period">per sempre</div>
                                <?php if ($currentPlanId === null): ?>
                                    <div class="badge bg-success mt-2">Piano Attuale</div>
                                <?php endif; ?>
                            </th>
                            <th class="border-0 text-center">
                                <div class="plan-title">Basic</div>
                                <div class="plan-price">€9,99</div>
                                <div class="plan-period">al mese</div>
                                <?php if ($currentPlanId == 2): // Supponiamo che 2 sia l'ID del piano Basic ?>
                                    <div class="badge bg-success mt-2">Piano Attuale</div>
                                <?php endif; ?>
                            </th>
                            <th class="border-0 text-center">
                            <div class="plan-title">Premium</div>
                                <div class="plan-price">€19,99</div>
                                <div class="plan-period">al mese</div>
                                <?php if ($currentPlanId == 3): // Supponiamo che 3 sia l'ID del piano Premium ?>
                                    <div class="badge bg-success mt-2">Piano Attuale</div>
                                <?php endif; ?>
                            </th>
                            <th class="border-0 text-center">
                                <div class="plan-title">Enterprise</div>
                                <div class="plan-price">€49,99</div>
                                <div class="plan-period">al mese</div>
                                <?php if ($currentPlanId == 4): // Supponiamo che 4 sia l'ID del piano Enterprise ?>
                                    <div class="badge bg-success mt-2">Piano Attuale</div>
                                <?php endif; ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Caratteristiche di base -->
                        <tr>
                            <td colspan="5" class="bg-light text-dark fw-bold">Funzionalità di Base</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Visualizzazione campionati pubblici</td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Statistiche base</td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Account personale</td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        
                        <!-- Limiti -->
                        <tr>
                            <td colspan="5" class="bg-light text-dark fw-bold">Limiti</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Campionati creabili</td>
                            <td class="text-center">0</td>
                            <td class="text-center">3</td>
                            <td class="text-center">10</td>
                            <td class="text-center">Illimitati</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Squadre per campionato</td>
                            <td class="text-center">0</td>
                            <td class="text-center">8</td>
                            <td class="text-center">20</td>
                            <td class="text-center">Illimitate</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Totale squadre gestibili</td>
                            <td class="text-center">0</td>
                            <td class="text-center">10</td>
                            <td class="text-center">40</td>
                            <td class="text-center">Illimitate</td>
                        </tr>
                        
                        <!-- Funzionalità avanzate -->
                        <tr>
                            <td colspan="5" class="bg-light text-dark fw-bold">Funzionalità Avanzate</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Creazione e gestione campionati</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Dashboard personalizzata</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Statistiche avanzate</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Calcolo probabilità</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Campionati privati</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">API Accesso</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Branding personalizzato</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Nessuna pubblicità</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        
                        <!-- Supporto -->
                        <tr>
                            <td colspan="5" class="bg-light text-dark fw-bold">Supporto</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Supporto email</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Supporto prioritario</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Supporto telefonico</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        
                        <!-- Azione -->
                        <tr>
                            <td></td>
                            <td class="text-center">
                                <button class="btn btn-secondary" disabled>Piano Attuale</button>
                            </td>
                            <td class="text-center">
                                <?php if (User::isLoggedIn()): ?>
                                    <?php if ($currentPlanId == 2): ?>
                                        <button class="btn btn-success" disabled>Piano Attuale</button>
                                    <?php else: ?>
                                        <button class="btn btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#selectPlanModal" 
                                                data-plan-id="2" 
                                                data-plan-name="Basic">
                                            <?php echo ($currentPlanId !== null && $currentPlanId < 2) ? 'Upgrade' : 'Seleziona'; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?php echo URL_ROOT; ?>/auth/login.php" class="btn btn-primary">Accedi per Abbonarti</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (User::isLoggedIn()): ?>
                                    <?php if ($currentPlanId == 3): ?>
                                        <button class="btn btn-success" disabled>Piano Attuale</button>
                                    <?php else: ?>
                                        <button class="btn btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#selectPlanModal" 
                                                data-plan-id="3" 
                                                data-plan-name="Premium">
                                            <?php echo ($currentPlanId !== null && $currentPlanId < 3) ? 'Upgrade' : 'Seleziona'; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?php echo URL_ROOT; ?>/auth/login.php" class="btn btn-primary">Accedi per Abbonarti</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (User::isLoggedIn()): ?>
                                    <?php if ($currentPlanId == 4): ?>
                                        <button class="btn btn-success" disabled>Piano Attuale</button>
                                    <?php else: ?>
                                        <button class="btn btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#selectPlanModal" 
                                                data-plan-id="4" 
                                                data-plan-name="Enterprise">
                                            <?php echo ($currentPlanId !== null && $currentPlanId < 4) ? 'Upgrade' : 'Seleziona'; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?php echo URL_ROOT; ?>/auth/login.php" class="btn btn-primary">Accedi per Abbonarti</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Domande frequenti -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">Domande Frequenti</h5>
        </div>
        <div class="card-body">
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqOne">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                            Come posso cambiare il mio piano di abbonamento?
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="faqOne" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Puoi facilmente passare a un piano superiore in qualsiasi momento. Seleziona il piano desiderato nella tabella sopra e conferma l'upgrade. Se desideri effettuare un downgrade, questo sarà applicato alla fine del periodo di fatturazione corrente.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqTwo">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            Cosa succede se raggiungo il limite del mio piano?
                        </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="faqTwo" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Se raggiungi il limite di campionati, squadre o altre risorse del tuo piano, non potrai crearne di nuovi finché non effettui l'upgrade a un piano superiore o non rimuovi alcune risorse esistenti per liberare spazio.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqThree">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            Posso annullare il mio abbonamento in qualsiasi momento?
                        </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="faqThree" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Sì, puoi annullare il tuo abbonamento in qualsiasi momento. Il tuo abbonamento rimarrà attivo fino alla fine del periodo di fatturazione corrente. Dopo tale data, il tuo account verrà automaticamente convertito in un account gratuito.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header" id="faqFour">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                            Come funzionano i campionati privati?
                        </button>
                    </h2>
                    <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="faqFour" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            I campionati privati sono visibili solo a te e agli utenti che inviti. Questa funzionalità è disponibile nei piani Premium ed Enterprise e ti permette di gestire campionati riservati senza renderli visibili al pubblico.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal per selezionare piano -->
<div class="modal fade" id="selectPlanModal" tabindex="-1" aria-labelledby="selectPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="selectPlanModalLabel">Conferma Abbonamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="plan_id" id="selected_plan_id" value="">
                    
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>Stai per attivare il piano <span id="selected_plan_name">Basic</span></h5>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> Il cambio di piano sarà effettivo immediatamente. Ti verrà addebitato l'importo corrispondente.
                    </div>
                    
                    <div id="downgrade_warning" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attenzione:</strong> Effettuando il downgrade potresti perdere l'accesso ad alcune funzionalità e dati se hai superato i limiti del nuovo piano.
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="terms_checkbox" required>
                        <label class="form-check-label" for="terms_checkbox">
                            Ho letto e accetto i <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Termini e Condizioni</a>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Conferma</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Termini e Condizioni -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Termini e Condizioni</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                <h6>1. Abbonamenti e Pagamenti</h6>
                <p>
                    1.1. I pagamenti per gli abbonamenti sono addebitati mensilmente in anticipo.<br>
                    1.2. Gli abbonamenti si rinnovano automaticamente alla fine di ogni periodo di fatturazione.<br>
                    1.3. Puoi annullare il tuo abbonamento in qualsiasi momento. L'annullamento sarà effettivo alla fine del periodo di fatturazione corrente.
                </p>
                
                <h6>2. Limiti di Utilizzo</h6>
                <p>
                    2.1. Ogni piano di abbonamento ha limiti specifici per il numero di campionati, squadre e altre risorse.<br>
                    2.2. Se superi i limiti del tuo piano, non potrai creare nuove risorse fino a quando non effettui l'upgrade o non rimuovi risorse esistenti.<br>
                    2.3. In caso di downgrade, se hai più risorse di quelle consentite dal nuovo piano, potresti perdere l'accesso ad alcune di esse.
                </p>
                
                <h6>3. Campionati Privati</h6>
                <p>
                    3.1. I campionati privati sono disponibili solo nei piani Premium ed Enterprise.<br>
                    3.2. I campionati privati sono visibili solo a te e agli utenti che inviti.
                </p>
                
                <h6>4. Supporto</h6>
                <p>
                    4.1. Il supporto via email è disponibile per tutti gli abbonati a pagamento.<br>
                    4.2. Il supporto prioritario è disponibile solo per i piani Premium ed Enterprise.<br>
                    4.3. Il supporto telefonico è disponibile solo per il piano Enterprise.
                </p>
                
                <h6>5. Modifiche ai Termini e ai Piani</h6>
                <p>
                    5.1. Ci riserviamo il diritto di modificare i termini e i piani in qualsiasi momento.<br>
                    5.2. Verrai informato di eventuali modifiche via email almeno 30 giorni prima della loro entrata in vigore.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Script JavaScript per gestire i modali -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestione del modal di selezione piano
    const selectPlanModal = document.getElementById('selectPlanModal');
    if (selectPlanModal) {
        selectPlanModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const planId = button.getAttribute('data-plan-id');
            const planName = button.getAttribute('data-plan-name');
            
            const selectedPlanIdInput = document.getElementById('selected_plan_id');
            const selectedPlanNameSpan = document.getElementById('selected_plan_name');
            const downgradeWarning = document.getElementById('downgrade_warning');
            
            if (selectedPlanIdInput) selectedPlanIdInput.value = planId;
            if (selectedPlanNameSpan) selectedPlanNameSpan.textContent = planName;
            
            // Mostra l'avviso di downgrade se necessario
            const currentPlanId = <?php echo $currentPlanId ? $currentPlanId : 0; ?>;
            if (downgradeWarning && currentPlanId > 0 && parseInt(planId) < currentPlanId) {
                downgradeWarning.classList.remove('d-none');
            } else if (downgradeWarning) {
                downgradeWarning.classList.add('d-none');
            }
        });
    }
});
</script>

<!-- Includi JS per gli abbonamenti -->
<script src="<?php echo URL_ROOT; ?>/assets/js/subscription.js"></script>

<?php include_once '../includes/footer.php'; ?>