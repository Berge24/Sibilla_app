<?php
// classes/Standing.php

class Standing {
    private $db;
    private $id;
    private $championshipId;
    private $teamId;
    private $played;
    private $won;
    private $drawn;
    private $lost;
    private $points;
    private $scored;
    private $conceded;
    private $winProbability;
    private $lastCalculated;
    
    /**
     * Costruttore
     * @param int|null $id ID classifica (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati della classifica dal database
     * @param int $id ID classifica* @return bool Successo/fallimento
     */
    public function load($id) {
        $standing = $this->db->fetchOne("SELECT * FROM standings WHERE id = ?", [$id]);
        
        if ($standing) {
            $this->id = $standing['id'];
            $this->championshipId = $standing['championship_id'];
            $this->teamId = $standing['team_id'];
            $this->played = $standing['played'];
            $this->won = $standing['won'];
            $this->drawn = $standing['drawn'];
            $this->lost = $standing['lost'];
            $this->points = $standing['points'];
            $this->scored = $standing['scored'];
            $this->conceded = $standing['conceded'];
            $this->winProbability = $standing['win_probability'];
            $this->lastCalculated = $standing['last_calculated'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Carica i dati della classifica basandosi su campionato e squadra
     * @param int $championshipId ID campionato
     * @param int $teamId ID squadra
     * @return bool Successo/fallimento
     */
    public function loadByChampionshipAndTeam($championshipId, $teamId) {
        $standing = $this->db->fetchOne("
            SELECT * FROM standings 
            WHERE championship_id = ? AND team_id = ?
        ", [$championshipId, $teamId]);
        
        if ($standing) {
            $this->id = $standing['id'];
            $this->championshipId = $standing['championship_id'];
            $this->teamId = $standing['team_id'];
            $this->played = $standing['played'];
            $this->won = $standing['won'];
            $this->drawn = $standing['drawn'];
            $this->lost = $standing['lost'];
            $this->points = $standing['points'];
            $this->scored = $standing['scored'];
            $this->conceded = $standing['conceded'];
            $this->winProbability = $standing['win_probability'];
            $this->lastCalculated = $standing['last_calculated'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Verifica se l'utente specificato ha accesso alla classifica
     * @param int $userId ID utente
     * @return bool True se l'utente ha accesso, false altrimenti
     */
    public function isAccessibleByUser($userId) {
        if (!$this->id || !$this->championshipId) {
            return false;
        }
        
        // Verifica l'accesso tramite il campionato
        $championship = Championship::findById($this->championshipId);
        return $championship ? $championship->isAccessibleByUser($userId) : false;
    }
    
    /**
     * Verifica se l'utente corrente può modificare questa classifica
     * @return bool
     */
    public function canEdit() {
        if (!$this->id || !$this->championshipId) {
            return false;
        }
        
        // Verifica se l'utente può modificare il campionato relativo
        $championship = Championship::findById($this->championshipId);
        return $championship ? $championship->canEdit() : false;
    }
    
    /**
     * Aggiorna i dati della classifica
     * @param array $data Dati da aggiornare
     * @return bool Successo/fallimento
     */
    public function update($data) {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente corrente possa modificare questa classifica
        if (!$this->canEdit()) {
            return false;
        }
        
        $result = $this->db->update('standings', $data, 'id = ?', [$this->id]);
        
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
     * Ottiene tutte le classifiche
     * @param int|null $championshipId Filtra per campionato (opzionale)
     * @param int|null $userId Filtra per utente proprietario del campionato (opzionale)
     * @param bool $includePublic Include classifiche di campionati pubblici
     * @return array
     */
    public static function getAll($championshipId = null, $userId = null, $includePublic = true) {
        $db = Database::getInstance();
        
        $query = "
            SELECT s.*, t.name as team_name, c.name as championship_name, c.type as championship_type
            FROM standings s
            JOIN teams t ON s.team_id = t.id
            JOIN championships c ON s.championship_id = c.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($championshipId) {
            $query .= " AND s.championship_id = ?";
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
                // Utente normale: mostra le sue classifiche e quelle di campionati pubblici
                if ($includePublic) {
                    $query .= " AND (c.user_id = ? OR c.is_public = 1)";
                    $params[] = $currentUser->getId();
                } else {
                    $query .= " AND c.user_id = ?";
                    $params[] = $currentUser->getId();
                }
            }
        } else {
            // Utente non loggato: mostra solo classifiche di campionati pubblici
            $query .= " AND c.is_public = 1";
        }
        
        $query .= " ORDER BY s.points DESC, s.won DESC, (s.scored - s.conceded) DESC";
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Ottiene la classifica di un campionato
     * @param int $championshipId ID campionato
     * @return array
     */
    public static function getByChampionship($championshipId) {
        $db = Database::getInstance();
        
        // Verifica che il campionato sia accessibile per l'utente corrente
        $championship = Championship::findById($championshipId);
        if (!$championship) {
            return [];
        }
        
        return $db->fetchAll("
            SELECT s.*, t.name as team_name, c.type as championship_type
            FROM standings s
            JOIN teams t ON s.team_id = t.id
            JOIN championships c ON s.championship_id = c.id
            WHERE s.championship_id = ?
            ORDER BY s.points DESC, s.won DESC, (s.scored - s.conceded) DESC
        ", [$championshipId]);
    }
    
    /**
     * Trova una classifica per ID
     * @param int $id ID classifica
     * @return Standing|null
     */
    public static function findById($id) {
        $standing = new self();
        if ($standing->load($id)) {
            // Verifica l'accesso dell'utente corrente
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if ($standing->isAccessibleByUser($currentUser->getId())) {
                    return $standing;
                }
            } else {
                // Verifica se il campionato è pubblico
                $championship = Championship::findById($standing->championshipId);
                if ($championship && $championship->getIsPublic()) {
                    return $standing;
                }
            }
            return null;
        }
        return null;
    }
    
    /**
     * Trova una classifica per campionato e squadra
     * @param int $championshipId ID campionato
     * @param int $teamId ID squadra
     * @return Standing|null
     */
    public static function findByChampionshipAndTeam($championshipId, $teamId) {
        // Verifica che il campionato sia accessibile per l'utente corrente
        $championship = Championship::findById($championshipId);
        if (!$championship) {
            return null;
        }
        
        $standing = new self();
        return $standing->loadByChampionshipAndTeam($championshipId, $teamId) ? $standing : null;
    }
    
    /**
     * Calcola la differenza reti
     * @return int Differenza reti
     */
    public function getGoalDifference() {
        return $this->scored - $this->conceded;
    }
    
    /**
     * Calcola la posizione in classifica
     * @return int Posizione in classifica
     */
    public function getPosition() {
        if (!$this->championshipId) {
            return 0;
        }
        
        $standings = self::getByChampionship($this->championshipId);
        
        foreach ($standings as $index => $standing) {
            if ($standing['id'] == $this->id) {
                return $index + 1;
            }
        }
        
        return 0;
    }
    
    /**
     * Ottiene le statistiche per un utente
     * @param int $userId ID utente
     * @return array
     */
    public static function getUserStats($userId) {
        $db = Database::getInstance();
        
        $stats = [
            'championships' => [],
            'teams' => [],
            'top_teams' => []
        ];
        
        // Ottieni le statistiche di classifica per campionato
        $stats['championships'] = $db->fetchAll("
            SELECT c.id, c.name, 
                   COUNT(s.id) as teams_count,
                   MIN(s.last_calculated) as first_calculated,
                   MAX(s.last_calculated) as last_calculated
            FROM championships c
            JOIN standings s ON c.id = s.championship_id
            WHERE c.user_id = ?
            GROUP BY c.id, c.name
            ORDER BY last_calculated DESC
        ", [$userId]);
        
        // Ottieni le statistiche di classifica per squadra (top 10)
        $stats['top_teams'] = $db->fetchAll("
            SELECT t.id, t.name, 
                   AVG(s.points) as avg_points,
                   SUM(s.won) as total_wins,
                   SUM(s.lost) as total_losses,
                   SUM(s.points) as total_points,
                   COUNT(s.id) as championships_count
            FROM teams t
            JOIN standings s ON t.id = s.team_id
            JOIN championships c ON s.championship_id = c.id
            WHERE c.user_id = ? AND s.played > 0
            GROUP BY t.id, t.name
            ORDER BY avg_points DESC
            LIMIT 10
        ", [$userId]);
        
        return $stats;
    }
    
    // Getter e setter
    public function getId() { return $this->id; }
    public function getChampionshipId() { return $this->championshipId; }
    public function getTeamId() { return $this->teamId; }
    public function getPlayed() { return $this->played; }
    public function getWon() { return $this->won; }
    public function getDrawn() { return $this->drawn; }
    public function getLost() { return $this->lost; }
    public function getPoints() { return $this->points; }
    public function getScored() { return $this->scored; }
    public function getConceeded() { return $this->conceded; }
    public function getWinProbability() { return $this->winProbability; }
    public function getLastCalculated() { return $this->lastCalculated; }
    
    public function setChampionshipId($championshipId) { $this->championshipId = $championshipId; }
    public function setTeamId($teamId) { $this->teamId = $teamId; }
    public function setPlayed($played) { $this->played = $played; }
    public function setWon($won) { $this->won = $won; }
    public function setDrawn($drawn) { $this->drawn = $drawn; }
    public function setLost($lost) { $this->lost = $lost; }
    public function setPoints($points) { $this->points = $points; }
    public function setScored($scored) { $this->scored = $scored; }
    public function setConceeded($conceeded) { $this->conceded = $conceeded; }
    public function setWinProbability($winProbability) { $this->winProbability = $winProbability; }
    public function setLastCalculated($lastCalculated) { $this->lastCalculated = $lastCalculated; }
}