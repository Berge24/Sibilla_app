<?php
// admin/team-add.php

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
$logo = '';
$description = '';

// Processa il form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $logo = filter_input(INPUT_POST, 'logo', FILTER_SANITIZE_URL);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    
    // Validazione
    if (empty($name)) {
        $error = 'Il nome della squadra è obbligatorio';
    } else {
        // Crea la squadra
        $team = new Team();
        $result = $team->create($name, $logo, $description);
        
        if ($result) {
            // Redirect con messaggio di successo
            header('Location: teams.php?success=add');
            exit;
        } else {
            $error = 'Errore durante la creazione della squadra. Il nome potrebbe essere già in uso.';
        }
    }
}

// Includi il template header
$pageTitle = 'Aggiungi Squadra';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Aggiungi Squadra</h1>
                <a href="teams.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna all'Elenco
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dettagli Squadra</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label required">Nome Squadra</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="logo" class="form-label">URL Logo</label>
                                <input type="url" class="form-control" id="logo" name="logo" value="<?php echo htmlspecialchars($logo); ?>">
                                <div class="form-text">URL a un'immagine logo (opzionale)</div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salva Squadra
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>