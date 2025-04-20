<?php
// admin/championship-teams.php

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

// Carica il campionato - ASSICURATI DI OTTENERE UN OGGETTO
$championship = Championship::findById($championshipId);
if (!$championship) {
    header('Location: championships.php?error=notfound');
    exit;
}

// Assicurati che championship sia un oggetto
if (!is_object($championship)) {
    // Log dell'errore
    error_log("Error: championship-teams.php - Championship con ID $championshipId non è un oggetto.");
    header('Location: championships.php?error=invalid');
    exit;
}

// Ottieni tutte le squadre del campionato
$teamsInChampionship = $championship->getTeams();

// Ottieni tutte le squadre
$allTeams = Team::getAll();

// Messaggi di stato
$success = '';
$error = '';

// Aggiungi squadra
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $teamId = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    
    if (!$teamId) {
        $error = 'Seleziona una squadra valida';
    } else {
        // Verifica che la squadra non sia già nel campionato
        $teamInChampionship = false;
        foreach ($teamsInChampionship as $team) {
            if ($team['id'] == $teamId) {
                $teamInChampionship = true;
                break;
            }
        }
        
        if ($teamInChampionship) {
            $error = 'La squadra è già presente in questo campionato';
        } else {
            // Aggiungi la squadra al campionato
            if ($championship->addTeam($teamId)) {
                $success = 'Squadra aggiunta con successo';
                // Ricarica le squadre del campionato
                $teamsInChampionship = $championship->getTeams();
            } else {
                $error = 'Errore durante l\'aggiunta della squadra';
            }
        }
    }
}

// Rimuovi squadra
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'remove') {
    $teamId = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    
    if (!$teamId) {
        $error = 'ID squadra non valido';
    } else {
        // Verifica che non ci siano partite per questa squadra
        $db = Database::getInstance();
        $hasMatches = $db->count('matches', 
            'championship_id = ? AND (home_team_id = ? OR away_team_id = ?)',
            [$championshipId, $teamId, $teamId]
        ) > 0;
        
        if ($hasMatches) {
            $error = 'Impossibile rimuovere la squadra perché ci sono partite associate';
        } else {
            // Rimuovi la squadra
            if ($championship->removeTeam($teamId)) {
                $success = 'Squadra rimossa con successo';
                // Ricarica le squadre del campionato
                $teamsInChampionship = $championship->getTeams();
            } else {
                $error = 'Errore durante la rimozione della squadra';
            }
        }
    }
}

// Includi il template header
$pageTitle = 'Gestione Squadre - ' . $championship->getName();
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Squadre - <?php echo htmlspecialchars($championship->getName()); ?></h1>
                <div>
                    <a href="championship-edit.php?id=<?php echo $championshipId; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna al Campionato
                    </a>
                    <a href="../public/championship.php?id=<?php echo $championshipId; ?>" class="btn btn-info ms-2">
                        <i class="fas fa-eye"></i> Visualizza
                    </a>
                </div>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-7">
                    <!-- Squadre del Campionato -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Squadre Partecipanti</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($teamsInChampionship)): ?>
                                <p class="text-center">Nessuna squadra partecipante</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Nome</th>
                                                <th>Logo</th>
                                                <th>Azioni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($teamsInChampionship as $team): ?>
                                                <tr>
                                                    <td><?php echo $team['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($team['name']); ?></td>
                                                    <td>
                                                        <?php if (!empty($team['logo'])): ?>
                                                            <img src="<?php echo htmlspecialchars($team['logo']); ?>" alt="Logo" class="team-logo img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                                        <?php else: ?>
                                                            <span class="text-muted">Nessun logo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="team-edit.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="../public/team.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Visualizza">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $championshipId; ?>" class="d-inline">
                                                                <input type="hidden" name="action" value="remove">
                                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Rimuovi">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
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
                
                <div class="col-md-5">
                    <!-- Aggiungi Squadra -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Aggiungi Squadra</h6>
                        </div>
                        <div class="card-body">
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
                            ?>
                            
                            <?php if (empty($teamsNotInChampionship)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> Tutte le squadre disponibili sono già in questo campionato.
                                </div>
                                <div class="text-center mt-3">
                                    <a href="team-add.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Crea Nuova Squadra
                                    </a>
                                </div>
                            <?php else: ?>
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $championshipId; ?>">
                                    <input type="hidden" name="action" value="add">
                                    
                                    <div class="mb-3">
                                        <label for="team_id" class="form-label required">Seleziona Squadra</label>
                                        <select class="form-select" id="team_id" name="team_id" required>
                                            <option value="">Seleziona Squadra</option>
                                            <?php foreach ($teamsNotInChampionship as $team): ?>
                                                <option value="<?php echo $team['id']; ?>">
                                                    <?php echo htmlspecialchars($team['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Aggiungi Squadra
                                        </button>
                                    </div>
                                </form>
                                
                                <hr>
                                
                                <div class="text-center">
                                    <a href="team-add.php" class="btn btn-outline-primary">
                                        <i class="fas fa-plus"></i> Crea Nuova Squadra
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informazioni Campionato -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Informazioni Campionato</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Nome:</strong> <?php echo htmlspecialchars($championship->getName()); ?></p>
                            <p><strong>Tipo:</strong> <?php echo $championship->getType(); ?></p>
                            <p><strong>Periodo:</strong> <?php echo date('d/m/Y', strtotime($championship->getStartDate())); ?> - <?php echo date('d/m/Y', strtotime($championship->getEndDate())); ?></p>
                            
                            <hr>
                            
                            <div class="d-grid gap-2">
                                <a href="matches.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-info">
                                    <i class="fas fa-calendar"></i> Gestisci Partite
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>