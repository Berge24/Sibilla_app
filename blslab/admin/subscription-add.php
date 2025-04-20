<?php
// admin/subscription-add.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Inizializza le variabili
$error = '';
$name = '';
$price = '';
$max_championships = '';
$max_teams = '';
$has_ads = '';
$features = '';

// Processa il form di creazione
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $max_championships = filter_input(INPUT_POST, 'max_championships', FILTER_VALIDATE_INT);
    $max_teams = filter_input(INPUT_POST, 'max_teams', FILTER_VALIDATE_INT);
    $has_ads = isset($_POST['has_ads']) ? 1 : 0;
    $features = filter_input(INPUT_POST, 'features', FILTER_UNSAFE_RAW);
    
    // Validazione
    if (empty($name)) {
        $error = 'Il nome è obbligatorio';
    } elseif ($price === false) {
        $error = 'Inserisci un prezzo valido';
    } elseif ($max_championships === false || ($max_championships < -1)) {
        $error = 'Inserisci un valore valido per il massimo dei campionati (-1 per illimitati)';
    } elseif ($max_teams === false || ($max_teams < -1)) {
        $error = 'Inserisci un valore valido per il massimo delle squadre (-1 per illimitate)';
    } else {
        // Crea nuovo abbonamento
        $subscription = new Subscription();
        $subscription->setName($name);
        $subscription->setPrice($price);
        $subscription->setMaxChampionships($max_championships);
        $subscription->setMaxTeams($max_teams);
        $subscription->setHasAds($has_ads);
        $subscription->setFeatures($features);
        
        if ($subscription->save()) {
            // Reindirizza con messaggio di successo
            header('Location: subscriptions.php?success=add');
            exit;
        } else {
            $error = 'Si è verificato un errore. Riprova.';
        }
    }
}

// Includi il template header
$pageTitle = 'Aggiungi Abbonamento';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Aggiungi Abbonamento</h1>
                <a href="subscriptions.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna agli Abbonamenti
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dettagli Abbonamento</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label required">Nome</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                    <div class="form-text">Nome del piano di abbonamento (es. Free, Premium, Pro)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="price" class="form-label required">Prezzo</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($price); ?>" step="0.01" min="0" required>
                                        <span class="input-group-text">€</span>
                                    </div>
                                    <div class="form-text">Prezzo mensile dell'abbonamento (0 per gratuito)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_championships" class="form-label required">Massimo Campionati</label>
                                    <input type="number" class="form-control" id="max_championships" name="max_championships" value="<?php echo htmlspecialchars($max_championships); ?>" min="-1" required>
                                    <div class="form-text">Numero massimo di campionati (-1 per illimitati)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_teams" class="form-label required">Massimo Squadre</label>
                                    <input type="number" class="form-control" id="max_teams" name="max_teams" value="<?php echo htmlspecialchars($max_teams); ?>" min="-1" required>
                                    <div class="form-text">Numero massimo di squadre (-1 per illimitate)</div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="has_ads" name="has_ads" <?php echo ($has_ads ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="has_ads">Mostra pubblicità</label>
                                    <div class="form-text">Seleziona se questo abbonamento mostra pubblicità</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="features" class="form-label">Caratteristiche</label>
                                    <textarea class="form-control" id="features" name="features" rows="10"><?php echo htmlspecialchars($features); ?></textarea>
                                    <div class="form-text">Elenco delle caratteristiche, una per riga</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salva Abbonamento
                            </button>
                            <a href="subscriptions.php" class="btn btn-secondary ms-2">Annulla</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>