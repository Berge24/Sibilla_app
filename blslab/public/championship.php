<?php
// public/championship.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che sia specificato un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: championships.php');
    exit;
}

$championshipId = intval($_GET['id']);

// Verifica se l'utente è loggato
$isLoggedIn = User::isLoggedIn();
$currentUser = $isLoggedIn ? User::getCurrentUser() : null;
$userId = $isLoggedIn ? $currentUser->getId() : null;

// Carica il campionato
$db = Database::getInstance();

// Alternativa 1: Carica direttamente dal database con i controlli di visibilità
$championshipData = $db->fetchOne("
    SELECT * FROM championships WHERE id = ? AND (is_public = 1 OR user_id = ?)
", [$championshipId, $userId ?? 0]);

if (!$championshipData) {
    // Se non trovi il campionato o l'utente non ha accesso, reindirizza
    header('Location: championships.php');
    exit;
}

// Verifica se l'utente è il proprietario
$isOwner = $isLoggedIn && $championshipData['user_id'] == $userId;

// Ottieni info stagione
$seasonInfo = $db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$championshipData['season_id']]);

// Ottieni le squadre del campionato
$teams = $db->fetchAll("
    SELECT t.* 
    FROM teams t
    JOIN championships_teams ct ON t.id = ct.team_id
    WHERE ct.championship_id = ?
    ORDER BY t.name
", [$championshipId]);

// Ottieni la classifica
$standings = Standing::getByChampionship($championshipId);

// Ottieni le partite
$matches = $db->fetchAll("
    SELECT m.*, 
           home.name as home_team_name, 
           away.name as away_team_name
    FROM matches m
    JOIN teams home ON m.home_team_id = home.id
    JOIN teams away ON m.away_team_id = away.id
    WHERE m.championship_id = ?
    ORDER BY m.match_date
", [$championshipId]);

// Organizza le partite per data
$matchesByDate = [];
foreach ($matches as $match) {
    $date = date('Y-m-d', strtotime($match['match_date']));
    $matchesByDate[$date][] = $match;
}

// Inizializza le variabili per le informazioni generali
$totalTeams = count($teams);
$totalMatches = count($matches);
$completedMatches = 0;
$scheduledMatches = 0;
$totalGoals = 0;

foreach ($matches as $match) {
    if ($match['status'] == MATCH_STATUS_COMPLETED) {
        $completedMatches++;
        $totalGoals += ($match['home_score'] + $match['away_score']);
    } elseif ($match['status'] == MATCH_STATUS_SCHEDULED) {
        $scheduledMatches++;
    }
}

$avgGoalsPerMatch = $completedMatches > 0 ? round($totalGoals / $completedMatches, 2) : 0;

// Ottieni l'abbonamento dell'utente
$userSubscription = null;
$subscriptionPlan = null;
$planCode = 'free';
$canEditChampionship = false;

if ($isLoggedIn) {
    $userSubscription = UserSubscription::getByUserId($userId);
    if ($userSubscription) {
        $subscriptionPlan = SubscriptionPlan::findById($userSubscription->getPlanId());
        $planCode = $subscriptionPlan ? strtolower($subscriptionPlan->getCode()) : 'free';
    }
    
    // Verifica se l'utente può modificare il campionato
    $canEditChampionship = $isOwner && ($planCode !== 'free') && !$userSubscription->isExpired();
}

// Includi il template header
$pageTitle = $championshipData['name'];
include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <?php echo htmlspecialchars($championshipData['name']); ?>
            <?php if (!$championshipData['is_public']): ?>
                <span class="ownership-badge ownership-badge-private">
                    <i class="fas fa-lock me-1"></i> Privato
                </span>
            <?php endif; ?>
            <?php if ($isOwner): ?>
                <span class="ownership-badge ownership-badge-owner">
                    <i class="fas fa-user-edit me-1"></i> Tuo
                </span>
            <?php endif; ?>
        </h1>
        <div>
            <?php if ($canEditChampionship): ?>
                <a href="<?php echo URL_ROOT; ?>/admin/championship_form.php?id=<?php echo $championshipId; ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit"></i> Modifica
                </a>
            <?php endif; ?>
            <a href="championships.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Torna ai Campionati
            </a>
        </div>
    </div>
    
    <!-- Informazioni Campionato -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-primary">Informazioni Campionato</h5>
                    <?php if ($isOwner): ?>
                        <span class="badge bg-primary">
                            <i class="fas fa-user me-1"></i> Creato da te
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nome:</strong> <?php echo htmlspecialchars($championshipData['name']); ?></p>
                            <p>
                                <strong>Tipo:</strong> 
                                <span class="badge bg-<?php echo ($championshipData['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                    <?php echo $championshipData['type']; ?>
                                </span>
                            </p>
                            <p><strong>Stagione:</strong> <?php echo htmlspecialchars($seasonInfo['name']); ?></p>
                            <p><strong>Data Inizio:</strong> <?php echo date('d/m/Y', strtotime($championshipData['start_date'])); ?></p>
                            <p><strong>Data Fine:</strong> <?php echo date('d/m/Y', strtotime($championshipData['end_date'])); ?></p>
                            <p><strong>Visibilità:</strong> 
                                <?php if ($championshipData['is_public']): ?>
                                    <span class="badge bg-success"><i class="fas fa-globe me-1"></i> Pubblico</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-lock me-1"></i> Privato</span>
                                    <?php if (!$isOwner): ?>
                                        <small class="text-muted ms-1">(hai accesso speciale)</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Squadre:</strong> <?php echo $totalTeams; ?></p>
                            <p><strong>Partite Totali:</strong> <?php echo $totalMatches; ?></p>
                            <p><strong>Partite Giocate:</strong> <?php echo $completedMatches; ?></p>
                            <p><strong>Partite da Giocare:</strong> <?php echo $scheduledMatches; ?></p>
                            <p><strong>Media Punti per Partita:</strong> <?php echo $avgGoalsPerMatch; ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($championshipData['description'])): ?>
                        <div class="mt-3">
                            <h6>Descrizione</h6>
                            <p><?php echo nl2br(htmlspecialchars($championshipData['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold text-primary">Squadre Partecipanti</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($teams)): ?>
                        <p class="text-center">Nessuna squadra partecipante</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($teams as $team): ?>
                                <a href="team.php?id=<?php echo $team['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($team['name']); ?>
                                    
                                    <?php if ($isLoggedIn && $team['user_id'] == $userId): ?>
                                        <span class="ownership-badge ownership-badge-owner">
                                            <i class="fas fa-user-edit"></i>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($canEditChampionship && $subscriptionPlan): ?>
                        <div class="text-center mt-3">
                            <a href="<?php echo URL_ROOT; ?>/admin/team_championship.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus-circle"></i> Gestisci Squadre
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Classifica -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 font-weight-bold text-primary">Classifica</h5>
            <a href="standings.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-sm btn-primary">
                Classifica Completa
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($standings)): ?>
                <p class="text-center">Nessuna classifica disponibile</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-standings">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">Pos.</th>
                                <th>Squadra</th>
                                <th class="text-center">Pt</th>
                                <th class="text-center">G</th>
                                <th class="text-center">V</th>
                                <th class="text-center">P</th>
                                <th class="text-center">S</th>
                                <th class="text-center">PF</th>
                                <th class="text-center">PS</th>
                                <th class="text-center">DP</th>
                                <th class="text-center">Prob. Vittoria</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standings as $index => $standing): ?>
                                <tr class="<?php echo ($index < 3) ? 'table-success' : ''; ?>">
                                    <td class="text-center"><?php echo $index + 1; ?></td>
                                    <td>
                                        <a href="team.php?id=<?php echo $standing['team_id']; ?>" class="d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($standing['team_name']); ?>
                                            
                                            <?php 
                                            // Verifica se la squadra è dell'utente
                                            $teamInfo = $db->fetchOne("SELECT user_id FROM teams WHERE id = ?", [$standing['team_id']]);
                                            if ($isLoggedIn && $teamInfo && $teamInfo['user_id'] == $userId): 
                                            ?>
                                                <span class="ownership-badge ownership-badge-owner ms-2">
                                                    <i class="fas fa-user-edit"></i>
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo $standing['points']; ?></td>
                                    <td class="text-center"><?php echo $standing['played']; ?></td>
                                    <td class="text-center"><?php echo $standing['won']; ?></td>
                                    <td class="text-center"><?php echo $standing['drawn']; ?></td>
                                    <td class="text-center"><?php echo $standing['lost']; ?></td>
                                    <td class="text-center"><?php echo $standing['scored']; ?></td>
                                    <td class="text-center"><?php echo $standing['conceded']; ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $diff = $standing['scored'] - $standing['conceded'];
                                        echo ($diff > 0 ? '+' : '') . $diff;
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (isset($standing['win_probability']) && $standing['win_probability'] > 0): ?>
                                            <div class="badge badge-probability bg-<?php 
                                                if ($standing['win_probability'] >= 50) echo 'success';
                                                elseif ($standing['win_probability'] >= 25) echo 'warning';
                                                else echo 'danger';
                                            ?>">
                                                <?php echo number_format($standing['win_probability'], 1); ?>%
                                            </div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Calendario Partite -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 font-weight-bold text-primary">Calendario Partite</h5>
            <div>
                <?php if ($canEditChampionship): ?>
                    <a href="<?php echo URL_ROOT; ?>/admin/match_form.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="fas fa-plus-circle"></i> Nuova Partita
                    </a>
                <?php endif; ?>
                <a href="matches.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-sm btn-primary">
                    Calendario Completo
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($matchesByDate)): ?>
                <p class="text-center">Nessuna partita programmata</p>
            <?php else: ?>
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
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Ora</th>
                                                    <th>Squadre</th>
                                                    <th class="text-center">Risultato</th><th>Stato</th>
                                                    <?php if ($canEditChampionship): ?>
                                                    <th>Azioni</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dayMatches as $match): ?>
                                                    <tr>
                                                        <td><?php echo date('H:i', strtotime($match['match_date'])); ?></td>
                                                        <td class="match-teams">
                                                            <a href="team.php?id=<?php echo $match['home_team_id']; ?>">
                                                                <?php echo htmlspecialchars($match['home_team_name']); ?>
                                                            </a>
                                                            <span class="text-muted mx-2">vs</span>
                                                            <a href="team.php?id=<?php echo $match['away_team_id']; ?>">
                                                                <?php echo htmlspecialchars($match['away_team_name']); ?>
                                                            </a>
                                                        </td>
                                                        <td class="text-center match-score">
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
                                                        <?php if ($canEditChampionship): ?>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="<?php echo URL_ROOT; ?>/admin/match_form.php?id=<?php echo $match['id']; ?>" class="btn btn-warning">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <?php if ($match['status'] == MATCH_STATUS_SCHEDULED): ?>
                                                                <a href="<?php echo URL_ROOT; ?>/admin/match_result.php?id=<?php echo $match['id']; ?>" class="btn btn-success">
                                                                    <i class="fas fa-check"></i> Risultato
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
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
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$isLoggedIn || ($isLoggedIn && $planCode === 'free')): ?>
    <!-- Spazio Pubblicitario (per utenti non loggati o free) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-light">
            <h5 class="m-0 text-muted">Spazio Pubblicitario</h5>
        </div>
        <div class="card-body text-center">
            <img src="<?php echo URL_ROOT; ?>/assets/images/ad-banner.png" alt="Advertisement" class="img-fluid">
            <?php if ($isLoggedIn): ?>
            <p class="small mt-2">
                <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php">Passa a un piano a pagamento</a> per rimuovere le pubblicità.
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($canEditChampionship && in_array($planCode, ['premium', 'enterprise'])): ?>
    <!-- Statistiche Avanzate (solo per utenti premium/enterprise) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h5 class="m-0 font-weight-bold text-primary">Statistiche Avanzate</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Distribuzione Punteggi</h6>
                    <canvas id="scoreDistribution" width="400" height="250"></canvas>
                </div>
                <div class="col-md-6">
                    <h6>Prestazioni Squadre</h6>
                    <canvas id="teamPerformance" width="400" height="250"></canvas>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="<?php echo URL_ROOT; ?>/public/statistics.php?championship_id=<?php echo $championshipId; ?>" class="btn btn-primary">
                    <i class="fas fa-chart-line me-1"></i> Visualizza Tutte le Statistiche
                </a>
            </div>
        </div>
    </div>
    <?php elseif ($isLoggedIn && $planCode === 'basic' && $isOwner): ?>
    <!-- Banner upgrade a Premium -->
    <div class="card shadow mb-4 border-warning">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="text-warning"><i class="fas fa-star me-2"></i> Sblocca Statistiche Avanzate!</h5>
                    <p class="mb-0">
                        Passa al piano Premium per accedere a statistiche avanzate, analisi delle prestazioni e predizioni.
                    </p>
                </div>
                <a href="<?php echo URL_ROOT; ?>/public/subscription_plans.php" class="btn btn-warning">
                    <i class="fas fa-arrow-circle-up me-1"></i> Upgrade a Premium
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($canEditChampionship && in_array($planCode, ['premium', 'enterprise'])): ?>
<!-- Script per statistiche avanzate -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Distribuzione Punteggi
    const scoreDistribution = document.getElementById('scoreDistribution').getContext('2d');
    new Chart(scoreDistribution, {
        type: 'bar',
        data: {
            labels: ['0-2', '3-5', '6-8', '9-11', '12+'],
            datasets: [{
                label: 'Distribuzione Punteggi',
                data: [
                    <?php 
                    // Calcolo distribuzione punteggi
                    $scoreRanges = [0, 0, 0, 0, 0]; // 0-2, 3-5, 6-8, 9-11, 12+
                    foreach ($matches as $match) {
                        if ($match['status'] == MATCH_STATUS_COMPLETED) {
                            $totalScore = $match['home_score'] + $match['away_score'];
                            if ($totalScore <= 2) $scoreRanges[0]++;
                            elseif ($totalScore <= 5) $scoreRanges[1]++;
                            elseif ($totalScore <= 8) $scoreRanges[2]++;
                            elseif ($totalScore <= 11) $scoreRanges[3]++;
                            else $scoreRanges[4]++;
                        }
                    }
                    echo implode(', ', $scoreRanges);
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Prestazioni Squadre
    const teamPerformance = document.getElementById('teamPerformance').getContext('2d');
    new Chart(teamPerformance, {
        type: 'radar',
        data: {
            labels: [
                <?php 
                // Ottieni le prime 5 squadre
                $topTeams = array_slice($standings, 0, 5);
                $teamNames = [];
                foreach ($topTeams as $team) {
                    $teamNames[] = "'" . addslashes($team['team_name']) . "'";
                }
                echo implode(', ', $teamNames);
                ?>
            ],
            datasets: [{
                label: 'Punti Fatti',
                data: [
                    <?php 
                    $scoredPoints = [];
                    foreach ($topTeams as $team) {
                        $scoredPoints[] = $team['scored'];
                    }
                    echo implode(', ', $scoredPoints);
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'Punti Subiti',
                data: [
                    <?php 
                    $concededPoints = [];
                    foreach ($topTeams as $team) {
                        $concededPoints[] = $team['conceded'];
                    }
                    echo implode(', ', $concededPoints);
                    ?>
                ],
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                r: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>