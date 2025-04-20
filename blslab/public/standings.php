<?php
// public/standings.php

// Includi la configurazione
require_once '../config/config.php';

// Ottieni parametri di filtro
$championshipId = filter_input(INPUT_GET, 'championship_id', FILTER_VALIDATE_INT);
$seasonId = filter_input(INPUT_GET, 'season_id', FILTER_VALIDATE_INT);

// Se non è specificato un campionato o una stagione, usa la stagione corrente
if (!$championshipId && !$seasonId) {
    $currentSeason = Season::getCurrentSeason();
    if ($currentSeason) {
        $seasonId = $currentSeason->getId();
    }
}

// Ottieni tutte le stagioni per il dropdown di filtro
$db = Database::getInstance();
$seasons = Season::getAll();

// Ottieni i campionati in base al filtro
$championships = [];
$selectedChampionshipId = null;

if ($seasonId) {
    $championships = $db->fetchAll("
        SELECT * FROM championships 
        WHERE season_id = ? 
        ORDER BY name
    ", [$seasonId]);
    
    // Se abbiamo trovato campionati e il parametro championship_id è fornito e valido
    if (!empty($championships) && $championshipId) {
        // Verifica che il campionato selezionato faccia parte della stagione selezionata
        $isValidChampionship = false;
        foreach ($championships as $c) {
            if ($c['id'] == $championshipId) {
                $isValidChampionship = true;
                break;
            }
        }
        
        if ($isValidChampionship) {
            $selectedChampionshipId = $championshipId;
        } else {
            // Il campionato richiesto non appartiene alla stagione selezionata, 
            // quindi scegliamo il primo campionato disponibile
            $selectedChampionshipId = $championships[0]['id'];
        }
    } 
    // Se championship_id non è valido o non è stato fornito, ma abbiamo campionati
    elseif (!empty($championships)) {
        $selectedChampionshipId = $championships[0]['id'];
    }
} 
// Se è specificato solo il campionato (senza stagione)
elseif ($championshipId) {
    // Ottieni info sul campionato specificato
    $championshipInfo = $db->fetchOne("
        SELECT c.*, s.id as season_id 
        FROM championships c
        JOIN seasons s ON c.season_id = s.id
        WHERE c.id = ?
    ", [$championshipId]);
    
    if ($championshipInfo) {
        $seasonId = $championshipInfo['season_id'];
        $selectedChampionshipId = $championshipId;
        
        // Ottieni tutti i campionati di questa stagione
        $championships = $db->fetchAll("
            SELECT * FROM championships 
            WHERE season_id = ? 
            ORDER BY name
        ", [$seasonId]);
    }
}

// Ottieni le classifiche del campionato selezionato
$standings = [];
$championshipInfo = null;

if ($selectedChampionshipId) {
    $championshipInfo = Championship::findById($selectedChampionshipId);
    
    if ($championshipInfo) {
        $standings = Standing::getByChampionship($selectedChampionshipId);
    }
}

// Includi il template header
$pageTitle = 'Classifiche';
include_once '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Classifiche</h1>
    
    <!-- Filtri -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Seleziona Campionato</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="season_id" class="form-label">Stagione</label>
                    <select class="form-select" id="season_id" name="season_id" onchange="this.form.submit()">
                        <option value="">Seleziona Stagione</option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?php echo $season['id']; ?>" <?php echo ($seasonId == $season['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($season['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-5">
                    <label for="championship_id" class="form-label">Campionato</label>
                    <select class="form-select" id="championship_id" name="championship_id" onchange="this.form.submit()" <?php echo empty($championships) ? 'disabled' : ''; ?>>
                        <option value="">Seleziona Campionato</option>
                        <?php foreach ($championships as $championship): ?>
                            <option value="<?php echo $championship['id']; ?>" <?php echo ($selectedChampionshipId == $championship['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($championship['name']); ?> 
                                (<?php echo $championship['type']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtra</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (empty($standings)): ?>
        <div class="alert alert-info">
            Seleziona un campionato per visualizzare la classifica.
        </div>
    <?php else: ?>
        <!-- Classifica -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="m-0 font-weight-bold text-primary">
                    Classifica: <?php echo htmlspecialchars($championshipInfo->getName()); ?>
                    <span class="badge bg-<?php echo ($championshipInfo->getType() == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?> ms-2">
                        <?php echo $championshipInfo->getType(); ?>
                    </span>
                </h5>
                
                <?php if (!empty($standings) && isset($standings[0]['last_calculated'])): ?>
                    <small class="text-muted">
                        Aggiornata al: <?php echo date('d/m/Y H:i', strtotime($standings[0]['last_calculated'])); ?>
                    </small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-standings">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">Pos.</th>
                                <th>Squadra</th>
                                <th class="text-center">Pt</th>
                                <th class="text-center">G</th>
                                <th class="text-center">V</th>
                                <!--<th class="text-center">P</th>-->
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
                                        <a href="team.php?id=<?php echo $standing['team_id']; ?>">
                                            <?php echo htmlspecialchars($standing['team_name']); ?>
                                        </a>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo $standing['points']; ?></td>
                                    <td class="text-center"><?php echo $standing['played']; ?></td>
                                    <td class="text-center"><?php echo $standing['won']; ?></td>
                                    <!--<td class="text-center"><?php echo $standing['drawn']; ?></td>-->
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
                                        <?php if ($standing['win_probability'] > 0): ?>
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
                
                <!-- Legenda -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0">Legenda</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Pt</strong>: Punti</p>
                                <p class="mb-1"><strong>G</strong>: Partite giocate</p>
                                <p class="mb-1"><strong>V</strong>: Vittorie</p>
                                <!--<p class="mb-1"><strong>P</strong>: Pareggi</p>-->
                                <p class="mb-1"><strong>S</strong>: Sconfitte</p>
                                <p class="mb-1"><strong>PF</strong>: Punti fatti</p>
                                <p class="mb-1"><strong>PS</strong>: Punti subiti</p>
                                <p class="mb-1"><strong>DP</strong>: Differenza punti</p>
                                <p class="mb-0"><strong>Prob. Vittoria</strong>: Probabilità di vittoria del campionato</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0">Sistema di Punteggio</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($championshipInfo && $championshipInfo->getType() == CHAMPIONSHIP_TYPE_CSI): ?>
                                    <h6>Campionato CSI:</h6>
                                    <p class="mb-1">• <strong><?php echo CSI_POINTS_WIN; ?> punti</strong> per vittoria</p>
                                    <!--<p class="mb-1">• <strong><?php echo CSI_POINTS_DRAW; ?> punto</strong> per pareggio</p>-->
                                    <p class="mb-0">• <strong><?php echo CSI_POINTS_LOSS; ?> punti</strong> per sconfitta</p>
                                <?php elseif ($championshipInfo && $championshipInfo->getType() == CHAMPIONSHIP_TYPE_UISP): ?>
                                    <h6>Campionato UISP:</h6>
                                    <p class="mb-1">• <strong><?php echo UISP_POINTS_PERIOD_WIN; ?> punti</strong> per tempo vinto</p>
                                    <!--<p class="mb-1">• <strong><?php echo UISP_POINTS_PERIOD_DRAW; ?> punti</strong> per tempo pareggiato</p>-->
                                    <p class="mb-1">• <strong><?php echo UISP_POINTS_PERIOD_LOSS; ?> punto</strong> per tempo perso</p>
                                    <!--<p class="mb-0">• <strong><?php echo UISP_POINTS_BONUS; ?> punto bonus</strong> in caso di parità nei punti, alla squadra con più gol totali</p>-->
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>