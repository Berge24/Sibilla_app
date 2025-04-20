<?php
// admin/subscriptions.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni tutti gli abbonamenti
$subscriptions = Subscription::getAll();

// Gestione messaggi di successo e errore
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Includi il template header
$pageTitle = 'Gestione Abbonamenti';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Abbonamenti</h1>
                <a href="subscription-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuovo Abbonamento
                </a>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php if ($success == 'add'): ?>
                        Abbonamento aggiunto con successo.
                    <?php elseif ($success == 'edit'): ?>
                        Abbonamento aggiornato con successo.
                    <?php elseif ($success == 'delete'): ?>
                        Abbonamento eliminato con successo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php if ($error == 'delete'): ?>
                        Impossibile eliminare l'abbonamento. Ci sono utenti associati a questo piano.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Elenco Abbonamenti</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($subscriptions)): ?>
                        <p class="text-center">Nessun abbonamento disponibile</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Prezzo</th>
                                        <th>Max Campionati</th>
                                        <th>Max Squadre</th>
                                        <th>Pubblicità</th>
                                        <th>Utenti</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $subscription): 
                                        $sub = new Subscription($subscription['id']);
                                        $userCount = $sub->getUserCount();
                                    ?>
                                        <tr>
                                            <td><?php echo $subscription['id']; ?></td>
                                            <td><?php echo htmlspecialchars($subscription['name']); ?></td>
                                            <td>
                                                <?php if ($subscription['price'] == 0): ?>
                                                    <span class="badge bg-success">Gratuito</span>
                                                <?php else: ?>
                                                    <?php echo number_format($subscription['price'], 2, ',', '.'); ?> €
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($subscription['max_championships'] < 0): ?>
                                                    <span class="badge bg-info">Illimitati</span>
                                                <?php else: ?>
                                                    <?php echo $subscription['max_championships']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($subscription['max_teams'] < 0): ?>
                                                    <span class="badge bg-info">Illimitate</span>
                                                <?php else: ?>
                                                    <?php echo $subscription['max_teams']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($subscription['has_ads']): ?>
                                                    <span class="badge bg-warning">Sì</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $userCount; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="subscription-edit.php?id=<?php echo $subscription['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($userCount == 0): ?>
                                                        <a href="subscription-delete.php?id=<?php echo $subscription['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger" disabled data-bs-toggle="tooltip" title="Non puoi eliminare un abbonamento con utenti">
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
            
            <!-- Informazioni Abbonamenti -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informazioni sui Piani</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($subscriptions as $plan): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header <?php echo ($plan['price'] == 0) ? 'bg-secondary' : (($plan['price'] < 15) ? 'bg-primary' : 'bg-danger'); ?> text-white">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($plan['name']); ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="h5">
                                                <?php if ($plan['price'] == 0): ?>
                                                    Gratuito
                                                <?php else: ?>
                                                    <?php echo number_format($plan['price'], 2, ',', '.'); ?> €
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <ul class="list-group mb-3">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Campionati
                                                <?php if ($plan['max_championships'] < 0): ?>
                                                    <span class="badge bg-primary rounded-pill">Illimitati</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $plan['max_championships']; ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Squadre
                                                <?php if ($plan['max_teams'] < 0): ?>
                                                    <span class="badge bg-primary rounded-pill">Illimitate</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $plan['max_teams']; ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Pubblicità
                                                <?php if ($plan['has_ads']): ?>
                                                    <span class="badge bg-warning rounded-pill">Sì</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success rounded-pill">No</span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                        <?php if (!empty($plan['features'])): ?>
                                            <div class="mt-3">
                                                <h6>Caratteristiche:</h6>
                                                <ul class="list-unstyled">
                                                    <?php foreach (explode("\n", $plan['features']) as $feature): ?>
                                                        <li><i class="fas fa-check text-success me-2"></i> <?php echo htmlspecialchars($feature); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>