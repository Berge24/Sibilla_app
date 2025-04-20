<?php
// admin/match-edit.php

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
$match = Match::findById($matchId);
if (!$match) {
    header('Location: matches.php?error=notfound');
    exit;
}

// Ottieni il campionato
$championship = Championship::findById($match->getChampionshipId());
if (!$championship) {
    header('Location: matches.php?error=invalid');
    exit;
}

// Inizializza le variabili
$error = '';
$success = '';
$championshipId = $match->getChampionshipId();
$homeTeamId = $match->getHomeTeamId();
$awayTeamId = $match->getAwayTeamId();
$matchDate = date('Y-m-d', strtotime($match->getMatchDate()));
$matchTime = date('H:i', strtotime($match->getMatchDate()));
$status = $match->getStatus();
$homeScore = $match->getHomeScore();
$awayScore = $match->getAwayScore();
$notes = $match->getNotes();

// Ottieni le squadre del campionato
$teams = $championship->getTeams();

// Ottieni i periodi per partite UISP
$periods = [];
if ($championship->getType() == CHAMPIONSHIP_TYPE_UISP) {
    $periods = $match->getPeriods();
}

// Processa il form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $homeTeamId = filter_input(INPUT_POST, 'home_team_id', FILTER_VALIDATE_INT);
    $awayTeamId = filter_input(INPUT_POST, 'away_team_id', FILTER_VALIDATE_INT);
    $matchDate = filter_input(INPUT_POST, 'match_date', FILTER_SANITIZE_STRING);
    $matchTime = filter_input(INPUT_POST, 'match_time', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $homeScore = filter_input(INPUT_POST, 'home_score', FILTER_VALIDATE_INT);
    $awayScore = filter_input(INPUT_POST, 'away_score', FILTER_VALIDATE_INT);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    // Validazione
    if (empty($homeTeamId) || empty($awayTeamId) || empty($matchDate) || empty($matchTime)) {
        $error = 'I campi Squadra di Casa, Squadra Ospite, Data e Ora sono obbligatori';
    } elseif ($homeTeamId == $awayTeamId) {
        $error = 'La squadra di casa e la squadra ospite devono essere diverse';
    } elseif ($status == MATCH_STATUS_COMPLETED && (!isset($homeScore) || !isset($awayScore))) {
        $error = 'Per una partita completata, i punteggi sono obbligatori';
    } else {
        // Formatta data e ora
        $matchDateTime = date('Y-m-d H:i:s', strtotime("$matchDate $matchTime"));
        
        // Aggiorna i dati della partita
        $updateData = [
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'match_date' => $matchDateTime,
            'status' => $status,
            'notes' => $notes
        ];
        
        // Aggiungi i punteggi se lo stato è completato
        if ($status == MATCH_STATUS_COMPLETED) {
            $updateData['home_score'] = $homeScore;
            $updateData['away_score'] = $awayScore;
            
            // Per i campionati UISP, gestiamo anche i periodi
            if ($championship->getType() == CHAMPIONSHIP_TYPE_UISP) {
                $periodsData = isset($_POST['periods']) ? $_POST['periods'] : [];
                
                // Processa i dati dei periodi
                $processedPeriods = [];
                foreach ($periodsData as $idx => $periodData) {
                    $homeResult = filter_var($periodData['home_result'], FILTER_SANITIZE_STRING);
                    $awayResult = filter_var($periodData['away_result'], FILTER_SANITIZE_STRING);
                    $homeScorePeriod = isset($periodData['home_score']) ? filter_var($periodData['home_score'], FILTER_VALIDATE_INT) : null;
                    $awayScorePeriod = isset($periodData['away_score']) ? filter_var($periodData['away_score'], FILTER_VALIDATE_INT) : null;
                    
                    // Verifica che i risultati siano validi
                    if (empty($homeResult) || empty($awayResult)) {
                        $error = 'I risultati dei periodi sono obbligatori';
                        break;
                    }
                    
                    // Verifica la coerenza dei risultati
                    if (($homeResult == PERIOD_RESULT_WIN && $awayResult != PERIOD_RESULT_LOSS) ||
                        ($homeResult == PERIOD_RESULT_LOSS && $awayResult != PERIOD_RESULT_WIN) ||
                        ($homeResult == PERIOD_RESULT_DRAW && $awayResult != PERIOD_RESULT_DRAW)) {
                        $error = 'I risultati dei periodi non sono coerenti';
                        break;
                    }
                    
                    $processedPeriods[] = [
                        'home_result' => $homeResult,
                        'away_result' => $awayResult,
                        'home_score' => $homeScorePeriod,
                        'away_score' => $awayScorePeriod
                    ];
                }
                
                // Se non ci sono errori, aggiorna la partita UISP
                if (empty($error)) {
                    if ($match->setUISPResult($processedPeriods, $homeScore, $awayScore)) {
                        $success = 'Partita aggiornata con successo';
                        // Ricarica i periodi
                        $periods = $match->getPeriods();
                    } else {
                        $error = 'Errore durante l\'aggiornamento della partita UISP';
                    }
                }
            } else {
                // Per i campionati CSI, aggiorna il risultato
                if ($match->update($updateData)) {
                    // Aggiorna la classifica
                    $championship->calculateStandings();
                    $success = 'Partita aggiornata con successo';
                } else {
                    $error = 'Errore durante l\'aggiornamento del risultato';
                }
            }
        } else {
            // Se lo stato non è completato, aggiorna solo i dati della partita
            if ($match->update($updateData)) {
                $success = 'Partita aggiornata con successo';
            } else {
                $error = 'Errore durante l\'aggiornamento della partita';
            }
        }
        
        // Se tutto è andato a buon fine, ricarica la partita per avere i dati aggiornati
        if (empty($error)) {
            $match = Match::findById($matchId);
            $homeTeamId = $match->getHomeTeamId();
            $awayTeamId = $match->getAwayTeamId();
            $matchDate = date('Y-m-d', strtotime($match->getMatchDate()));
            $matchTime = date('H:i', strtotime($match->getMatchDate()));
            $status = $match->getStatus();
            $homeScore = $match->getHomeScore();
            $awayScore = $match->getAwayScore();
            $notes = $match->getNotes();
        }
    }
}

// Includi il template header
$pageTitle = 'Modifica Partita';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Modifica Partita</h1>
                <div>
                    <a href="matches.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Torna all'Elenco
                    </a>
                    <a href="../public/match.php?id=<?php echo $matchId; ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> Visualizza
                    </a>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dettagli Partita</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $matchId; ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="championship_info" class="form-label">Campionato</label>
                                <input type="text" class="form-control" id="championship_info" value="<?php echo htmlspecialchars($championship->getName()) . ' (' . $championship->getType() . ')'; ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label required">Stato</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="<?php echo MATCH_STATUS_SCHEDULED; ?>" <?php echo ($status == MATCH_STATUS_SCHEDULED) ? 'selected' : ''; ?>>Programmata</option>
                                    <option value="<?php echo MATCH_STATUS_COMPLETED; ?>" <?php echo ($status == MATCH_STATUS_COMPLETED) ? 'selected' : ''; ?>>Completata</option>
                                    <option value="<?php echo MATCH_STATUS_POSTPONED; ?>" <?php echo ($status == MATCH_STATUS_POSTPONED) ? 'selected' : ''; ?>>Rinviata</option>
                                    <option value="<?php echo MATCH_STATUS_CANCELLED; ?>" <?php echo ($status == MATCH_STATUS_CANCELLED) ? 'selected' : ''; ?>>Annullata</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="home_team_id" class="form-label required">Squadra di Casa</label>
                                <select class="form-select" id="home_team_id" name="home_team_id" required>
                                    <option value="">Seleziona Squadra di Casa</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo $team['id']; ?>" <?php echo ($homeTeamId == $team['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="away_team_id" class="form-label required">Squadra Ospite</label>
                                <select class="form-select" id="away_team_id" name="away_team_id" required>
                                    <option value="">Seleziona Squadra Ospite</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo $team['id']; ?>" <?php echo ($awayTeamId == $team['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="match_date" class="form-label required">Data</label>
                                <input type="date" class="form-control" id="match_date" name="match_date" value="<?php echo htmlspecialchars($matchDate); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="match_time" class="form-label required">Ora</label>
                                <input type="time" class="form-control" id="match_time" name="match_time" value="<?php echo htmlspecialchars($matchTime); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="home_score" class="form-label">Punteggio Casa</label>
                                <input type="number" class="form-control" id="home_score" name="home_score" min="0" value="<?php echo $homeScore !== null ? $homeScore : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="away_score" class="form-label">Punteggio Ospite</label>
                                <input type="number" class="form-control" id="away_score" name="away_score" min="0" value="<?php echo $awayScore !== null ? $awayScore : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Periodi UISP -->
                        <?php if ($championship->getType() == CHAMPIONSHIP_TYPE_UISP): ?>
                            <div id="periods_container" class="mb-3">
                                <label class="form-label">Periodi UISP</label>
                                
                                <?php 
                                // Se non ci sono periodi, creiamo 3 periodi di default
                                if (empty($periods)) {
                                    $periods = [
                                        ['home_result' => '', 'away_result' => '', 'home_score' => '', 'away_score' => ''],
                                        ['home_result' => '', 'away_result' => '', 'home_score' => '', 'away_score' => ''],
                                        ['home_result' => '', 'away_result' => '', 'home_score' => '', 'away_score' => '']
                                    ];
                                }
                                
                                foreach ($periods as $index => $period): 
                                ?>
                                    <div class="period-item card mb-3">
                                        <div class="card-header">
                                            <h5 class="mb-0">Periodo <?php echo $index + 1; ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="home_result_<?php echo $index + 1; ?>">Esito squadra casa</label>
                                                        <select class="form-control" id="home_result_<?php echo $index + 1; ?>" name="periods[<?php echo $index; ?>][home_result]" required>
                                                            <option value="">Seleziona Esito</option>
                                                            <option value="<?php echo PERIOD_RESULT_WIN; ?>" <?php echo ($period['home_result'] == PERIOD_RESULT_WIN) ? 'selected' : ''; ?>>Vittoria</option>
                                                            <option value="<?php echo PERIOD_RESULT_DRAW; ?>" <?php echo ($period['home_result'] == PERIOD_RESULT_DRAW) ? 'selected' : ''; ?>>Pareggio</option>
                                                            <option value="<?php echo PERIOD_RESULT_LOSS; ?>" <?php echo ($period['home_result'] == PERIOD_RESULT_LOSS) ? 'selected' : ''; ?>>Sconfitta</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="away_result_<?php echo $index + 1; ?>">Esito squadra ospite</label>
                                                        <select class="form-control" id="away_result_<?php echo $index + 1; ?>" name="periods[<?php echo $index; ?>][away_result]" required>
                                                            <option value="">Seleziona Esito</option>
                                                            <option value="<?php echo PERIOD_RESULT_WIN; ?>" <?php echo ($period['away_result'] == PERIOD_RESULT_WIN) ? 'selected' : ''; ?>>Vittoria</option>
                                                            <option value="<?php echo PERIOD_RESULT_DRAW; ?>" <?php echo ($period['away_result'] == PERIOD_RESULT_DRAW) ? 'selected' : ''; ?>>Pareggio</option>
                                                            <option value="<?php echo PERIOD_RESULT_LOSS; ?>" <?php echo ($period['away_result'] == PERIOD_RESULT_LOSS) ? 'selected' : ''; ?>>Sconfitta</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="home_score_<?php echo $index + 1; ?>">Punti squadra casa</label>
                                                        <input type="number" class="form-control" id="home_score_<?php echo $index + 1; ?>" name="periods[<?php echo $index; ?>][home_score]" min="0" value="<?php echo isset($period['home_score']) ? $period['home_score'] : ''; ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="away_score_<?php echo $index + 1; ?>">Punti squadra ospite</label>
                                                        <input type="number" class="form-control" id="away_score_<?php echo $index + 1; ?>" name="periods[<?php echo $index; ?>][away_score]" min="0" value="<?php echo isset($period['away_score']) ? $period['away_score'] : ''; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="btn-group mt-2">
                                    <button type="button" id="add_period_btn" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Aggiungi Periodo
                                    </button>
                                    <button type="button" id="remove_period_btn" class="btn btn-danger" <?php echo (count($periods) <= 1) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-minus"></i> Rimuovi Periodo
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label">Note</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Aggiorna Partita
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>