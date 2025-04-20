<?php
// classes/Team.php

class Team {
    private $db;
    private $id;
    private $user_id;
    private $name;
    private $logo;
    private $description;
    private $is_public;
    
    /**
     * Costruttore
     * @param int|null $id ID squadra (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati della squadra dal database
     * @param int $id ID squadra
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $team = $this->db->fetchOne("SELECT * FROM teams WHERE id = ?", [$id]);
        
        if ($team) {
            $this->id = $team['id'];
            $this->user_id = $team['user_id'];
            $this->name = $team['name'];
            $this->logo = $team['logo'];
            $this->description = $team['description'];
            $this->is_public = $team['is_public'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Crea una nuova squadra
     * @param int $userId ID dell'utente proprietario
     * @param string $name Nome della squadra
     * @param string $logo URL del logo
     * @param string $description Descrizione
     * @param bool $isPublic Visibilità pubblica
     * @return int|false ID della squadra creata o false in caso di errore
     */
    public function create($userId, $name, $logo = null, $description = null, $isPublic = false) {
        // Verifica se esiste già una squadra con lo stesso nome per lo stesso utente
        if ($this->db->count('teams', 'name = ? AND user_id = ?', [$name, $userId]) > 0) {
            return false;
        }
        
        // Verifica che l'utente non abbia superato il limite di squadre
        $user = User::findById($userId);
        if (!$user || !$user->canCreateTeam()) {
            return false;
        }
        
        // Inserimento squadra
        $teamId = $this->db->insert('teams', [
            'user_id' => $userId,
            'name' => $name,
            'logo' => $logo,
            'description' => $description,
            'is_public' => $isPublic ? 1 : 0
        ]);
        
        if ($teamId) {
            $this->id = $teamId;
            $this->user_id = $userId;
            $this->name = $name;
            $this->logo = $logo;
            $this->description = $description;
            $this->is_public = $isPublic;
            
            // Aggiorna statistiche utente
            $stats = new UserStats($userId);
            $stats->incrementTeams();
        }
        
        return $teamId;
    }
    
    /**
     * Aggiorna i dati della squadra
     * @param array $data Dati da aggiornare
     * @return bool Successo/fallimento
     */
    public function update($data) {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente corrente sia il proprietario
        $currentUser = User::getCurrentUser();
        if (!$currentUser || ($this->user_id != $currentUser->getId() && !$currentUser->isAdmin())) {
            return false;
        }
        
        $result = $this->db->update('teams', $data, 'id = ?', [$this->id]);
        
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
     * Elimina la squadra
     * @return bool Successo/fallimento
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente corrente sia il proprietario
        $currentUser = User::getCurrentUser();
        if (!$currentUser || ($this->user_id != $currentUser->getId() && !$currentUser->isAdmin())) {
            return false;
        }
        
        // Utilizza una transazione per eliminare anche i dati correlati
        $this->db->beginTransaction();
        
        try {
            // Elimina le relazioni con i campionati
            $this->db->delete('championships_teams', 'team_id = ?', [$this->id]);
            
            // Elimina le partite
            $this->db->delete('matches', 'home_team_id = ? OR away_team_id = ?', [$this->id, $this->id]);
            
            // Elimina le classifiche
            $this->db->delete('standings', 'team_id = ?', [$this->id]);
            
            // Elimina la squadra
            $result = $this->db->delete('teams', 'id = ?', [$this->id]);
            
            $this->db->commit();
            
            if ($result) {
                // Aggiorna statistiche utente
                if ($this->user_id) {
                    $stats = new UserStats($this->user_id);
                    $stats->decrementTeams();
                }
                
                $this->id = null;
                $this->user_id = null;
                $this->name = null;
                $this->logo = null;
                $this->description = null;
                $this->is_public = null;
            }
            
            return $result > 0;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Ottiene tutti i campionati a cui partecipa la squadra
     * @param bool $accessibleOnly Se true, restituisce solo i campionati accessibili all'utente corrente
     * @return array
     */
    public function getChampionships($accessibleOnly = true) {
        if (!$this->id) {
            return [];
        }
        
        $query = "
            SELECT c.*, s.name as season_name
            FROM championships c
            JOIN championships_teams ct ON c.id = ct.championship_id
            JOIN seasons s ON c.season_id = s.id
            WHERE ct.team_id = ?
        ";
        
        $params = [$this->id];
        
        if ($accessibleOnly) {
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if (!$currentUser->isAdmin()) {
                    $query .= " AND (c.user_id = ? OR c.is_public = 1)";
                    $params[] = $currentUser->getId();
                }
            } else {
                $query .= " AND c.is_public = 1";
            }
        }
        
        $query .= " ORDER BY c.start_date DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Ottiene tutte le partite della squadra
     * @param int|null $championshipId Filtra per campionato (opzionale)
     * @param string|null $status Filtra per stato della partita (opzionale)
     * @param bool $accessibleOnly Se true, restituisce solo le partite dei campionati accessibili all'utente corrente
     * @return array
     */
    public function getMatches($championshipId = null, $status = null, $accessibleOnly = true) {
        if (!$this->id) {
            return [];
        }
        
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
            WHERE (m.home_team_id = ? OR m.away_team_id = ?)
        ";
        
        $params = [$this->id, $this->id];
        
        if ($championshipId) {
            $query .= " AND m.championship_id = ?";
            $params[] = $championshipId;
        }
        
        if ($status) {
            $query .= " AND m.status = ?";
            $params[] = $status;
        }
        
        if ($accessibleOnly) {
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if (!$currentUser->isAdmin()) {
                    $query .= " AND (c.user_id = ? OR c.is_public = 1)";
                    $params[] = $currentUser->getId();
                }
            } else {
                $query .= " AND c.is_public = 1";
            }
        }
        
        $query .= " ORDER BY m.match_date";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Ottiene le statistiche della squadra in un campionato
     * @param int $championshipId ID del campionato
     * @return array|false Statistiche o false se non trovate o non accessibili
     */
    public function getStats($championshipId) {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che il campionato sia accessibile per l'utente corrente
        $championship = Championship::findById($championshipId);
        if (!$championship) {
            return false;
        }
        
        return $this->db->fetchOne("
            SELECT * 
            FROM standings
            WHERE championship_id = ? AND team_id = ?
        ", [$championshipId, $this->id]);
    }
    
    /**
     * Verifica se l'utente specificato ha accesso alla squadra
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
        
        // Il proprietario ha sempre accesso
        if ($this->user_id == $userId) {
            return true;
        }
        
        // Tutti hanno accesso se la squadra è pubblica
        return $this->is_public == 1;
    }
    
    /**
     * Verifica se l'utente corrente può modificare questa squadra
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
        
        return $this->user_id == $currentUser->getId();
    }
    
    /**
     * Ottiene tutte le squadre
     * @param int|null $userId Filtra per ID utente (opzionale)
     * @param bool $includePublic Include squadre pubbliche
     * @return array
     */
    public static function getAll($userId = null, $includePublic = true) {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM teams";
        $params = [];
        $conditions = [];
        
        // Filtra per accessibilità
        $currentUser = User::getCurrentUser();
        
        if ($userId !== null) {
            // Filtra per proprietario specifico
            $conditions[] = "user_id = ?";
            $params[] = $userId;
        } else if ($currentUser) {
            if (!$currentUser->isAdmin()) {
                // Utente normale: mostra le sue squadre e quelle pubbliche
                if ($includePublic) {
                    $conditions[] = "(user_id = ? OR is_public = 1)";
                    $params[] = $currentUser->getId();
                } else {
                    $conditions[] = "user_id = ?";
                    $params[] = $currentUser->getId();
                }
            }
        } else {
            // Utente non loggato: mostra solo squadre pubbliche
            $conditions[] = "is_public = 1";
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY name";
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Trova una squadra per ID
     * @param int $id ID squadra
     * @return Team|null
     */
    public static function findById($id) {
        $team = new self();
        if ($team->load($id)) {
            // Verifica l'accesso dell'utente corrente
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if ($team->isAccessibleByUser($currentUser->getId())) {
                    return $team;
                }
            } else if ($team->is_public) {
                return $team;
            }
            return null;
        }
        return null;
    }
    
    /**
     * Trova una squadra per nome
     * @param string $name Nome squadra
     * @param int|null $userId ID utente proprietario (opzionale)
     * @return Team|null
     */
    public static function findByName($name, $userId = null) {
        $db = Database::getInstance();
        $query = "SELECT * FROM teams WHERE name = ?";
        $params = [$name];
        
        if ($userId !== null) {
            $query .= " AND user_id = ?";
            $params[] = $userId;
        } else {
            // Se non specifico l'utente, cerca solo fra le squadre pubbliche o dell'utente corrente
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if (!$currentUser->isAdmin()) {
                    $query .= " AND (user_id = ? OR is_public = 1)";
                    $params[] = $currentUser->getId();
                }
            } else {
                $query .= " AND is_public = 1";
            }
        }
        
        $teamData = $db->fetchOne($query, $params);
        
        if ($teamData) {
            $team = new self();
            $team->id = $teamData['id'];
            $team->user_id = $teamData['user_id'];
            $team->name = $teamData['name'];
            $team->logo = $teamData['logo'];
            $team->description = $teamData['description'];
            $team->is_public = $teamData['is_public'];
            return $team;
        }
        
        return null;
    }
    
    // Getter e setter
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getName() { return $this->name; }
    public function getLogo() { return $this->logo; }
    public function getDescription() { return $this->description; }
    public function getIsPublic() { return $this->is_public; }
    
    public function setUserId($userId) { $this->user_id = $userId; }
    public function setName($name) { $this->name = $name; }
    public function setLogo($logo) { $this->logo = $logo; }
    public function setDescription($description) { $this->description = $description; }
    public function setIsPublic($isPublic) { $this->is_public = $isPublic; }
}