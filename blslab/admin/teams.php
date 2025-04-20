<?php
// admin/teams.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni tutte le squadre
$db = Database::getInstance();
$teams = $db->fetchAll("
    SELECT t.*, 
           (SELECT COUNT(*) FROM championships_teams WHERE team_id = t.id) as championship_count
    FROM teams t
    ORDER BY t.name
");

// Includi il template header
$pageTitle = 'Gestione Squadre';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Squadre</h1>
                <a href="team-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuova Squadra
                </a>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php if ($_GET['success'] == 'add'): ?>
                        Squadra aggiunta con successo.
                    <?php elseif ($_GET['success'] == 'edit'): ?>
                        Squadra aggiornata con successo.
                    <?php elseif ($_GET['success'] == 'delete'): ?>
                        Squadra eliminata con successo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php if ($_GET['error'] == 'delete'): ?>
                        Impossibile eliminare la squadra. Verifica che non ci siano campionati o partite associate.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Elenco Squadre</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($teams)): ?>
                        <p class="text-center">Nessuna squadra disponibile</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Logo</th>
                                        <th>Campionati</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teams as $team): ?>
                                        <tr>
                                            <td><?php echo $team['id']; ?></td>
                                            <td><?php echo htmlspecialchars($team['name']); ?></td>
                                            <td>
                                                <?php if (!empty($team['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($team['logo']); ?>" alt="Logo" class="team-logo img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                                <?php else: ?>
                                                    <span class="text-muted">Nessun logo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $team['championship_count']; ?>
                                                <?php if ($team['championship_count'] > 0): ?>
                                                    <a href="championships.php?team_id=<?php echo $team['id']; ?>" class="ms-2 btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="team-edit.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="../public/team.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Visualizza">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($team['championship_count'] == 0): ?>
                                                        <a href="team-delete.php?id=<?php echo $team['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
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