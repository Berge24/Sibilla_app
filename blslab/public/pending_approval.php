<?php
// public/pending_approval.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia loggato
if (!User::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni l'utente corrente
$user = User::getCurrentUser();

// Se l'utente è approvato o è un amministratore, reindirizza alla dashboard
if ($user->isApproved() || User::isAdmin()) {
    header('Location: ../user/dashboard.php');
    exit;
}

// Includi il template header
$pageTitle = 'Account in Attesa di Approvazione';
include_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow text-center">
                <div class="card-body p-5">
                    <div class="display-1 text-warning mb-4">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h1 class="mb-4">Account in Attesa di Approvazione</h1>
                    <p class="lead mb-4">
                        Grazie per esserti registrato! Il tuo account è in fase di revisione da parte dei nostri amministratori.
                    </p>
                    <p class="mb-4">
                        Ti informeremo tramite email quando il tuo account sarà stato approvato. 
                        Nel frattempo, puoi esplorare le parti pubbliche del sito o aggiornare il tuo profilo.
                    </p>
                    
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Dettagli Account</h5>
                            <p class="mb-2"><strong>Username:</strong> <?php echo htmlspecialchars($user->getUsername()); ?></p>
                            <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($user->getEmail()); ?></p>
                            <p class="mb-2"><strong>Data di registrazione:</strong> 
                                <?php
                                $db = Database::getInstance();
                                $userData = $db->fetchOne("SELECT created_at FROM users WHERE id = ?", [$user->getId()]);
                                echo date('d/m/Y H:i', strtotime($userData['created_at']));
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Il processo di approvazione richiede generalmente 24-48 ore lavorative.
                    </div>
                    
                    <div class="mt-4">
                        <a href="../user/profile.php" class="btn btn-primary me-2">
                            <i class="fas fa-user-edit me-1"></i> Aggiorna Profilo
                        </a>
                        <a href="../public/index.php" class="btn btn-secondary">
                            <i class="fas fa-home me-1"></i> Vai alla Homepage
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <a href="../auth/logout.php" class="btn btn-link text-danger">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>