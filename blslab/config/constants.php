<?php
// config/constants.php

// Tipi di campionato
define('CHAMPIONSHIP_TYPE_CSI', 'CSI');
define('CHAMPIONSHIP_TYPE_UISP', 'UISP');

// Stati delle partite
define('MATCH_STATUS_SCHEDULED', 'scheduled');
define('MATCH_STATUS_COMPLETED', 'completed');
define('MATCH_STATUS_POSTPONED', 'postponed');
define('MATCH_STATUS_CANCELLED', 'cancelled');

// Risultati tempi partite UISP
define('PERIOD_RESULT_WIN', 'win');
define('PERIOD_RESULT_DRAW', 'draw');
define('PERIOD_RESULT_LOSS', 'loss');

// Punti per i campionati CSI
define('CSI_POINTS_WIN', 3);
define('CSI_POINTS_DRAW', 0);
define('CSI_POINTS_LOSS', 0);

// Punti per i campionati UISP
define('UISP_POINTS_PERIOD_WIN', 2);
define('UISP_POINTS_PERIOD_DRAW', 0);
define('UISP_POINTS_PERIOD_LOSS', 0);
define('UISP_POINTS_BONUS', 1); // Punto bonus per chi segna più gol in caso di parità nei punti

// Ruoli utenti
define('USER_ROLE_ADMIN', 'admin');
define('USER_ROLE_USER', 'user');