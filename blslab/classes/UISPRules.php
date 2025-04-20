<?php
// classes/UISPRules.php

class UISPRules {
    private $db;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Calcola e aggiorna la classifica per un campionato UISP
     * @param int $championshipId ID del campionato
     * @return bool Successo/fallimento
     */
    public function calculateStandings($championshipId) {
        // Verifica che il campionato esista, sia di tipo UISP e sia accessibile all'utente corrente
        $championship = Championship::findById($championshipId);
        if (!$championship || $championship->getType() !== CHAMPIONSHIP_TYPE_UISP || !$championship->canEdit()) {
            return false;
        }
        
        // Ottieni tutte le squadre del campionato
        $teams = $this->db->fetchAll("
            SELECT team_id FROM championships_teams WHERE championship_id = ?
        ", [$championshipId]);
        
        if (empty($teams)) {
            return false;
        }
        
        // Inizializza la transazione
        $this->db->beginTransaction();
        
        try {
            // Per ogni squadra, calcola le statistiche
            foreach ($teams as $team) {
                $teamId = $team['team_id'];
                
                // Reset delle statistiche per la squadra corrente
                $this->db->update('standings', [
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'points' => 0,
                    'scored' => 0,
                    'conceded' => 0,
                    'last_calculated' => date('Y-m-d H:i:s')
                ], 'championship_id = ? AND team_id = ?', [$championshipId, $teamId]);
                
                // Ottieni tutte le partite completate per questa squadra in questo campionato
                $matches = $this->db->fetchAll("
                    SELECT * FROM matches 
                    WHERE championship_id = ? 
                    AND (home_team_id = ? OR away_team_id = ?)
                    AND status = ?
                ", [$championshipId, $teamId, $teamId, MATCH_STATUS_COMPLETED]);
                
                // Inizializza le statistiche
                $played = count($matches);
                $won = 0;
                $drawn = 0;
                $lost = 0;
                $points = 0;
                $scored = 0;
                $conceded = 0;
                
                // Calcola le statistiche basate sulle partite
                foreach ($matches as $match) {
                    // Ottieni i periodi della partita
                    $periods = $this->db->fetchAll("
                        SELECT * FROM match_periods
                        WHERE match_id = ?
                        ORDER BY period_number
                    ", [$match['id']]);
                    
                    if (empty($periods)) {
                        continue; // Salta questa partita se non ha periodi
                    }
                    
                    if ($match['home_team_id'] == $teamId) {
                        // Squadra gioca in casa
                        $scored += $match['home_score'];
                        $conceded += $match['away_score'];
                        
                        // Calcola i punti basati sui periodi
                        $homePeriodPoints = 0;
                        $awayPeriodPoints = 0;
                        
                        foreach ($periods as $period) {
                            if ($period['home_result'] == PERIOD_RESULT_WIN) {
                                $homePeriodPoints += UISP_POINTS_PERIOD_WIN;
                            } elseif ($period['home_result'] == PERIOD_RESULT_DRAW) {
                                $homePeriodPoints += UISP_POINTS_PERIOD_DRAW;
                            } elseif ($period['home_result'] == PERIOD_RESULT_LOSS) {
                                $homePeriodPoints += UISP_POINTS_PERIOD_LOSS;
                            }
                            
                            if ($period['away_result'] == PERIOD_RESULT_WIN) {
                                $awayPeriodPoints += UISP_POINTS_PERIOD_WIN;
                            } elseif ($period['away_result'] == PERIOD_RESULT_DRAW) {
                                $awayPeriodPoints += UISP_POINTS_PERIOD_DRAW;
                            } elseif ($period['away_result'] == PERIOD_RESULT_LOSS) {
                                $awayPeriodPoints += UISP_POINTS_PERIOD_LOSS;
                            }
                        }
                        
                        // Aggiungi i punti dei periodi
                        $matchPoints = $homePeriodPoints;
                        
                        // In caso di parità nei punti periodo, assegna un punto extra alla squadra con più punti segnati
                        if ($homePeriodPoints == $awayPeriodPoints && $match['home_score'] > $match['away_score']) {
                            $matchPoints += UISP_POINTS_BONUS;
                        }
                        
                        // Aggiorna le statistiche di vittoria/pareggio/sconfitta
                        if ($homePeriodPoints > $awayPeriodPoints) {
                            $won++;
                        } elseif ($homePeriodPoints == $awayPeriodPoints) {
                            if ($match['home_score'] > $match['away_score']) {
                                $won++; // Vittoria per bonus punto
                            } elseif ($match['home_score'] == $match['away_score']) {
                                $drawn++;
                            } else {
                                $lost++;
                            }
                        } else {
                            $lost++;
                        }
                        
                        $points += $matchPoints;
                    } else {
                        // Squadra gioca fuori casa
                        $scored += $match['away_score'];
                        $conceded += $match['home_score'];
                        
                        // Calcola i punti basati sui periodi
                        $homePeriodPoints = 0;
                        $awayPeriodPoints = 0;
                        
                        foreach ($periods as $period) {
                            if ($period['home_result'] == PERIOD_RESULT_WIN) {
                                $homePeriodPoints += UISP_POINTS_PERIOD_WIN;
                            } elseif ($period['home_result'] == PERIOD_RESULT_DRAW) {
                                $homePeriodPoints += UISP_POINTS_PERIOD_DRAW;
                            } elseif ($period['home_result'] == PERIOD_RESULT_LOSS) {
                                $homePeriodPoints += UISP_POINTS_PERIOD_LOSS;
                            }
                            
                            if ($period['away_result'] == PERIOD_RESULT_WIN) {
                                $awayPeriodPoints += UISP_POINTS_PERIOD_WIN;
                            } elseif ($period['away_result'] == PERIOD_RESULT_DRAW) {
                                $awayPeriodPoints += UISP_POINTS_PERIOD_DRAW;
                            } elseif ($period['away_result'] == PERIOD_RESULT_LOSS) {
                                $awayPeriodPoints += UISP_POINTS_PERIOD_LOSS;
                            }
                        }
                        
                        // Aggiungi i punti dei periodi
                        $matchPoints = $awayPeriodPoints;
                        
                        // In caso di parità nei punti periodo, assegna un punto extra alla squadra con più punti segnati
                        if ($homePeriodPoints == $awayPeriodPoints && $match['away_score'] > $match['home_score']) {
                            $matchPoints += UISP_POINTS_BONUS;
                        }
                        
                        // Aggiorna le statistiche di vittoria/pareggio/sconfitta
                        if ($awayPeriodPoints > $homePeriodPoints) {
                            $won++;
                        } elseif ($awayPeriodPoints == $homePeriodPoints) {
                            if ($match['away_score'] > $match['home_score']) {
                                $won++; // Vittoria per bonus punto
                            } elseif ($match['away_score'] == $match['home_score']) {
                                $drawn++;
                            } else {
                                $lost++;
                            }
                        } else {
                            $lost++;
                        }
                        
                        $points += $matchPoints;
                    }
                }
                
                // Aggiorna la tabella standings
                $this->db->update('standings', [
                    'played' => $played,
                    'won' => $won,
                    'drawn' => $drawn,
                    'lost' => $lost,
                    'points' => $points,
                    'scored' => $scored,
                    'conceded' => $conceded,
                    'last_calculated' => date('Y-m-d H:i:s')
                ], 'championship_id = ? AND team_id = ?', [$championshipId, $teamId]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            // Log dell'errore per il debug
            error_log("Errore in calculateStandings UISP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola il risultato previsto di una partita per la simulazione Monte Carlo
     * Per le partite UISP, predice sia i punteggi che gli esiti dei periodi
     * 
     * @param int $homeTeamId ID squadra di casa
     * @param int $awayTeamId ID squadra ospite
     * @param int $championshipId ID campionato
     * @param int $numPeriods Numero di periodi da simulare
     * @return array Array con punteggi e esiti dei periodi
     */
    public function predictMatchResult($homeTeamId, $awayTeamId, $championshipId, $numPeriods = 3) {
        // Verifica che il campionato sia accessibile
        $championship = Championship::findById($championshipId);
        if (!$championship) {
            return $this->generateRandomUISPResult($numPeriods);
        }
        
        // Verifica che l'utente possa accedere alle predizioni
        $currentUser = User::getCurrentUser();
        if (!$currentUser || !$currentUser->canAccessPredictions()) {
            return $this->generateRandomUISPResult($numPeriods);
        }
        
        // Ottieni le statistiche delle squadre
        $homeStats = $this->db->fetchOne("
            SELECT * FROM standings 
            WHERE championship_id = ? AND team_id = ?
        ", [$championshipId, $homeTeamId]);
        
        $awayStats = $this->db->fetchOne("
            SELECT * FROM standings 
            WHERE championship_id = ? AND team_id = ?
        ", [$championshipId, $awayTeamId]);
        
        // Se non ci sono statistiche, usa valori di default
        if (!$homeStats || !$awayStats) {
            return $this->generateRandomUISPResult($numPeriods);
        }
        
        // Calcola la media di punti segnati e subiti per partita
        $homeScored = $homeStats['played'] > 0 ? $homeStats['scored'] / $homeStats['played'] : 1;
        $homeConceeded = $homeStats['played'] > 0 ? $homeStats['conceded'] / $homeStats['played'] : 1;
        $awayScored = $awayStats['played'] > 0 ? $awayStats['scored'] / $awayStats['played'] : 1;
        $awayConceeded = $awayStats['played'] > 0 ? $awayStats['conceded'] / $awayStats['played'] : 1;
        
        // Fattore casa (le squadre segnano in media il 10% in più quando giocano in casa)
        $homeAdvantage = 1.1;
        
        // Calcola i punteggi attesi totali
        $expectedHomeScoreTotal = $homeScored * $awayConceeded * $homeAdvantage;
        $expectedAwayScoreTotal = $awayScored * $homeConceeded;
        
        // Genera punteggi totali usando una distribuzione di Poisson
        $homeScoreTotal = $this->poissonRandom($expectedHomeScoreTotal);
        $awayScoreTotal = $this->poissonRandom($expectedAwayScoreTotal);
        
        // Distribuisci i punti tra i periodi
        $homePeriodScores = $this->distributeScores($homeScoreTotal, $numPeriods);
        $awayPeriodScores = $this->distributeScores($awayScoreTotal, $numPeriods);
        
        // Costruisci i risultati dei periodi
        $periods = [];
        $homeTeamPeriodPoints = 0;
        $awayTeamPeriodPoints = 0;
        
        for ($i = 0; $i < $numPeriods; $i++) {
            $homeScore = $homePeriodScores[$i];
            $awayScore = $awayPeriodScores[$i];
            
            if ($homeScore > $awayScore) {
                $homeResult = PERIOD_RESULT_WIN;
                $awayResult = PERIOD_RESULT_LOSS;
                $homeTeamPeriodPoints += UISP_POINTS_PERIOD_WIN;
                $awayTeamPeriodPoints += UISP_POINTS_PERIOD_LOSS;
            } elseif ($homeScore == $awayScore) {
                $homeResult = PERIOD_RESULT_DRAW;
                $awayResult = PERIOD_RESULT_DRAW;
                $homeTeamPeriodPoints += UISP_POINTS_PERIOD_DRAW;
                $awayTeamPeriodPoints += UISP_POINTS_PERIOD_DRAW;
            } else {
                $homeResult = PERIOD_RESULT_LOSS;
                $awayResult = PERIOD_RESULT_WIN;
                $homeTeamPeriodPoints += UISP_POINTS_PERIOD_LOSS;
                $awayTeamPeriodPoints += UISP_POINTS_PERIOD_WIN;
            }
            
            $periods[] = [
                'home_result' => $homeResult,
                'away_result' => $awayResult,
                'home_score' => $homeScore,
                'away_score' => $awayScore
            ];
        }
        
        // In caso di parità nei punti dei periodi, assegna un punto bonus alla squadra con più punti
        if ($homeTeamPeriodPoints == $awayTeamPeriodPoints) {
            if ($homeScoreTotal > $awayScoreTotal) {
                $homeTeamPeriodPoints += UISP_POINTS_BONUS;
            } elseif ($awayScoreTotal > $homeScoreTotal) {
                $awayTeamPeriodPoints += UISP_POINTS_BONUS;
            }
        }
        
        return [
            'home_score' => $homeScoreTotal,
            'away_score' => $awayScoreTotal,
            'home_period_points' => $homeTeamPeriodPoints,
            'away_period_points' => $awayTeamPeriodPoints,
            'periods' => $periods
        ];
    }
    
    /**
     * Genera un punteggio casuale usando una distribuzione di Poisson
     * @param float $lambda Media della distribuzione
     * @return int Numero casuale
     */
    private function poissonRandom($lambda) {
        $lambda = max(0.1, $lambda); // Evita valori negativi o zero
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L && $k < 100); // Aggiunto limite per evitare loop infiniti
        
        return max(0, $k - 1); // Garantisce che non sia negativo
    }
    
    /**
     * Distribuisce un punteggio totale tra un numero di periodi
     * @param int $totalScore Punteggio totale
     * @param int $numPeriods Numero di periodi
     * @return array Array di punteggi per periodo
     */
    private function distributeScores($totalScore, $numPeriods) {
        $periodScores = array_fill(0, $numPeriods, 0);
        
        // Garantisci che numPeriods sia positivo per evitare divisione per zero
        $numPeriods = max(1, $numPeriods);
        
        // Se il punteggio totale è 0, restituisci direttamente array di zeri
        if ($totalScore <= 0) {
            return $periodScores;
        }
        
        // Distribuisci i punti in modo casuale tra i periodi
        for ($i = 0; $i < $totalScore; $i++) {
            $periodIdx = mt_rand(0, $numPeriods - 1);
            $periodScores[$periodIdx]++;
        }
        
        return $periodScores;
    }
    
    /**
     * Genera un risultato UISP casuale quando non ci sono dati sufficienti
     * @param int $numPeriods Numero di periodi
     * @return array Risultato casuale
     */
    private function generateRandomUISPResult($numPeriods) {
        // Verifica che numPeriods sia valido
        $numPeriods = max(1, $numPeriods);
        
        $homeScoreTotal = mt_rand(0, 5);
        $awayScoreTotal = mt_rand(0, 5);
        
        $homePeriodScores = $this->distributeScores($homeScoreTotal, $numPeriods);
        $awayPeriodScores = $this->distributeScores($awayScoreTotal, $numPeriods);
        
        $periods = [];
        $homeTeamPeriodPoints = 0;
        $awayTeamPeriodPoints = 0;
        
        for ($i = 0; $i < $numPeriods; $i++) {
            $homeScore = $homePeriodScores[$i];
            $awayScore = $awayPeriodScores[$i];
            
            if ($homeScore > $awayScore) {
                $homeResult = PERIOD_RESULT_WIN;
                $awayResult = PERIOD_RESULT_LOSS;
                $homeTeamPeriodPoints += UISP_POINTS_PERIOD_WIN;
                $awayTeamPeriodPoints += UISP_POINTS_PERIOD_LOSS;
            } elseif ($homeScore == $awayScore) {
                $homeResult = PERIOD_RESULT_DRAW;
                $awayResult = PERIOD_RESULT_DRAW;
                $homeTeamPeriodPoints += UISP_POINTS_PERIOD_DRAW;
                $awayTeamPeriodPoints += UISP_POINTS_PERIOD_DRAW;
            } else {
                $homeResult = PERIOD_RESULT_LOSS;
                $awayResult = PERIOD_RESULT_WIN;
                $homeTeamPeriodPoints += UISP_POINTS_PERIOD_LOSS;
                $awayTeamPeriodPoints += UISP_POINTS_PERIOD_WIN;
            }
            
            $periods[] = [
                'home_result' => $homeResult,
                'away_result' => $awayResult,
                'home_score' => $homeScore,
                'away_score' => $awayScore
            ];
        }
        
        // In caso di parità nei punti dei periodi, assegna un punto bonus alla squadra con più punti
        if ($homeTeamPeriodPoints == $awayTeamPeriodPoints) {
            if ($homeScoreTotal > $awayScoreTotal) {
                $homeTeamPeriodPoints += UISP_POINTS_BONUS;
            } elseif ($awayScoreTotal > $homeScoreTotal) {
                $awayTeamPeriodPoints += UISP_POINTS_BONUS;
            }
        }
        
        return [
            'home_score' => $homeScoreTotal,
            'away_score' => $awayScoreTotal,
            'home_period_points' => $homeTeamPeriodPoints,
            'away_period_points' => $awayTeamPeriodPoints,
            'periods' => $periods
        ];
    }
}