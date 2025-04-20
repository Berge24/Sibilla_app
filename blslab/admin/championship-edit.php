<?php
// admin/championship-edit.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: championships.php?error=invalid');
    exit;
}

$championshipId = intval($_GET['id']);

// Carica il campionato
$championship = Championship::findById($championshipId);
if (!$championship) {
    header('Location: championships.php?error=notfound');
    exit;
}

// Ottieni tutte le stagioni
$seasons = Season::getAll();

// Inizializza le variabili
$error = '';
$name = $championship->getName();
$type = $championship->getType();
$seasonId = $championship->getSeasonId();
$description = $championship->getDescription();
$startDate = $championship->getStartDate();
$endDate = $championship->getEndDate();

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
            // Aggiorna il campionato
            $result = $championship->update([
                'name' => $name,
                'type' => $type,
                'season_id' => $seasonId,
                'description' => $description,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            if ($result) {
                // Redirect con messaggio di successo
                header('Location: championships.php?success=edit');
                exit;
            } else {
                $error = 'Errore durante l\'aggiornamento del campionato';
            }
        }
    }
}

// Ottieni le squadre del campionato
$teamsInChampionship = $championship->getTeams();

// Ottieni tutte le squadre
$allTeams = Team::getAll();

// Includi il template header
$pageTitle = 'Modifica Campionato';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Modifica Campionato</h1>
                <div>
                    <a href="championships.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Torna all'Elenco
                    </a>
                    <a href="../public/championship.php?id=<?php echo $championshipId; ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> Visualizza
                    </a>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Dettagli Campionato</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $championshipId; ?>">
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
                                        <i class="fas fa-save"></i> Aggiorna Campionato
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Bottoni di azione -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Azioni</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <a href="match-add.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-success btn-block w-100">
                                        <i class="fas fa-plus"></i> Aggiungi Partita
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="matches.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-info btn-block w-100">
                                        <i class="fas fa-calendar"></i> Gestisci Partite
                                    </a>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <a href="championship-teams.php?id=<?php echo $championshipId; ?>" class="btn btn-warning btn-block w-100">
                                        <i class="fas fa-users"></i> Gestisci Squadre
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Calcola Classifiche e Probabilità -->
                            <hr>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <form method="post" action="calculate-standings.php">
                                        <input type="hidden" name="championship_id" value="<?php echo $championshipId; ?>">
                                        <button type="submit" class="btn btn-primary btn-block w-100">
                                            <i class="fas fa-table"></i> Calcola Classifiche
                                        </button>
                                    </form>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <form method="post" action="calculate-probabilities.php">
                                        <input type="hidden" name="championship_id" value="<?php echo $championshipId; ?>">
                                        <button type="submit" class="btn btn-secondary btn-block w-100">
                                            <i class="fas fa-chart-line"></i> Calcola Probabilità
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Squadre del Campionato -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Squadre Partecipanti</h6>
                            <a href="championship-teams.php?id=<?php echo $championshipId; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-users"></i> Gestisci
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($teamsInChampionship)): ?>
                                <p class="text-center">Nessuna squadra partecipante</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($teamsInChampionship as $team): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if (!empty($team['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($team['logo']); ?>" alt="Logo" 
                                                        class="team-logo img-thumbnail me-2" style="width: 30px; height: 30px; object-fit: contain;">
                                                <?php endif; ?>
                                                <a href="team-edit.php?id=<?php echo $team['id']; ?>">
                                                    <?php echo htmlspecialchars($team['name']); ?>
                                                </a>
                                            </div>
                                            <form method="post" action="championship-remove-team.php" style="display: inline;">
                                                <input type="hidden" name="championship_id" value="<?php echo $championshipId; ?>">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Rimuovi">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <!-- Form per aggiungere una squadra -->
                            <form method="post" action="championship-add-team.php" class="mt-3">
                                <input type="hidden" name="championship_id" value="<?php echo $championshipId; ?>">
                                <div class="mb-3">
                                    <label for="team_id" class="form-label">Aggiungi Squadra</label>
                                    <select class="form-select" id="team_id" name="team_id" required>
                                        <option value="">Seleziona Squadra</option>
                                        <?php 
                                        // Filtra le squadre che non sono già nel campionato
                                        $teamsNotInChampionship = array_filter($allTeams, function($team) use ($teamsInChampionship) {
                                            foreach ($teamsInChampionship as $teamInChampionship) {
                                                if ($team['id'] == $teamInChampionship['id']) {
                                                    return false;
                                                }
                                            }
                                            return true;
                                        });
                                        
                                        foreach ($teamsNotInChampionship as $team): 
                                        ?>
                                            <option value="<?php echo $team['id']; ?>">
                                                <?php echo htmlspecialchars($team['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary" <?php echo empty($teamsNotInChampionship) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-plus"></i> Aggiungi Squadra
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>