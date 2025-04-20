<?php
// classes/Match.php

class Match {
    private $db;
    private $id;
    private $championshipId;
    private $homeTeamId;
    private $awayTeamId;
    private $matchDate;
    private $homeScore;
    private $awayScore;
    private $status;
    private $notes;
    
    /**
     * Costruttore
     * @param int|null $id ID partita (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati della partita dal database
     * @param int $id ID partita
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $match = $this->db->fetchOne("SELECT * FROM matches WHERE id = ?", [$id]);
        
        if ($match) {
            $this->id = $match['id'];
            $this->championshipId = $match['championship_id'];
            $this->homeTeamId = $match['home_team_id'];
            $this->awayTeamId = $match['away_team_id'];
            $this->matchDate = $match['match_date'];
            $this->homeScore = $match['home_score'];
            $this->awayScore = $match['away_score'];
            $this->status = $match['status'];
            $this->notes = $match['notes'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Verifica se l'utente specificato ha accesso alla partita
     * @param int $userId ID utente
     * @return bool True se l'utente ha accesso, false altrimenti
     */
    public function isAccessibleByUser($userId) {
        if (!$this->id) {
            return false;
        }
        
        // Gli admin hanno sempre accesso
        $user = User::findById($userId);
        if ($user && $user->isAdmin()) {
            return true;
        }
        
        // Verifica l'accesso tramite il campionato
        $championship = Championship::findById($this->championshipId);
        return $championship ? $championship->isAccessibleByUser($userId) : false;
    }
    
    /**
     * Verifica se l'utente corrente può modificare questa partita
     * @return bool
     */
    public function canEdit() {
        $currentUser = User::getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        
        if ($currentUser->isAdmin()) {
            return true;
        }
        
        // Verifica se l'utente è proprietario del campionato
        $championship = Championship::findById($this->championshipId);
        return $championship ? ($championship->getUserId() == $currentUser->getId()) : false;
    }
    
    /**
     * Crea una nuova partita
     * @param int $championshipId ID del campionato
     * @param int $homeTeamId ID della squadra di casa
     * @param int $awayTeamId ID della squadra ospite
     * @param string $matchDate Data e ora della partita
     * @param string $status Stato della partita
     * @param string|null $notes Note
     * @return int|false ID della partita creata o false in caso di errore
     */
    public function create($championshipId, $homeTeamId, $awayTeamId, $matchDate, $status = MATCH_STATUS_SCHEDULED, $notes = null) {
        // Verifica che il campionato esista e sia accessibile all'utente corrente
        $championship = Championship::findById($championshipId);
        if (!$championship || !$championship->canEdit()) {
            return false;
        }
        
        // Verifica che le squadre esistano e partecipino al campionato
        if (!$this->isTeamInChampionship($homeTeamId, $championshipId) || 
            !$this->isTeamInChampionship($awayTeamId, $championshipId)) {
            return false;
        }
        
        // Inserimento partita
        $matchId = $this->db->insert('matches', [
            'championship_id' => $championshipId,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'match_date' => $matchDate,
            'status' => $status,
            'notes' => $notes,
            'home_score' => ($status == MATCH_STATUS_COMPLETED) ? 0 : null,
            'away_score' => ($status == MATCH_STATUS_COMPLETED) ? 0 : null
        ]);
        
        if ($matchId) {
            $this->id = $matchId;
            $this->championshipId = $championshipId;
            $this->homeTeamId = $homeTeamId;
            $this->awayTeamId = $awayTeamId;
            $this->matchDate = $matchDate;
            $this->status = $status;
            $this->notes = $notes;
            $this->homeScore = ($status == MATCH_STATUS_COMPLETED) ? 0 : null;
            $this->awayScore = ($status == MATCH_STATUS_COMPLETED) ? 0 : null;
            
            // Aggiorna statistiche utente
            if ($championship) {
                $userId = $championship->getUserId();
                $stats = new UserStats($userId);
                $stats->incrementMatches();
            }
        }
        
        return $matchId;
    }
    
    /**
     * Aggiorna i dati della partita
     * @param array $data Dati da aggiornare
     * @return bool Successo/fallimento
     */
    public function update($data) {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente corrente possa modificare questa partita
        if (!$this->canEdit()) {
            return false;
        }
        
        // Usa una transazione per assicurarsi che tutti gli aggiornamenti siano completi
        $this->db->beginTransaction();
        
        try {
            $result = $this->db->update('matches', $data, 'id = ?', [$this->id]);
            
            // Aggiorna le proprietà locali
            if ($result) {
                foreach ($data as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->$key = $value;
                    }
                }
            }
            
            // Se è stato completato, aggiorna la classifica
            if (isset($data['status']) && $data['status'] == MATCH_STATUS_COMPLETED) {
                $championship = Championship::findById($this->championshipId);
                if ($championship) {
                    $championship->calculateStandings();
                    // Calcola le probabilità di vittoria dopo l'aggiornamento della classifica
                    $this->calculateWinProbabilities();
                }
            }
            
            $this->db->commit();
            return $result > 0;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Errore nell'aggiornamento della partita: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina la partita
     * @return bool Successo/fallimento
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente corrente possa modificare questa partita
        if (!$this->canEdit()) {
            return false;
        }
        
        // Utilizza una transazione per garantire l'integrità dei dati
        $this->db->beginTransaction();
        
        try {
            // Elimina le probabilità della partita
            $this->db->delete('match_probabilities', 'match_id = ?', [$this->id]);
            
            // Elimina la partita
            $result = $this->db->delete('matches', 'id = ?', [$this->id]);
            
            if ($result) {
                // Se la partita era completata, ricalcola le classifiche
                if ($this->status == MATCH_STATUS_COMPLETED) {
                    $championship = Championship::findById($this->championshipId);
                    if ($championship) {
                        $championship->calculateStandings();
                    }
                }
                
                // Aggiorna statistiche utente
                $championship = Championship::findById($this->championshipId);
                if ($championship) {
                    $userId = $championship->getUserId();
                    $stats = new UserStats($userId);
                    $stats->decrementMatches();
                }
                
                $this->id = null;
                $this->championshipId = null;
                $this->homeTeamId = null;
                $this->awayTeamId = null;
                $this->matchDate = null;
                $this->homeScore = null;
                $this->awayScore = null;
                $this->status = null;
                $this->notes = null;
            }
            
            $this->db->commit();
            return $result > 0;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Errore nell'eliminazione della partita: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Imposta il risultato della partita
     * @param int $homeScore Punteggio squadra di casa
     * @param int $awayScore Punteggio squadra ospite
     * @return bool Successo/fallimento
     */
    public function setResult($homeScore, $awayScore) {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente corrente possa modificare questa partita
        if (!$this->canEdit()) {
            return false;
        }
        
        $result = $this->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'status' => MATCH_STATUS_COMPLETED
        ]);
        
        if ($result) {
            // Aggiorna la classifica
            $championship = Championship::findById($this->championshipId);
            if ($championship) {
                $championship->calculateStandings();
                
                // Calcola le probabilità di vittoria
                $this->calculateWinProbabilities();
            }
        }
        
        return $result;
    }
    
    /**
     * Calcola le probabilità di vittoria per questa partita
     * @return bool Successo/fallimento
     */
    public function calculateWinProbabilities() {
        if (!$this->id || $this->status == MATCH_STATUS_COMPLETED) {
            return false; // Non calcolare per partite già completate
        }
        
        // Verifica che l'utente possa accedere alle predizioni
        $championship = Championship::findById($this->championshipId);
        if (!$championship) {
            return false;
        }
        
        $currentUser = User::getCurrentUser();
        if (!$currentUser || !$currentUser->canAccessPredictions()) {
            return false;
        }
        
        // Ottieni le statistiche delle squadre nel campionato attuale
        $homeTeamStats = $this->db->fetchOne("
            SELECT * FROM standings
            WHERE championship_id = ? AND team_id = ?
        ", [$this->championshipId, $this->homeTeamId]);
        
        $awayTeamStats = $this->db->fetchOne("
            SELECT * FROM standings
            WHERE championship_id = ? AND team_id = ?
        ", [$this->championshipId, $this->awayTeamId]);
        
        // Ottieni anche le statistiche storiche di entrambe le squadre
        $homeTeamHistoricalStats = $this->getTeamHistoricalStats($this->homeTeamId);
        $awayTeamHistoricalStats = $this->getTeamHistoricalStats($this->awayTeamId);
        
        // Calcola le statistiche testa a testa tra le due squadre
        $headToHeadStats = $this->getHeadToHeadStats($this->homeTeamId, $this->awayTeamId);
        
        // Calcola le forze delle squadre combinando statistiche correnti e storiche
        $homeStrength = $this->calculateTeamStrength($homeTeamStats, $homeTeamHistoricalStats, true);
        $awayStrength = $this->calculateTeamStrength($awayTeamStats, $awayTeamHistoricalStats, false);
        
        // Applica fattori correttivi basati su statistiche testa a testa
        if ($headToHeadStats && $headToHeadStats['total_matches'] > 0) {
            $homeWinRate = $headToHeadStats['home_wins'] / $headToHeadStats['total_matches'];
            $homeStrength = $homeStrength * 0.7 + $homeWinRate * 0.3;
            
            $awayWinRate = $headToHeadStats['away_wins'] / $headToHeadStats['total_matches'];
            $awayStrength = $awayStrength * 0.7 + $awayWinRate * 0.3;
        }
        
        // Normalizza le forze per evitare valori estremi
        $homeStrength = max(0.2, min(0.95, $homeStrength));
        $awayStrength = max(0.2, min(0.95, $awayStrength));
        
        // Calcola le probabilità
        $totalStrength = $homeStrength + $awayStrength;
        $homeWinProb = ($totalStrength > 0) ? ($homeStrength / $totalStrength) * 100 : 50;
        $awayWinProb = 100 - $homeWinProb;
        
        // Limita le probabilità tra 5% e 95% per maggiore realismo
        $homeWinProb = max(5, min(95, $homeWinProb));
        $awayWinProb = max(5, min(95, $awayWinProb));
        
        // Arrotonda a una cifra decimale
        $homeWinProb = round($homeWinProb, 1);
        $awayWinProb = round($awayWinProb, 1);
        
        // Verifica se esiste già una riga di probabilità per questa partita
        $existingProb = $this->db->fetchOne("
            SELECT * FROM match_probabilities
            WHERE match_id = ?
        ", [$this->id]);
        
        if ($existingProb) {
            // Aggiorna le probabilità esistenti
            return $this->db->update('match_probabilities', [
                'home_win_probability' => $homeWinProb,
                'away_win_probability' => $awayWinProb,
                'calculated_at' => date('Y-m-d H:i:s')
            ], 'match_id = ?', [$this->id]) > 0;
        } else {
            // Inserisci nuove probabilità
            return $this->db->insert('match_probabilities', [
                'match_id' => $this->id,
                'home_win_probability' => $homeWinProb,
                'away_win_probability' => $awayWinProb,
                'calculated_at' => date('Y-m-d H:i:s')
            ]) !== false;
        }
    }

    /**
     * Calcola un punteggio di forza per una squadra basato su statistiche correnti e storiche
     * @param array|null $currentStats Statistiche nel campionato corrente
     * @param array|null $historicalStats Statistiche storiche
     * @param bool $isHomeTeam Indica se la squadra gioca in casa
     * @return float Punteggio di forza
     */
    private function calculateTeamStrength($currentStats, $historicalStats, $isHomeTeam = false) {
        // Forza basata sulle statistiche correnti
        $currentStrength = 0.5;
        if ($currentStats && $currentStats['played'] > 0) {
            // Percentuale di vittorie nel campionato attuale
            $winPercentage = $currentStats['won'] / $currentStats['played'];
            
            // Differenza punti per partita
            $pointDiff = $currentStats['scored'] - $currentStats['conceded'];
            $pointDiffPerGame = $pointDiff / $currentStats['played'];
            
            // Normalizza la differenza punti (tipicamente tra -20 e +20 nel basket)
            $pointDiffFactor = ($pointDiffPerGame + 20) / 40;
            $pointDiffFactor = max(0.1, min(0.9, $pointDiffFactor));
            
            // Combina i fattori
            $currentStrength = ($winPercentage * 0.6) + ($pointDiffFactor * 0.4);
        }
        
        // Forza basata sulle statistiche storiche
        $historicalStrength = 0.5;
        if ($historicalStats && isset($historicalStats['total_matches']) && $historicalStats['total_matches'] > 0) {
            // Percentuale di vittorie storiche
            $historicalWinPercentage = $historicalStats['wins'] / $historicalStats['total_matches'];
            
            // Differenza punti storica per partita
            $historicalPointDiff = $historicalStats['points_scored'] - $historicalStats['points_conceded'];
            $historicalPointDiffPerGame = $historicalPointDiff / $historicalStats['total_matches'];
            
            // Normalizza
            $historicalPointDiffFactor = ($historicalPointDiffPerGame + 20) / 40;
            $historicalPointDiffFactor = max(0.1, min(0.9, $historicalPointDiffFactor));
            
            // Considera anche la performance in casa/trasferta
            $homeAwayFactor = 0.5;
            if (isset($historicalStats['home_matches']) && $historicalStats['home_matches'] > 0 &&
                isset($historicalStats['away_matches']) && $historicalStats['away_matches'] > 0) {
                
                $homeWinPercentage = $historicalStats['home_wins'] / $historicalStats['home_matches'];
                $awayWinPercentage = $historicalStats['away_wins'] / $historicalStats['away_matches'];
                
                $homeAwayFactor = $isHomeTeam ? $homeWinPercentage : $awayWinPercentage;
            }
            
            // Combina i fattori
            $historicalStrength = ($historicalWinPercentage * 0.5) + 
                                  ($historicalPointDiffFactor * 0.3) + 
                                  ($homeAwayFactor * 0.2);
        }
        
        // Combina le forze correnti e storiche (più peso alle statistiche correnti)
        // Ma se non ci sono molte partite nel campionato attuale, dai più peso allo storico
        $currentWeight = 0.7;
        if ($currentStats && $currentStats['played'] < 5) {
            // Reduce il peso delle statistiche correnti se ci sono poche partite
            $currentWeight = 0.3 + ($currentStats['played'] * 0.1);
        }
        
        $strength = ($currentStrength * $currentWeight) + ($historicalStrength * (1 - $currentWeight));
        
        // Aggiungi vantaggio casa se applicabile (Il fattore casa è importante nel basket)
        if ($isHomeTeam) {
            $strength *= 1.1; // 10% di vantaggio per giocare in casa
        }
        
        return $strength;
    }

    /**
     * Ottiene le statistiche storiche di una squadra
     * @param int $teamId ID della squadra
     * @return array|null Statistiche storiche o null se non disponibili
     */
    private function getTeamHistoricalStats($teamId) {
        // Filtra per campionati accessibili all'utente corrente
        $championshipFilter = "";
        $params = [
            $teamId, $teamId, // Per wins
            $teamId, $teamId, // Per points
            $teamId, $teamId, // Per home stats
            $teamId, $teamId, // Per away stats
            $teamId, $teamId, MATCH_STATUS_COMPLETED // Filtro
        ];
        
        $currentUser = User::getCurrentUser();
        if ($currentUser && !$currentUser->isAdmin()) {
            $championshipFilter = "AND m.championship_id IN (
                SELECT id FROM championships WHERE user_id = ? OR is_public = 1
            )";
            $params[] = $currentUser->getId();
        }
        
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(m.id) as total_matches,
                SUM(CASE WHEN (m.home_team_id = ? AND m.home_score > m.away_score) OR 
                             (m.away_team_id = ? AND m.away_score > m.home_score) 
                        THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN m.home_team_id = ? THEN m.home_score ELSE m.away_score END) as points_scored,
                SUM(CASE WHEN m.home_team_id = ? THEN m.away_score ELSE m.home_score END) as points_conceded,
                
                -- Statistiche in casa
                COUNT(CASE WHEN m.home_team_id = ? THEN 1 ELSE NULL END) as home_matches,
                SUM(CASE WHEN m.home_team_id = ? AND m.home_score > m.away_score THEN 1 ELSE 0 END) as home_wins,
                
                -- Statistiche in trasferta
                COUNT(CASE WHEN m.away_team_id = ? THEN 1 ELSE NULL END) as away_matches,
                SUM(CASE WHEN m.away_team_id = ? AND m.away_score > m.home_score THEN 1 ELSE 0 END) as away_wins
                
            FROM matches m
            WHERE (m.home_team_id = ? OR m.away_team_id = ?) AND m.status = ?
            $championshipFilter
        ", $params);
        
        return $stats;
    }

    /**
     * Ottiene le statistiche degli scontri diretti tra due squadre
     * @param int $team1Id ID della prima squadra
     * @param int $team2Id ID della seconda squadra
     * @return array|null Statistiche degli scontri diretti o null se non disponibili
     */
    private function getHeadToHeadStats($team1Id, $team2Id) {
        // Filtra per campionati accessibili all'utente corrente
        $championshipFilter = "";
        $params = [
            $team1Id, $team1Id, 
            $team1Id, $team2Id, 
            $team2Id, $team1Id, 
            MATCH_STATUS_COMPLETED
        ];
        
        $currentUser = User::getCurrentUser();
        if ($currentUser && !$currentUser->isAdmin()) {
            $championshipFilter = "AND m.championship_id IN (
                SELECT id FROM championships WHERE user_id = ? OR is_public = 1
            )";
            $params[] = $currentUser->getId();
        }
        
        $stats = $this->db->fetchOne("
            SELECT 
                COUNT(m.id) as total_matches,
                SUM(CASE WHEN m.home_team_id = ? AND m.home_score > m.away_score THEN 1 ELSE 0 END) as home_wins,
                SUM(CASE WHEN m.away_team_id = ? AND m.away_score > m.home_score THEN 1 ELSE 0 END) as away_wins
            FROM matches m
            WHERE ((m.home_team_id = ? AND m.away_team_id = ?) OR 
                   (m.home_team_id = ? AND m.away_team_id = ?)) 
                  AND m.status = ?
            $championshipFilter
        ", $params);
        
        return $stats;
    }
    
    /**
     * Ottiene le probabilità di vittoria per questa partita
     * @return array|null Probabilità di vittoria o null se non disponibili
     */
    public function getWinProbabilities() {
        if (!$this->id) {
            return null;
        }
        
        // Verifica che l'utente possa accedere alle predizioni
        $currentUser = User::getCurrentUser();
        if (!$currentUser || !$currentUser->canAccessPredictions()) {
            return null;
        }
        
        return $this->db->fetchOne("
            SELECT * FROM match_probabilities
            WHERE match_id = ?
            ORDER BY calculated_at DESC
            LIMIT 1
        ", [$this->id]);
    }
    
    /**
     * Verifica se una squadra partecipa a un campionato
     * @param int $teamId ID squadra
     * @param int $championshipId ID campionato
     * @return bool
     */
    private function isTeamInChampionship($teamId, $championshipId) {
        return $this->db->count('championships_teams', 
            'championship_id = ? AND team_id = ?', 
            [$championshipId, $teamId]
        ) > 0;
    }
    
    /**
     * Ottiene tutte le partite
     * @param int|null $championshipId Filtra per campionato (opzionale)
     * @param string|null $status Filtra per stato (opzionale)
     * @param int|null $userId Filtra per utente proprietario del campionato (opzionale)
     * @param bool $includePublic Include partite di campionati pubblici
     * @return array
     */
    public static function getAll($championshipId = null, $status = null, $userId = null, $includePublic = true) {
        $db = Database::getInstance();
        
        $query = "
            SELECT m.*, 
                   c.name as championship_name,
                   c.type as championship_type,
                   home.name as home_team_name, 
                   away.name as away_team_name
            FROM matches m
            JOIN championships c ON m.championship_id = c.id
            JOIN teams home ON m.home_team_id = home.id
            JOIN teams away ON m.away_team_id = away.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($championshipId) {
            $query .= " AND m.championship_id = ?";
            $params[] = $championshipId;
        }
        
        if ($status) {
            $query .= " AND m.status = ?";
            $params[] = $status;
        }
        
        // Filtra per accessibilità
        $currentUser = User::getCurrentUser();
        
        if ($userId !== null) {
            // Filtra per proprietario specifico
            $query .= " AND c.user_id = ?";
            $params[] = $userId;
        } else if ($currentUser) {
            if (!$currentUser->isAdmin()) {
                // Utente normale: mostra le sue partite e quelle di campionati pubblici
                if ($includePublic) {
                    $query .= " AND (c.user_id = ? OR c.is_public = 1)";
                    $params[] = $currentUser->getId();
                } else {
                    $query .= " AND c.user_id = ?";
                    $params[] = $currentUser->getId();
                }
            }
        } else {
            // Utente non loggato: mostra solo partite di campionati pubblici
            $query .= " AND c.is_public = 1";
        }
        
        $query .= " ORDER BY m.match_date";
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Trova una partita per ID
     * @param int $id ID partita
     * @return Match|null
     */
    public static function findById($id) {
        $match = new self();
        if ($match->load($id)) {
            // Verifica l'accesso dell'utente corrente
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if ($match->isAccessibleByUser($currentUser->getId())) {
                    return $match;
                }
            } else {
                // Verifica se il campionato è pubblico
                $championship = Championship::findById($match->championshipId);
                if ($championship && $championship->getIsPublic()) {
                    return $match;
                }
            }
            return null;
        }
        return null;
    }
    
    /**
     * Ottiene le partite future
     * @param int|null $championshipId Filtra per campionato (opzionale)
     * @param int|null $limit Limite numero risultati (opzionale)
     * @param int|null $userId Filtra per utente proprietario del campionato (opzionale)
     * @param bool $includePublic Include partite di campionati pubblici
     * @return array
     */
    public static function getUpcoming($championshipId = null, $limit = null, $userId = null, $includePublic = true) {
        $db = Database::getInstance();
        
        $query = "
            SELECT m.*, 
                   c.name as championship_name,
                   c.type as championship_type,
                   home.name as home_team_name, 
                   away.name as away_team_name,
                   mp.home_win_probability,
                   mp.away_win_probability
            FROM matches m
            JOIN championships c ON m.championship_id = c.id
            JOIN teams home ON m.home_team_id = home.id
            JOIN teams away ON m.away_team_id = away.id
            LEFT JOIN match_probabilities mp ON m.id = mp.match_id
            WHERE m.match_date >= NOW() AND m.status = ?
        ";
        
        $params = [MATCH_STATUS_SCHEDULED];
        
        if ($championshipId) {
            $query .= " AND m.championship_id = ?";
            $params[] = $championshipId;
        }
        
        // Filtra per accessibilità
        $currentUser = User::getCurrentUser();
        
        if ($userId !== null) {
            // Filtra per proprietario specifico
            $query .= " AND c.user_id = ?";
            $params[] = $userId;
        } else if ($currentUser) {
            if (!$currentUser->isAdmin()) {
                // Utente normale: mostra le sue partite e quelle di campionati pubblici
                if ($includePublic) {
                    $query .= " AND (c.user_id = ? OR c.is_public = 1)";
                    $params[] = $currentUser->getId();
                } else {
                    $query .= " AND c.user_id = ?";
                    $params[] = $currentUser->getId();
                }
            }
        } else {
            // Utente non loggato: mostra solo partite di campionati pubblici
            $query .= " AND c.is_public = 1";
        }
        
        $query .= " ORDER BY m.match_date";
        
        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
        }
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Ottiene i risultati recenti
     * @param int|null $championshipId Filtra per campionato (opzionale)
     * @param int|null $limit Limite numero risultati (opzionale)
     * @param int|null $userId Filtra per utente proprietario del campionato (opzionale)
     * @param bool $includePublic Include partite di campionati pubblici
     * @return array
     */
    public static function getRecent($championshipId = null, $limit = null, $userId = null, $includePublic = true) {
        $db = Database::getInstance();
        
        $query = "
            SELECT m.*, 
                   c.name as championship_name,
                   c.type as championship_type,
                   home.name as home_team_name, 
                   away.name as away_team_name
            FROM matches m
            JOIN championships c ON m.championship_id = c.id
            JOIN teams home ON m.home_team_id = home.id
            JOIN teams away ON m.away_team_id = away.id
            WHERE m.status = ?
        ";
        
        $params = [MATCH_STATUS_COMPLETED];
        
        if ($championshipId) {
            $query .= " AND m.championship_id = ?";
            $params[] = $championshipId;
        }
        
        // Filtra per accessibilità
        $currentUser = User::getCurrentUser();
        
        if ($userId !== null) {
            // Filtra per proprietario specifico
            $query .= " AND c.user_id = ?";
            $params[] = $userId;
        } else if ($currentUser) {
            if (!$currentUser->isAdmin()) {
                // Utente normale: mostra le sue partite e quelle di campionati pubblici
                if ($includePublic) {
                    $query .= " AND (c.user_id = ? OR c.is_public = 1)";
                    $params[] = $currentUser->getId();
                } else {
                    $query .= " AND c.user_id = ?";
                    $params[] = $currentUser->getId();
                }
            }
        } else {
            // Utente non loggato: mostra solo partite di campionati pubblici
            $query .= " AND c.is_public = 1";
        }
        
        $query .= " ORDER BY m.match_date DESC";
        
        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
        }
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Ottiene le statistiche delle partite per utente
     * @param int $userId ID utente
     * @return array
     */
    public static function getUserStats($userId) {
        $db = Database::getInstance();
        
        $stats = [
            'total' => 0,
            'completed' => 0,
            'scheduled' => 0,
            'postponed' => 0,
            'cancelled' => 0,
            'by_championship' => []
        ];
        
        // Conteggio totale partite per stato
        $statusCounts = $db->fetchAll("
            SELECT m.status, COUNT(m.id) as count
            FROM matches m
            JOIN championships c ON m.championship_id = c.id
            WHERE c.user_id = ?
            GROUP BY m.status
        ", [$userId]);
        
        if ($statusCounts) {
            foreach ($statusCounts as $row) {
                $status = $row['status'];
                $stats[$status] = $row['count'];
                $stats['total'] += $row['count'];
            }
        }
        
        // Conteggio partite per campionato
        $championshipCounts = $db->fetchAll("
            SELECT c.id, c.name, COUNT(m.id) as match_count
            FROM championships c
            LEFT JOIN matches m ON c.id = m.championship_id
            WHERE c.user_id = ?
            GROUP BY c.id, c.name
            ORDER BY match_count DESC
        ", [$userId]);
        
        if ($championshipCounts) {
            $stats['by_championship'] = $championshipCounts;
        }
        
        return $stats;
    }
    
    // Getter e setter
    public function getId() { return $this->id; }
    public function getChampionshipId() { return $this->championshipId; }
    public function getHomeTeamId() { return $this->homeTeamId; }
    public function getAwayTeamId() { return $this->awayTeamId; }
    public function getMatchDate() { return $this->matchDate; }
    public function getHomeScore() { return $this->homeScore; }
    public function getAwayScore() { return $this->awayScore; }
    public function getStatus() { return $this->status; }
    public function getNotes() { return $this->notes; }
    
    public function setChampionshipId($championshipId) { $this->championshipId = $championshipId; }
    public function setHomeTeamId($homeTeamId) { $this->homeTeamId = $homeTeamId; }
    public function setAwayTeamId($awayTeamId) { $this->awayTeamId = $awayTeamId; }
    public function setMatchDate($matchDate) { $this->matchDate = $matchDate; }
    public function setHomeScore($homeScore) { $this->homeScore = $homeScore; }
    public function setAwayScore($awayScore) { $this->awayScore = $awayScore; }
    public function setStatus($status) { $this->status = $status; }
    public function setNotes($notes) { $this->notes = $notes; }
}