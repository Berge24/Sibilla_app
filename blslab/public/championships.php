<?php
// public/championships.php

// Includi la configurazione
require_once '../config/config.php';

// Ottieni parametri di filtro
$seasonId = filter_input(INPUT_GET, 'season_id', FILTER_VALIDATE_INT);

// Se non è specificata una stagione, usa la stagione corrente
if (!$seasonId) {
    $currentSeason = Season::getCurrentSeason();
    if ($currentSeason) {
        $seasonId = $currentSeason->getId();
    }
}

// Ottieni database
$db = Database::getInstance();

// Ottieni tutte le stagioni per il dropdown di filtro
$seasons = Season::getAll();

// Ottieni i campionati filtrati per stagione
$query = "
    SELECT c.*, 
           s.name as season_name
    FROM championships c
    JOIN seasons s ON c.season_id = s.id
";

$params = [];

if ($seasonId) {
    $query .= " WHERE c.season_id = ?";
    $params[] = $seasonId;
    
    // Ottieni informazioni sulla stagione selezionata
    $selectedSeason = Season::findById($seasonId);
    $seasonName = $selectedSeason ? $selectedSeason->getName() : 'Stagione selezionata';
} else {
    $seasonName = 'Tutte le stagioni';
}

$query .= " ORDER BY c.start_date DESC";
$championships = $db->fetchAll($query, $params);

// Calcola il numero di squadre e partite per ogni campionato
if (!empty($championships)) {
    foreach ($championships as &$championship) {
        // Conteggio squadre
        $teamCount = $db->fetchColumn("
            SELECT COUNT(*) 
            FROM championships_teams 
            WHERE championship_id = ?
        ", [$championship['id']]);
        
        // Conteggio partite
        $matchCount = $db->fetchColumn("
            SELECT COUNT(*) 
            FROM matches 
            WHERE championship_id = ?
        ", [$championship['id']]);
        
        $championship['team_count'] = $teamCount ? intval($teamCount) : 0;
        $championship['match_count'] = $matchCount ? intval($matchCount) : 0;
    }
    unset($championship); // Rimuovi il riferimento all'ultimo elemento
}

// Includi il template header
$pageTitle = 'Campionati';
include_once '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Campionati</h1>
    
    <!-- Filtro per stagione -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Seleziona Stagione</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="season_id" class="form-label">Stagione</label>
                    <select class="form-select" id="season_id" name="season_id" onchange="this.form.submit()">
                        <option value="">Tutte le Stagioni</option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?php echo $season['id']; ?>" <?php echo ($seasonId == $season['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($season['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary">Filtra</button>
                    <?php if ($seasonId): ?>
                        <a href="championships.php" class="btn btn-outline-secondary ms-2">Mostra tutti</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Elenco campionati -->
    <h2 class="mb-3">Campionati - <?php echo htmlspecialchars($seasonName); ?></h2>
    
    <?php if (empty($championships)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Nessun campionato disponibile per questa stagione.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($championships as $championship): ?>
                <?php 
                // Determina lo stato del campionato
                $now = new DateTime();
                $startDate = new DateTime($championship['start_date']);
                $endDate = new DateTime($championship['end_date']);
                
                if ($now < $startDate) {
                    $status = 'Non iniziato';
                    $statusClass = 'warning';
                } elseif ($now > $endDate) {
                    $status = 'Terminato';
                    $statusClass = 'secondary';
                } else {
                    $status = 'In corso';
                    $statusClass = 'success';
                }
                ?>
                
                <div class="col">
                    <div class="card h-100 shadow-sm card-championship">
                        <div class="card-header bg-<?php echo ($championship['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?> text-white">
                            <h5 class="mb-0"><?php echo htmlspecialchars($championship['name']); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-<?php echo ($championship['type'] == CHAMPIONSHIP_TYPE_CSI) ? 'primary' : 'info'; ?>">
                                    <?php echo $championship['type']; ?>
                                </span>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </div>
                            
                            <p class="card-text">
                                <strong>Stagione:</strong> <?php echo htmlspecialchars($championship['season_name']); ?><br>
                                <strong>Periodo:</strong> <?php echo date('d/m/Y', strtotime($championship['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($championship['end_date'])); ?><br>
                                <strong>Squadre:</strong> <?php echo isset($championship['team_count']) ? $championship['team_count'] : 0; ?><br>
                                <strong>Partite:</strong> <?php echo isset($championship['match_count']) ? $championship['match_count'] : 0; ?>
                            </p>
                            
                            <?php if (!empty($championship['description'])): ?>
                                <p class="card-text text-muted">
                                    <?php echo mb_substr(htmlspecialchars($championship['description']), 0, 80); ?>
                                    <?php if (mb_strlen($championship['description']) > 80): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-grid gap-2">
                                <a href="championship.php?id=<?php echo $championship['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-trophy"></i> Visualizza Dettagli
                                </a>
                            </div>
                            <div class="btn-group w-100 mt-2">
                                <a href="teams.php?championship_id=<?php echo $championship['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-users"></i> Squadre
                                </a>
                                <a href="matches.php?championship_id=<?php echo $championship['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-calendar"></i> Partite
                                </a>
                                <a href="standings.php?championship_id=<?php echo $championship['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-table"></i> Classifica
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>