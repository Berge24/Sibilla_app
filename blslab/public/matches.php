<?php
// public/matches.php

// Includi la configurazione
require_once '../config/config.php';

// Ottieni parametri di filtro
$championshipId = filter_input(INPUT_GET, 'championship_id', FILTER_VALIDATE_INT);
$teamId = filter_input(INPUT_GET, 'team_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$seasonId = filter_input(INPUT_GET, 'season_id', FILTER_VALIDATE_INT);

// Ottieni database
$db = Database::getInstance();

// Costruisci la query in base ai filtri
$query = "
    SELECT m.*, 
           c.name as championship_name, c.type as championship_type,
           s.name as season_name,
           home.name as home_team_name, 
           away.name as away_team_name,
           mp.home_win_probability,
           mp.away_win_probability
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN seasons s ON c.season_id = s.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    LEFT JOIN match_probabilities mp ON m.id = mp.match_id
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
    }
}

if ($status) {
    $query .= " AND m.status = ?";
    $params[] = $status;
}

if ($seasonId) {
    $query .= " AND c.season_id = ?";
    $params[] = $seasonId;
}

// Ordina per data
$query .= " ORDER BY m.match_date";

if ($status == MATCH_STATUS_COMPLETED) {
    $query .= " DESC"; // Partite completate dalla più recente
} else {
    $query .= " ASC"; // Partite future dalla più vicina
}

// Esegui la query
$matches = $db->fetchAll($query, $params);

// Organizza le partite per data
$matchesByDate = [];
foreach ($matches as $match) {
    $date = date('Y-m-d', strtotime($match['match_date']));
    $matchesByDate[$date][] = $match;
}

// Ottieni tutti i campionati per i filtri
$championships = Championship::getAll();
$seasons = Season::getAll();

// Imposta il titolo della pagina
if (!isset($pageTitle)) {
    if ($status == MATCH_STATUS_COMPLETED) {
        $pageTitle = 'Risultati Partite';
    } elseif ($status == MATCH_STATUS_SCHEDULED) {
        $pageTitle = 'Calendario Partite';
    } else {
        $pageTitle = 'Tutte le Partite';
    }
}

// Includi il template header
include_once '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4"><?php echo $pageTitle; ?></h1>
    
    <!-- Filtri -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtra Partite</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
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
                
                <div class="col-md-3 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-primary">Filtra</button>
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (empty($matchesByDate)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Nessuna partita disponibile con i filtri selezionati.
        </div>
    <?php else: ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="m-0 font-weight-bold text-primary">Partite</h5>
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
                                        <table class="table table-hover table-matches">
                                            <thead>
                                                <tr>
                                                    <th>Ora</th>
                                                    <th>Campionato</th>
                                                    <th>Squadre</th>
                                                    <th class="text-center">Risultato</th>
                                                    <th>Stato</th>
                                                    <?php if (!$hasCompletedMatches): ?>
                                                    <th>Probabilità</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dayMatches as $match): ?>
                                                    <tr>
                                                        <td><?php echo date('H:i', strtotime($match['match_date'])); ?></td>
                                                        <td>
                                                            <a href="championship.php?id=<?php echo $match['championship_id']; ?>">
                                                                <?php echo htmlspecialchars($match['championship_name']); ?>
                                                            </a>
                                                            <br>
                                                            <small class="badge bg-<?php echo ($match['championship_type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                                                <?php echo $match['championship_type']; ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <a href="team.php?id=<?php echo $match['home_team_id']; ?>">
                                                                <?php echo htmlspecialchars($match['home_team_name']); ?>
                                                            </a>
                                                            <span class="text-muted mx-2">vs</span>
                                                            <a href="team.php?id=<?php echo $match['away_team_id']; ?>">
                                                                <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($match['status'] == MATCH_STATUS_COMPLETED): ?>
                                                                <a href="match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-secondary">
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
                                                        <?php if (!$hasCompletedMatches && isset($match['home_win_probability']) && isset($match['away_win_probability'])): ?>
                                                        <td>
                                                            <div class="progress">
                                                                <div class="progress-bar bg-primary" role="progressbar" 
                                                                     style="width: <?php echo $match['home_win_probability']; ?>%" 
                                                                     aria-valuenow="<?php echo $match['home_win_probability']; ?>" 
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                    <?php echo round($match['home_win_probability']); ?>%
                                                                </div>
                                                                <div class="progress-bar bg-danger" role="progressbar" 
                                                                     style="width: <?php echo $match['away_win_probability']; ?>%" 
                                                                     aria-valuenow="<?php echo $match['away_win_probability']; ?>" 
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                    <?php echo round($match['away_win_probability']); ?>%
                                                                </div>
                                                            </div>
                                                            <div class="text-center mt-1 small">
                                                                <span class="text-primary"><?php echo htmlspecialchars($match['home_team_name']); ?></span> vs 
                                                                <span class="text-danger"><?php echo htmlspecialchars($match['away_team_name']); ?></span>
                                                            </div>
                                                        </td>
                                                        <?php elseif (!$hasCompletedMatches): ?>
                                                        <td>
                                                            <span class="text-muted">Non disponibile</span>
                                                        </td>
                                                        <?php endif; ?>
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
        
        <!-- Legenda Probabilità -->
        <?php if ($status == MATCH_STATUS_SCHEDULED): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="m-0 font-weight-bold text-primary">Informazioni sulle Probabilità</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Come interpretiamo le probabilità</h6>
                        <p>Le probabilità di vittoria vengono calcolate utilizzando una combinazione di:</p>
                        <ul>
                            <li>Performance della squadra nelle partite precedenti</li>
                            <li>Differenza punti (punti segnati meno punti subiti)</li>
                            <li>Vantaggio del campo casalingo (circa 10%)</li>
                            <li>Statistiche testa a testa delle partite passate</li>
                        </ul>
                        <p>Una probabilità più alta indica una maggiore possibilità di vittoria.</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Esempio di probabilità</h6>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 70%" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100">70%</div>
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 30%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100">30%</div>
                        </div>
                        <p>In questo esempio, la squadra di casa ha il 70% di probabilità di vincere, mentre la squadra ospite ha il 30%.</p>
                        <p class="text-muted small">Nota: Le probabilità sono basate su simulazioni statistiche e non garantiscono il risultato finale della partita.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>