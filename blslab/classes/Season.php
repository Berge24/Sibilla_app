<?php
// classes/Season.php

class Season {
    private $db;
    private $id;
    private $user_id;
    private $name;
    private $startDate;
    private $endDate;
    private $is_public;
    
    /**
     * Costruttore
     * @param int|null $id ID stagione (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati della stagione dal database
     * @param int $id ID stagione
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $season = $this->db->fetchOne("SELECT * FROM seasons WHERE id = ?", [$id]);
        
        if ($season) {
            $this->id = $season['id'];
            $this->user_id = $season['user_id'];
            $this->name = $season['name'];
            $this->startDate = $season['start_date'];
            $this->endDate = $season['end_date'];
            $this->is_public = $season['is_public'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Crea una nuova stagione
     * @param int $userId ID dell'utente proprietario
     * @param string $name Nome della stagione
     * @param string $startDate Data di inizio
     * @param string $endDate Data di fine
     * @param bool $isPublic Visibilità pubblica
     * @return int|false ID della stagione creata o false in caso di errore
     */
    public function create($userId, $name, $startDate, $endDate, $isPublic = false) {
        // Verifica che l'utente non abbia superato il limite di stagioni
        $user = User::findById($userId);
        if (!$user || !$user->canCreateChampionship()) {
            return false;
        }
        
        // Inserimento stagione
        $seasonId = $this->db->insert('seasons', [
            'user_id' => $userId,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_public' => $isPublic ? 1 : 0
        ]);
        
        if ($seasonId) {
            $this->id = $seasonId;
            $this->user_id = $userId;
            $this->name = $name;
            $this->startDate = $startDate;
            $this->endDate = $endDate;
            $this->is_public = $isPublic;
            
            // Aggiorna statistiche utente
            $stats = new UserStats($userId);
            $stats->incrementChampionships();
        }
        
        return $seasonId;
    }
    
    /**
     * Aggiorna i dati della stagione
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
        
        $result = $this->db->update('seasons', $data, 'id = ?', [$this->id]);
        
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
     * Elimina la stagione
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
        
        // Utilizza una transazione
        $this->db->beginTransaction();
        
        try {
            // Ottieni tutti i campionati della stagione
            $championships = $this->db->fetchAll("
                SELECT id FROM championships WHERE season_id = ?
            ", [$this->id]);
            
            // Elimina tutti i campionati correlati
            foreach ($championships as $championship) {
                $champ = new Championship($championship['id']);
                $champ->delete();
            }
            
            // Elimina la stagione
            $result = $this->db->delete('seasons', 'id = ?', [$this->id]);
            
            $this->db->commit();
            
            if ($result) {
                // Aggiorna statistiche utente
                if ($this->user_id) {
                    $stats = new UserStats($this->user_id);
                    $stats->decrementChampionships();
                }
                
                $this->id = null;
                $this->user_id = null;
                $this->name = null;
                $this->startDate = null;
                $this->endDate = null;
                $this->is_public = null;
            }
            
            return $result > 0;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Ottiene tutti i campionati della stagione
     * @param bool $accessibleOnly Se true, restituisce solo i campionati accessibili all'utente corrente
     * @return array
     */
    public function getChampionships($accessibleOnly = true) {
        if (!$this->id) {
            return [];
        }
        
        $query = "
            SELECT * FROM championships
            WHERE season_id = ?
        ";
        $params = [$this->id];
        
        if ($accessibleOnly) {
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
        
        $query .= " ORDER BY start_date";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Verifica se l'utente specificato ha accesso alla stagione
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
        
        // Tutti hanno accesso se la stagione è pubblica
        return $this->is_public == 1;
    }
    
    /**
     * Ottiene tutte le stagioni
     * @param bool $activeOnly Filtra solo le stagioni attive
     * @param int|null $userId Filtra per ID utente
     * @param bool $includePublic Include stagioni pubbliche
     * @return array
     */
    public static function getAll($activeOnly = false, $userId = null, $includePublic = true) {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM seasons";
        $params = [];
        $conditions = [];
        
        if ($activeOnly) {
            $conditions[] = "(start_date <= CURRENT_DATE() AND end_date >= CURRENT_DATE())";
        }
        
        // Filtra per accessibilità
        $currentUser = User::getCurrentUser();
        
        if ($userId !== null) {
            // Filtra per proprietario specifico
            $conditions[] = "user_id = ?";
            $params[] = $userId;
        } else if ($currentUser) {
            if (!$currentUser->isAdmin()) {
                // Utente normale: mostra le sue stagioni e quelle pubbliche
                if ($includePublic) {
                    $conditions[] = "(user_id = ? OR is_public = 1)";
                    $params[] = $currentUser->getId();
                } else {
                    $conditions[] = "user_id = ?";
                    $params[] = $currentUser->getId();
                }
            }
        } else {
            // Utente non loggato: mostra solo stagioni pubbliche
            $conditions[] = "is_public = 1";
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY start_date DESC";
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Trova una stagione per ID
     * @param int $id ID stagione
     * @return Season|null
     */
    public static function findById($id) {
        $season = new self();
        if ($season->load($id)) {
            // Verifica l'accesso dell'utente corrente
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if ($season->isAccessibleByUser($currentUser->getId())) {
                    return $season;
                }
            } else if ($season->is_public) {
                return $season;
            }
            return null;
        }
        return null;
    }
    
    /**
     * Ottiene la stagione corrente
     * @param int|null $userId Filtra per ID utente
     * @return Season|null
     */
    public static function getCurrentSeason($userId = null) {
        $db = Database::getInstance();
        $query = "
            SELECT * FROM seasons
            WHERE start_date <= CURRENT_DATE() AND end_date >= CURRENT_DATE()
        ";
        $params = [];
        
        // Filtra per accessibilità
        $currentUser = User::getCurrentUser();
        
        if ($userId !== null) {
            // Filtra per proprietario specifico
            $query .= " AND user_id = ?";
            $params[] = $userId;
        } else if ($currentUser) {
            if (!$currentUser->isAdmin()) {
                // Utente normale: mostra le sue stagioni e quelle pubbliche
                $query .= " AND (user_id = ? OR is_public = 1)";
                $params[] = $currentUser->getId();
            }
        } else {
            // Utente non loggato: mostra solo stagioni pubbliche
            $query .= " AND is_public = 1";
        }
        
        $query .= " ORDER BY start_date DESC LIMIT 1";
        
        $seasonData = $db->fetchOne($query, $params);
        
        if ($seasonData) {
            $season = new self();
            $season->id = $seasonData['id'];
            $season->user_id = $seasonData['user_id'];
            $season->name = $seasonData['name'];
            $season->startDate = $seasonData['start_date'];
            $season->endDate = $seasonData['end_date'];
            $season->is_public = $seasonData['is_public'];
            return $season;
        }
        
        return null;
    }
    
    /**
     * Verifica se l'utente corrente può modificare questa stagione
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
    
    // Getter e setter
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getName() { return $this->name; }
    public function getStartDate() { return $this->startDate; }
    public function getEndDate() { return $this->endDate; }
    public function getIsPublic() { return $this->is_public; }
    
    public function setName($name) { $this->name = $name; }
    public function setUserId($userId) { $this->user_id = $userId; }
    public function setStartDate($startDate) { $this->startDate = $startDate; }
    public function setEndDate($endDate) { $this->endDate = $endDate; }
    public function setIsPublic($isPublic) { $this->is_public = $isPublic; }
}