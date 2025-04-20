<?php
// admin/championship-add.php

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
$type = CHAMPIONSHIP_TYPE_CSI;
$seasonId = filter_input(INPUT_GET, 'season_id', FILTER_VALIDATE_INT) ?? '';
$description = '';
$startDate = '';
$endDate = '';

// Ottieni tutte le stagioni
$seasons = Season::getAll();

// Processa il form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $seasonId = filter_input(INPUT_POST, 'season_id', FILTER_VALIDATE_INT);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $startDate = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $endDate = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
    
    // Validazione
    if (empty($name) || empty($type) || empty($seasonId) || empty($startDate) || empty($endDate)) {
        $error = 'I campi Nome, Tipo, Stagione, Data Inizio e Data Fine sono obbligatori';
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        $error = 'La data di inizio non può essere successiva alla data di fine';
    } elseif ($type !== CHAMPIONSHIP_TYPE_CSI && $type !== CHAMPIONSHIP_TYPE_UISP) {
        $error = 'Tipo di campionato non valido';
    } else {
        // Verifica che la stagione esista
        $season = Season::findById($seasonId);
        if (!$season) {
            $error = 'Stagione non valida';
        } else {
            // Crea il campionato
            $championship = new Championship();
            $result = $championship->create($name, $type, $seasonId, $startDate, $endDate, $description);
            
            if ($result) {
                // Redirect con messaggio di successo
                header('Location: championships.php?success=add');
                exit;
            } else {
                $error = 'Errore durante la creazione del campionato';
            }
        }
    }
}

// Includi il template header
$pageTitle = 'Aggiungi Campionato';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Aggiungi Campionato</h1>
                <a href="championships.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna all'Elenco
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dettagli Campionato</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label required">Nome Campionato</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                <div class="form-text">Es. "Serie A", "Campionato Regionale", ecc.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="type" class="form-label required">Tipo Campionato</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="<?php echo CHAMPIONSHIP_TYPE_CSI; ?>" <?php echo ($type == CHAMPIONSHIP_TYPE_CSI) ? 'selected' : ''; ?>>CSI</option>
                                    <option value="<?php echo CHAMPIONSHIP_TYPE_UISP; ?>" <?php echo ($type == CHAMPIONSHIP_TYPE_UISP) ? 'selected' : ''; ?>>UISP</option>
                                </select>
                                <div class="form-text">Determina il sistema di punteggio</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="season_id" class="form-label required">Stagione</label>
                                <select class="form-select" id="season_id" name="season_id" required>
                                    <option value="">Seleziona Stagione</option>
                                    <?php foreach ($seasons as $season): ?>
                                        <option value="<?php echo $season['id']; ?>" <?php echo ($seasonId == $season['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($season['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
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
                                <i class="fas fa-save"></i> Salva Campionato
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>