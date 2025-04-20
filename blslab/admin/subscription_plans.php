<?php
// admin/subscription_plans.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni tutti i piani di abbonamento
$plans = SubscriptionPlan::getAll(false); // Include anche i piani disattivati

// Gestione messaggi di successo e errore
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Includi il template header
$pageTitle = 'Gestione Piani Abbonamento';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Piani Abbonamento</h1>
                <a href="subscription_plan_add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuovo Piano
                </a>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php if ($success == 'add'): ?>
                        Piano abbonamento aggiunto con successo.
                    <?php elseif ($success == 'edit'): ?>
                        Piano abbonamento aggiornato con successo.
                    <?php elseif ($success == 'delete'): ?>
                        Piano abbonamento eliminato con successo.
                    <?php elseif ($success == 'disable'): ?>
                        Piano abbonamento disattivato con successo.
                    <?php elseif ($success == 'enable'): ?>
                        Piano abbonamento attivato con successo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php if ($error == 'delete'): ?>
                        Impossibile eliminare il piano. Ci sono utenti associati a questo piano.
                    <?php elseif ($error == 'default'): ?>
                        Impossibile eliminare il piano predefinito.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
            <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Elenco Piani Abbonamento</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($plans)): ?>
                        <p class="text-center">Nessun piano abbonamento disponibile</p>
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
                                        <th>Predizioni</th>
                                        <th>Durata</th>
                                        <th>Stato</th>
                                        <th>Utenti</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plans as $plan): 
                                        $planObj = new SubscriptionPlan($plan['id']);
                                        $userCount = $planObj->getActiveUserCount();
                                    ?>
                                        <tr class="<?php echo $plan['is_active'] ? '' : 'table-secondary'; ?>">
                                            <td><?php echo $plan['id']; ?></td>
                                            <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                            <td>
                                                <?php if ($plan['price'] == 0): ?>
                                                    <span class="badge bg-success">Gratuito</span>
                                                <?php else: ?>
                                                    <?php echo number_format($plan['price'], 2, ',', '.'); ?> €
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($plan['max_championships'] < 0 || $plan['max_championships'] >= 999): ?>
                                                    <span class="badge bg-info">Illimitati</span>
                                                <?php else: ?>
                                                    <?php echo $plan['max_championships']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($plan['max_teams'] < 0 || $plan['max_teams'] >= 999): ?>
                                                    <span class="badge bg-info">Illimitate</span>
                                                <?php else: ?>
                                                    <?php echo $plan['max_teams']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($plan['has_ads']): ?>
                                                    <span class="badge bg-warning">Sì</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($plan['has_predictions']): ?>
                                                    <span class="badge bg-success">Sì</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $planObj->getFormattedDuration(); ?>
                                            </td>
                                            <td>
                                                <?php if ($plan['is_active']): ?>
                                                    <span class="badge bg-success">Attivo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Disattivato</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $userCount; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="subscription_plan_edit.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <?php if ($plan['is_active']): ?>
                                                        <a href="subscription_plan_disable.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Disattiva">
                                                            <i class="fas fa-power-off"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="subscription_plan_enable.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Attiva">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($userCount == 0 && $plan['id'] != 1): ?>
                                                        <a href="subscription_plan_delete.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger" disabled data-bs-toggle="tooltip" title="Non puoi eliminare un piano con utenti o il piano predefinito">
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
            
            <!-- Informazioni Piani -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Confronto Piani</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        // Filtra solo i piani attivi per il confronto
                        $activePlans = array_filter($plans, function($p) { return $p['is_active'] == 1; });
                        foreach ($activePlans as $plan): 
                            $planObj = new SubscriptionPlan($plan['id']);
                        ?>
                            <div class="col-lg-4 col-md-6 mb-4">
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
                                                    <?php echo number_format($plan['price'], 2, ',', '.'); ?> €/<?php echo $planObj->getFormattedDuration(); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <ul class="list-group mb-3">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Campionati
                                                <?php if ($plan['max_championships'] < 0 || $plan['max_championships'] >= 999): ?>
                                                    <span class="badge bg-primary rounded-pill">Illimitati</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $plan['max_championships']; ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Squadre
                                                <?php if ($plan['max_teams'] < 0 || $plan['max_teams'] >= 999): ?>
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
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Predizioni
                                                <?php if ($plan['has_predictions']): ?>
                                                    <span class="badge bg-success rounded-pill">Sì</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning rounded-pill">No</span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                        
                                        <?php if (!empty($plan['description'])): ?>
                                            <div class="mt-3">
                                                <h6>Caratteristiche:</h6>
                                                <ul class="list-unstyled">
                                                    <?php foreach (explode("\n", $plan['description']) as $feature): ?>
                                                        <li><i class="fas fa-check text-success me-2"></i> <?php echo htmlspecialchars($feature); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3 text-center">
                                            <span class="badge <?php echo $planObj->getActiveUserCount() > 0 ? 'bg-primary' : 'bg-secondary'; ?>">
                                                <?php echo $planObj->getActiveUserCount(); ?> utenti attivi
                                            </span>
                                        </div>
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