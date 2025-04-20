<?php
// classes/Subscription.php

class Subscription {
    private $db;
    private $id;
    private $name;
    private $price;
    private $max_championships;
    private $max_teams;
    private $has_ads;
    private $features;
    
    /**
     * Costruttore
     * @param int|null $id ID abbonamento (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati dell'abbonamento dal database
     * @param int $id ID abbonamento
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $subscription = $this->db->fetchOne("SELECT * FROM subscriptions WHERE id = ?", [$id]);
        
        if ($subscription) {
            $this->id = $subscription['id'];
            $this->name = $subscription['name'];
            $this->price = $subscription['price'];
            $this->max_championships = $subscription['max_championships'];
            $this->max_teams = $subscription['max_teams'];
            $this->has_ads = $subscription['has_ads'];
            $this->features = $subscription['features'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Salva l'abbonamento nel database
     * @return int|bool ID abbonamento inserito o aggiornato, o false in caso di errore
     */
    public function save() {
        $data = [
            'name' => $this->name,
            'price' => $this->price,
            'max_championships' => $this->max_championships,
            'max_teams' => $this->max_teams,
            'has_ads' => $this->has_ads,
            'features' => $this->features
        ];
        
        if ($this->id) {
            // Aggiorna abbonamento esistente
            $result = $this->db->update('subscriptions', $data, 'id = ?', [$this->id]);
            return $result ? $this->id : false;
        } else {
            // Crea nuovo abbonamento
            $this->id = $this->db->insert('subscriptions', $data);
            return $this->id;
        }
    }
    
    /**
     * Elimina l'abbonamento
     * @return bool Successo/fallimento
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che non sia utilizzato da utenti prima di eliminarlo
        $usersCount = $this->db->count('users', 'subscription_id = ?', [$this->id]);
        if ($usersCount > 0) {
            return false;
        }
        
        $result = $this->db->delete('subscriptions', 'id = ?', [$this->id]);
        
        if ($result) {
            $this->id = null;
            $this->name = null;
            $this->price = null;
            $this->max_championships = null;
            $this->max_teams = null;
            $this->has_ads = null;
            $this->features = null;
        }
        
        return $result > 0;
    }
    
    /**
     * Verifica se un utente ha raggiunto il limite di campionati
     * @param int $userId ID utente
     * @return bool True se ha raggiunto il limite, false altrimenti
     */
    public function hasReachedChampionshipsLimit($userId) {
        if ($this->max_championships < 0) {
            // Piano illimitato
            return false;
        }
        
        $count = $this->db->count('championships', 'user_id = ?', [$userId]);
        return $count >= $this->max_championships;
    }
    
    /**
     * Verifica se un utente ha raggiunto il limite di squadre
     * @param int $userId ID utente
     * @return bool True se ha raggiunto il limite, false altrimenti
     */
    public function hasReachedTeamsLimit($userId) {
        if ($this->max_teams < 0) {
            // Piano illimitato
            return false;
        }
        
        $count = $this->db->count('teams', 'user_id = ?', [$userId]);
        return $count >= $this->max_teams;
    }
    
    /**
     * Ottiene tutti gli abbonamenti
     * @return array
     */
    public static function getAll() {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT * FROM subscriptions ORDER BY price");
    }
    
    /**
     * Trova un abbonamento per ID
     * @param int $id ID abbonamento
     * @return Subscription|null
     */
    public static function findById($id) {
        $subscription = new self();
        return $subscription->load($id) ? $subscription : null;
    }
    
    /**
     * Ottiene il numero di utenti con questo abbonamento
     * @return int
     */
    public function getUserCount() {
        if (!$this->id) {
            return 0;
        }
        
        return $this->db->count('users', 'subscription_id = ?', [$this->id]);
    }
    
    /**
     * Aggiorna abbonamento di un utente
     * @param int $userId ID utente
     * @param int $subscriptionId ID nuovo abbonamento
     * @param string|null $startDate Data inizio (opzionale)
     * @param string|null $endDate Data fine (opzionale)
     * @return bool Successo/fallimento
     */
    public static function updateUserSubscription($userId, $subscriptionId, $startDate = null, $endDate = null) {
        $db = Database::getInstance();
        
        $data = [
            'subscription_id' => $subscriptionId
        ];
        
        if ($startDate) {
            $data['subscription_start'] = $startDate;
        } else {
            $data['subscription_start'] = date('Y-m-d');
        }
        
        if ($endDate) {
            $data['subscription_end'] = $endDate;
        }
        
        $result = $db->update('users', $data, 'id = ?', [$userId]);
        
        return $result > 0;
    }
    
    /**
     * Verifica se un abbonamento è scaduto
     * @param string|null $endDate Data di fine abbonamento
     * @return bool True se scaduto, false altrimenti
     */
    public static function isExpired($endDate) {
        if (!$endDate) {
            return false;
        }
        
        $currentDate = new DateTime();
        $expiryDate = new DateTime($endDate);
        
        return $currentDate > $expiryDate;
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
     * Getter per prezzo
     * @return float|null
     */
    public function getPrice() {
        return $this->price;
    }
    
    /**
     * Setter per prezzo
     * @param float $price
     */
    public function setPrice($price) {
        $this->price = $price;
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
     * Getter per features
     * @return string|null
     */
    public function getFeatures() {
        return $this->features;
    }
    
    /**
     * Setter per features
     * @param string $features
     */
    public function setFeatures($features) {
        $this->features = $features;
    }
    
    /**
     * Ottiene array di features
     * @return array
     */
    public function getFeaturesArray() {
        if (empty($this->features)) {
            return [];
        }
        
        return explode("\n", $this->features);
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
        if ($this->max_championships < 0) {
            return "Illimitati";
        }
        
        return $this->max_championships;
    }
    
    /**
     * Formatta il limite di squadre
     * @return string
     */
    public function getFormattedMaxTeams() {
        if ($this->max_teams < 0) {
            return "Illimitate";
        }
        
        return $this->max_teams;
    }
}