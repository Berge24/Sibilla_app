<?php
// user/profile.php

// Includi la configurazione
require_once '../config/config.php';

// Verifica che l'utente sia loggato
if (!User::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Ottieni l'utente corrente
$user = User::getCurrentUser();

// Verifica che l'utente sia approvato
if (!$user->isApproved() && !User::isAdmin()) {
    header('Location: ../public/pending_approval.php');
    exit;
}

// Inizializza le variabili
$error = '';
$success = '';
$username = $user->getUsername();
$email = $user->getEmail();

// Processa il form per l'aggiornamento del profilo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    // Validazione
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Inserisci un indirizzo email valido';
    } else {
        // Aggiorna il profilo
        if ($user->update(['email' => $email])) {
            $success = 'Profilo aggiornato con successo';
        } else {
            $error = 'Si è verificato un errore durante l\'aggiornamento del profilo';
        }
    }
}

// Processa il form per il cambio password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = filter_input(INPUT_POST, 'current_password', FILTER_UNSAFE_RAW);
    $new_password = filter_input(INPUT_POST, 'new_password', FILTER_UNSAFE_RAW);
    $confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_UNSAFE_RAW);
    
    // Validazione
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Tutti i campi sono obbligatori';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Le nuove password non corrispondono';
    } elseif (strlen($new_password) < 6) {
        $error = 'La nuova password deve essere di almeno 6 caratteri';
    } else {
        // Verifica la password corrente
        $db = Database::getInstance();
        $userData = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$user->getId()]);
        
        if (!$userData || !password_verify($current_password, $userData['password'])) {
            $error = 'Password corrente non valida';
        } else {
            // Aggiorna la password
            if ($user->changePassword($new_password)) {
                $success = 'Password cambiata con successo';
            } else {
                $error = 'Si è verificato un errore durante il cambio password';
            }
        }
    }
}

// Includi il template header
$pageTitle = 'Profilo Utente';
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once '../includes/user_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Profilo Utente</h1>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Torna alla Dashboard
                </a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Informazioni Profilo -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Informazioni Personali</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
                                    <div class="form-text">Lo username non può essere modificato</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label required">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Ruolo</label>
                                    <input type="text" class="form-control" value="<?php echo $user->getRole() === 'admin' ? 'Amministratore' : 'Utente'; ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Data Registrazione</label>
                                    <?php 
                                    $db = Database::getInstance();
                                    $userData = $db->fetchOne("SELECT created_at FROM users WHERE id = ?", [$user->getId()]);
                                    $created_at = $userData ? $userData['created_at'] : '';
                                    ?>
                                    <input type="text" class="form-control" value="<?php echo $created_at ? date('d/m/Y H:i', strtotime($created_at)) : 'N/A'; ?>" readonly>
                                </div>
                                
                                <input type="hidden" name="update_profile" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Aggiorna Profilo
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Cambio Password -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Cambia Password</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label required">Password Attuale</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label required">Nuova Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">La password deve essere di almeno 6 caratteri</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label required">Conferma Nuova Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <input type="hidden" name="change_password" value="1">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key"></i> Cambia Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Informazioni Abbonamento -->
                <div class="col-md-12 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Dettagli Abbonamento</h6>
                            <a href="subscription.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-cog"></i> Gestisci Abbonamento
                            </a>
                        </div>
                        <div class="card-body">
                            <?php
                            $subscription = $user->getSubscription();
                            $isExpired = $user->isSubscriptionExpired();
                            ?>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <p><strong>Piano:</strong> <?php echo htmlspecialchars($subscription['name']); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p>
                                        <strong>Prezzo:</strong> 
                                        <?php if ($subscription['price'] == 0): ?>
                                            <span class="badge bg-success">Gratuito</span>
                                        <?php else: ?>
                                            <?php echo number_format($subscription['price'], 2, ',', '.'); ?> €/mese
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p>
                                        <strong>Stato:</strong>
                                        <?php if ($isExpired): ?>
                                            <span class="badge bg-danger">Scaduto</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Attivo</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <p>
                                        <strong>Data attivazione:</strong> 
                                        <?php echo $user->getSubscriptionStart() ? date('d/m/Y', strtotime($user->getSubscriptionStart())) : 'N/A'; ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p>
                                        <strong>Data scadenza:</strong>
                                        <?php if ($user->getSubscriptionEnd()): ?>
                                            <span class="<?php echo $isExpired ? 'text-danger' : ''; ?>">
                                                <?php echo date('d/m/Y', strtotime($user->getSubscriptionEnd())); ?>
                                            </span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <p>
                                        <strong>Pubblicità:</strong>
                                        <?php if ($subscription['has_ads']): ?>
                                            <span class="badge bg-warning">Sì</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">No</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <p><strong>Limite Campionati:</strong> 
                                        <?php if ($subscription['max_championships'] < 0): ?>
                                            Illimitati
                                        <?php else: ?>
                                            <?php echo $subscription['max_championships']; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Limite Squadre:</strong> 
                                        <?php if ($subscription['max_teams'] < 0): ?>
                                            Illimitate
                                        <?php else: ?>
                                            <?php echo $subscription['max_teams']; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($isExpired): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Il tuo abbonamento è scaduto. <a href="subscription.php" class="alert-link">Rinnova ora</a> per continuare a usufruire di tutte le funzionalità.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>