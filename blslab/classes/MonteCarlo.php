<?php
// classes/MonteCarlo.php

class MonteCarlo {
    private $db;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Esegue una simulazione Monte Carlo per calcolare le probabilità di vittoria
     * @param int $championshipId ID del campionato
     * @return bool Successo/fallimento
     */
    public function simulateChampionship($championshipId) {
        // Ottieni informazioni sul campionato
        $championship = $this->db->fetchOne("SELECT * FROM championships WHERE id = ?", [$championshipId]);
        
        if (!$championship) {
            return false;
        }
        
        // Determina i punti per vittoria in base al tipo di campionato
        $pointsForWin = ($championship['type'] === CHAMPIONSHIP_TYPE_CSI) ? 3 : 2;
        
        // Ottieni tutte le squadre del campionato con le statistiche attuali
        $teams = $this->db->fetchAll("
            SELECT t.id, t.name, s.points, s.played, s.won, s.lost, s.scored, s.conceded
            FROM teams t
            JOIN championships_teams ct ON t.id = ct.team_id
            JOIN standings s ON t.id = s.team_id AND s.championship_id = ?
            WHERE ct.championship_id = ?
            ORDER BY s.points DESC
        ", [$championshipId, $championshipId]);
        
        // Ottieni le partite ancora da giocare
        $remainingMatches = $this->db->fetchAll("
            SELECT * FROM matches
            WHERE championship_id = ? AND status = ?
        ", [$championshipId, MATCH_STATUS_SCHEDULED]);
        
        if (empty($teams) || empty($remainingMatches)) {
            // Se non ci sono partite rimanenti, la prima squadra in classifica ha 100% di vincere
            if (empty($remainingMatches) && !empty($teams)) {
                $this->updateFinalStandings($championshipId, $teams);
                return true;
            }
            return false;
        }
        
        // Ottieni lo storico completo delle partite per ogni squadra (da tutti i campionati)
        $teamHistoricalStats = $this->getTeamHistoricalStats();
        
        // Calcola le forze relative delle squadre basate su performance attuali e storiche
        $teamStrengths = [];
        foreach ($teams as $team) {
            // Forza basata sulle statistiche del campionato attuale
            $currentStrength = $this->calculateCurrentStrength($team);
            
            // Forza basata sulle statistiche storiche (se disponibili)
            $historicalStrength = isset($teamHistoricalStats[$team['id']]) ? 
                                  $this->calculateHistoricalStrength($teamHistoricalStats[$team['id']]) : 
                                  0.5; // Valore di default se non ci sono dati storici
            
            // Combina le due forze (dando più peso alle statistiche correnti)
            $teamStrengths[$team['id']] = ($currentStrength * 0.7) + ($historicalStrength * 0.3);
        }
        
        // Inizializza i contatori di vittoria
        $winCounts = [];
        foreach ($teams as $team) {
            $winCounts[$team['id']] = 0;
        }
        
        // Esegui le simulazioni
        $numSimulations = 1000;
        
        for ($sim = 0; $sim < $numSimulations; $sim++) {
            // Crea una copia delle statistiche attuali
            $simulatedStandings = [];
            foreach ($teams as $team) {
                $simulatedStandings[$team['id']] = [
                    'points' => $team['points'],
                    'won' => $team['won'],
                    'lost' => $team['lost'],
                    'scored' => $team['scored'],
                    'conceded' => $team['conceded']
                ];
            }
            
            // Simula le partite rimanenti
            foreach ($remainingMatches as $match) {
                $homeTeamId = $match['home_team_id'];
                $awayTeamId = $match['away_team_id'];
                
                // Ottieni le forze delle squadre
                $homeStrength = $teamStrengths[$homeTeamId] ?? 0.5;
                $awayStrength = $teamStrengths[$awayTeamId] ?? 0.5;
                
                // Aggiungi fattore casa (10% vantaggio)
                $homeStrength *= 1.1;
                
                // Nel basket non ci sono pareggi, quindi simuliamo solo vittoria o sconfitta
                // Calcola probabilità di vittoria per la squadra di casa
                $homeWinProb = $homeStrength / ($homeStrength + $awayStrength);
                $rand = mt_rand() / mt_getrandmax();
                
                if ($rand < $homeWinProb) {
                    // Vittoria casa
                    $simulatedStandings[$homeTeamId]['points'] += $pointsForWin;
                    $simulatedStandings[$homeTeamId]['won'] += 1;
                    $simulatedStandings[$awayTeamId]['lost'] += 1;
                    
                    // Simula anche i punteggi (più realistica per il basket)
                    $avgTotalPoints = 160; // media dei punti totali in una partita di basket
                    $homeScoreRatio = $homeWinProb + 0.1; // squadra più forte segna di più
                    
                    $totalPoints = mt_rand($avgTotalPoints - 20, $avgTotalPoints + 20);
                    $homeScore = round($totalPoints * $homeScoreRatio);
                    $awayScore = $totalPoints - $homeScore;
                    
                    // Assicura che la squadra vincente abbia un punteggio maggiore
                    if ($homeScore <= $awayScore) {
                        $homeScore = $awayScore + mt_rand(1, 5);
                    }
                    
                    $simulatedStandings[$homeTeamId]['scored'] += $homeScore;
                    $simulatedStandings[$homeTeamId]['conceded'] += $awayScore;
                    $simulatedStandings[$awayTeamId]['scored'] += $awayScore;
                    $simulatedStandings[$awayTeamId]['conceded'] += $homeScore;
                } else {
                    // Vittoria trasferta
                    $simulatedStandings[$awayTeamId]['points'] += $pointsForWin;
                    $simulatedStandings[$awayTeamId]['won'] += 1;
                    $simulatedStandings[$homeTeamId]['lost'] += 1;
                    
                    // Simula anche i punteggi
                    $avgTotalPoints = 160;
                    $awayScoreRatio = (1 - $homeWinProb) + 0.1;
                    
                    $totalPoints = mt_rand($avgTotalPoints - 20, $avgTotalPoints + 20);
                    $awayScore = round($totalPoints * $awayScoreRatio);
                    $homeScore = $totalPoints - $awayScore;
                    
                    // Assicura che la squadra vincente abbia un punteggio maggiore
                    if ($awayScore <= $homeScore) {
                        $awayScore = $homeScore + mt_rand(1, 5);
                    }
                    
                    $simulatedStandings[$homeTeamId]['scored'] += $homeScore;
                    $simulatedStandings[$homeTeamId]['conceded'] += $awayScore;
                    $simulatedStandings[$awayTeamId]['scored'] += $awayScore;
                    $simulatedStandings[$awayTeamId]['conceded'] += $homeScore;
                }
            }
            
            // Determina il vincitore
            $winner = $this->determineWinner($simulatedStandings);
            
            if ($winner) {
                $winCounts[$winner]++;
            }
        }
        
        // Aggiorna le probabilità di vittoria nel database
        $this->db->beginTransaction();
        
        try {
            // Calcola le probabilità di vittoria
            foreach ($teams as $team) {
                $probability = ($winCounts[$team['id']] / $numSimulations) * 100;
                
                $this->db->update('standings', [
                    'win_probability' => $probability,
                    'last_calculated' => date('Y-m-d H:i:s')
                ], 'championship_id = ? AND team_id = ?', [$championshipId, $team['id']]);
            }
            
            // Salva i dati della simulazione
            $this->db->insert('simulation_history', [
                'championship_id' => $championshipId,
                'simulation_date' => date('Y-m-d H:i:s')
            ]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Errore in simulateChampionship: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola la forza della squadra basata sulle statistiche attuali
     * @param array $team Statistiche della squadra
     * @return float Valore della forza
     */
    private function calculateCurrentStrength($team) {
        if ($team['played'] == 0) {
            return 0.5; // Valore predefinito se non ci sono partite giocate
        }
        
        // Calcola la percentuale di vittorie
        $winPercentage = $team['won'] / $team['played'];
        
        // Considera la differenza punti (importante nel basket)
        $pointDiff = $team['scored'] - $team['conceded'];
        $pointDiffFactor = 0;
        
        if ($team['played'] > 0) {
            // Normalizza la differenza punti per partita
            $pointDiffPerGame = $pointDiff / $team['played'];
            // Converti in un fattore (range tipico nel basket: -20 a +20 punti per partita)
            $pointDiffFactor = ($pointDiffPerGame + 20) / 40;
            // Limita il fattore tra 0.1 e 0.9
            $pointDiffFactor = max(0.1, min(0.9, $pointDiffFactor));
        }
        
        // Combina i fattori per un calcolo più accurato
        // Il basket dà molta importanza sia alle vittorie che al margine di vittoria
        $strength = ($winPercentage * 0.6) + ($pointDiffFactor * 0.4);
        
        return max(0.1, min(0.9, $strength));
    }
    
    /**
     * Calcola la forza storica della squadra basata su tutte le partite precedenti
     * @param array $stats Statistiche storiche della squadra
     * @return float Valore della forza
     */
    private function calculateHistoricalStrength($stats) {
        if ($stats['total_matches'] == 0) {
            return 0.5; // Valore predefinito se non ci sono partite
        }
        
        // Calcola la percentuale di vittorie
        $winPercentage = $stats['wins'] / $stats['total_matches'];
        
        // Considera la differenza punti storica
        $pointDiff = $stats['points_scored'] - $stats['points_conceded'];
        $pointDiffFactor = 0;
        
        if ($stats['total_matches'] > 0) {
            $pointDiffPerGame = $pointDiff / $stats['total_matches'];
            $pointDiffFactor = ($pointDiffPerGame + 20) / 40;
            $pointDiffFactor = max(0.1, min(0.9, $pointDiffFactor));
        }
        
        // Combina i fattori come per il calcolo corrente
        $strength = ($winPercentage * 0.6) + ($pointDiffFactor * 0.4);
        
        return max(0.1, min(0.9, $strength));
    }
    
    /**
     * Ottiene le statistiche storiche delle squadre da tutti i campionati
     * @return array Array di statistiche per squadra
     */
    private function getTeamHistoricalStats() {
        $stats = $this->db->fetchAll("
            SELECT 
                t.id, 
                COUNT(m.id) as total_matches,
                SUM(CASE 
                    WHEN (m.home_team_id = t.id AND m.home_score > m.away_score) OR 
                         (m.away_team_id = t.id AND m.away_score > m.home_score) 
                    THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END) as points_scored,
                SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score ELSE m.home_score END) as points_conceded
            FROM teams t
            JOIN matches m ON t.id = m.home_team_id OR t.id = m.away_team_id
            WHERE m.status = ?
            GROUP BY t.id
        ", [MATCH_STATUS_COMPLETED]);
        
        // Converto in un array associativo con chiave team_id
        $result = [];
        foreach ($stats as $stat) {
            $result[$stat['id']] = $stat;
        }
        
        return $result;
    }
    
    /**
     * Determina il vincitore di una simulazione
     * @param array $standings Classifiche simulate
     * @return int|null ID della squadra vincitrice o null in caso di parità non risolvibile
     */
    private function determineWinner($standings) {
        if (empty($standings)) {
            return null;
        }
        
        // Trova il punteggio massimo
        $maxPoints = -1;
        $tiedTeams = [];
        $winner = null;
        
        foreach ($standings as $teamId => $stats) {
            if ($stats['points'] > $maxPoints) {
                $maxPoints = $stats['points'];
                $tiedTeams = [$teamId];
                $winner = $teamId;
            } elseif ($stats['points'] == $maxPoints) {
                $tiedTeams[] = $teamId;
            }
        }
        
        // Se c'è un solo vincitore, restituiscilo
        if (count($tiedTeams) == 1) {
            return $winner;
        }
        
        // In caso di parità, usa la differenza punti (importante nel basket)
        $maxGoalDiff = PHP_INT_MIN;
        
        foreach ($tiedTeams as $teamId) {
            $goalDiff = $standings[$teamId]['scored'] - $standings[$teamId]['conceded'];
            if ($goalDiff > $maxGoalDiff) {
                $maxGoalDiff = $goalDiff;
                $winner = $teamId;
            }
        }
        
        return $winner;
    }
    
    /**
     * Aggiorna le probabilità quando tutte le partite sono state giocate
     * @param int $championshipId ID del campionato
     * @param array $teams Squadre del campionato
     * @return bool Successo/fallimento
     */
    private function updateFinalStandings($championshipId, $teams) {
        $this->db->beginTransaction();
        
        try {
            // La squadra in prima posizione ha 100% di probabilità di vincere
            $winnerId = $teams[0]['id'];
            
            foreach ($teams as $team) {
                $probability = ($team['id'] == $winnerId) ? 100.0 : 0.0;
                
                $this->db->update('standings', [
                    'win_probability' => $probability,
                    'last_calculated' => date('Y-m-d H:i:s')
                ], 'championship_id = ? AND team_id = ?', [$championshipId, $team['id']]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Errore in updateFinalStandings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola le probabilità di vittoria per una partita specifica
     * @param int $matchId ID della partita
     * @return array|false Probabilità di vittoria o false in caso di errore
     */
    public function calculateMatchProbabilities($matchId) {
        $match = Match::findById($matchId);
        
        if (!$match || $match->getStatus() == MATCH_STATUS_COMPLETED) {
            return false;
        }
        
        // Calcola le probabilità interne alla partita
        return $match->calculateWinProbabilities();
    }
}