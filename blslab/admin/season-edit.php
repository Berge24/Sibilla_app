<?php
// admin/season-edit.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: seasons.php?error=invalid');
    exit;
}

$seasonId = intval($_GET['id']);

// Carica la stagione
$season = Season::findById($seasonId);
if (!$season) {
    header('Location: seasons.php?error=notfound');
    exit;
}

// Ottieni tutti i campionati della stagione
$championships = $season->getChampionships();

// Inizializza le variabili
$error = '';
$name = $season->getName();
$startDate = $season->getStartDate();
$endDate = $season->getEndDate();

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
        // Aggiorna la stagione
        $result = $season->update([
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        if ($result) {
            // Redirect con messaggio di successo
            header('Location: seasons.php?success=edit');
            exit;
        } else {
            $error = 'Errore durante l\'aggiornamento della stagione';
        }
    }
}

// Includi il template header
$pageTitle = 'Modifica Stagione';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Modifica Stagione</h1>
                <a href="seasons.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna all'Elenco
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Dettagli Stagione</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $seasonId; ?>">
                                <div class="mb-3">
                                    <label for="name" class="form-label required">Nome Stagione</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="start_date" class="form-label required">Data Inizio</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="end_date" class="form-label required">Data Fine</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Salva Modifiche
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Campionati in questa Stagione</h6>
                            <a href="championship-add.php?season_id=<?php echo $seasonId; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Aggiungi Campionato
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($championships)): ?>
                                <p class="text-center">Nessun campionato in questa stagione</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nome</th>
                                                <th>Tipo</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($championships as $championship): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($championship['name']); ?></td>
                                                    <td><?php echo $championship['type']; ?></td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="championship-edit.php?id=<?php echo $championship['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="../public/championship.php?id=<?php echo $championship['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Visualizza">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>