<?php
// public/match.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: matches.php');
    exit;
}

$matchId = intval($_GET['id']);

// Carica la partita
$match = Match::findById($matchId);
if (!$match) {
    header('Location: matches.php');
    exit;
}

// Ottieni info campionato
$championship = Championship::findById($match->getChampionshipId());
if (!$championship) {
    header('Location: matches.php');
    exit;
}

// Ottieni info squadre
$db = Database::getInstance();
$homeTeam = $db->fetchOne("SELECT * FROM teams WHERE id = ?", [$match->getHomeTeamId()]);
$awayTeam = $db->fetchOne("SELECT * FROM teams WHERE id = ?", [$match->getAwayTeamId()]);

// Ottieni le probabilità della partita se lo stato è programmato
$matchProbabilities = null;
if ($match->getStatus() == MATCH_STATUS_SCHEDULED) {
    $matchProbabilities = MatchProbability::getByMatchId($matchId);
}

// Includi il template header
$pageTitle = $homeTeam['name'] . ' vs ' . $awayTeam['name'];
include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Dettagli Partita</h1>
        <a href="matches.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna al Calendario
        </a>
    </div>
    
    <!-- Informazioni Partita -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">
                <?php echo htmlspecialchars($homeTeam['name']); ?> vs <?php echo htmlspecialchars($awayTeam['name']); ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p>
                        <strong>Campionato:</strong> 
                        <a href="championship.php?id=<?php echo $championship->getId(); ?>">
                            <?php echo htmlspecialchars($championship->getName()); ?>
                        </a>
                        <span class="badge bg-<?php echo ($championship->getType() == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?> ms-2">
                            <?php echo $championship->getType(); ?>
                        </span>
                    </p>
                    <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($match->getMatchDate())); ?></p>
                    <p><strong>Ora:</strong> <?php echo date('H:i', strtotime($match->getMatchDate())); ?></p>
                    <p>
                        <strong>Stato:</strong>
                        <?php 
                        switch ($match->getStatus()) {
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
                    </p>
                </div>
                <div class="col-md-6">
                    <?php if ($match->getStatus() == MATCH_STATUS_COMPLETED): ?>
                        <p><strong>Risultato Finale:</strong></p>
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="text-end me-3">
                                <h5 class="mb-0">
                                    <a href="team.php?id=<?php echo $homeTeam['id']; ?>">
                                        <?php echo htmlspecialchars($homeTeam['name']); ?>
                                    </a>
                                </h5>
                            </div>
                            <div class="px-4 py-2 bg-light rounded-3 text-center">
                                <h3 class="mb-0 fw-bold">
                                    <?php echo $match->getHomeScore(); ?> - <?php echo $match->getAwayScore(); ?>
                                </h3>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0">
                                    <a href="team.php?id=<?php echo $awayTeam['id']; ?>">
                                        <?php echo htmlspecialchars($awayTeam['name']); ?>
                                    </a>
                                </h5>
                            </div>
                        </div>
                    <?php elseif ($match->getStatus() == MATCH_STATUS_SCHEDULED): ?>
                        <!-- Visualizzazione delle probabilità per partite programmate -->
                        <div class="card bg-light">
                            <div class="card-header">
                                <h5 class="mb-0">Probabilità di Vittoria</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($matchProbabilities): ?>
                                    <div class="text-center mb-3">
                                        <div class="progress" style="height: 30px;">
                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                 style="width: <?php echo $matchProbabilities->getHomeWinProbability(); ?>%" 
                                                 aria-valuenow="<?php echo $matchProbabilities->getHomeWinProbability(); ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo round($matchProbabilities->getHomeWinProbability()); ?>%
                                            </div>
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                 style="width: <?php echo $matchProbabilities->getAwayWinProbability(); ?>%" 
                                                 aria-valuenow="<?php echo $matchProbabilities->getAwayWinProbability(); ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo round($matchProbabilities->getAwayWinProbability()); ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row text-center">
                                        <div class="col-6 text-primary">
                                            <strong><?php echo htmlspecialchars($homeTeam['name']); ?></strong>
                                            <p><?php echo round($matchProbabilities->getHomeWinProbability()); ?>% probabilità</p>
                                        </div>
                                        <div class="col-6 text-danger">
                                            <strong><?php echo htmlspecialchars($awayTeam['name']); ?></strong>
                                            <p><?php echo round($matchProbabilities->getAwayWinProbability()); ?>% probabilità</p>
                                        </div>
                                    </div>
                                    <div class="text-muted small text-center mt-2">
                                        Ultimo aggiornamento: <?php echo date('d/m/Y H:i', strtotime($matchProbabilities->getCalculatedAt())); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle me-2"></i> Le probabilità di vittoria non sono ancora disponibili per questa partita.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            <?php 
                            switch ($match->getStatus()) {
                                case MATCH_STATUS_POSTPONED:
                                    echo 'Questa partita è stata rinviata. Risultato non disponibile.';
                                    break;
                                case MATCH_STATUS_CANCELLED:
                                    echo 'Questa partita è stata annullata. Risultato non disponibile.';
                                    break;
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($match->getNotes())): ?>
                <div class="mt-3">
                    <h6>Note</h6>
                    <div class="alert alert-secondary">
                        <?php echo nl2br(htmlspecialchars($match->getNotes())); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistiche delle Squadre -->
    <?php if ($match->getStatus() == MATCH_STATUS_SCHEDULED): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">Statistiche Stagionali</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                // Ottieni statistiche delle squadre per questo campionato
                $homeStats = $db->fetchOne("
                    SELECT * FROM standings 
                    WHERE championship_id = ? AND team_id = ?
                ", [$championship->getId(), $homeTeam['id']]);
                
                $awayStats = $db->fetchOne("
                    SELECT * FROM standings 
                    WHERE championship_id = ? AND team_id = ?
                ", [$championship->getId(), $awayTeam['id']]);
                ?>
                
                <div class="col-md-6">
                    <h5 class="text-center text-primary mb-3"><?php echo htmlspecialchars($homeTeam['name']); ?></h5>
                    <table class="table table-striped">
                        <tr>
                            <th>Partite Giocate</th>
                            <td><?php echo $homeStats ? $homeStats['played'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Vittorie</th>
                            <td><?php echo $homeStats ? $homeStats['won'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Pareggi</th>
                            <td><?php echo $homeStats ? $homeStats['drawn'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Sconfitte</th>
                            <td><?php echo $homeStats ? $homeStats['lost'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Punti Segnati</th>
                            <td><?php echo $homeStats ? $homeStats['scored'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Punti Subiti</th>
                            <td><?php echo $homeStats ? $homeStats['conceded'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Differenza Punti</th>
                            <td>
                                <?php 
                                if ($homeStats) {
                                    $diff = $homeStats['scored'] - $homeStats['conceded'];
                                    echo ($diff > 0 ? '+' : '') . $diff;
                                } else {
                                    echo 0;
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h5 class="text-center text-danger mb-3"><?php echo htmlspecialchars($awayTeam['name']); ?></h5>
                    <table class="table table-striped">
                        <tr>
                            <th>Partite Giocate</th>
                            <td><?php echo $awayStats ? $awayStats['played'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Vittorie</th>
                            <td><?php echo $awayStats ? $awayStats['won'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Pareggi</th>
                            <td><?php echo $awayStats ? $awayStats['drawn'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Sconfitte</th>
                            <td><?php echo $awayStats ? $awayStats['lost'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Punti Segnati</th>
                            <td><?php echo $awayStats ? $awayStats['scored'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Punti Subiti</th>
                            <td><?php echo $awayStats ? $awayStats['conceded'] : 0; ?></td>
                        </tr>
                        <tr>
                            <th>Differenza Punti</th>
                            <td>
                                <?php 
                                if ($awayStats) {
                                    $diff = $awayStats['scored'] - $awayStats['conceded'];
                                    echo ($diff > 0 ? '+' : '') . $diff;
                                } else {
                                    echo 0;
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Precedenti Scontri Diretti -->
    <?php
    // Ottieni partite precedenti tra queste due squadre
    $previousMatches = $db->fetchAll("
        SELECT m.*, 
               c.name as championship_name,
               c.type as championship_type
        FROM matches m
        JOIN championships c ON m.championship_id = c.id
        WHERE m.status = ? 
        AND ((m.home_team_id = ? AND m.away_team_id = ?) OR (m.home_team_id = ? AND m.away_team_id = ?))
        AND m.id != ?
        ORDER BY m.match_date DESC
        LIMIT 5
    ", [MATCH_STATUS_COMPLETED, $homeTeam['id'], $awayTeam['id'], $awayTeam['id'], $homeTeam['id'], $matchId]);
    
    if (!empty($previousMatches)):
    ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">Precedenti Scontri Diretti</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Campionato</th>
                            <th>Partita</th>
                            <th class="text-center">Risultato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previousMatches as $prevMatch): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($prevMatch['match_date'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($prevMatch['championship_name']); ?>
                                    <br>
                                    <small class="badge bg-<?php echo ($prevMatch['championship_type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                        <?php echo $prevMatch['championship_type']; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $isHomeTeamHome = ($prevMatch['home_team_id'] == $homeTeam['id']);
                                    if ($isHomeTeamHome) {
                                        echo '<strong class="text-primary">' . htmlspecialchars($homeTeam['name']) . '</strong>';
                                        echo ' vs ';
                                        echo '<strong class="text-danger">' . htmlspecialchars($awayTeam['name']) . '</strong>';
                                    } else {
                                        echo '<strong class="text-danger">' . htmlspecialchars($awayTeam['name']) . '</strong>';
                                        echo ' vs ';
                                        echo '<strong class="text-primary">' . htmlspecialchars($homeTeam['name']) . '</strong>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <a href="match.php?id=<?php echo $prevMatch['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <?php echo $prevMatch['home_score']; ?> - <?php echo $prevMatch['away_score']; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>