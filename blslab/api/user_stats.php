<?php
// api/user_stats.php
// Endpoint API per statistiche utente

// Includi la configurazione
require_once '../config/config.php';

// Imposta l'header per JSON
header('Content-Type: application/json');

// Verifica se l'utente è autenticato
if (!User::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Accesso non autorizzato'
    ]);
    exit;
}

// Ottieni l'utente corrente
$currentUser = User::getCurrentUser();
$userId = $currentUser->getId();

// Controlla il tipo di statistiche richieste
$action = isset($_GET['action']) ? $_GET['action'] : 'summary';

switch ($action) {
    case 'subscription':
        // Informazioni sull'abbonamento
        $subscription = $currentUser->getActiveSubscription();
        
        if (!$subscription) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'has_subscription' => false,
                    'message' => 'Nessun abbonamento attivo'
                ]
            ]);
            exit;
        }
        
        $plan = $subscription->getPlan();
        $usageStats = $subscription->getUsageStats();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'has_subscription' => true,
                'plan_name' => $plan['name'],
                'plan_price' => $plan['price'],
                'start_date' => $subscription->getStartDate(),
                'end_date' => $subscription->getEndDate(),
                'is_expired' => $subscription->isExpired(),
                'days_remaining' => $subscription->getDaysRemaining(),
                'usage' => $usageStats
            ]
        ]);
        break;
        
    case 'usage_limits':
        // Limiti di utilizzo
        $stats = $currentUser->getStats(true); // Aggiorna le statistiche
        $limits = $currentUser->getEffectiveLimits();
        $fullStats = $stats->getFullStats();
        
        echo json_encode([
            'success' => true,
            'data' => $fullStats
        ]);
        break;
        
    case 'championships':
        // Statistiche sui campionati
        $championships = Championship::getAll(null, $userId, false);
        $stats = [];
        
        foreach ($championships as $championship) {
            $id = $championship['id'];
            $champ = new Championship($id);
            
            // Ottieni squadre
            $teams = $champ->getTeams();
            $teamCount = count($teams);
            
            // Ottieni partite
            $matches = $champ->getMatches();
            $matchCount = count($matches);
            $completedMatches = 0;
            $scheduledMatches = 0;
            
            foreach ($matches as $match) {
                if ($match['status'] == MATCH_STATUS_COMPLETED) {
                    $completedMatches++;
                } elseif ($match['status'] == MATCH_STATUS_SCHEDULED) {
                    $scheduledMatches++;
                }
            }
            
            $stats[] = [
                'id' => $id,
                'name' => $championship['name'],
                'type' => $championship['type'],
                'season' => $championship['season_name'],
                'start_date' => $championship['start_date'],
                'end_date' => $championship['end_date'],
                'team_count' => $teamCount,
                'match_count' => $matchCount,
                'completed_matches' => $completedMatches,
                'scheduled_matches' => $scheduledMatches,
                'progress' => ($matchCount > 0) ? round(($completedMatches / $matchCount) * 100) : 0
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        break;
        
    case 'teams':
        // Statistiche sulle squadre
        $teams = Team::getAll($userId, false);
        $stats = [];
        
        foreach ($teams as $team) {
            $id = $team['id'];
            $teamObj = new Team($id);
            
            // Ottieni campionati
            $championships = $teamObj->getChampionships();
            $championshipCount = count($championships);
            
            // Ottieni partite
            $matches = $teamObj->getMatches();
            $matchCount = count($matches);
            $wins = 0;
            $losses = 0;
            
            foreach ($matches as $match) {
                if ($match['status'] != MATCH_STATUS_COMPLETED) {
                    continue;
                }
                
                if (($match['home_team_id'] == $id && $match['home_score'] > $match['away_score']) ||
                    ($match['away_team_id'] == $id && $match['away_score'] > $match['home_score'])) {
                    $wins++;
                } else {
                    $losses++;
                }
            }
            
            $stats[] = [
                'id' => $id,
                'name' => $team['name'],
                'logo' => $team['logo'],
                'championship_count' => $championshipCount,
                'match_count' => $matchCount,
                'wins' => $wins,
                'losses' => $losses,
                'win_percentage' => ($matchCount > 0) ? round(($wins / $matchCount) * 100) : 0
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        break;
        
    case 'matches':
        // Statistiche sulle partite
        $champId = isset($_GET['championship_id']) ? intval($_GET['championship_id']) : null;
        
        $query = "
            SELECT m.*, 
                   c.name as championship_name, c.type as championship_type,
                   home.name as home_team_name, 
                   away.name as away_team_name
            FROM matches m
            JOIN championships c ON m.championship_id = c.id
            JOIN teams home ON m.home_team_id = home.id
            JOIN teams away ON m.away_team_id = away.id
            WHERE c.user_id = ?
        ";
        
        $params = [$userId];
        
        if ($champId) {
            $query .= " AND m.championship_id = ?";
            $params[] = $champId;
        }
        
        $query .= " ORDER BY m.match_date DESC";
        
        $db = Database::getInstance();
        $matches = $db->fetchAll($query, $params);
        
        $stats = [
            'total' => count($matches),
            'completed' => 0,
            'scheduled' => 0,
            'postponed' => 0,
            'cancelled' => 0,
            'by_championship' => [],
            'recent' => array_slice($matches, 0, 5) // Ultime 5 partite
        ];
        
        // Conteggi per stato
        foreach ($matches as $match) {
            $status = $match['status'];
            $stats[$status]++;
            
            $champId = $match['championship_id'];
            $champName = $match['championship_name'];
            
            if (!isset($stats['by_championship'][$champId])) {
                $stats['by_championship'][$champId] = [
                    'id' => $champId,
                    'name' => $champName,
                    'total' => 0,
                    'completed' => 0,
                    'scheduled' => 0
                ];
            }
            
            $stats['by_championship'][$champId]['total']++;
            $stats['by_championship'][$champId][$status]++;
        }
        
        // Converti l'array associativo in array numerico per JSON
        $stats['by_championship'] = array_values($stats['by_championship']);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        break;
        
    case 'predictions':
        // Verifica che l'utente possa accedere alle predizioni
        if (!$currentUser->canAccessPredictions()) {
            echo json_encode([
                'success' => false,
                'message' => 'Funzionalità non disponibile con il tuo abbonamento'
            ]);
            exit;
        }
        
        // Statistiche sulle predizioni
        $champId = isset($_GET['championship_id']) ? intval($_GET['championship_id']) : null;
        
        if (!$champId) {
            echo json_encode([
                'success' => false,
                'message' => 'ID campionato non specificato'
            ]);
            exit;
        }
        
        // Verifica che il campionato sia accessibile
        $championship = Championship::findById($champId);
        if (!$championship || !$championship->isAccessibleByUser($userId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Campionato non accessibile'
            ]);
            exit;
        }
        
        // Ottieni probabilità di vittoria
        $standings = $championship->getStandings();
        
        // Ordina per probabilità di vittoria
        usort($standings, function($a, $b) {
            return $b['win_probability'] <=> $a['win_probability'];
        });
        
        // Ottieni prossime partite con probabilità
        $db = Database::getInstance();
        $matches = $db->fetchAll("
            SELECT m.*, 
                   home.name as home_team_name, 
                   away.name as away_team_name,
                   mp.home_win_probability,
                   mp.away_win_probability,
                   mp.calculated_at
            FROM matches m
            JOIN teams home ON m.home_team_id = home.id
            JOIN teams away ON m.away_team_id = away.id
            LEFT JOIN match_probabilities mp ON m.id = mp.match_id
            WHERE m.championship_id = ? AND m.status = ?
            ORDER BY m.match_date ASC
            LIMIT 10
        ", [$champId, MATCH_STATUS_SCHEDULED]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'team_win_probabilities' => $standings,
                'upcoming_matches' => $matches
            ]
        ]);
        break;
        
    default:
        // Riepilogo generale
        $stats = new UserStats($userId);
        $stats->refresh(); // Aggiorna le statistiche
        
        $championshipCount = $stats->getChampionshipsCount();
        $teamCount = $stats->getTeamsCount();
        $matchCount = $stats->getMatchesCount();
        
        // Calcola i campionati attivi
        $db = Database::getInstance();
        $activeChampionships = $db->fetchAll("
            SELECT COUNT(*) as count
            FROM championships
            WHERE user_id = ? AND end_date >= CURRENT_DATE()
        ", [$userId]);
        
        $activeCount = $activeChampionships[0]['count'] ?? 0;
        
        // Statistiche abbonamento
        $subscription = $currentUser->getActiveSubscription();
        $subscriptionInfo = null;
        
        if ($subscription) {
            $plan = $subscription->getPlan();
            $subscriptionInfo = [
                'plan_name' => $plan['name'],
                'is_expired' => $subscription->isExpired(),
                'days_remaining' => $subscription->getDaysRemaining()
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'championship_count' => $championshipCount,
                'active_championship_count' => $activeCount,
                'team_count' => $teamCount,
                'match_count' => $matchCount,
                'subscription' => $subscriptionInfo,
                'can_create_championship' => $currentUser->canCreateChampionship(),
                'can_create_team' => $currentUser->canCreateTeam(),
                'can_access_predictions' => $currentUser->canAccessPredictions(),
                'has_ads' => $currentUser->showAds(),
                'last_update' => $stats->getLastUpdate()
            ]
        ]);
        break;
}