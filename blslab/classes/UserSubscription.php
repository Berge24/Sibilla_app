<?php
// classes/UserSubscription.php

class UserSubscription {
    private $db;
    private $id;
    private $user_id;
    private $plan_id;
    private $start_date;
    private $end_date;
    private $is_active;
    private $payment_id;
    
    /**
     * Costruttore
     * @param int|null $id ID abbonamento utente (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati dell'abbonamento utente dal database
     * @param int $id ID abbonamento utente
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $subscription = $this->db->fetchOne("SELECT * FROM user_subscriptions WHERE id = ?", [$id]);
        
        if ($subscription) {
            $this->id = $subscription['id'];
            $this->user_id = $subscription['user_id'];
            $this->plan_id = $subscription['plan_id'];
            $this->start_date = $subscription['start_date'];
            $this->end_date = $subscription['end_date'];
            $this->is_active = $subscription['is_active'];
            $this->payment_id = $subscription['payment_id'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Salva l'abbonamento utente nel database
     * @return int|bool ID abbonamento inserito o aggiornato, o false in caso di errore
     */
    public function save() {
        $data = [
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'payment_id' => $this->payment_id
        ];
        
        if ($this->id) {
            // Aggiorna abbonamento esistente
            $result = $this->db->update('user_subscriptions', $data, 'id = ?', [$this->id]);
            return $result ? $this->id : false;
        } else {
            // Crea nuovo abbonamento
            $this->id = $this->db->insert('user_subscriptions', $data);
            return $this->id;
        }
    }
    
    /**
     * Disattiva tutti gli abbonamenti attivi di un utente
     * @param int $userId ID utente
     * @return bool Successo/fallimento
     */
    public static function disableActiveSubscriptions($userId) {
        $db = Database::getInstance();
        $result = $db->update('user_subscriptions', ['is_active' => 0], 'user_id = ? AND is_active = 1', [$userId]);
        return $result > 0;
    }
    
    /**
     * Ottiene l'abbonamento attivo di un utente
     * @param int $userId ID utente
     * @return UserSubscription|null
     */
    public static function getActiveSubscription($userId) {
        $db = Database::getInstance();
        $subscription = $db->fetchOne("
            SELECT id FROM user_subscriptions 
            WHERE user_id = ? AND is_active = 1 
            ORDER BY end_date DESC 
            LIMIT 1
        ", [$userId]);
        
        if ($subscription) {
            return new self($subscription['id']);
        }
        
        return null;
    }
    
    /**
     * Ottiene lo storico degli abbonamenti di un utente
     * @param int $userId ID utente
     * @param int $limit Limite risultati (opzionale)
     * @return array
     */
    public static function getUserSubscriptionHistory($userId, $limit = 0) {
        $db = Database::getInstance();
        
        $query = "
            SELECT us.*, sp.name as plan_name, sp.price 
            FROM user_subscriptions us
            JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE us.user_id = ? 
            ORDER BY us.start_date DESC
        ";
        
        if ($limit > 0) {
            $query .= " LIMIT " . (int)$limit;
        }
        
        return $db->fetchAll($query, [$userId]);
    }
    
    /**
     * Crea un nuovo abbonamento per un utente
     * @param int $userId ID utente
     * @param int $planId ID piano abbonamento
     * @param string|null $startDate Data inizio (opzionale, default: oggi)
     * @param string|null $paymentId ID pagamento (opzionale)
     * @return UserSubscription|bool Nuovo abbonamento o false in caso di errore
     */
    public static function createUserSubscription($userId, $planId, $startDate = null, $paymentId = null) {
        // Disattiva eventuali abbonamenti attivi
        self::disableActiveSubscriptions($userId);
        
        // Carica il piano abbonamento
        $plan = SubscriptionPlan::findById($planId);
        if (!$plan) {
            return false;
        }
        
        // Imposta la data di inizio
        $startDate = $startDate ? $startDate : date('Y-m-d');
        
        // Calcola la data di fine
        $endDate = $plan->calculateEndDate($startDate);
        
        // Crea il nuovo abbonamento
        $subscription = new self();
        $subscription->setUserId($userId);
        $subscription->setPlanId($planId);
        $subscription->setStartDate($startDate);
        $subscription->setEndDate($endDate);
        $subscription->setIsActive(1);
        $subscription->setPaymentId($paymentId);
        
        if ($subscription->save()) {
            return $subscription;
        }
        
        return false;
    }
    
    /**
     * Verifica se un abbonamento è scaduto
     * @return bool True se scaduto, false altrimenti
     */
    public function isExpired() {
        if (!$this->end_date) {
            return false;
        }
        
        $currentDate = new DateTime();
        $expiryDate = new DateTime($this->end_date);
        
        return $currentDate > $expiryDate;
    }
    
    /**
     * Rinnova un abbonamento esistente
     * @param string|null $paymentId ID pagamento (opzionale)
     * @return bool Successo/fallimento
     */
    public function renew($paymentId = null) {
        if (!$this->id || !$this->plan_id) {
            return false;
        }
        
        // Disattiva l'abbonamento corrente
        $this->is_active = 0;
        $this->save();
        
        // Carica il piano abbonamento
        $plan = SubscriptionPlan::findById($this->plan_id);
        if (!$plan) {
            return false;
        }
        
        // Determina la data di inizio (oggi o la data di fine precedente, quella più recente)
        $today = new DateTime();
        $endDate = new DateTime($this->end_date);
        $startDate = ($today > $endDate) ? $today : $endDate;
        
        // Crea il nuovo abbonamento
        return self::createUserSubscription(
            $this->user_id, 
            $this->plan_id, 
            $startDate->format('Y-m-d'),
            $paymentId
        );
    }
    
    /**
     * Ottiene i dettagli del piano abbonamento
     * @return array|null
     */
    public function getPlan() {
        if (!$this->id || !$this->plan_id) {
            return null;
        }
        
        return $this->db->fetchOne("SELECT * FROM subscription_plans WHERE id = ?", [$this->plan_id]);
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
     * Getter per plan_id
     * @return int|null
     */
    public function getPlanId() {
        return $this->plan_id;
    }
    
    /**
     * Setter per plan_id
     * @param int $plan_id
     */
    public function setPlanId($plan_id) {
        $this->plan_id = $plan_id;
    }
    
    /**
     * Getter per start_date
     * @return string|null
     */
    public function getStartDate() {
        return $this->start_date;
    }
    
    /**
     * Setter per start_date
     * @param string $start_date
     */
    public function setStartDate($start_date) {
        $this->start_date = $start_date;
    }
    
    /**
     * Getter per end_date
     * @return string|null
     */
    public function getEndDate() {
        return $this->end_date;
    }
    
    /**
     * Setter per end_date
     * @param string $end_date
     */
    public function setEndDate($end_date) {
        $this->end_date = $end_date;
    }
    
    /**
     * Getter per is_active
     * @return bool|null
     */
    public function getIsActive() {
        return $this->is_active;
    }
    

// [Continuazione della classe UserSubscription]

    /**
     * Setter per is_active
     * @param bool $is_active
     */
    public function setIsActive($is_active) {
        $this->is_active = $is_active;
    }
    
    /**
     * Getter per payment_id
     * @return string|null
     */
    public function getPaymentId() {
        return $this->payment_id;
    }
    
    /**
     * Setter per payment_id
     * @param string $payment_id
     */
    public function setPaymentId($payment_id) {
        $this->payment_id = $payment_id;
    }
    
    /**
     * Formatta la data di inizio
     * @return string
     */
    public function getFormattedStartDate() {
        if (empty($this->start_date)) {
            return 'N/D';
        }
        
        return date('d/m/Y', strtotime($this->start_date));
    }
    
    /**
     * Formatta la data di fine
     * @return string
     */
    public function getFormattedEndDate() {
        if (empty($this->end_date)) {
            return 'N/D';
        }
        
        $isExpired = $this->isExpired();
        $formattedDate = date('d/m/Y', strtotime($this->end_date));
        
        return $isExpired ? '<span class="text-danger">' . $formattedDate . '</span>' : $formattedDate;
    }
    
    /**
     * Calcola i giorni rimanenti
     * @return int
     */
    public function getDaysRemaining() {
        if (empty($this->end_date) || !$this->is_active) {
            return 0;
        }
        
        $currentDate = new DateTime();
        $expiryDate = new DateTime($this->end_date);
        
        if ($currentDate > $expiryDate) {
            return 0;
        }
        
        $interval = $currentDate->diff($expiryDate);
        return $interval->days;
    }
    
    /**
     * Formatta i giorni rimanenti
     * @return string
     */
    public function getFormattedDaysRemaining() {
        $daysRemaining = $this->getDaysRemaining();
        
        if ($daysRemaining <= 0) {
            return '<span class="text-danger">Scaduto</span>';
        } elseif ($daysRemaining <= 7) {
            return '<span class="text-warning">' . $daysRemaining . ' giorni</span>';
        } else {
            return $daysRemaining . ' giorni';
        }
    }
    
    /**
     * Verifica se è possibile rinnovare l'abbonamento
     * @return bool
     */
    public function canRenew() {
        // Si può rinnovare se il piano è ancora attivo
        $plan = SubscriptionPlan::findById($this->plan_id);
        return ($plan && $plan->getIsActive());
    }
    
    /**
     * Ottiene statistiche di utilizzo dell'abbonamento
     * @return array
     */
    public function getUsageStats() {
        $stats = [];
        
        // Carica il piano per ottenere i limiti
        $plan = $this->getPlan();
        if (!$plan) {
            return $stats;
        }
        
        // Carica le statistiche utente
        $userStats = $this->db->fetchOne("SELECT * FROM user_stats WHERE user_id = ?", [$this->user_id]);
        
        if (!$userStats) {
            // Se non ci sono statistiche, inizializza tutto a zero
            $userStats = [
                'championships_count' => 0,
                'teams_count' => 0,
                'matches_count' => 0
            ];
        }
        
        // Calcola percentuali e limiti
        $stats['championships'] = [
            'count' => $userStats['championships_count'],
            'limit' => $plan['max_championships'],
            'unlimited' => ($plan['max_championships'] < 0 || $plan['max_championships'] >= 999),
            'percentage' => ($plan['max_championships'] > 0 && $plan['max_championships'] < 999) 
                ? min(100, ($userStats['championships_count'] / $plan['max_championships']) * 100) 
                : 0,
            'reached' => ($plan['max_championships'] > 0 && $plan['max_championships'] < 999) 
                ? ($userStats['championships_count'] >= $plan['max_championships']) 
                : false
        ];
        
        $stats['teams'] = [
            'count' => $userStats['teams_count'],
            'limit' => $plan['max_teams'],
            'unlimited' => ($plan['max_teams'] < 0 || $plan['max_teams'] >= 999),
            'percentage' => ($plan['max_teams'] > 0 && $plan['max_teams'] < 999) 
                ? min(100, ($userStats['teams_count'] / $plan['max_teams']) * 100) 
                : 0,
            'reached' => ($plan['max_teams'] > 0 && $plan['max_teams'] < 999) 
                ? ($userStats['teams_count'] >= $plan['max_teams']) 
                : false
        ];
        
        $stats['matches'] = [
            'count' => $userStats['matches_count']
        ];
        
        $stats['features'] = [
            'has_ads' => (bool)$plan['has_ads'],
            'has_predictions' => (bool)$plan['has_predictions']
        ];
        
        return $stats;
    }
}