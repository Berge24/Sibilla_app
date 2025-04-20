<?php
// utils/subscription_limits.php

/**
 * Verifica se l'utente può creare un nuovo campionato
 * 
 * @param int $userId ID dell'utente
 * @param bool $returnMessage Se true, restituisce un messaggio di errore invece di un booleano
 * @return bool|string True se l'utente può creare un campionato, false o messaggio di errore altrimenti
 */
function canCreateChampionship($userId, $returnMessage = false) {
    $user = User::findById($userId);
    
    if (!$user) {
        return $returnMessage ? "Utente non trovato." : false;
    }
    
    if (!$user->getIsApproved()) {
        return $returnMessage ? "Il tuo account non è ancora stato approvato. Contatta l'amministratore." : false;
    }
    
    // Verifica abbonamento scaduto
    if ($user->isSubscriptionExpired()) {
        return $returnMessage ? "Il tuo abbonamento è scaduto. Rinnova per poter creare nuovi campionati." : false;
    }
    
    // Verifica che non abbia raggiunto il limite di campionati
    if ($user->hasReachedChampionshipsLimit()) {
        $limits = $user->getEffectiveLimits();
        return $returnMessage ? 
            "Hai raggiunto il limite di {$limits['max_championships']} campionati. Aggiorna il tuo abbonamento per crearne altri." : 
            false;
    }
    
    return $returnMessage ? "" : true;
}

/**
 * Verifica se l'utente può creare una nuova squadra
 * 
 * @param int $userId ID dell'utente
 * @param bool $returnMessage Se true, restituisce un messaggio di errore invece di un booleano
 * @return bool|string True se l'utente può creare una squadra, false o messaggio di errore altrimenti
 */
function canCreateTeam($userId, $returnMessage = false) {
    $user = User::findById($userId);
    
    if (!$user) {
        return $returnMessage ? "Utente non trovato." : false;
    }
    
    if (!$user->getIsApproved()) {
        return $returnMessage ? "Il tuo account non è ancora stato approvato. Contatta l'amministratore." : false;
    }
    
    // Verifica abbonamento scaduto
    if ($user->isSubscriptionExpired()) {
        return $returnMessage ? "Il tuo abbonamento è scaduto. Rinnova per poter creare nuove squadre." : false;
    }
    
    // Verifica che non abbia raggiunto il limite di squadre
    if ($user->hasReachedTeamsLimit()) {
        $limits = $user->getEffectiveLimits();
        return $returnMessage ? 
            "Hai raggiunto il limite di {$limits['max_teams']} squadre. Aggiorna il tuo abbonamento per crearne altre." : 
            false;
    }
    
    return $returnMessage ? "" : true;
}

/**
 * Verifica se l'utente può utilizzare le predizioni
 * 
 * @param int $userId ID dell'utente
 * @param bool $returnMessage Se true, restituisce un messaggio di errore invece di un booleano
 * @return bool|string True se l'utente può utilizzare le predizioni, false o messaggio di errore altrimenti
 */
function canUsePredictions($userId, $returnMessage = false) {
    $user = User::findById($userId);
    
    if (!$user) {
        return $returnMessage ? "Utente non trovato." : false;
    }
    
    if (!$user->getIsApproved()) {
        return $returnMessage ? "Il tuo account non è ancora stato approvato. Contatta l'amministratore." : false;
    }
    
    // Verifica abbonamento scaduto
    if ($user->isSubscriptionExpired()) {
        return $returnMessage ? "Il tuo abbonamento è scaduto. Rinnova per poter utilizzare le predizioni." : false;
    }
    
    // Verifica se l'abbonamento include l'accesso alle predizioni
    if (!$user->canAccessPredictions()) {
        return $returnMessage ? 
            "Le predizioni non sono incluse nel tuo piano. Aggiorna il tuo abbonamento per sbloccare questa funzionalità." : 
            false;
    }
    
    return $returnMessage ? "" : true;
}

/**
 * Ottiene un messaggio informativo sullo stato dell'abbonamento dell'utente
 * 
 * @param int $userId ID dell'utente
 * @return string Messaggio informativo
 */
function getSubscriptionStatusMessage($userId) {
    $user = User::findById($userId);
    
    if (!$user) {
        return "Impossibile verificare lo stato dell'abbonamento.";
    }
    
    $subscription = $user->getActiveSubscription();
    if (!$subscription) {
        return "Non hai un abbonamento attivo. <a href='" . url('subscription.php') . "'>Sottoscrivi un piano</a> per sbloccare tutte le funzionalità.";
    }
    
    if ($user->isSubscriptionExpired()) {
        return "Il tuo abbonamento è scaduto. <a href='" . url('subscription.php') . "'>Rinnova ora</a> per continuare a utilizzare tutte le funzionalità.";
    }
    
    $plan = $subscription->getPlan();
    $daysRemaining = $subscription->getDaysRemaining();
    
    $message = "Abbonamento attivo: <strong>" . htmlspecialchars($plan['name']) . "</strong>. ";
    
    if ($daysRemaining <= 7) {
        $message .= "<span class='text-warning'>Scade tra {$daysRemaining} giorni</span>. <a href='" . url('subscription.php') . "'>Rinnova ora</a>.";
    } else {
        $message .= "Valido per altri {$daysRemaining} giorni.";
    }
    
    return $message;
}

/**
 * Ottiene un riepilogo dei limiti di utilizzo dell'utente
 * 
 * @param int $userId ID dell'utente
 * @return string HTML del riepilogo limiti
 */
function getUsageSummaryHtml($userId) {
    $user = User::findById($userId);
    
    if (!$user) {
        return "Impossibile verificare i limiti di utilizzo.";
    }
    
    $stats = $user->getStats(true); // Forza l'aggiornamento delle statistiche
    $limits = $user->getEffectiveLimits();
    $fullStats = $stats->getFullStats();
    
    $html = '<div class="card mb-4">';
    $html .= '<div class="card-header bg-light"><h5 class="mb-0">Limiti Utilizzo</h5></div>';
    $html .= '<div class="card-body">';
    
    // Campionati
    $html .= '<div class="mb-3">';
    $html .= '<h6>Campionati</h6>';
    
    if ($fullStats['championships']['unlimited']) {
        $html .= '<p>Hai creato <strong>' . $fullStats['championships']['count'] . '</strong> campionati. <span class="badge bg-success">Illimitati</span></p>';
    } else {
        $html .= '<p>Hai creato <strong>' . $fullStats['championships']['count'] . '</strong> di <strong>' . $limits['max_championships'] . '</strong> campionati disponibili.</p>';
        
        // Barra di progresso
        $percentage = $fullStats['championships']['percentage'];
        $bgClass = $percentage > 90 ? 'danger' : ($percentage > 70 ? 'warning' : 'success');
        
        $html .= '<div class="progress" style="height: 20px;">';
        $html .= '<div class="progress-bar bg-' . $bgClass . '" role="progressbar" style="width: ' . $percentage . '%;" ';
        $html .= 'aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100">' . round($percentage) . '%</div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    // Squadre
    $html .= '<div class="mb-3">';
    $html .= '<h6>Squadre</h6>';
    
    if ($fullStats['teams']['unlimited']) {
        $html .= '<p>Hai creato <strong>' . $fullStats['teams']['count'] . '</strong> squadre. <span class="badge bg-success">Illimitate</span></p>';
    } else {
        $html .= '<p>Hai creato <strong>' . $fullStats['teams']['count'] . '</strong> di <strong>' . $limits['max_teams'] . '</strong> squadre disponibili.</p>';
        
        // Barra di progresso
        $percentage = $fullStats['teams']['percentage'];
        $bgClass = $percentage > 90 ? 'danger' : ($percentage > 70 ? 'warning' : 'success');
        
        $html .= '<div class="progress" style="height: 20px;">';
        $html .= '<div class="progress-bar bg-' . $bgClass . '" role="progressbar" style="width: ' . $percentage . '%;" ';
        $html .= 'aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100">' . round($percentage) . '%</div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    // Funzionalità
    $html .= '<div class="mb-2">';
    $html .= '<h6>Funzionalità</h6>';
    $html .= '<ul class="list-group list-group-flush">';
    
    // Pubblicità
    $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">';
    $html .= 'Pubblicità';
    if ($limits['has_ads']) {
        $html .= '<span class="badge bg-warning">Attive</span>';
    } else {
        $html .= '<span class="badge bg-success">Disattivate</span>';
    }
    $html .= '</li>';
    
    // Predizioni
    $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">';
    $html .= 'Predizioni Avanzate';
    if ($limits['has_predictions']) {
        $html .= '<span class="badge bg-success">Abilitate</span>';
    } else {
        $html .= '<span class="badge bg-secondary">Non disponibili</span>';
    }
    $html .= '</li>';
    
    $html .= '</ul>';
    $html .= '</div>';
    
    if (!$user->isSubscriptionExpired()) {
        $subscription = $user->getActiveSubscription();
        $plan = $subscription->getPlan();
        
        $html .= '<div class="text-center mt-3">';
        if ($plan['price'] > 0) {
            $html .= '<a href="' . url('subscription.php') . '" class="btn btn-sm btn-outline-primary">Gestisci Abbonamento</a>';
        } else {
            $html .= '<a href="' . url('subscription.php') . '" class="btn btn-sm btn-outline-success">Aggiorna Piano</a>';
        }
        $html .= '</div>';
    } else {
        $html .= '<div class="alert alert-warning text-center">';
        $html .= 'Il tuo abbonamento è scaduto. <a href="' . url('subscription.php') . '">Rinnova ora</a> per sbloccare tutte le funzionalità.';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // card-body
    $html .= '</div>'; // card
    
    return $html;
}

/**
 * Verifica se l'entità è accessibile all'utente specificato
 * 
 * @param string $entityType Tipo di entità (championship, season, team, match)
 * @param int $entityId ID dell'entità
 * @param int $userId ID dell'utente
 * @param bool $checkEditPermission Se true, verifica i permessi di modifica invece della sola lettura
 * @return bool True se l'utente ha accesso all'entità, false altrimenti
 */
function isEntityAccessible($entityType, $entityId, $userId, $checkEditPermission = false) {
    switch ($entityType) {
        case 'championship':
            $entity = Championship::findById($entityId);
            return $entity ? ($checkEditPermission ? $entity->canEdit() : $entity->isAccessibleByUser($userId)) : false;
            
        case 'season':
            $entity = Season::findById($entityId);
            return $entity ? ($checkEditPermission ? $entity->canEdit() : $entity->isAccessibleByUser($userId)) : false;
            
        case 'team':
            $entity = Team::findById($entityId);
            return $entity ? ($checkEditPermission ? $entity->canEdit() : $entity->isAccessibleByUser($userId)) : false;
            
        case 'match':
            $entity = Match::findById($entityId);
            return $entity ? ($checkEditPermission ? $entity->canEdit() : $entity->isAccessibleByUser($userId)) : false;
            
        case 'standing':
            $entity = Standing::findById($entityId);
            return $entity ? ($checkEditPermission ? $entity->canEdit() : $entity->isAccessibleByUser($userId)) : false;
            
        default:
            return false;
    }
}

/**
 * Funzione di utilità per reindirizzare l'utente se non ha accesso a un'entità
 * 
 * @param string $entityType Tipo di entità (championship, season, team, match)
 * @param int $entityId ID dell'entità
 * @param bool $checkEditPermission Se true, verifica i permessi di modifica invece della sola lettura
 * @return void
 */
function redirectIfNotAccessible($entityType, $entityId, $checkEditPermission = false) {
    $currentUser = User::getCurrentUser();
    
    if (!$currentUser) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(url('auth/login.php'));
    }
    
    if (!isEntityAccessible($entityType, $entityId, $currentUser->getId(), $checkEditPermission)) {
        // Salva un messaggio di errore
        $_SESSION['error_message'] = "Non hai il permesso di accedere a questa risorsa.";
        
        // Reindirizza alla pagina appropriata in base al tipo di entità
        switch ($entityType) {
            case 'championship':
                redirect(url('championships.php'));
                break;
                
            case 'season':
                redirect(url('seasons.php'));
                break;
                
            case 'team':
                redirect(url('teams.php'));
                break;
                
            case 'match':
                redirect(url('matches.php'));
                break;
                
            default:
                redirect(url('index.php'));
                break;
        }
    }
}

/**
 * Verifica se l'utente ha raggiunto uno dei suoi limiti
 * 
 * @param int $userId ID dell'utente
 * @return array Array associativo con lo stato dei limiti raggiunti
 */
function hasReachedLimits($userId) {
    $user = User::findById($userId);
    
    if (!$user) {
        return [
            'any' => true,
            'championships' => true,
            'teams' => true,
            'subscription_expired' => true
        ];
    }
    
    return [
        'any' => $user->hasReachedChampionshipsLimit() || $user->hasReachedTeamsLimit() || $user->isSubscriptionExpired(),
        'championships' => $user->hasReachedChampionshipsLimit(),
        'teams' => $user->hasReachedTeamsLimit(),
        'subscription_expired' => $user->isSubscriptionExpired()
    ];
}

/**
 * Formatta le informazioni sul piano abbonamento in una tabella HTML
 * 
 * @param array $plan Dati del piano abbonamento
 * @return string HTML della tabella
 */
function formatPlanDetailsTable($plan) {
    $html = '<table class="table table-striped">';
    $html .= '<tbody>';
    
    // Nome e Prezzo
    $html .= '<tr>';
    $html .= '<th>Piano</th>';
    $html .= '<td>' . htmlspecialchars($plan['name']) . '</td>';
    $html .= '</tr>';
    
    $html .= '<tr>';
    $html .= '<th>Prezzo</th>';
    $html .= '<td>' . ($plan['price'] > 0 ? number_format($plan['price'], 2, ',', '.') . ' € / ' . ($plan['duration_days'] >= 30 ? 'mese' : $plan['duration_days'] . ' giorni') : '<span class="badge bg-success">Gratuito</span>') . '</td>';
    $html .= '</tr>';
    
    // Limiti di Campionati
    $html .= '<tr>';
    $html .= '<th>Campionati</th>';
    $html .= '<td>';
    if ($plan['max_championships'] < 0 || $plan['max_championships'] >= 999) {
        $html .= '<span class="badge bg-success">Illimitati</span>';
    } else {
        $html .= 'Max ' . $plan['max_championships'];
    }
    $html .= '</td>';
    $html .= '</tr>';
    
    // Limiti di Squadre
    $html .= '<tr>';
    $html .= '<th>Squadre</th>';
    $html .= '<td>';
    if ($plan['max_teams'] < 0 || $plan['max_teams'] >= 999) {
        $html .= '<span class="badge bg-success">Illimitate</span>';
    } else {
        $html .= 'Max ' . $plan['max_teams'];
    }
    $html .= '</td>';
    $html .= '</tr>';
    
    // Pubblicità
    $html .= '<tr>';
    $html .= '<th>Pubblicità</th>';
    $html .= '<td>';
    if ($plan['has_ads']) {
        $html .= '<span class="badge bg-warning">Presenti</span>';
    } else {
        $html .= '<span class="badge bg-success">Rimosse</span>';
    }
    $html .= '</td>';
    $html .= '</tr>';
    
    // Predizioni
    $html .= '<tr>';
    $html .= '<th>Predizioni Avanzate</th>';
    $html .= '<td>';
    if ($plan['has_predictions']) {
        $html .= '<span class="badge bg-success">Incluse</span>';
    } else {
        $html .= '<span class="badge bg-secondary">Non disponibili</span>';
    }
    $html .= '</td>';
    $html .= '</tr>';
    
    $html .= '</tbody>';
    $html .= '</table>';
    
    return $html;
}