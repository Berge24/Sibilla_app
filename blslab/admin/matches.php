<?php
// admin/matches.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni parametri di filtro
$championshipId = filter_input(INPUT_GET, 'championship_id', FILTER_VALIDATE_INT);
$teamId = filter_input(INPUT_GET, 'team_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$seasonId = filter_input(INPUT_GET, 'season_id', FILTER_VALIDATE_INT);

// Stabilisci titolo della pagina in base ai filtri
$pageTitle = 'Gestione Partite';
$filterDescription = '';

// Ottieni database
$db = Database::getInstance();

// Costruisci la query in base ai filtri
$query = "
    SELECT m.*, 
           c.name as championship_name, c.type as championship_type,
           s.name as season_name,
           home.name as home_team_name, 
           away.name as away_team_name
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN seasons s ON c.season_id = s.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    WHERE 1=1
";

$params = [];

if ($championshipId) {
    $query .= " AND m.championship_id = ?";
    $params[] = $championshipId;
    
    // Ottieni info campionato per il titolo
    $championship = Championship::findById($championshipId);
    if ($championship) {
        $pageTitle = 'Partite del Campionato ' . $championship->getName();
        $filterDescription = 'Campionato: ' . $championship->getName();
    }
}

if ($teamId) {
    $query .= " AND (m.home_team_id = ? OR m.away_team_id = ?)";
    $params[] = $teamId;
    $params[] = $teamId;
    
    // Ottieni info squadra per il titolo
    $team = Team::findById($teamId);
    if ($team) {
        $pageTitle = 'Partite di ' . $team->getName();
        $filterDescription = 'Squadra: ' . $team->getName();
    }
}

if ($status) {
    $query .= " AND m.status = ?";
    $params[] = $status;
    
    // Aggiorna titolo in base allo stato
    if ($status == MATCH_STATUS_COMPLETED) {
        $pageTitle = 'Risultati ' . ($filterDescription ? 'di ' . $filterDescription : '');
        $filterDescription = ($filterDescription ? $filterDescription . ', ' : '') . 'Stato: Completate';
    } elseif ($status == MATCH_STATUS_SCHEDULED) {
        $pageTitle = 'Prossime Partite ' . ($filterDescription ? 'di ' . $filterDescription : '');
        $filterDescription = ($filterDescription ? $filterDescription . ', ' : '') . 'Stato: Programmate';
    }
}

if ($seasonId) {
    $query .= " AND c.season_id = ?";
    $params[] = $seasonId;
    
    // Ottieni info stagione per il titolo
    $season = Season::findById($seasonId);
    if ($season) {
        $filterDescription = ($filterDescription ? $filterDescription . ', ' : '') . 'Stagione: ' . $season->getName();
    }
}

// Ordina per data (prima le più recenti o future)
$query .= " ORDER BY m.match_date";
if ($status == MATCH_STATUS_COMPLETED) {
    $query .= " DESC";
}

// Esegui la query
$matches = $db->fetchAll($query, $params);

// Organizza le partite per data
$matchesByDate = [];
foreach ($matches as $match) {
    $date = date('Y-m-d', strtotime($match['match_date']));
    $matchesByDate[$date][] = $match;
}

// Ottieni tutti i campionati e stagioni per i filtri
$championships = Championship::getAll();
$seasons = Season::getAll();
$allTeams = Team::getAll();

// Gestione messaggi di successo e errore
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Includi il template header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?php echo $pageTitle; ?></h1>
                <div>
                    <?php if ($championshipId): ?>
                        <a href="championship-edit.php?id=<?php echo $championshipId; ?>" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Torna al Campionato
                        </a>
                    <?php endif; ?>
                    
                    <a href="match-add.php<?php echo $championshipId ? '?championship_id=' . $championshipId : ''; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuova Partita
                    </a>
                </div>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php if ($success == 'add'): ?>
                        Partita aggiunta con successo.
                    <?php elseif ($success == 'edit'): ?>
                        Partita aggiornata con successo.
                    <?php elseif ($success == 'delete'): ?>
                        Partita eliminata con successo.
                    <?php elseif ($success == 'result'): ?>
                        Risultato inserito con successo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php if ($error == 'delete'): ?>
                        Impossibile eliminare la partita.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Accesso Rapido Inserimento Risultati -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Inserimento Rapido Risultati</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Utilizzare questa sezione per inserire rapidamente i risultati delle partite completate.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Partite da Completare</h5>
                                    <p>Visualizza l'elenco delle partite programmate per inserire i risultati</p>
                                    <a href="matches.php?status=<?php echo MATCH_STATUS_SCHEDULED; ?>" class="btn btn-warning w-100">
                                        <i class="fas fa-list"></i> Partite da Completare
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Risultati Recenti</h5>
                                    <p>Visualizza e modifica i risultati delle partite completate di recente</p>
                                    <a href="matches.php?status=<?php echo MATCH_STATUS_COMPLETED; ?>" class="btn btn-success w-100">
                                        <i class="fas fa-check"></i> Risultati Recenti
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtri -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Filtra Partite</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="championship_id" class="form-label">Campionato</label>
                            <select class="form-select" id="championship_id" name="championship_id">
                                <option value="">Tutti i Campionati</option>
                                <?php foreach ($championships as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($championshipId == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['type']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="team_id" class="form-label">Squadra</label>
                            <select class="form-select" id="team_id" name="team_id">
                                <option value="">Tutte le Squadre</option>
                                <?php foreach ($allTeams as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo ($teamId == $t['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="status" class="form-label">Stato</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tutti gli Stati</option>
                                <option value="<?php echo MATCH_STATUS_SCHEDULED; ?>" <?php echo ($status == MATCH_STATUS_SCHEDULED) ? 'selected' : ''; ?>>Programmate</option>
                                <option value="<?php echo MATCH_STATUS_COMPLETED; ?>" <?php echo ($status == MATCH_STATUS_COMPLETED) ? 'selected' : ''; ?>>Completate</option>
                                <option value="<?php echo MATCH_STATUS_POSTPONED; ?>" <?php echo ($status == MATCH_STATUS_POSTPONED) ? 'selected' : ''; ?>>Rinviate</option>
                                <option value="<?php echo MATCH_STATUS_CANCELLED; ?>" <?php echo ($status == MATCH_STATUS_CANCELLED) ? 'selected' : ''; ?>>Annullate</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="season_id" class="form-label">Stagione</label>
                            <select class="form-select" id="season_id" name="season_id">
                                <option value="">Tutte le Stagioni</option>
                                <?php foreach ($seasons as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($seasonId == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-filter"></i> Filtra
                            </button>
                            <a href="matches.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        </div>
                    </form>
                    
                    <?php if ($filterDescription): ?>
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Filtro attivo: <?php echo $filterDescription; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Calendario Partite -->
            <?php if (empty($matchesByDate)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Nessuna partita trovata con i filtri selezionati.
                </div>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Elenco Partite</h6>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="accordionMatches">
                            <?php 
                            $counter = 0;
                            foreach ($matchesByDate as $date => $dayMatches): 
                                $dateFormatted = date('d/m/Y', strtotime($date));
                                $itemId = 'collapse-' . $counter;
                                $headingId = 'heading-' . $counter;
                                
                                // Determina se ci sono partite completate in questa data
                                $hasCompletedMatches = false;
                                foreach ($dayMatches as $match) {
                                    if ($match['status'] == MATCH_STATUS_COMPLETED) {
                                        $hasCompletedMatches = true;
                                        break;
                                    }
                                }
                            ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="<?php echo $headingId; ?>">
                                        <button class="accordion-button <?php echo ($counter > 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $itemId; ?>" aria-expanded="<?php echo ($counter === 0) ? 'true' : 'false'; ?>" aria-controls="<?php echo $itemId; ?>">
                                            <?php echo $dateFormatted; ?> 
                                            <span class="badge bg-<?php echo $hasCompletedMatches ? 'success' : 'warning'; ?> ms-2">
                                                <?php echo $hasCompletedMatches ? 'Completata' : 'Programmata'; ?>
                                            </span>
                                            <span class="badge bg-secondary ms-2"><?php echo count($dayMatches); ?> partite</span>
                                        </button>
                                    </h2>
                                    <div id="<?php echo $itemId; ?>" class="accordion-collapse collapse <?php echo ($counter === 0) ? 'show' : ''; ?>" aria-labelledby="<?php echo $headingId; ?>" data-bs-parent="#accordionMatches">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Ora</th>
                                                            <th>Campionato</th>
                                                            <th>Squadre</th>
                                                            <th class="text-center">Risultato</th>
                                                            <th>Stato</th>
                                                            <th>Azioni</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($dayMatches as $match): ?>
                                                            <tr>
                                                                <td><?php echo date('H:i', strtotime($match['match_date'])); ?></td>
                                                                <td>
                                                                    <a href="championship-edit.php?id=<?php echo $match['championship_id']; ?>">
                                                                        <?php echo htmlspecialchars($match['championship_name']); ?>
                                                                    </a>
                                                                    <br>
                                                                    <small class="badge bg-<?php echo ($match['championship_type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                                                        <?php echo $match['championship_type']; ?>
                                                                    </small>
                                                                </td>
                                                                <td class="match-teams">
                                                                    <a href="team-edit.php?id=<?php echo $match['home_team_id']; ?>">
                                                                        <?php echo htmlspecialchars($match['home_team_name']); ?>
                                                                    </a>
                                                                    <span class="text-muted mx-2">vs</span>
                                                                    <a href="team-edit.php?id=<?php echo $match['away_team_id']; ?>">
                                                                        <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                                    </a>
                                                                </td>
                                                                <td class="text-center match-score">
                                                                    <?php if ($match['status'] == MATCH_STATUS_COMPLETED): ?>
                                                                        <a href="match-edit.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                                            <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        -
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php 
                                                                    switch ($match['status']) {
                                                                        case MATCH_STATUS_COMPLETED:
                                                                            echo '<span class="badge bg-success">Completata</span>';
                                                                            break;
                                                                        case MATCH_STATUS_SCHEDULED:
                                                                            echo '<span class="badge bg-warning text-dark">Programmata</span>';
                                                                            break;
                                                                        case MATCH_STATUS_POSTPONED:
                                                                            echo '<span class="badge bg-info">Rinviata</span>';
                                                                            break;
                                                                        case MATCH_STATUS_CANCELLED:
                                                                            echo '<span class="badge bg-danger">Annullata</span>';
                                                                            break;
                                                                    }
                                                                    ?>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group" role="group">
                                                                        <a href="match-edit.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                                            <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        
                                                                            <a href="match-result.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Inserisci Risultato">
                                                                                <i class="fas fa-basketball-ball"></i>
                                                                            </a>
                                                                        
                                                                        
                                                                        <a href="../public/match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Visualizza">
                                                                            <i class="fas fa-eye"></i>
                                                                        </a>
                                                                        <a href="match-delete.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
                                                                            <i class="fas fa-trash"></i>
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                $counter++;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>