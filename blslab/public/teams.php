<?php
// public/teams.php

// Includi la configurazione
require_once '../config/config.php';

// Ottieni parametri di filtro
$championshipId = filter_input(INPUT_GET, 'championship_id', FILTER_VALIDATE_INT);

// Ottieni tutte le squadre
$db = Database::getInstance();

if ($championshipId) {
    // Se è specificato un campionato, ottieni solo le squadre di quel campionato
    $teams = $db->fetchAll("
        SELECT t.*, 
               (SELECT COUNT(*) FROM championships_teams WHERE team_id = t.id) as championship_count
        FROM teams t
        JOIN championships_teams ct ON t.id = ct.team_id
        WHERE ct.championship_id = ?
        ORDER BY t.name
    ", [$championshipId]);
    
    // Ottieni info sul campionato
    $championship = Championship::findById($championshipId);
    $pageTitle = 'Squadre del Campionato ' . ($championship ? $championship->getName() : '');
} else {
    // Altrimenti, ottieni tutte le squadre
    $teams = $db->fetchAll("
        SELECT t.*, 
               (SELECT COUNT(*) FROM championships_teams WHERE team_id = t.id) as championship_count
        FROM teams t
        ORDER BY t.name
    ");
    
    $pageTitle = 'Tutte le Squadre';
}

// Includi il template header
include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $pageTitle; ?></h1>
        <?php if ($championshipId && $championship): ?>
            <a href="championship.php?id=<?php echo $championshipId; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Torna al Campionato
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($teams)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Nessuna squadra disponibile.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($teams as $team): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <?php if (!empty($team['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($team['logo']); ?>" alt="Logo" 
                                        class="team-logo img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: contain;">
                                <?php else: ?>
                                    <div class="team-logo-placeholder me-3">
                                        <i class="fas fa-shield-alt fa-2x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($team['name']); ?></h5>
                            </div>
                            
                            <?php if (!empty($team['description'])): ?>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($team['description'], 0, 100))); ?>
                                    <?php if (strlen($team['description']) > 100): ?>...<?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="card-text text-muted">Nessuna descrizione disponibile</p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <span class="badge bg-primary"><?php echo $team['championship_count']; ?> campionati</span>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="team.php?id=<?php echo $team['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-info-circle"></i> Dettagli
                            </a>
                            
                            <?php if (!$championshipId): ?>
                                <a href="matches.php?team_id=<?php echo $team['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-calendar"></i> Partite
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../includes/footer.php'; ?>