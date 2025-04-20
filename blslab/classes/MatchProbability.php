<?php
// classes/MatchProbability.php

class MatchProbability {
    private $db;
    private $id;
    private $matchId;
    private $homeWinProbability;
    private $awayWinProbability;
    private $calculatedAt;
    
    /**
     * Costruttore
     * @param int|null $id ID probabilità partita (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati della probabilità partita dal database
     * @param int $id ID probabilità partita
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $probability = $this->db->fetchOne("SELECT * FROM match_probabilities WHERE id = ?", [$id]);
        
        if ($probability) {
            $this->id = $probability['id'];
            $this->matchId = $probability['match_id'];
            $this->homeWinProbability = $probability['home_win_probability'];
            $this->awayWinProbability = $probability['away_win_probability'];
            $this->calculatedAt = $probability['calculated_at'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Carica i dati della probabilità partita basandosi sull'ID partita
     * @param int $matchId ID partita
     * @return bool Successo/fallimento
     */
    public function loadByMatchId($matchId) {
        $probability = $this->db->fetchOne("
            SELECT * FROM match_probabilities 
            WHERE match_id = ?
            ORDER BY calculated_at DESC
            LIMIT 1
        ", [$matchId]);
        
        if ($probability) {
            $this->id = $probability['id'];
            $this->matchId = $probability['match_id'];
            $this->homeWinProbability = $probability['home_win_probability'];
            $this->awayWinProbability = $probability['away_win_probability'];
            $this->calculatedAt = $probability['calculated_at'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Crea o aggiorna una probabilità partita
     * @param int $matchId ID partita
     * @param float $homeWinProbability Probabilità vittoria casa
     * @param float $awayWinProbability Probabilità vittoria ospite
     * @return int|bool ID della probabilità creata o false in caso di errore
     */
    public function createOrUpdate($matchId, $homeWinProbability, $awayWinProbability) {
        // Verifica se esiste già una probabilità per questa partita
        $existing = $this->db->fetchOne("
            SELECT id FROM match_probabilities WHERE match_id = ?
        ", [$matchId]);
        
        if ($existing) {
            // Aggiorna la probabilità esistente
            $this->id = $existing['id'];
            return $this->update([
                'home_win_probability' => $homeWinProbability,
                'away_win_probability' => $awayWinProbability,
                'calculated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Crea una nuova probabilità
            $id = $this->db->insert('match_probabilities', [
                'match_id' => $matchId,
                'home_win_probability' => $homeWinProbability,
                'away_win_probability' => $awayWinProbability,
                'calculated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($id) {
                $this->id = $id;
                $this->matchId = $matchId;
                $this->homeWinProbability = $homeWinProbability;
                $this->awayWinProbability = $awayWinProbability;
                $this->calculatedAt = date('Y-m-d H:i:s');
            }
            
            return $id;
        }
    }
    
    /**
     * Aggiorna i dati della probabilità partita
     * @param array $data Dati da aggiornare
     * @return bool Successo/fallimento
     */
    public function update($data) {
        if (!$this->id) {
            return false;
        }
        
        $result = $this->db->update('match_probabilities', $data, 'id = ?', [$this->id]);
        
        // Aggiorna le proprietà locali
        if ($result) {
            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
        
        return $result > 0;
    }
    
    /**
     * Elimina la probabilità partita
     * @return bool Successo/fallimento
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        $result = $this->db->delete('match_probabilities', 'id = ?', [$this->id]);
        
        if ($result) {
            $this->id = null;
            $this->matchId = null;
            $this->homeWinProbability = null;
            $this->awayWinProbability = null;
            $this->calculatedAt = null;
        }
        
        return $result > 0;
    }
    
    /**
     * Calcola le probabilità di vittoria per una partita
     * @param int $matchId ID partita
     * @return bool Successo/fallimento
     */
    public static function calculate($matchId) {
        $match = Match::findById($matchId);
        
        if (!$match || $match->getStatus() != MATCH_STATUS_SCHEDULED) {
            return false;
        }
        
        return $match->calculateWinProbabilities();
    }
    
    /**
     * Ottiene la probabilità di vittoria per una partita
     * @param int $matchId ID partita
     * @return array|null Dati probabilità o null se non esiste
     */
    public static function getByMatchId($matchId) {
        $probability = new self();
        return $probability->loadByMatchId($matchId) ? $probability : null;
    }
    
    /**
     * Ottiene tutte le probabilità delle partite future per un campionato
     * @param int $championshipId ID campionato
     * @return array
     */
    public static function getAllForChampionship($championshipId) {
        $db = Database::getInstance();
        
        return $db->fetchAll("
            SELECT mp.*, m.home_team_id, m.away_team_id, m.match_date,
                   home.name as home_team_name, away.name as away_team_name
            FROM match_probabilities mp
            JOIN matches m ON mp.match_id = m.id
            JOIN teams home ON m.home_team_id = home.id
            JOIN teams away ON m.away_team_id = away.id
            WHERE m.championship_id = ? AND m.status = ?
            ORDER BY m.match_date ASC
        ", [$championshipId, MATCH_STATUS_SCHEDULED]);
    }
    
    // Getter e setter
    public function getId() { return $this->id; }
    public function getMatchId() { return $this->matchId; }
    public function getHomeWinProbability() { return $this->homeWinProbability; }
    public function getAwayWinProbability() { return $this->awayWinProbability; }
    public function getCalculatedAt() { return $this->calculatedAt; }
    
    public function setMatchId($matchId) { $this->matchId = $matchId; }
    public function setHomeWinProbability($probability) { $this->homeWinProbability = $probability; }
    public function setAwayWinProbability($probability) { $this->awayWinProbability = $probability; }
}