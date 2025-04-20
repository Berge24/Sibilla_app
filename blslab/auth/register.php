<?php
// auth/register.php

// Includi la configurazione
require_once '../config/config.php';

// Inizializza le variabili
$error = '';
$success = '';
$username = '';
$email = '';

// Se l'utente è già loggato, reindirizza alla dashboard
if (User::isLoggedIn()) {
    if (User::isAdmin()) {
        header('Location: ../admin/index.php');
    } else {
        $user = User::getCurrentUser();
        if ($user && !$user->isApproved()) {
            header('Location: ../public/pending_approval.php');
        } else {
            header('Location: ../user/dashboard.php');
        }
    }
    exit;
}

// Processa il form di registrazione
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
    $passwordConfirm = filter_input(INPUT_POST, 'password_confirm', FILTER_UNSAFE_RAW);
    
    // Validazione base
    if (empty($username) || empty($email) || empty($password) || empty($passwordConfirm)) {
        $error = 'Tutti i campi sono obbligatori';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email non valida';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Le password non corrispondono';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
    } else {
        // Tenta la registrazione
        $user = new User();
        $result = $user->register($username, $password, $email);
        
        if ($result !== false) {
            $success = 'Registrazione completata con successo. La tua richiesta è in fase di revisione. Verrai notificato via email quando il tuo account sarà approvato.';
            // Reset dei campi dopo successo
            $username = '';
            $email = '';
        } else {
            $error = 'Username o email già in uso';
        }
    }
}

// Includi il template header
$pageTitle = 'Registrazione';
include_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Registrazione</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-primary">Vai al Login</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Dopo la registrazione, il tuo account dovrà essere approvato prima di poter accedere a tutte le funzionalità.
                        </div>
                        
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <div class="form-group mb-3">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">La password deve essere di almeno 6 caratteri.</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="password_confirm">Conferma Password</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-block">Registrati</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Hai già un account? <a href="login.php">Accedi</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>