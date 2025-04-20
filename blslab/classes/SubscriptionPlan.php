<?php
// classes/SubscriptionPlan.php

class SubscriptionPlan {
    private $db;
    private $id;
    private $name;
    private $max_championships;
    private $max_teams;
    private $has_ads;
    private $has_predictions;
    private $price;
    private $duration_days;
    private $description;
    private $is_active;
    
    /**
     * Costruttore
     * @param int|null $id ID piano abbonamento (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati del piano abbonamento dal database
     * @param int $id ID piano abbonamento
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $plan = $this->db->fetchOne("SELECT * FROM subscription_plans WHERE id = ?", [$id]);
        
        if ($plan) {
            $this->id = $plan['id'];
            $this->name = $plan['name'];
            $this->max_championships = $plan['max_championships'];
            $this->max_teams = $plan['max_teams'];
            $this->has_ads = $plan['has_ads'];
            $this->has_predictions = $plan['has_predictions'];
            $this->price = $plan['price'];
            $this->duration_days = $plan['duration_days'];
            $this->description = $plan['description'];
            $this->is_active = $plan['is_active'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Salva il piano abbonamento nel database
     * @return int|bool ID piano inserito o aggiornato, o false in caso di errore
     */
    public function save() {
        $data = [
            'name' => $this->name,
            'max_championships' => $this->max_championships,
            'max_teams' => $this->max_teams,
            'has_ads' => $this->has_ads,
            'has_predictions' => $this->has_predictions,
            'price' => $this->price,
            'duration_days' => $this->duration_days,
            'description' => $this->description,
            'is_active' => $this->is_active
        ];
        
        if ($this->id) {
            // Aggiorna piano esistente
            $result = $this->db->update('subscription_plans', $data, 'id = ?', [$this->id]);
            return $result ? $this->id : false;
        } else {
            // Crea nuovo piano
            $this->id = $this->db->insert('subscription_plans', $data);
            return $this->id;
        }
    }
    
    /**
     * Elimina il piano abbonamento
     * @return bool Successo/fallimento
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che non sia utilizzato da utenti prima di eliminarlo
        $userSubscriptionsCount = $this->db->count('user_subscriptions', 'plan_id = ? AND is_active = 1', [$this->id]);
        if ($userSubscriptionsCount > 0) {
            return false;
        }
        
        $result = $this->db->delete('subscription_plans', 'id = ?', [$this->id]);
        
        if ($result) {
            $this->id = null;
            $this->name = null;
            $this->max_championships = null;
            $this->max_teams = null;
            $this->has_ads = null;
            $this->has_predictions = null;
            $this->price = null;
            $this->duration_days = null;
            $this->description = null;
            $this->is_active = null;
        }
        
        return $result > 0;
    }
    
    /**
     * Disattiva il piano (invece di eliminarlo)
     * @return bool Successo/fallimento
     */
    public function disable() {
        if (!$this->id) {
            return false;
        }
        
        $result = $this->db->update('subscription_plans', ['is_active' => 0], 'id = ?', [$this->id]);
        
        if ($result) {
            $this->is_active = 0;
        }
        
        return $result > 0;
    }
    
    /**
     * Ottiene tutti i piani di abbonamento
     * @param bool $onlyActive Se true, restituisce solo i piani attivi
     * @return array
     */
    public static function getAll($onlyActive = true) {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM subscription_plans";
        $params = [];
        
        if ($onlyActive) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY price";
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Trova un piano abbonamento per ID
     * @param int $id ID piano abbonamento
     * @return SubscriptionPlan|null
     */
    public static function findById($id) {
        $plan = new self();
        return $plan->load($id) ? $plan : null;
    }
    
    /**
     * Ottiene il piano gratuito predefinito
     * @return SubscriptionPlan|null
     */
    public static function getFreePlan() {
        $db = Database::getInstance();
        $planData = $db->fetchOne("SELECT id FROM subscription_plans WHERE price = 0 AND is_active = 1 ORDER BY id LIMIT 1");
        
        if ($planData) {
            return self::findById($planData['id']);
        }
        
        return null;
    }
    
    /**
     * Ottiene il numero di utenti con questo piano di abbonamento attivo
     * @return int
     */
    public function getActiveUserCount() {
        if (!$this->id) {
            return 0;
        }
        
        return $this->db->count('user_subscriptions', 'plan_id = ? AND is_active = 1', [$this->id]);
    }
    
    /**
     * Ottiene il numero totale di utenti che hanno avuto questo piano
     * @return int
     */
    public function getTotalUserCount() {
        if (!$this->id) {
            return 0;
        }
        
        return $this->db->count('user_subscriptions', 'plan_id = ?', [$this->id]);
    }
    
    /**
     * Calcola la data di fine abbonamento in base alla data di inizio
     * @param string $startDate Data di inizio (Y-m-d)
     * @return string Data di fine (Y-m-d)
     */
    public function calculateEndDate($startDate) {
        $date = new DateTime($startDate);
        $date->add(new DateInterval('P' . $this->duration_days . 'D'));
        return $date->format('Y-m-d');
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
     * Getter per nome
     * @return string|null
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * Setter per nome
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
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
     * @param int $max_championships
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
     * @param int $max_teams
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
     * @param bool $has_ads
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
     * @param bool $has_predictions
     */
    public function setHasPredictions($has_predictions) {
        $this->has_predictions = $has_predictions;
    }
    
    /**
     * Getter per price
     * @return float|null
     */
    public function getPrice() {
        return $this->price;
    }
    
    /**
     * Setter per price
     * @param float $price
     */
    public function setPrice($price) {
        $this->price = $price;
    }
    
    /**
     * Getter per duration_days
     * @return int|null
     */
    public function getDurationDays() {
        return $this->duration_days;
    }
    
    /**
     * Setter per duration_days
     * @param int $duration_days
     */
    public function setDurationDays($duration_days) {
        $this->duration_days = $duration_days;
    }
    
    /**
     * Getter per description
     * @return string|null
     */
    public function getDescription() {
        return $this->description;
    }
    
    /**
     * Setter per description
     * @param string $description
     */
    public function setDescription($description) {
        $this->description = $description;
    }
    
    /**
     * Getter per is_active
     * @return bool|null
     */
    public function getIsActive() {
        return $this->is_active;
    }
    
    /**
     * Setter per is_active
     * @param bool $is_active
     */
    public function setIsActive($is_active) {
        $this->is_active = $is_active;
    }
    
    /**
     * Ottiene array di features dal description
     * @return array
     */
    public function getFeaturesArray() {
        if (empty($this->description)) {
            return [];
        }
        
        return explode("\n", $this->description);
    }
    
    /**
     * Formatta il prezzo
     * @return string
     */
    public function getFormattedPrice() {
        if ($this->price == 0) {
            return "Gratuito";
        }
        
        return number_format($this->price, 2, ',', '.') . ' €';
    }
    
    /**
     * Formatta il limite di campionati
     * @return string
     */
    public function getFormattedMaxChampionships() {
        if ($this->max_championships < 0 || $this->max_championships >= 999) {
            return "Illimitati";
        }
        
        return $this->max_championships;
    }
    
    /**
     * Formatta il limite di squadre
     * @return string
     */
    public function getFormattedMaxTeams() {
        if ($this->max_teams < 0 || $this->max_teams >= 999) {
            return "Illimitate";
        }
        
        return $this->max_teams;
    }
    
    /**
     * Formatta la durata
     * @return string
     */
    public function getFormattedDuration() {
        if ($this->duration_days >= 365) {
            $years = floor($this->duration_days / 365);
            return $years . ($years == 1 ? " anno" : " anni");
        } elseif ($this->duration_days >= 30) {
            $months = floor($this->duration_days / 30);
            return $months . ($months == 1 ? " mese" : " mesi");
        } else {
            return $this->duration_days . " giorni";
        }
    }
}