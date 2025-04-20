<?php
// auth/login.php

// Includi la configurazione
require_once '../config/config.php';

// Inizializza le variabili
$error = '';
$username = '';

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

// Processa il form di login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ottieni e filtra i dati del form
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
    
    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password';
    } else {
        $user = new User();
        if ($user->login($username, $password)) {
            // Login riuscito, reindirizza in base al ruolo
            if (User::isAdmin()) {
                header('Location: ../admin/index.php');
            } else {
                // Controlla se l'utente è approvato
                if (!$user->isApproved()) {
                    header('Location: ../public/pending_approval.php');
                } else {
                    header('Location: ../user/dashboard.php');
                }
            }
            exit;
        } else {
            $error = 'Username o password non validi o account non approvato';
        }
    }
}

// Includi il template header
$pageTitle = 'Login';
include_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Accedi</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="form-group mb-3">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-block">Accedi</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Non hai un account? <a href="register.php">Registrati</a></p>
                    <p class="mt-2 mb-0"><a href="reset_password.php">Password dimenticata?</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>