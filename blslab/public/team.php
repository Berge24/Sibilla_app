<?php
// public/team.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: teams.php');
    exit;
}

$teamId = intval($_GET['id']);

// Carica la squadra
$team = Team::findById($teamId);
if (!$team) {
    header('Location: teams.php');
    exit;
}

// Ottieni i campionati della squadra
$championships = $team->getChampionships();

// Ottieni le partite recenti della squadra
$recentMatches = $team->getMatches(null, MATCH_STATUS_COMPLETED);
// Prendi solo le ultime 5 partite
$recentMatches = array_slice($recentMatches, 0, 5);

// Ottieni le prossime partite della squadra
$upcomingMatches = $team->getMatches(null, MATCH_STATUS_SCHEDULED);
// Prendi solo le prossime 5 partite
$upcomingMatches = array_slice($upcomingMatches, 0, 5);

// Includi il template header
$pageTitle = $team->getName();
include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo htmlspecialchars($team->getName()); ?></h1>
        <a href="teams.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna alle Squadre
        </a>
    </div>
    
    <!-- Informazioni Squadra -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">Informazioni Squadra</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <?php if (!empty($team->getLogo())): ?>
                                <img src="<?php echo htmlspecialchars($team->getLogo()); ?>" alt="Logo" 
                                    class="img-thumbnail mb-3" style="max-width: 100%; max-height: 200px;">
                            <?php else: ?>
                                <div class="bg-light p-4 mb-3 rounded">
                                    <i class="fas fa-shield-alt fa-4x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h4><?php echo htmlspecialchars($team->getName()); ?></h4>
                            
                            <?php if (!empty($team->getDescription())): ?>
                                <div class="mt-3">
                                    <h6>Descrizione</h6>
                                    <p><?php echo nl2br(htmlspecialchars($team->getDescription())); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
    <h6>Campionati a cui Partecipa</h6>
    <?php if (empty($championships)): ?>
        <p class="text-muted">Questa squadra non partecipa ad alcun campionato</p>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($championships as $index => $championship): ?>
                <?php if ($index < 3): ?>
                    <a href="championship.php?id=<?php echo $championship['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($championship['name']); ?>
                        <span class="badge badge-pill bg-<?php echo ($championship['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                            <?php echo $championship['type']; ?>
                        </span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if (count($championships) > 3): ?>
                <a href="#championships" class="list-group-item list-group-item-action text-center" data-bs-toggle="collapse">
                    <i class="fas fa-chevron-down"></i> Mostra tutti (<?php echo count($championships); ?>)
                </a>
                <div class="collapse" id="championships">
                    <?php for ($i = 3; $i < count($championships); $i++): ?>
                        <a href="championship.php?id=<?php echo $championships[$i]['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($championships[$i]['name']); ?>
                            <span class="badge badge-pill bg-<?php echo ($championships[$i]['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                <?php echo $championships[$i]['type']; ?>
                            </span>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
    <div class="card shadow">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">Statistiche</h5>
        </div>
        <div class="card-body">
            <?php if (empty($championships)): ?>
                <p class="text-center">Nessuna statistica disponibile</p>
            <?php else: ?>
                <?php 
                // Calcola statistiche complessive
                $totalPlayed = 0;
                $totalWon = 0;
                $totalDrawn = 0;  // Manteniamo la variabile ma non la mostriamo
                $totalLost = 0;
                $totalScored = 0;
                $totalConceded = 0;
                
                foreach ($championships as $championship) {
                    $stats = $team->getStats($championship['id']);
                    if ($stats) {
                        $totalPlayed += $stats['played'];
                        $totalWon += $stats['won'];
                        $totalDrawn += $stats['drawn'];
                        $totalLost += $stats['lost'];
                        $totalScored += $stats['scored'];
                        $totalConceded += $stats['conceded'];
                    }
                }
                ?>
                
                <div class="text-center">
                    <h6 class="mb-3">Statistiche Totali</h6>
                    <div class="row">
                        <div class="col-4">
                            <div class="stat-box bg-light p-2 rounded mb-2">
                                <div class="stat-value"><?php echo $totalPlayed; ?></div>
                                <div class="stat-label">Partite</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box bg-success text-white p-2 rounded mb-2">
                                <div class="stat-value"><?php echo $totalWon; ?></div>
                                <div class="stat-label">Vittorie</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box bg-danger text-white p-2 rounded mb-2">
                                <div class="stat-value"><?php echo $totalLost; ?></div>
                                <div class="stat-label">Sconfitte</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-6">
                            <div class="stat-box bg-info text-white p-2 rounded mb-2">
                                <div class="stat-value"><?php echo $totalScored; ?></div>
                                <div class="stat-label">Punti Fatti</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box bg-secondary text-white p-2 rounded mb-2">
                                <div class="stat-value"><?php echo $totalConceded; ?></div>
                                <div class="stat-label">Punti Subiti</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <a href="matches.php?team_id=<?php echo $teamId; ?>" class="btn btn-primary">
                        <i class="fas fa-calendar"></i> Calendario Partite
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    </div>
    
    <!-- Partite Recenti e Prossime -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">Calendario Partite</h5>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="matchTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="true">Prossime Partite</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent" type="button" role="tab" aria-controls="recent" aria-selected="false">Partite Recenti</button>
                </li>
            </ul>
            <div class="tab-content pt-3" id="matchTabsContent">
                <!-- Prossime Partite Tab -->
                <div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                    <?php if (empty($upcomingMatches)): ?>
                        <p class="text-center">Nessuna partita programmata</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-matches">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Campionato</th>
                                        <th>Squadre</th>
                                        <th>Stato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingMatches as $match): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($match['match_date'])); ?><br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($match['match_date'])); ?></small>
                                            </td>
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
                                                <?php
                                                $isHome = ($match['home_team_id'] == $teamId);
                                                $opponentId = $isHome ? $match['away_team_id'] : $match['home_team_id'];
                                                $opponentName = $isHome ? $match['away_team_name'] : $match['home_team_name'];
                                                ?>
                                                
                                                <?php if ($isHome): ?>
                                                    <strong><?php echo htmlspecialchars($team->getName()); ?></strong>
                                                    <span class="text-muted mx-2">vs</span>
                                                    <a href="team.php?id=<?php echo $opponentId; ?>">
                                                        <?php echo htmlspecialchars($opponentName); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="team.php?id=<?php echo $opponentId; ?>">
                                                        <?php echo htmlspecialchars($opponentName); ?>
                                                    </a>
                                                    <span class="text-muted mx-2">vs</span>
                                                    <strong><?php echo htmlspecialchars($team->getName()); ?></strong>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">Programmata</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Partite Recenti Tab -->
                <div class="tab-pane fade" id="recent" role="tabpanel" aria-labelledby="recent-tab">
                    <?php if (empty($recentMatches)): ?>
                        <p class="text-center">Nessun risultato disponibile</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-matches">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Campionato</th>
                                        <th>Squadre</th>
                                        <th>Risultato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentMatches as $match): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($match['match_date'])); ?>
                                            </td>
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
                                                <?php
                                                $isHome = ($match['home_team_id'] == $teamId);
                                                $opponentId = $isHome ? $match['away_team_id'] : $match['home_team_id'];
                                                $opponentName = $isHome ? $match['away_team_name'] : $match['home_team_name'];
                                                ?>
                                                
                                                <?php if ($isHome): ?>
                                                    <strong><?php echo htmlspecialchars($team->getName()); ?></strong>
                                                    <span class="text-muted mx-2">vs</span>
                                                    <a href="team.php?id=<?php echo $opponentId; ?>">
                                                        <?php echo htmlspecialchars($opponentName); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="team.php?id=<?php echo $opponentId; ?>">
                                                        <?php echo htmlspecialchars($opponentName); ?>
                                                    </a>
                                                    <span class="text-muted mx-2">vs</span>
                                                    <strong><?php echo htmlspecialchars($team->getName()); ?></strong>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <?php if ($isHome): ?>
                                                        <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                                                    <?php else: ?>
                                                        <?php echo $match['away_score']; ?> - <?php echo $match['home_score']; ?>
                                                    <?php endif; ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="matches.php?team_id=<?php echo $teamId; ?>" class="btn btn-primary">
                    Vedi Tutte le Partite
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>