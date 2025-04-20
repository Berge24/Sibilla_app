<?php
// admin/season-add.php

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
$startDate = '';
$endDate = '';

// Processa il form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $startDate = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $endDate = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
    
    // Validazione
    if (empty($name) || empty($startDate) || empty($endDate)) {
        $error = 'Tutti i campi sono obbligatori';
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        $error = 'La data di inizio non può essere successiva alla data di fine';
    } else {
        // Crea la stagione
        $season = new Season();
        $result = $season->create($name, $startDate, $endDate);
        
        if ($result) {
            // Redirect con messaggio di successo
            header('Location: seasons.php?success=add');
            exit;
        } else {
            $error = 'Errore durante la creazione della stagione';
        }
    }
}

// Includi il template header
$pageTitle = 'Aggiungi Stagione';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Aggiungi Stagione</h1>
                <a href="seasons.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna all'Elenco
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dettagli Stagione</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label required">Nome Stagione</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                <div class="form-text">Es. "Stagione 2023-2024", "Campionato Invernale 2023"</div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label required">Data Inizio</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label required">Data Fine</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salva Stagione
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>