<?php
// admin/team-edit.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: teams.php?error=invalid');
    exit;
}

$teamId = intval($_GET['id']);

// Carica la squadra
$team = Team::findById($teamId);
if (!$team) {
    header('Location: teams.php?error=notfound');
    exit;
}

// Ottieni i campionati della squadra
$championships = $team->getChampionships();

// Inizializza le variabili
$error = '';
$name = $team->getName();
$logo = $team->getLogo();
$description = $team->getDescription();

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
        // Aggiorna la squadra
        $result = $team->update([
            'name' => $name,
            'logo' => $logo,
            'description' => $description
        ]);
        
        if ($result) {
            // Redirect con messaggio di successo
            header('Location: teams.php?success=edit');
            exit;
        } else {
            $error = 'Errore durante l\'aggiornamento della squadra';
        }
    }
}

// Includi il template header
$pageTitle = 'Modifica Squadra';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Modifica Squadra</h1>
                <a href="teams.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna all'Elenco
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Dettagli Squadra</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $teamId; ?>">
                                <div class="mb-3">
                                    <label for="name" class="form-label required">Nome Squadra</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="logo" class="form-label">URL Logo</label>
                                    <input type="url" class="form-control" id="logo" name="logo" value="<?php echo htmlspecialchars($logo); ?>">
                                    <?php if (!empty($logo)): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo Anteprima" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Descrizione</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
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
                
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Campionati</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($championships)): ?>
                                <p class="text-center">Questa squadra non partecipa ad alcun campionato</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($championships as $championship): ?>
                                        <a href="championship-edit.php?id=<?php echo $championship['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($championship['name']); ?></h6>
                                                <small><?php echo $championship['type']; ?></small>
                                            </div>
                                            <small><?php echo htmlspecialchars($championship['season_name']); ?></small>
                                        </a>
                                    <?php endforeach; ?>
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