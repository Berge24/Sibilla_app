<?php
// admin/championships.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Filtro per stagione
$seasonId = filter_input(INPUT_GET, 'season_id', FILTER_VALIDATE_INT);
$seasonName = 'Tutte le Stagioni';

if ($seasonId) {
    $season = Season::findById($seasonId);
    if ($season) {
        $seasonName = $season->getName();
    }
}

// Ottieni tutte le stagioni per il dropdown di filtro
$seasons = Season::getAll();

// Ottieni tutti i campionati, eventualmente filtrati per stagione
$db = Database::getInstance();
$query = "
    SELECT c.*, s.name as season_name
    FROM championships c
    JOIN seasons s ON c.season_id = s.id
";

$params = [];

if ($seasonId) {
    $query .= " WHERE c.season_id = ?";
    $params = [$seasonId];
}

$query .= " ORDER BY c.start_date DESC";
$championships = $db->fetchAll($query, $params);

// Per ogni campionato, ottieni separatamente il numero di squadre e partite
if (!empty($championships)) {
    foreach ($championships as &$championship) {
        // Numero squadre
        $teamCount = $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM championships_teams 
            WHERE championship_id = ?
        ", [$championship['id']]);
        
        // Numero partite
        $matchCount = $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM matches 
            WHERE championship_id = ?
        ", [$championship['id']]);
        
        $championship['team_count'] = $teamCount ? intval($teamCount['count']) : 0;
        $championship['match_count'] = $matchCount ? intval($matchCount['count']) : 0;
    }
    unset($championship); // Rimuovi il riferimento all'ultimo elemento
}

// Includi il template header
$pageTitle = 'Gestione Campionati';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Campionati</h1>
                <a href="championship-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuovo Campionato
                </a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php if ($_GET['success'] == 'add'): ?>
                        Campionato aggiunto con successo.
                    <?php elseif ($_GET['success'] == 'edit'): ?>
                        Campionato aggiornato con successo.
                    <?php elseif ($_GET['success'] == 'delete'): ?>
                        Campionato eliminato con successo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php if ($_GET['error'] == 'delete'): ?>
                        Impossibile eliminare il campionato. Verifica che non ci siano partite associate.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtro per stagione -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Filtra per Stagione</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="d-flex align-items-center">
                        <div class="me-2 flex-grow-1">
                            <select name="season_id" id="season_id" class="form-select">
                                <option value="">Tutte le Stagioni</option>
                                <?php foreach ($seasons as $season): ?>
                                    <option value="<?php echo $season['id']; ?>" <?php echo ($seasonId == $season['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($season['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Filtra</button>
                        <?php if ($seasonId): ?>
                            <a href="championships.php" class="btn btn-outline-secondary ms-2">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Campionati - <?php echo htmlspecialchars($seasonName); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($championships)): ?>
                        <p class="text-center">Nessun campionato disponibile</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Stagione</th>
                                        <th>Data Inizio</th>
                                        <th>Data Fine</th>
                                        <th>Squadre</th>
                                        <th>Partite</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($championships as $championship): ?>
                                        <?php 
                                        // Determina lo stato del campionato
                                        $now = new DateTime();
                                        $startDate = new DateTime($championship['start_date']);
                                        $endDate = new DateTime($championship['end_date']);
                                        
                                        if ($now < $startDate) {
                                            $status = 'Non iniziato';
                                            $statusClass = 'text-warning';
                                        } elseif ($now > $endDate) {
                                            $status = 'Terminato';
                                            $statusClass = 'text-danger';
                                        } else {
                                            $status = 'In corso';
                                            $statusClass = 'text-success';
                                        }
                                        
                                        // Assicurati che i conteggi siano definiti
                                        $teamCount = isset($championship['team_count']) ? intval($championship['team_count']) : 0;
                                        $matchCount = isset($championship['match_count']) ? intval($championship['match_count']) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $championship['id']; ?></td>
                                            <td><?php echo htmlspecialchars($championship['name']); ?></td>
                                            <td>
                                                <?php if ($championship['type'] == CHAMPIONSHIP_TYPE_CSI): ?>
                                                    <span class="badge bg-primary">CSI</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">UISP</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($championship['season_name']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($championship['start_date'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($championship['end_date'])); ?></td>
                                            <td>
                                                <?php echo $teamCount; ?>
                                                <a href="championship-teams.php?id=<?php echo $championship['id']; ?>" class="ms-1 btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-users"></i>
                                                </a>
                                            </td>
                                            <td>
                                                <?php echo $matchCount; ?>
                                                <a href="matches.php?championship_id=<?php echo $championship['id']; ?>" class="ms-1 btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-calendar"></i>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="championship-edit.php?id=<?php echo $championship['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="../public/championship.php?id=<?php echo $championship['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Visualizza">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="match-add.php?championship_id=<?php echo $championship['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Aggiungi Partita">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                    <?php if ($matchCount == 0): ?>
                                                        <a href="championship-delete.php?id=<?php echo $championship['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger" disabled data-bs-toggle="tooltip" title="Non eliminabile">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>