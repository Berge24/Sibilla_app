<?php
// classes/CSIRules.php

class CSIRules {
    private $db;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Calcola e aggiorna la classifica per un campionato CSI
     * @param int $championshipId ID del campionato
     * @return bool Successo/fallimento
     */
    public function calculateStandings($championshipId) {
        // Verifica che il campionato esista, sia di tipo CSI e sia accessibile all'utente corrente
        $championship = Championship::findById($championshipId);
        if (!$championship || $championship->getType() !== CHAMPIONSHIP_TYPE_CSI || !$championship->canEdit()) {
            return false;
        }
        
        // Ottieni tutte le squadre del campionato
        $teams = $this->db->fetchAll("
            SELECT team_id FROM championships_teams WHERE championship_id = ?
        ", [$championshipId]);
        
        // Inizializza la transazione
        $this->db->beginTransaction();
        
        try {
            // Per ogni squadra, calcola le statistiche
            foreach ($teams as $team) {
                $teamId = $team['team_id'];
                
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
                    if ($match['home_team_id'] == $teamId) {
                        // Squadra gioca in casa
                        $scored += $match['home_score'];
                        $conceded += $match['away_score'];
                        
                        if ($match['home_score'] > $match['away_score']) {
                            // Vittoria
                            $won++;
                            $points += CSI_POINTS_WIN;
                        } elseif ($match['home_score'] == $match['away_score']) {
                            // Pareggio
                            $drawn++;
                            $points += CSI_POINTS_DRAW;
                        } else {
                            // Sconfitta
                            $lost++;
                            $points += CSI_POINTS_LOSS;
                        }
                    } else {
                        // Squadra gioca fuori casa
                        $scored += $match['away_score'];
                        $conceded += $match['home_score'];
                        
                        if ($match['away_score'] > $match['home_score']) {
                            // Vittoria
                            $won++;
                            $points += CSI_POINTS_WIN;
                        } elseif ($match['away_score'] == $match['home_score']) {
                            // Pareggio
                            $drawn++;
                            $points += CSI_POINTS_DRAW;
                        } else {
                            // Sconfitta
                            $lost++;
                            $points += CSI_POINTS_LOSS;
                        }
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
            error_log("Errore nel calcolo della classifica CSI: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola il risultato previsto di una partita per la simulazione Monte Carlo
     * Utilizza un modello semplice basato sulle prestazioni passate delle squadre
     * 
     * @param int $homeTeamId ID squadra di casa
     * @param int $awayTeamId ID squadra ospite
     * @param int $championshipId ID campionato
     * @return array Array con punteggi predetti [home_score, away_score]
     */
    public function predictMatchResult($homeTeamId, $awayTeamId, $championshipId) {
        // Verifica che il campionato sia accessibile
        $championship = Championship::findById($championshipId);
        if (!$championship) {
            return $this->generateRandomScore();
        }
        
        // Verifica che l'utente possa accedere alle predizioni
        $currentUser = User::getCurrentUser();
        if (!$currentUser || !$currentUser->canAccessPredictions()) {
            return $this->generateRandomScore();
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
            return $this->generateRandomScore();
        }
        
        // Calcola la media di gol segnati e subiti per partita
        $homeScored = $homeStats['played'] > 0 ? $homeStats['scored'] / $homeStats['played'] : 1;
        $homeConceeded = $homeStats['played'] > 0 ? $homeStats['conceded'] / $homeStats['played'] : 1;
        $awayScored = $awayStats['played'] > 0 ? $awayStats['scored'] / $awayStats['played'] : 1;
        $awayConceeded = $awayStats['played'] > 0 ? $awayStats['conceded'] / $awayStats['played'] : 1;
        
        // Fattore casa (le squadre segnano in media il 10% in più quando giocano in casa)
        $homeAdvantage = 1.1;
        
        // Calcola i punteggi attesi
        $expectedHomeScore = $homeScored * $awayConceeded * $homeAdvantage;
        $expectedAwayScore = $awayScored * $homeConceeded;
        
        // Genera punteggi usando una distribuzione di Poisson (approssimata)
        $homeScore = $this->poissonRandom($expectedHomeScore);
        $awayScore = $this->poissonRandom($expectedAwayScore);
        
        return [$homeScore, $awayScore];
    }
    
    /**
     * Genera un punteggio casuale usando una distribuzione di Poisson
     * @param float $lambda Media della distribuzione
     * @return int Numero casuale
     */
    private function poissonRandom($lambda) {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L);
        
        return $k - 1;
    }
    
    /**
     * Genera un punteggio casuale quando non ci sono dati sufficienti
     * @return array [home_score, away_score]
     */
    private function generateRandomScore() {
        return [mt_rand(0, 3), mt_rand(0, 3)];
    }
}