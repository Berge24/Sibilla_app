<?php
// admin/users.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni tutti gli utenti
$users = User::getAll();

// Ottieni utenti in attesa di approvazione
$pendingUsers = User::getPendingApproval();

// Gestione messaggi di successo e errore
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Includi il template header
$pageTitle = 'Gestione Utenti';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestione Utenti</h1>
                <a href="user-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuovo Utente
                </a>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php if ($success == 'add'): ?>
                        Utente aggiunto con successo.
                    <?php elseif ($success == 'edit'): ?>
                        Utente aggiornato con successo.
                    <?php elseif ($success == 'delete'): ?>
                        Utente eliminato con successo.
                    <?php elseif ($success == 'approve'): ?>
                        Utente approvato con successo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php if ($error == 'delete'): ?>
                        Impossibile eliminare l'utente.
                    <?php elseif ($error == 'approve'): ?>
                        Impossibile approvare l'utente.
                    <?php else: ?>
                        Si è verificato un errore. Riprova.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Utenti in attesa di approvazione -->
            <?php if (!empty($pendingUsers)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-warning">
                        <h6 class="m-0 font-weight-bold text-white">Utenti in attesa di approvazione</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Abbonamento</th>
                                        <th>Data Registrazione</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingUsers as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($user['subscription_name']); ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="user-approve.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Approva">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="user-delete.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tutti gli utenti -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Elenco Utenti</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <p class="text-center">Nessun utente disponibile</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Ruolo</th>
                                        <th>Abbonamento</th>
                                        <th>Stato</th>
                                        <th>Data Registrazione</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['role'] == USER_ROLE_ADMIN): ?>
                                                    <span class="badge bg-danger">Amministratore</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Utente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($user['subscription_name'])): ?>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($user['subscription_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Free</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['is_approved']): ?>
                                                    <span class="badge bg-success">Approvato</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">In attesa</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if (!$user['is_approved']): ?>
                                                        <a href="user-approve.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Approva">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Modifica">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="user-delete.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="tooltip" title="Elimina">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger" disabled data-bs-toggle="tooltip" title="Non puoi eliminare te stesso">
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
            
            <!-- Informazioni Ruoli -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informazioni sui Ruoli</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">Amministratore</h5>
                                </div>
                                <div class="card-body">
                                    <p>Gli utenti con ruolo amministratore possono:</p>
                                    <ul>
                                        <li>Gestire stagioni, campionati, squadre e partite</li>
                                        <li>Gestire altri utenti</li>
                                        <li>Gestire abbonamenti</li>
                                        <li>Approvare nuovi utenti</li>
                                        <li>Calcolare classifiche e statistiche</li>
                                        <li>Accedere a tutte le funzionalità dell'applicazione</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0">Utente</h5>
                                </div>
                                <div class="card-body">
                                    <p>Gli utenti con ruolo utente standard possono:</p>
                                    <ul>
                                        <li>Gestire propri campionati, squadre e partite (secondo i limiti dell'abbonamento)</li>
                                        <li>Visualizzare classifiche e statistiche</li>
                                        <li>Gestire il proprio profilo e abbonamento</li>
                                        <li>Non possono gestire altri utenti o abbonamenti</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>