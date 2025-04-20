<?php
// classes/User.php

class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $role;
    private $is_approved;
    private $created_at;
    private $updated_at;
    private $last_login;
    
    /**
     * Costruttore
     * @param int|null $id ID utente (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati dell'utente dal database
     * @param int $id ID utente
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        
        if ($user) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->email = $user['email'];
            $this->role = $user['role'];
            $this->is_approved = $user['is_approved'];
            $this->created_at = $user['created_at'];
            $this->updated_at = $user['updated_at'];
            $this->last_login = $user['last_login'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Autentica un utente con username e password
     * @param string $username Username
     * @param string $password Password
     * @return bool Successo/fallimento
     */
    public function login($username, $password) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE username = ?", [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Verifica che l'utente sia approvato
            if (!$user['is_approved'] && $user['role'] !== USER_ROLE_ADMIN) {
                return false;
            }
            
            // Imposta i dati dell'utente
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->email = $user['email'];
            $this->role = $user['role'];
            $this->is_approved = $user['is_approved'];
            $this->created_at = $user['created_at'];
            $this->updated_at = $user['updated_at'];
            $this->last_login = $user['last_login'];
            
            // Imposta i dati di sessione
            $_SESSION['user_id'] = $this->id;
            $_SESSION['username'] = $this->username;
            $_SESSION['role'] = $this->role;
            
            // Aggiorna timestamp ultimo accesso
            $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$this->id]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Registra un nuovo utente
     * @param string $username Username
     * @param string $password Password
     * @param string $email Email
     * @param string $role Ruolo (default 'user')
     * @return int|false ID utente creato o false in caso di errore
     */
    public function register($username, $password, $email, $role = USER_ROLE_USER) {
        // Verifica se l'username esiste già
        if ($this->db->count('users', 'username = ?', [$username]) > 0) {
            return false;
        }
        
        // Verifica se l'email esiste già
        if ($this->db->count('users', 'email = ?', [$email]) > 0) {
            return false;
        }
        
        // Hashing della password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Inserimento utente
        $userId = $this->db->insert('users', [
            'username' => $username,
            'password' => $hashedPassword,
            'email' => $email,
            'role' => $role,
            'is_approved' => ($role === USER_ROLE_ADMIN) ? 1 : 0 // Auto-approva admin
        ]);
        
        if ($userId) {
            $this->id = $userId;
            $this->username = $username;
            $this->email = $email;
            $this->role = $role;
            $this->is_approved = ($role === USER_ROLE_ADMIN) ? 1 : 0;
            $this->created_at = date('Y-m-d H:i:s');
            $this->updated_at = date('Y-m-d H:i:s');
            
            // Crea un abbonamento gratuito predefinito per il nuovo utente
            $freePlan = SubscriptionPlan::getFreePlan();
            if ($freePlan) {
                UserSubscription::createUserSubscription($userId, $freePlan->getId());
            }
            
            // Inizializza le statistiche utente
            $stats = new UserStats($userId);
            $stats->save();
        }
        
        return $userId;
    }
    
    /**
     * Esegue il logout dell'utente
     */
    public function logout() {
        // Elimina variabili di sessione
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['role']);
        
        // Distruggi la sessione
        session_destroy();
    }
    
    /**
     * Verifica se l'utente è autenticato
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Verifica se l'utente ha il ruolo specificato
     * @param string $role Ruolo da verificare
     * @return bool
     */
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    /**
     * Verifica se l'utente è un amministratore
     * @return bool
     */
    public static function isAdmin() {
        return self::hasRole(USER_ROLE_ADMIN);
    }
    
    /**
     * Verifica se l'utente è approvato
     * @return bool
     */
    public function isApproved() {
        return $this->is_approved == 1;
    }
    
    /**
     * Approva un utente
     * @return bool Successo/fallimento
     */
    public function approve() {
        if (!$this->id) {
            return false;
        }
        
        $result = $this->db->update('users', ['is_approved' => 1], 'id = ?', [$this->id]);
        
        if ($result) {
            $this->is_approved = 1;
        }
        
        return $result > 0;
    }
    
    /**
     * Ottiene l'utente corrente dalla sessione
     * @return User|null
     */
    public static function getCurrentUser() {
        if (self::isLoggedIn()) {
            return new self($_SESSION['user_id']);
        }
        return null;
    }
    
    /**
     * Aggiorna i dati dell'utente
     * @param array $data Dati da aggiornare
     * @return bool Successo/fallimento
     */
    public function update($data) {
        if (!$this->id) {
            return false;
        }
        
        $result = $this->db->update('users', $data, 'id = ?', [$this->id]);
        
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
     * Cambia la password dell'utente
     * @param string $newPassword Nuova password
     * @return bool Successo/fallimento
     */
    public function changePassword($newPassword) {
        if (!$this->id) {
            return false;
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = $this->db->update('users', ['password' => $hashedPassword], 'id = ?', [$this->id]);
        
        return $result > 0;
    }
    
    /**
     * Elimina l'utente
     * @return bool Successo/fallimento
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        // Elimina tutti i dati associati all'utente
        $this->db->delete('user_subscriptions', 'user_id = ?', [$this->id]);
        $this->db->delete('user_limits', 'user_id = ?', [$this->id]);
        $this->db->delete('user_stats', 'user_id = ?', [$this->id]);
        
        // Elimina l'utente
        $result = $this->db->delete('users', 'id = ?', [$this->id]);
        
        if ($result) {
            $this->id = null;
            $this->username = null;
            $this->email = null;
            $this->role = null;
            $this->is_approved = null;
            $this->created_at = null;
            $this->updated_at = null;
            $this->last_login = null;
        }
        
        return $result > 0;
    }
    
    /**
     * Ottiene tutti gli utenti
     * @param bool $includeUnapproved Includi utenti non approvati
     * @return array
     */
    public static function getAll($includeUnapproved = true) {
        $db = Database::getInstance();
        
        $query = "SELECT u.*, 
                    (SELECT us.id FROM user_subscriptions us 
                     WHERE us.user_id = u.id AND us.is_active = 1 
                     ORDER BY us.end_date DESC LIMIT 1) as subscription_id,
                    (SELECT sp.name FROM user_subscriptions us 
                     JOIN subscription_plans sp ON us.plan_id = sp.id 
                     WHERE us.user_id = u.id AND us.is_active = 1 
                     ORDER BY us.end_date DESC LIMIT 1) as subscription_name
                 FROM users u";
        
        if (!$includeUnapproved) {
            $query .= " WHERE u.is_approved = 1";
        }
        
        $query .= " ORDER BY u.username";
        
        return $db->fetchAll($query);
    }
    
    /**
     * Ottiene utenti in attesa di approvazione
     * @return array
     */
    public static function getPendingApproval() {
        $db = Database::getInstance();
        
        return $db->fetchAll("
            SELECT u.*, 
                (SELECT sp.name FROM user_subscriptions us 
                 JOIN subscription_plans sp ON us.plan_id = sp.id 
                 WHERE us.user_id = u.id AND us.is_active = 1 
                 ORDER BY us.end_date DESC LIMIT 1) as subscription_name
            FROM users u
            WHERE u.is_approved = 0 AND u.role != ?
            ORDER BY u.created_at DESC
        ", [USER_ROLE_ADMIN]);
    }
    
    /**
     * Trova un utente per ID
     * @param int $id ID utente
     * @return User|null
     */
    public static function findById($id) {
        $user = new self();
        return $user->load($id) ? $user : null;
    }
    
    /**
     * Trova un utente per username
     * @param string $username Username
     * @return User|null
     */
    public static function findByUsername($username) {
        $db = Database::getInstance();
        $userData = $db->fetchOne("SELECT * FROM users WHERE username = ?", [$username]);
        
        if ($userData) {
            $user = new self();
            $user->id = $userData['id'];
            $user->username = $userData['username'];
            $user->email = $userData['email'];
            $user->role = $userData['role'];
            $user->is_approved = $userData['is_approved'];
            $user->created_at = $userData['created_at'];
            $user->updated_at = $userData['updated_at'];
            $user->last_login = $userData['last_login'];
            return $user;
        }
        
        return null;
    }
    
    /**
     * Ottiene l'abbonamento attivo dell'utente
     * @return UserSubscription|null
     */
    public function getActiveSubscription() {
        if (!$this->id) {
            return null;
        }
        
        return UserSubscription::getActiveSubscription($this->id);
    }
    
    /**
     * Ottiene lo storico degli abbonamenti dell'utente
     * @param int $limit Limite risultati (opzionale)
     * @return array
     */
    public function getSubscriptionHistory($limit = 0) {
        if (!$this->id) {
            return [];
        }
        
        return UserSubscription::getUserSubscriptionHistory($this->id, $limit);
    }
    
    /**
     * Controlla se l'abbonamento è scaduto
     * @return bool
     */
    public function isSubscriptionExpired() {
        $subscription = $this->getActiveSubscription();
        
        if (!$subscription) {
            // Se non c'è un abbonamento attivo, consideriamo come scaduto
            return true;
        }
        
        return $subscription->isExpired();
    }
    
    /**
     * Aggiorna l'abbonamento dell'utente
     * @param int $planId ID piano abbonamento
     * @param string|null $startDate Data inizio (opzionale)
     * @param string|null $paymentId ID pagamento (opzionale)
     * @return bool Successo/fallimento
     */
    public function updateSubscription($planId, $startDate = null, $paymentId = null) {
        if (!$this->id) {
            return false;
        }
        
        return UserSubscription::createUserSubscription($this->id, $planId, $startDate, $paymentId) !== false;
    }
    
    /**
     * Ottiene le statistiche dell'utente
     * @param bool $refresh Se true, aggiorna le statistiche prima di restituirle
     * @return UserStats|null
     */
    public function getStats($refresh = false) {
        if (!$this->id) {
            return null;
        }
        
        $stats = new UserStats($this->id);
        
        if ($refresh) {
            $stats->refresh();
        }
        
        return $stats;
    }
    
    /**
     * Ottiene i limiti personalizzati dell'utente
     * @return UserLimits|null
     */
    public function getLimits() {
        if (!$this->id) {
            return null;
        }
        
        return new UserLimits($this->id);
    }
    
    /**
     * Ottiene i limiti effettivi dell'utente (combinando abbonamento e override)
     * @return array
     */
    public function getEffectiveLimits() {
        if (!$this->id) {
            return [];
        }
        
        return UserLimits::getEffectiveLimits($this->id);
    }
    
    /**
     * Verifica se l'utente ha raggiunto il limite di campionati
     * @return bool
     */
    public function hasReachedChampionshipsLimit() {
        if (!$this->id) {
            return true;
        }
        
        $stats = $this->getStats();
        return $stats->hasReachedChampionshipsLimit();
    }
    
    /**
     * Verifica se l'utente ha raggiunto il limite di squadre
     * @return bool
     */
    public function hasReachedTeamsLimit() {
        if (!$this->id) {
            return true;
        }
        
        $stats = $this->getStats();
        return $stats->hasReachedTeamsLimit();
    }
    
    /**
     * Verifica se l'utente può creare un nuovo campionato
     * @return bool
     */
    public function canCreateChampionship() {
        return $this->is_approved && !$this->isSubscriptionExpired() && !$this->hasReachedChampionshipsLimit();
    }
    
    /**
     * Verifica se l'utente può creare una nuova squadra
     * @return bool
     */
    public function canCreateTeam() {
        return $this->is_approved && !$this->isSubscriptionExpired() && !$this->hasReachedTeamsLimit();
    }
    
    /**
     * Verifica se l'utente deve visualizzare pubblicità
     * @return bool
     */
    public function showAds() {
        $limits = $this->getEffectiveLimits();
        return $limits['has_ads'] == 1;
    }
    
    /**
     * Verifica se l'utente può accedere alle predizioni
     * @return bool
     */
    public function canAccessPredictions() {
        $limits = $this->getEffectiveLimits();
        return $limits['has_predictions'] == 1;
    }
    
    // Getters
    
    /**
     * Getter per ID
     * @return int|null
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     * Getter per username
     * @return string|null
     */
    public function getUsername() {
        return $this->username;
    }
    
    /**
     * Getter per email
     * @return string|null
     */
    public function getEmail() {
        return $this->email;
    }
    
    /**
     * Getter per ruolo
     * @return string|null
     */
    public function getRole() {
        return $this->role;
    }
    
    /**
     * Getter per is_approved
     * @return int|null
     */
    public function getIsApproved() {
        return $this->is_approved;
    }
    
    /**
     * Getter per created_at
     * @return string|null
     */
    public function getCreatedAt() {
        return $this->created_at;
    }
    
    /**
     * Getter per updated_at
     * @return string|null
     */
    public function getUpdatedAt() {
        return $this->updated_at;
    }
    
    /**
     * Getter per last_login
     * @return string|null
     */
    public function getLastLogin() {
        return $this->last_login;
    }
    
    /**
     * Formatta la data di registrazione
     * @return string
     */
    public function getFormattedCreatedAt() {
        if (empty($this->created_at)) {
            return 'N/D';
        }
        
        return date('d/m/Y H:i', strtotime($this->created_at));
    }
    
    /**
     * Formatta la data ultimo accesso
     * @return string
     */
    public function getFormattedLastLogin() {
        if (empty($this->last_login)) {
            return 'Mai';
        }
        
        return date('d/m/Y H:i', strtotime($this->last_login));
    }
}