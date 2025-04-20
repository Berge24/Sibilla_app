<?php
// admin/match-add.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni il campionato specificato (se presente)
$championshipId = filter_input(INPUT_GET, 'championship_id', FILTER_VALIDATE_INT);
$championship = null;

if ($championshipId) {
    $championship = Championship::findById($championshipId);
}

// Inizializza le variabili
$error = '';
$homeTeamId = '';
$awayTeamId = '';
$matchDate = '';
$matchTime = '';
$status = MATCH_STATUS_SCHEDULED;
$notes = '';

// Ottieni tutti i campionati
$championships = Championship::getAll();

// Se non ci sono campionati, reindirizza alla pagina di creazione
if (empty($championships)) {
    header('Location: championship-add.php');
    exit;
}

// Processa il form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $championshipId = filter_input(INPUT_POST, 'championship_id', FILTER_VALIDATE_INT);
    $homeTeamId = filter_input(INPUT_POST, 'home_team_id', FILTER_VALIDATE_INT);
    $awayTeamId = filter_input(INPUT_POST, 'away_team_id', FILTER_VALIDATE_INT);
    $matchDate = filter_input(INPUT_POST, 'match_date', FILTER_SANITIZE_STRING);
    $matchTime = filter_input(INPUT_POST, 'match_time', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    // Validazione
    if (empty($championshipId) || empty($homeTeamId) || empty($awayTeamId) || empty($matchDate) || empty($matchTime)) {
        $error = 'I campi Campionato, Squadra di Casa, Squadra Ospite, Data e Ora sono obbligatori';
    } elseif ($homeTeamId == $awayTeamId) {
        $error = 'La squadra di casa e la squadra ospite devono essere diverse';
    } else {
        // Formatta data e ora
        $matchDateTime = date('Y-m-d H:i:s', strtotime("$matchDate $matchTime"));
        
        // Crea la partita
        $match = new Match();
        $result = $match->create($championshipId, $homeTeamId, $awayTeamId, $matchDateTime, $status, $notes);
        
        if ($result) {
            // Redirect con messaggio di successo
            header('Location: matches.php?championship_id=' . $championshipId . '&success=add');
            exit;
        } else {
            $error = 'Errore durante la creazione della partita. Verifica che le squadre partecipino al campionato selezionato.';
        }
    }
    
    // Se c'è stato un errore, ottieni il campionato per caricare le squadre
    if (!empty($error) && $championshipId) {
        $championship = Championship::findById($championshipId);
    }
}

// Ottieni le squadre del campionato selezionato
$teams = [];
if ($championship) {
    $teams = $championship->getTeams();
}

// Includi il template header
$pageTitle = 'Aggiungi Partita';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Aggiungi Partita</h1>
                <?php if ($championshipId): ?>
                    <a href="matches.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna all'Elenco
                    </a>
                <?php else: ?>
                    <a href="matches.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna all'Elenco
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Dettagli Partita</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="championship_id" class="form-label required">Campionato</label>
                                <select class="form-select" id="championship_id" name="championship_id" required onchange="this.form.submit()">
                                    <option value="">Seleziona Campionato</option>
                                    <?php foreach ($championships as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo ($championshipId == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['type']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                                <select class="form-select" id="home_team_id" name="home_team_id" required <?php echo empty($teams) ? 'disabled' : ''; ?>>
                                    <option value="">Seleziona Squadra di Casa</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo $team['id']; ?>" <?php echo ($homeTeamId == $team['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($teams) && $championshipId): ?>
                                    <div class="form-text text-danger">Nessuna squadra disponibile per questo campionato. <a href="championship-teams.php?id=<?php echo $championshipId; ?>">Aggiungi squadre</a>.</div>
                                <?php elseif (empty($teams)): ?>
                                    <div class="form-text text-danger">Seleziona prima un campionato per visualizzare le squadre disponibili.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="away_team_id" class="form-label required">Squadra Ospite</label>
                                <select class="form-select" id="away_team_id" name="away_team_id" required <?php echo empty($teams) ? 'disabled' : ''; ?>>
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
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label">Note</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Nota: Se lo stato della partita è "Completata", ricordati di impostare il risultato dopo aver creato la partita.
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary" <?php echo (empty($teams) && $championshipId) ? 'disabled' : ''; ?>>
                                <i class="fas fa-save"></i> Salva Partita
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>