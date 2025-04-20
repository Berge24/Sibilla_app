<?php
// public/error.php

// Includi la configurazione
require_once '../config/config.php';

// Ottieni il codice di errore
$errorCode = filter_input(INPUT_GET, 'code', FILTER_VALIDATE_INT) ?? 404;

// Definisci messaggi di errore
$errorMessages = [
    400 => 'Richiesta non valida',
    401 => 'Non autorizzato',
    403 => 'Accesso negato',
    404 => 'Pagina non trovata',
    500 => 'Errore interno del server'
];

// Ottieni il messaggio di errore
$errorMessage = $errorMessages[$errorCode] ?? 'Si è verificato un errore';

// Includi il template header
$pageTitle = "Errore $errorCode";
include_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow text-center">
                <div class="card-body p-5">
                    <h1 class="display-1 text-danger"><?php echo $errorCode; ?></h1>
                    <h2 class="mb-4"><?php echo $errorMessage; ?></h2>
                    <p class="lead mb-4">Ci dispiace, si è verificato un errore durante l'elaborazione della tua richiesta.</p>
                    
                    <?php if ($errorCode == 404): ?>
                        <p>La pagina che stai cercando potrebbe essere stata rimossa, rinominata o temporaneamente non disponibile.</p>
                    <?php elseif ($errorCode == 403): ?>
                        <p>Non hai i permessi necessari per accedere a questa risorsa.</p>
                    <?php elseif ($errorCode == 500): ?>
                        <p>Si è verificato un errore interno del server. Riprova più tardi o contatta l'amministratore del sito.</p>
                    <?php endif; ?>
                    
                    <div class="mt-5">
                        <a href="<?php echo URL_ROOT; ?>/index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i> Torna alla Homepage
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>