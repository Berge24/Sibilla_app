<?php
// admin/match-result.php
// Pagina per l'inserimento rapido dei risultati delle partite

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: matches.php?error=invalid');
    exit;
}

$matchId = intval($_GET['id']);

// Carica la partita
$db = Database::getInstance();
$match = $db->fetchOne("SELECT * FROM matches WHERE id = ?", [$matchId]);

if (!$match) {
    header('Location: matches.php?error=notfound');
    exit;
}

// Ottieni il campionato
$championship = $db->fetchOne("SELECT * FROM championships WHERE id = ?", [$match['championship_id']]);
if (!$championship) {
    header('Location: matches.php?error=invalid');
    exit;
}

// Inizializza le variabili
$error = '';
$success = '';
$homeTeamId = $match['home_team_id'];
$awayTeamId = $match['away_team_id'];
$homeScore = $match['home_score'];
$awayScore = $match['away_score'];
$status = $match['status'];

// Ottieni informazioni sulle squadre
$homeTeam = $db->fetchOne("SELECT * FROM teams WHERE id = ?", [$homeTeamId]);
$awayTeam = $db->fetchOne("SELECT * FROM teams WHERE id = ?", [$awayTeamId]);

// Periodi per partite UISP (default vuoti)
$periods = [];
if ($championship['type'] == CHAMPIONSHIP_TYPE_UISP) {
    // Inizializziamo dei periodi vuoti per UISP senza accedere al database
    $periods = [
        ['home_result' => '', 'away_result' => '', 'home_score' => '', 'away_score' => ''],
        ['home_result' => '', 'away_result' => '', 'home_score' => '', 'away_score' => ''],
        ['home_result' => '', 'away_result' => '', 'home_score' => '', 'away_score' => '']
    ];
}

// Processa il form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $homeScore = filter_input(INPUT_POST, 'home_score', FILTER_VALIDATE_INT);
    $awayScore = filter_input(INPUT_POST, 'away_score', FILTER_VALIDATE_INT);
    
    // Validazione di base
    if (!isset($homeScore) || !isset($awayScore)) {
        $error = 'I punteggi sono obbligatori';
    } elseif ($homeScore < 0 || $awayScore < 0) {
        $error = 'I punteggi non possono essere negativi';
    } else {
        // Per i campionati UISP, gestiamo anche i periodi ma senza salvarli nel database
        if ($championship['type'] == CHAMPIONSHIP_TYPE_UISP) {
            $periodsData = isset($_POST['periods']) ? $_POST['periods'] : [];
            
            // Validazione periodi
            $validPeriods = true;
            
            foreach ($periodsData as $idx => $periodData) {
                $homeResult = filter_var($periodData['home_result'] ?? '', FILTER_SANITIZE_STRING);
                $awayResult = filter_var($periodData['away_result'] ?? '', FILTER_SANITIZE_STRING);
                
                // Verifica che i risultati siano validi
                if (empty($homeResult) || empty($awayResult)) {
                    $error = 'I risultati dei periodi sono obbligatori';
                    $validPeriods = false;
                    break;
                }
                
                // Verifica la coerenza dei risultati (win/loss, draw/draw)
                if (($homeResult == PERIOD_RESULT_WIN && $awayResult != PERIOD_RESULT_LOSS) ||
                    ($homeResult == PERIOD_RESULT_LOSS && $awayResult != PERIOD_RESULT_WIN) ||
                    ($homeResult == PERIOD_RESULT_DRAW && $awayResult != PERIOD_RESULT_DRAW)) {
                    $error = 'I risultati dei periodi non sono coerenti';
                    $validPeriods = false;
                    break;
                }
            }
            
            // Se tutti i periodi sono validi, procedi con l'aggiornamento (solo punteggio totale)
            if ($validPeriods) {
                try {
                    // Aggiorna solo i punteggi totali della partita
                    $result = $db->update('matches', [
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'status' => MATCH_STATUS_COMPLETED
                    ], 'id = ?', [$matchId]);
                    
                    if ($result) {
                        // Aggiorna lo stato
                        $status = MATCH_STATUS_COMPLETED;
                        
                        // Aggiorna la classifica
                        $championshipObj = new Championship($championship['id']);
                        $championshipObj->calculateStandings();
                        
                        $success = 'Risultato inserito con successo';
                    } else {
                        $error = 'Errore durante l\'inserimento del risultato';
                    }
                } catch (Exception $e) {
                    $error = 'Errore durante l\'inserimento del risultato: ' . $e->getMessage();
                }
            }
        } else {
            // Per i campionati CSI, è più semplice
            try {
                // Aggiorna i punteggi totali della partita
                $result = $db->update('matches', [
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'status' => MATCH_STATUS_COMPLETED
                ], 'id = ?', [$matchId]);
                
                if ($result) {
                    // Aggiorna lo stato
                    $status = MATCH_STATUS_COMPLETED;
                    
                    // Aggiorna la classifica
                    $championshipObj = new Championship($championship['id']);
                    $championshipObj->calculateStandings();
                    
                    $success = 'Risultato inserito con successo';
                } else {
                    $error = 'Errore durante l\'inserimento del risultato';
                }
            } catch (Exception $e) {
                $error = 'Errore durante l\'inserimento del risultato: ' . $e->getMessage();
            }
        }
    }
}

// Includi il template header
$pageTitle = 'Inserimento Risultato';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Inserimento Risultato</h1>
                <div>
                    <a href="matches.php?status=<?php echo MATCH_STATUS_SCHEDULED; ?>" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Torna alle Partite
                    </a>
                    <a href="../public/match.php?id=<?php echo $matchId; ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> Visualizza
                    </a>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dettagli Partita</h6>
                </div>
                <div class="card-body">
                    <!-- Informazioni Partita -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p>
                                <strong>Campionato:</strong> 
                                <?php echo htmlspecialchars($championship['name']); ?>
                                <span class="badge bg-<?php echo ($championship['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?> ms-2">
                                    <?php echo $championship['type']; ?>
                                </span>
                            </p>
                            <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($match['match_date'])); ?></p>
                            <p><strong>Ora:</strong> <?php echo date('H:i', strtotime($match['match_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="text-end me-3">
                                    <h5 class="mb-0">
                                        <?php echo htmlspecialchars($homeTeam['name']); ?>
                                    </h5>
                                </div>
                                <div class="px-4 py-2 text-center">
                                    <h3 class="mb-0 fw-bold">vs</h3>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0">
                                        <?php echo htmlspecialchars($awayTeam['name']); ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($status == MATCH_STATUS_COMPLETED): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> Questa partita è già stata completata con i seguenti risultati:
                            <strong><?php echo $homeTeam['name']; ?> <?php echo $homeScore; ?> - <?php echo $awayScore; ?> <?php echo $awayTeam['name']; ?></strong>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $matchId; ?>" id="result-form">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Inserisci Risultato</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-4 text-end">
                                                <h5><?php echo htmlspecialchars($homeTeam['name']); ?></h5>
                                            </div>
                                            <div class="col-4">
                                                <div class="input-group">
                                                    <input type="number" class="form-control form-control-lg text-center" 
                                                           id="home_score" name="home_score" 
                                                           value="<?php echo $homeScore !== null ? $homeScore : ''; ?>" 
                                                           min="0" required>
                                                    <span class="input-group-text">-</span>
                                                    <input type="number" class="form-control form-control-lg text-center" 
                                                           id="away_score" name="away_score" 
                                                           value="<?php echo $awayScore !== null ? $awayScore : ''; ?>" 
                                                           min="0" required>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <h5><?php echo htmlspecialchars($awayTeam['name']); ?></h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Periodi UISP -->
                        <?php if ($championship['type'] == CHAMPIONSHIP_TYPE_UISP): ?>
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i> 
                                <strong>Nota:</strong> Per le partite UISP, inserisci solo il punteggio totale. 
                                I dettagli dei periodi saranno calcolati automaticamente.
                            </div>
                            
                            <div id="periods_container" class="mb-4">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">Periodi UISP</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($periods as $index => $period): ?>
                                            <div class="period-item card mb-3">
                                                <div class="card-header">
                                                    <h5 class="mb-0">Periodo <?php echo $index + 1; ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="home_result_<?php echo $index + 1; ?>" class="form-label">
                                                                    Esito <?php echo htmlspecialchars($homeTeam['name']); ?>
                                                                </label>
                                                                <select class="form-select" 
                                                                        id="home_result_<?php echo $index + 1; ?>" 
                                                                        name="periods[<?php echo $index; ?>][home_result]" required>
                                                                    <option value="">Seleziona Esito</option>
                                                                    <option value="<?php echo PERIOD_RESULT_WIN; ?>" 
                                                                            <?php echo (isset($period['home_result']) && $period['home_result'] == PERIOD_RESULT_WIN) ? 'selected' : ''; ?>>
                                                                        Vittoria
                                                                    </option>
                                                                    <option value="<?php echo PERIOD_RESULT_DRAW; ?>" 
                                                                            <?php echo (isset($period['home_result']) && $period['home_result'] == PERIOD_RESULT_DRAW) ? 'selected' : ''; ?>>
                                                                        Pareggio
                                                                    </option>
                                                                    <option value="<?php echo PERIOD_RESULT_LOSS; ?>" 
                                                                            <?php echo (isset($period['home_result']) && $period['home_result'] == PERIOD_RESULT_LOSS) ? 'selected' : ''; ?>>
                                                                        Sconfitta
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="away_result_<?php echo $index + 1; ?>" class="form-label">
                                                                    Esito <?php echo htmlspecialchars($awayTeam['name']); ?>
                                                                </label>
                                                                <select class="form-select" 
                                                                        id="away_result_<?php echo $index + 1; ?>" 
                                                                        name="periods[<?php echo $index; ?>][away_result]" required>
                                                                    <option value="">Seleziona Esito</option>
                                                                    <option value="<?php echo PERIOD_RESULT_WIN; ?>" 
                                                                            <?php echo (isset($period['away_result']) && $period['away_result'] == PERIOD_RESULT_WIN) ? 'selected' : ''; ?>>
                                                                        Vittoria
                                                                    </option>
                                                                    <option value="<?php echo PERIOD_RESULT_DRAW; ?>" 
                                                                            <?php echo (isset($period['away_result']) && $period['away_result'] == PERIOD_RESULT_DRAW) ? 'selected' : ''; ?>>
                                                                        Pareggio
                                                                    </option>
                                                                    <option value="<?php echo PERIOD_RESULT_LOSS; ?>" 
                                                                            <?php echo (isset($period['away_result']) && $period['away_result'] == PERIOD_RESULT_LOSS) ? 'selected' : ''; ?>>
                                                                        Sconfitta
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="home_score_<?php echo $index + 1; ?>" class="form-label">
                                                                    Punti <?php echo htmlspecialchars($homeTeam['name']); ?>
                                                                </label>
                                                                <input type="number" class="form-control" 
                                                                       id="home_score_<?php echo $index + 1; ?>" 
                                                                       name="periods[<?php echo $index; ?>][home_score]" 
                                                                       min="0" 
                                                                       value="<?php echo isset($period['home_score']) ? $period['home_score'] : ''; ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="away_score_<?php echo $index + 1; ?>" class="form-label">
                                                                    Punti <?php echo htmlspecialchars($awayTeam['name']); ?>
                                                                </label>
                                                                <input type="number" class="form-control" 
                                                                       id="away_score_<?php echo $index + 1; ?>" 
                                                                       name="periods[<?php echo $index; ?>][away_score]"
                                                                       min="0" 
                                                                       value="<?php echo isset($period['away_score']) ? $period['away_score'] : ''; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="btn-group">
                                                    <button type="button" id="add_period_btn" class="btn btn-success">
                                                        <i class="fas fa-plus"></i> Aggiungi Periodo
                                                    </button>
                                                    <button type="button" id="remove_period_btn" class="btn btn-danger" <?php echo (count($periods) <= 1) ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-minus"></i> Rimuovi Periodo
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle me-2"></i> Nel campionato UISP, ogni periodo contribuisce al punteggio finale. Assicurati di specificare correttamente l'esito di ogni periodo.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Salva Risultato
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($status == MATCH_STATUS_COMPLETED): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Altre Azioni</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="match-edit.php?id=<?php echo $matchId; ?>" class="btn btn-warning w-100">
                                    <i class="fas fa-edit"></i> Modifica Tutti i Dettagli
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <form method="post" action="calculate-standings.php">
                                    <input type="hidden" name="championship_id" value="<?php echo $championship['id']; ?>">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-table"></i> Aggiorna Classifiche
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript per la sincronizzazione dei risultati dei periodi -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funzione per sincronizzare i risultati dei periodi
    function syncPeriodResults() {
        const periodsContainer = document.getElementById('periods_container');
        if (!periodsContainer) return;
        
        const periodsItems = periodsContainer.querySelectorAll('.period-item');
        
        periodsItems.forEach((item, index) => {
            const homeResultSelect = item.querySelector(`[id^="home_result_"]`);
            const awayResultSelect = item.querySelector(`[id^="away_result_"]`);
            
            if (homeResultSelect && awayResultSelect) {
                homeResultSelect.addEventListener('change', function() {
                    if (this.value === 'win') {
                        awayResultSelect.value = 'loss';
                    } else if (this.value === 'loss') {
                        awayResultSelect.value = 'win';
                    } else {
                        awayResultSelect.value = 'draw';
                    }
                });
                
                awayResultSelect.addEventListener('change', function() {
                    if (this.value === 'win') {
                        homeResultSelect.value = 'loss';
                    } else if (this.value === 'loss') {
                        homeResultSelect.value = 'win';
                    } else {
                        homeResultSelect.value = 'draw';
                    }
                });
            }
        });
    }
    
    // Inizializzazione dei periodi esistenti
    syncPeriodResults();
    
    // Gestione del bottone per aggiungere un periodo
    const addPeriodBtn = document.getElementById('add_period_btn');
    const removePeriodBtn = document.getElementById('remove_period_btn');
    const periodsContainer = document.getElementById('periods_container');
    
    if (addPeriodBtn && periodsContainer) {
        addPeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const periodsItems = periodsContainer.querySelectorAll('.period-item');
            const newPeriodNumber = periodsItems.length + 1;
            
            // Template per un nuovo periodo
            const template = `
            <div class="period-item card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Periodo ${newPeriodNumber}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="home_result_${newPeriodNumber}" class="form-label">
                                    Esito ${homeTeamName}
                                </label>
                                <select class="form-select" 
                                        id="home_result_${newPeriodNumber}" 
                                        name="periods[${newPeriodNumber-1}][home_result]" required>
                                    <option value="">Seleziona Esito</option>
                                    <option value="win">Vittoria</option>
                                    <option value="draw">Pareggio</option>
                                    <option value="loss">Sconfitta</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="away_result_${newPeriodNumber}" class="form-label">
                                    Esito ${awayTeamName}
                                </label>
                                <select class="form-select" 
                                        id="away_result_${newPeriodNumber}" 
                                        name="periods[${newPeriodNumber-1}][away_result]" required>
                                    <option value="">Seleziona Esito</option>
                                    <option value="win">Vittoria</option>
                                    <option value="draw">Pareggio</option>
                                    <option value="loss">Sconfitta</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="home_score_${newPeriodNumber}" class="form-label">
                                    Punti ${homeTeamName}
                                </label>
                                <input type="number" class="form-control" 
                                       id="home_score_${newPeriodNumber}" 
                                       name="periods[${newPeriodNumber-1}][home_score]" 
                                       min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="away_score_${newPeriodNumber}" class="form-label">
                                    Punti ${awayTeamName}
                                </label>
                                <input type="number" class="form-control" 
                                       id="away_score_${newPeriodNumber}" 
                                       name="periods[${newPeriodNumber-1}][away_score]"
                                       min="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
            
            // Sostituisci i nomi delle squadre
            const homeTeamName = "<?php echo addslashes(htmlspecialchars($homeTeam['name'])); ?>";
            const awayTeamName = "<?php echo addslashes(htmlspecialchars($awayTeam['name'])); ?>";
            
            const newPeriodHtml = template
                .replace(/\${homeTeamName}/g, homeTeamName)
                .replace(/\${awayTeamName}/g, awayTeamName)
                .replace(/\${newPeriodNumber}/g, newPeriodNumber);
            
            // Inserisci il nuovo periodo prima dei bottoni
            const btnContainer = periodsContainer.querySelector('.row:last-child');
            btnContainer.insertAdjacentHTML('beforebegin', newPeriodHtml);
            
            // Abilita il bottone di rimozione
            removePeriodBtn.disabled = false;
            
            // Sincronizza i risultati per il nuovo periodo
            syncPeriodResults();
        });
    }
    
    if (removePeriodBtn && periodsContainer) {
        removePeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const periodsItems = periodsContainer.querySelectorAll('.period-item');
            if (periodsItems.length > 1) {
                // Rimuovi l'ultimo periodo
                periodsItems[periodsItems.length - 1].remove();
                
                // Disabilita il bottone se rimane solo un periodo
                if (periodsContainer.querySelectorAll('.period-item').length <= 1) {
                    removePeriodBtn.disabled = true;
                }
            }
        });
    }
    
    // Validazione del form
    const form = document.getElementById('result-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Verifica che i punteggi totali siano inseriti
            const homeScore = document.getElementById('home_score');
            const awayScore = document.getElementById('away_score');
            
            if (!homeScore.value || !awayScore.value) {
                alert('I punteggi totali sono obbligatori');
                isValid = false;
            }
            
            // Se è UISP, verifica che tutti i periodi abbiano un risultato
            const periodsContainer = document.getElementById('periods_container');
            if (periodsContainer) {
                const periodsItems = periodsContainer.querySelectorAll('.period-item');
                
                periodsItems.forEach((item, index) => {
                    const homeResult = item.querySelector(`[id^="home_result_"]`);
                    const awayResult = item.querySelector(`[id^="away_result_"]`);
                    
                    if (!homeResult.value || !awayResult.value) {
                        alert(`Inserisci l'esito per tutti i periodi`);
                        isValid = false;
                    }
                });
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>