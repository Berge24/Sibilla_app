<?php
// classes/UserLimits.php

class UserLimits {
    private $db;
    private $id;
    private $user_id;
    private $max_championships;
    private $max_teams;
    private $has_ads;
    private $has_predictions;
    private $notes;
    
    /**
     * Costruttore
     * @param int|null $userId ID utente (opzionale)
     */
    public function __construct($userId = null) {
        $this->db = Database::getInstance();
        
        if ($userId) {
            $this->loadByUserId($userId);
        }
    }
    
    /**
     * Carica i limiti utente dal database usando l'ID del record
     * @param int $id ID del record
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $limits = $this->db->fetchOne("SELECT * FROM user_limits WHERE id = ?", [$id]);
        
        if ($limits) {
            $this->id = $limits['id'];
            $this->user_id = $limits['user_id'];
            $this->max_championships = $limits['max_championships'];
            $this->max_teams = $limits['max_teams'];
            $this->has_ads = $limits['has_ads'];
            $this->has_predictions = $limits['has_predictions'];
            $this->notes = $limits['notes'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Carica i limiti utente dal database usando l'ID utente
     * @param int $userId ID utente
     * @return bool Successo/fallimento
     */
    public function loadByUserId($userId) {
        $limits = $this->db->fetchOne("SELECT * FROM user_limits WHERE user_id = ?", [$userId]);
        
        if ($limits) {
            $this->id = $limits['id'];
            $this->user_id = $limits['user_id'];
            $this->max_championships = $limits['max_championships'];
            $this->max_teams = $limits['max_teams'];
            $this->has_ads = $limits['has_ads'];
            $this->has_predictions = $limits['has_predictions'];
            $this->notes = $limits['notes'];
            return true;
        }
        
        // Se non esiste, inizializza con un nuovo record vuoto
        $this->user_id = $userId;
        $this->max_championships = null;
        $this->max_teams = null;
        $this->has_ads = null;
        $this->has_predictions = null;
        $this->notes = null;
        
        return false;
    }
    
    /**
     * Salva i limiti utente nel database
     * @return int|bool ID limiti inseriti o aggiornati, o false in caso di errore
     */
    public function save() {
        if (!$this->user_id) {
            return false;
        }
        
        $data = [
            'user_id' => $this->user_id,
            'max_championships' => $this->max_championships,
            'max_teams' => $this->max_teams,
            'has_ads' => $this->has_ads,
            'has_predictions' => $this->has_predictions,
            'notes' => $this->notes
        ];
        
        if ($this->id) {
            // Aggiorna limiti esistenti
            $result = $this->db->update('user_limits', $data, 'id = ?', [$this->id]);
            return $result ? $this->id : false;
        } else {
            // Controlla se esiste già un record per questo utente
            $existing = $this->db->fetchOne("SELECT id FROM user_limits WHERE user_id = ?", [$this->user_id]);
            
            if ($existing) {
                $this->id = $existing['id'];
                $result = $this->db->update('user_limits', $data, 'id = ?', [$this->id]);
                return $result ? $this->id : false;
            } else {
                // Crea nuovi limiti
                $this->id = $this->db->insert('user_limits', $data);
                return $this->id;
            }
        }
    }
    
    /**
     * Rimuove i limiti personalizzati di un utente
     * @return bool Successo/fallimento
     */
    public function remove() {
        if (!$this->id) {
            return false;
        }
        
        $result = $this->db->delete('user_limits', 'id = ?', [$this->id]);
        
        if ($result) {
            $this->id = null;
            $this->max_championships = null;
            $this->max_teams = null;
            $this->has_ads = null;
            $this->has_predictions = null;
            $this->notes = null;
        }
        
        return $result > 0;
    }
    
    /**
     * Controlla se un utente ha limiti personalizzati
     * @param int $userId ID utente
     * @return bool
     */
    public static function hasCustomLimits($userId) {
        $db = Database::getInstance();
        $count = $db->count('user_limits', 'user_id = ?', [$userId]);
        return $count > 0;
    }
    
    /**
     * Ottiene i limiti effettivi per un utente
     * @param int $userId ID utente
     * @return array
     */
    public static function getEffectiveLimits($userId) {
        $db = Database::getInstance();
        $limits = [];
        
        // Ottieni l'abbonamento attivo dell'utente
        $activeSubscription = UserSubscription::getActiveSubscription($userId);
        
        if ($activeSubscription) {
            $plan = $activeSubscription->getPlan();
            
            if ($plan) {
                // Imposta i limiti base dal piano
                $limits['max_championships'] = $plan['max_championships'];
                $limits['max_teams'] = $plan['max_teams'];
                $limits['has_ads'] = $plan['has_ads'];
                $limits['has_predictions'] = $plan['has_predictions'];
            }
        } else {
            // Piano Free predefinito se non c'è un abbonamento attivo
            $freePlan = SubscriptionPlan::getFreePlan();
            
            if ($freePlan) {
                $limits['max_championships'] = $freePlan->getMaxChampionships();
                $limits['max_teams'] = $freePlan->getMaxTeams();
                $limits['has_ads'] = $freePlan->getHasAds();
                $limits['has_predictions'] = $freePlan->getHasPredictions();
            } else {
                // Valori predefiniti se non c'è un piano free
                $limits['max_championships'] = 1;
                $limits['max_teams'] = 10;
                $limits['has_ads'] = 1;
                $limits['has_predictions'] = 0;
            }
        }
        
        // Controlla se ci sono limiti personalizzati per l'utente
        $customLimits = $db->fetchOne("SELECT * FROM user_limits WHERE user_id = ?", [$userId]);
        
        if ($customLimits) {
            // Sovrascrive i limiti del piano con quelli personalizzati, se presenti
            if ($customLimits['max_championships'] !== null) {
                $limits['max_championships'] = $customLimits['max_championships'];
            }
            
            if ($customLimits['max_teams'] !== null) {
                $limits['max_teams'] = $customLimits['max_teams'];
            }
            
            if ($customLimits['has_ads'] !== null) {
                $limits['has_ads'] = $customLimits['has_ads'];
            }
            
            if ($customLimits['has_predictions'] !== null) {
                $limits['has_predictions'] = $customLimits['has_predictions'];
            }
            
            $limits['notes'] = $customLimits['notes'];
            $limits['has_custom_limits'] = true;
        } else {
            $limits['has_custom_limits'] = false;
        }
        
        return $limits;
    }
    
    // Getters e Setters
    
    /**
     * Getter per ID
     * @return int|null
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     * Getter per user_id
     * @return int|null
     */
    public function getUserId() {
        return $this->user_id;
    }
    
    /**
     * Setter per user_id
     * @param int $user_id
     */
    public function setUserId($user_id) {
        $this->user_id = $user_id;
    }
    
    /**
     * Getter per max_championships
     * @return int|null
     */
    public function getMaxChampionships() {
        return $this->max_championships;
    }
    
    /**
     * Setter per max_championships
     * @param int|null $max_championships
     */
    public function setMaxChampionships($max_championships) {
        $this->max_championships = $max_championships;
    }
    
    /**
     * Getter per max_teams
     * @return int|null
     */
    public function getMaxTeams() {
        return $this->max_teams;
    }
    
    /**
     * Setter per max_teams
     * @param int|null $max_teams
     */
    public function setMaxTeams($max_teams) {
        $this->max_teams = $max_teams;
    }
    
    /**
     * Getter per has_ads
     * @return bool|null
     */
    public function getHasAds() {
        return $this->has_ads;
    }
    
    /**
     * Setter per has_ads
     * @param bool|null $has_ads
     */
    public function setHasAds($has_ads) {
        $this->has_ads = $has_ads;
    }
    
    /**
     * Getter per has_predictions
     * @return bool|null
     */
    public function getHasPredictions() {
        return $this->has_predictions;
    }
    
    /**
     * Setter per has_predictions
     * @param bool|null $has_predictions
     */
    public function setHasPredictions($has_predictions) {
        $this->has_predictions = $has_predictions;
    }
    
    /**
     * Getter per notes
     * @return string|null
     */
    public function getNotes() {
        return $this->notes;
    }
    
    /**
     * Setter per notes
     * @param string|null $notes
     */
    public function setNotes($notes) {
        $this->notes = $notes;
    }
    
    /**
     * Formatta il limite di campionati
     * @return string
     */
    public function getFormattedMaxChampionships() {
        if ($this->max_championships === null) {
            return 'Default';
        } elseif ($this->max_championships < 0 || $this->max_championships >= 999) {
            return "Illimitati";
        } else {
            return $this->max_championships;
        }
    }
    
    /**
     * Formatta il limite di squadre
     * @return string
     */
    public function getFormattedMaxTeams() {
        if ($this->max_teams === null) {
            return 'Default';
        } elseif ($this->max_teams < 0 || $this->max_teams >= 999) {
            return "Illimitate";
        } else {
            return $this->max_teams;
        }
    }
    
    /**
     * Formatta l'opzione ads
     * @return string
     */
    public function getFormattedHasAds() {
        if ($this->has_ads === null) {
            return 'Default';
        } elseif ($this->has_ads == 1) {
            return "Con Pubblicità";
        } else {
            return "Senza Pubblicità";
        }
    }
    
    /**
     * Formatta l'opzione predictions
     * @return string
     */
    public function getFormattedHasPredictions() {
        if ($this->has_predictions === null) {
            return 'Default';
        } elseif ($this->has_predictions == 1) {
            return "Abilitate";
        } else {
            return "Disabilitate";
        }
    }
}