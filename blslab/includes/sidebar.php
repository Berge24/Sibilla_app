<?php
// includes/sidebar.php

// Verifica che l'utente sia un amministratore
if (!User::isAdmin()) {
    header('Location: ../auth/login.php');
    exit;
}

// Determina la pagina corrente
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="col-md-3 col-lg-2 p-0">
    <div class="list-group sticky-top mt-4">
        <a href="<?php echo URL_ROOT; ?>/admin/index.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        <a href="<?php echo URL_ROOT; ?>/admin/seasons.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'seasons.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt me-2"></i> Gestione Stagioni
        </a>
        <a href="<?php echo URL_ROOT; ?>/admin/championships.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'championships.php') ? 'active' : ''; ?>">
            <i class="fas fa-trophy me-2"></i> Gestione Campionati
        </a>
        <a href="<?php echo URL_ROOT; ?>/admin/teams.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'teams.php') ? 'active' : ''; ?>">
            <i class="fas fa-users me-2"></i> Gestione Squadre
        </a>
        <a href="<?php echo URL_ROOT; ?>/admin/matches.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'matches.php') ? 'active' : ''; ?>">
            <i class="fas fa-futbol me-2"></i> Gestione Partite
        </a>
        <a href="<?php echo URL_ROOT; ?>/admin/users.php" class="list-group-item list-group-item-action <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-cog me-2"></i> Gestione Utenti
        </a>
        <a href="<?php echo URL_ROOT; ?>/public/index.php" class="list-group-item list-group-item-action">
            <i class="fas fa-home me-2"></i> Torna al Sito
        </a>
    </div>
</div>