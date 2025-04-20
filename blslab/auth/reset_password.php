<?php
// auth/reset_password.php

// Includi la configurazione
require_once '../config/config.php';

// Inizializza le variabili
$error = '';
$success = '';
$email = '';
$token = '';
$password = '';
$passwordConfirm = '';

// Step 1: Richiesta reset password (form email)
if (!isset($_GET['token']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    $step = 'request';
}
// Step 2: Verifica token e inserimento nuova password
elseif (isset($_GET['token'])) {
    $step = 'reset';
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
    
    // Verifica che il token sia valido
    $db = Database::getInstance();
    $resetInfo = $db->fetchOne("
        SELECT * FROM password_resets 
        WHERE token = ? AND expires_at > NOW()
    ", [$token]);
    
    if (!$resetInfo) {
        $error = 'Token non valido o scaduto. Richiedi un nuovo link per il reset della password.';
        $step = 'request';
    } else {
        $email = $resetInfo['email'];
    }
}
// Step 3: Processo del form di richiesta
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'request') {
    $step = 'request';
    
    // Ottieni e filtra email
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Inserisci un indirizzo email valido';
    } else {
        // Verifica che l'email sia associata a un utente
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            // Non rivelare se l'email esiste o meno per motivi di sicurezza
            $success = 'Se l\'indirizzo email è associato a un account, riceverai un link per il reset della password.';
        } else {
            // Genera un token di reset
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 ora di validità
            
            // Elimina eventuali token precedenti per questo utente
            $db->delete('password_resets', 'email = ?', [$email]);
            
            // Inserisci il nuovo token
            $db->insert('password_resets', [
                'email' => $email,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expires
            ]);
            
            // In una applicazione reale, qui invieresti un'email all'utente con il link di reset
            // Per questo esempio, mostreremo il link direttamente nella pagina
            $resetLink = URL_ROOT . '/auth/reset_password.php?token=' . $token;
            
            $success = 'Un link per il reset della password è stato inviato al tuo indirizzo email.';
            // Per scopi di debug, mostra il link (rimuovi in produzione)
            $success .= '<br><br>Link di reset (solo per debug): <a href="' . $resetLink . '">' . $resetLink . '</a>';
        }
    }
}
// Step 4: Processo del form di reset
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset') {
    $step = 'reset';
    
    // Ottieni e filtra i dati
    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
    $passwordConfirm = filter_input(INPUT_POST, 'password_confirm', FILTER_UNSAFE_RAW);
    
    // Verifica che il token sia valido
    $db = Database::getInstance();
    $resetInfo = $db->fetchOne("
        SELECT * FROM password_resets 
        WHERE token = ? AND expires_at > NOW()
    ", [$token]);
    
    if (!$resetInfo) {
        $error = 'Token non valido o scaduto. Richiedi un nuovo link per il reset della password.';
        $step = 'request';
    } elseif (empty($password) || strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
        $email = $resetInfo['email'];
    } elseif ($password !== $passwordConfirm) {
        $error = 'Le password non corrispondono';
        $email = $resetInfo['email'];
    } else {
        $email = $resetInfo['email'];
        
        // Ottieni l'utente associato all'email
        $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        
        if ($user) {
            // Aggiorna la password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $db->update('users', ['password' => $hashedPassword], 'id = ?', [$user['id']]);
            
            // Elimina il token di reset
            $db->delete('password_resets', 'token = ?', [$token]);
            
            $success = 'Password aggiornata con successo. Ora puoi accedere con la tua nuova password.';
            $step = 'success';
        } else {
            $error = 'Utente non trovato. Contatta l\'amministratore.';
            $step = 'request';
        }
    }
}

// Includi il template header
$pageTitle = 'Reset Password';
include_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Reset Password</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php if ($step == 'success'): ?>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-primary">Vai al Login</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($step == 'request' && empty($success)): ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="action" value="request">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                <div class="form-text">Inserisci l'email associata al tuo account</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Invia Link Reset</button>
                            </div>
                        </form>
                    <?php elseif ($step == 'reset' && empty($success)): ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($email); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Nuova Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">La password deve essere di almeno 6 caratteri</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Conferma Password</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Aggiorna Password</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <p class="mb-0">Ricordi la password? <a href="login.php">Accedi</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>