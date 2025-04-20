<?php
// admin/index.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni statistiche generali
$db = Database::getInstance();

// Conteggio delle squadre
$totalTeams = $db->count('teams');

// Conteggio dei campionati
$totalChampionships = $db->count('championships');

// Conteggio delle partite
$totalMatches = $db->count('matches');
$completedMatches = $db->count('matches', 'status = ?', [MATCH_STATUS_COMPLETED]);
$scheduledMatches = $db->count('matches', 'status = ?', [MATCH_STATUS_SCHEDULED]);

// Conteggio degli utenti
$totalUsers = $db->count('users');

// Campionati recenti
$recentChampionships = $db->fetchAll("
    SELECT c.*, s.name as season_name, 
           (SELECT COUNT(*) FROM championships_teams WHERE championship_id = c.id) as team_count,
           (SELECT COUNT(*) FROM matches WHERE championship_id = c.id) as match_count
    FROM championships c
    JOIN seasons s ON c.season_id = s.id
    ORDER BY c.created_at DESC
    LIMIT 5
");

// Partite recenti
$recentMatches = $db->fetchAll("
    SELECT m.*, 
           c.name as championship_name,
           home.name as home_team_name, 
           away.name as away_team_name
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    WHERE m.status = ?
    ORDER BY m.match_date DESC
    LIMIT 5
", [MATCH_STATUS_COMPLETED]);

// Prossime partite con probabilità
$upcomingMatches = $db->fetchAll("
    SELECT m.*, 
           c.name as championship_name,
           home.name as home_team_name, 
           away.name as away_team_name,
           mp.home_win_probability,
           mp.away_win_probability
    FROM matches m
    JOIN championships c ON m.championship_id = c.id
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    LEFT JOIN match_probabilities mp ON m.id = mp.match_id
    WHERE m.status = ? AND m.match_date >= CURRENT_DATE()
    ORDER BY m.match_date ASC
", [MATCH_STATUS_SCHEDULED]);

// Ottieni i top campionati con le loro classifiche
$topChampionships = $db->fetchAll("
    SELECT c.id, c.name, c.type, s.name as season_name
    FROM championships c
    JOIN seasons s ON c.season_id = s.id
    WHERE EXISTS (SELECT 1 FROM matches WHERE championship_id = c.id)
      AND c.start_date <= CURRENT_DATE()
      AND c.end_date >= CURRENT_DATE()
    ORDER BY c.start_date DESC
    LIMIT 3
");

// Ottieni le classifiche per ogni campionato
$championshipStandings = [];
foreach ($topChampionships as $championship) {
    $championshipStandings[$championship['id']] = $db->fetchAll("
        SELECT s.*, t.name as team_name
        FROM standings s
        JOIN teams t ON s.team_id = t.id
        WHERE s.championship_id = ?
        ORDER BY s.points DESC, s.won DESC, (s.scored - s.conceded) DESC
        LIMIT 5
    ", [$championship['id']]);
}

// Includi il template header
$pageTitle = 'Dashboard Amministrazione';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <h1 class="mb-4">Dashboard Amministrazione</h1>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2 stat-card stat-card-primary">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Squadre</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalTeams; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2 stat-card stat-card-success">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Campionati</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalChampionships; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-trophy fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2 stat-card stat-card-info">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Partite</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalMatches; ?></div>
                                    <div class="small text-muted"><?php echo $completedMatches; ?> completate, <?php echo $scheduledMatches; ?> programmate</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-futbol fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-0 shadow h-100 py-2 stat-card stat-card-warning">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Utenti</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalUsers; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Probabilità di Vittoria & Classifiche -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="m-0 font-weight-bold">Probabilità di Vittoria</h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="probabilityTabs" role="tablist">
                                <?php foreach ($topChampionships as $index => $championship): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo ($index === 0) ? 'active' : ''; ?>" 
                                            id="championship-<?php echo $championship['id']; ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#championship-<?php echo $championship['id']; ?>" 
                                            type="button" role="tab" 
                                            aria-controls="championship-<?php echo $championship['id']; ?>" 
                                            aria-selected="<?php echo ($index === 0) ? 'true' : 'false'; ?>">
                                        <?php echo htmlspecialchars($championship['name']); ?>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <div class="tab-content mt-3" id="probabilityTabsContent">
                                <?php foreach ($topChampionships as $index => $championship): ?>
                                <div class="tab-pane fade <?php echo ($index === 0) ? 'show active' : ''; ?>" 
                                     id="championship-<?php echo $championship['id']; ?>" 
                                     role="tabpanel" 
                                     aria-labelledby="championship-<?php echo $championship['id']; ?>-tab">
                                     
                                    <?php if (!empty($championshipStandings[$championship['id']])): ?>
                                        <h6 class="mb-3">Classifica e Probabilità di Vittoria</h6>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Pos</th>
                                                        <th>Squadra</th>
                                                        <th class="text-center">Pt</th>
                                                        <th class="text-center">G</th>
                                                        <th class="text-center">V</th>
                                                        <th class="text-center">Prob. Vittoria</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($championshipStandings[$championship['id']] as $pos => $standing): ?>
                                                    <tr>
                                                        <td><?php echo $pos + 1; ?></td>
                                                        <td>
                                                            <a href="../public/team.php?id=<?php echo $standing['team_id']; ?>">
                                                                <?php echo htmlspecialchars($standing['team_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td class="text-center fw-bold"><?php echo $standing['points']; ?></td>
                                                        <td class="text-center"><?php echo $standing['played']; ?></td>
                                                        <td class="text-center"><?php echo $standing['won']; ?></td>
                                                        <td class="text-center">
                                                            <div class="progress">
                                                                <div class="progress-bar <?php echo ($standing['win_probability'] >= 50) ? 'bg-success' : (($standing['win_probability'] >= 25) ? 'bg-warning' : 'bg-danger'); ?>" 
                                                                     role="progressbar" 
                                                                     style="width: <?php echo $standing['win_probability']; ?>%" 
                                                                     aria-valuenow="<?php echo $standing['win_probability']; ?>" 
                                                                     aria-valuemin="0" 
                                                                     aria-valuemax="100">
                                                                    <?php echo number_format($standing['win_probability'], 1); ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="text-center mt-3">
                                            <a href="calculate-standings.php?championship_id=<?php echo $championship['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-sync"></i> Aggiorna Classifica
                                            </a>
                                            <a href="calculate-probabilities.php?championship_id=<?php echo $championship['id']; ?>" class="btn btn-info btn-sm ms-2">
                                                <i class="fas fa-percentage"></i> Ricalcola Probabilità
                                            </a>
                                            <a href="../public/standings.php?championship_id=<?php echo $championship['id']; ?>" class="btn btn-secondary btn-sm ms-2">
                                                <i class="fas fa-eye"></i> Visualizza Completa
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> Nessuna classifica disponibile per questo campionato.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent & Upcoming Matches -->
                <div class="col-md-12 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-success text-white">
                            <h5 class="m-0 font-weight-bold">Partite Recenti e Prossime</h5>
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
                            <div class="tab-content" id="matchTabsContent">
                                <!-- Upcoming Matches Tab -->
<div class="tab-pane fade show active" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
    <?php if (empty($upcomingMatches)): ?>
        <p class="text-center mt-3">Nessuna partita programmata</p>
    <?php else: ?>
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-striped">
                <thead class="sticky-top bg-white">
                    <tr>
                        <th>Data</th>
                        <th>Campionato</th>
                        <th>Squadre</th>
                        <th>Probabilità</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingMatches as $match): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($match['match_date'])); ?></td>
                            <td><?php echo htmlspecialchars($match['championship_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($match['home_team_name']); ?> - 
                                <?php echo htmlspecialchars($match['away_team_name']); ?>
                            </td>
                            <td>
                                <?php if (isset($match['home_win_probability']) && isset($match['away_win_probability'])): ?>
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
                                <?php else: ?>
                                <span class="badge bg-secondary">Non calcolata</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="match-edit.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="match-result.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-basketball-ball"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-end mt-2">
            <span class="text-muted">Totale: <?php echo count($upcomingMatches); ?> partite</span>
        </div>
    <?php endif; ?>
</div>
                                
                                <!-- Recent Matches Tab -->
                                <div class="tab-pane fade" id="recent" role="tabpanel" aria-labelledby="recent-tab">
                                    <?php if (empty($recentMatches)): ?>
                                        <p class="text-center mt-3">Nessuna partita recente</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Data</th>
                                                        <th>Campionato</th>
                                                        <th>Squadre</th>
                                                        <th>Risultato</th>
                                                        <th>Azioni</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentMatches as $match): ?>
                                                        <tr>
                                                            <td><?php echo date('d/m/Y', strtotime($match['match_date'])); ?></td>
                                                            <td><?php echo htmlspecialchars($match['championship_name']); ?></td>
                                                            <td>
                                                                <?php echo htmlspecialchars($match['home_team_name']); ?> - 
                                                                <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                            </td>
                                                            <td>
                                                                <a href="match-edit.php?id=<?php echo $match['id']; ?>">
                                                                    <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <a href="match-edit.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="../public/match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-info">
                                                                    <i class="fas fa-eye"></i>
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
                                <a href="matches.php" class="btn btn-success btn-sm">Gestisci Partite</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-12 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-info text-white">
                            <h5 class="m-0 font-weight-bold">Azioni Rapide</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="season-add.php" class="btn btn-outline-primary btn-block w-100">
                                        <i class="fas fa-calendar-plus"></i> Nuova Stagione
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="championship-add.php" class="btn btn-outline-success btn-block w-100">
                                        <i class="fas fa-trophy"></i> Nuovo Campionato
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="team-add.php" class="btn btn-outline-info btn-block w-100">
                                        <i class="fas fa-users"></i> Nuova Squadra
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="match-add.php" class="btn btn-outline-warning btn-block w-100">
                                        <i class="fas fa-futbol"></i> Nuova Partita
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script per i tooltip -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>