<?php
// admin/seasons.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni l'istanza del database
$db = Database::getInstance();

// Prima ottieni tutte le stagioni
$seasons = $db->fetchAll("
    SELECT * FROM seasons 
    ORDER BY start_date DESC
");

// Poi, per ogni stagione, ottieni il conteggio dei campionati
if (!empty($seasons)) {
    foreach ($seasons as &$season) {
        $count = $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM championships 
            WHERE season_id = ?
        ", [$season['id']]);
        
        $season['championship_count'] = $count ? intval($count['count']) : 0;
    }
    unset($season); // Importante: rimuovi il riferimento all'ultimo elemento
}

// Includi il template header
$pageTitle = 'Gestione Stagioni';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Stagioni</h1>
                <a href="season-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuova Stagione
                </a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php if ($_GET['success'] == 'add'): ?>
                        Stagione aggiunta con successo.
                    <?php elseif ($_GET['success'] == 'edit'): ?>
                        Stagione aggiornata con successo.
                    <?php elseif ($_GET['success'] == 'delete'): ?>
                        Stagione eliminata con successo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php if ($_GET['error'] == 'delete'): ?>
                        Impossibile eliminare la stagione. Verifica che non ci siano campionati associati.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Elenco Stagioni</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($seasons)): ?>
                        <p class="text-center">Nessuna stagione disponibile</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Data Inizio</th>
                                        <th>Data Fine</th>
                                        <th>Campionati</th>
                                        <th>Stato</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seasons as $season): ?>
                                        <?php 
                                        // Determina lo stato della stagione
                                        $now = new DateTime();
                                        $startDate = new DateTime($season['start_date']);
                                        $endDate = new DateTime($season['end_date']);
                                        
                                        if ($now < $startDate) {
                                            $status = 'Non iniziata';
                                            $statusClass = 'text-warning';
                                        } elseif ($now > $endDate) {
                                            $status = 'Terminata';
                                            $statusClass = 'text-danger';
                                        } else {
                                            $status = 'In corso';
                                            $statusClass = 'text-success';
                                        }
                                        
                                        // Assicurati che championship_count esista e sia un numero
                                        $championshipCount = isset($season['championship_count']) ? intval($season['championship_count']) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $season['id']; ?></td>
                                            <td><?php echo htmlspecialchars($season['name']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($season['start_date'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($season['end_date'])); ?></td>
                                            <td>
                                                <?php echo $championshipCount; ?>
                                                <?php if ($championshipCount > 0): ?>
                                                    <a href="championships.php?season_id=<?php echo $season['id']; ?>" class="ms-2 btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="<?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="season-edit.php?id=<?php echo $season['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="championship-add.php?season_id=<?php echo $season['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Aggiungi Campionato">
                                                        <i class="fas fa-trophy"></i>
                                                    </a>
                                                    <?php if ($championshipCount == 0): ?>
                                                        <a href="season-delete.php?id=<?php echo $season['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
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