<?php
// classes/UserStats.php

class UserStats {
    private $db;
    private $id;
    private $user_id;
    private $championships_count;
    private $teams_count;
    private $matches_count;
    private $last_update;
    
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
     * Carica le statistiche utente dal database usando l'ID del record
     * @param int $id ID del record
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $stats = $this->db->fetchOne("SELECT * FROM user_stats WHERE id = ?", [$id]);
        
        if ($stats) {
            $this->id = $stats['id'];
            $this->user_id = $stats['user_id'];
            $this->championships_count = $stats['championships_count'];
            $this->teams_count = $stats['teams_count'];
            $this->matches_count = $stats['matches_count'];
            $this->last_update = $stats['last_update'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Carica le statistiche utente dal database usando l'ID utente
     * @param int $userId ID utente
     * @return bool Successo/fallimento
     */
    public function loadByUserId($userId) {
        $stats = $this->db->fetchOne("SELECT * FROM user_stats WHERE user_id = ?", [$userId]);
        
        if ($stats) {
            $this->id = $stats['id'];
            $this->user_id = $stats['user_id'];
            $this->championships_count = $stats['championships_count'];
            $this->teams_count = $stats['teams_count'];
            $this->matches_count = $stats['matches_count'];
            $this->last_update = $stats['last_update'];
            return true;
        }
        
        // Se non esiste, inizializza con un nuovo record
        $this->user_id = $userId;
        $this->championships_count = 0;
        $this->teams_count = 0;
        $this->matches_count = 0;
        $this->last_update = date('Y-m-d H:i:s');
        
        // Salva subito il nuovo record
        $this->save();
        
        return false;
    }
    
    /**
     * Salva le statistiche utente nel database
     * @return int|bool ID statistiche inserite o aggiornate, o false in caso di errore
     */
    public function save() {
        if (!$this->user_id) {
            return false;
        }
        
        $data = [
            'user_id' => $this->user_id,
            'championships_count' => $this->championships_count,
            'teams_count' => $this->teams_count,
            'matches_count' => $this->matches_count,
            'last_update' => date('Y-m-d H:i:s')
        ];
        
        if ($this->id) {
            // Aggiorna statistiche esistenti
            $result = $this->db->update('user_stats', $data, 'id = ?', [$this->id]);
            return $result ? $this->id : false;
        } else {
            // Crea nuove statistiche
            $this->id = $this->db->insert('user_stats', $data);
            return $this->id;
        }
    }
    
    /**
     * Aggiorna le statistiche dell'utente calcolandole dal database
     * @return bool Successo/fallimento
     */
    public function refresh() {
        if (!$this->user_id) {
            return false;
        }
        
        // Conta i campionati dell'utente
        $this->championships_count = $this->db->count('championships', 'user_id = ?', [$this->user_id]);
        
        // Conta le squadre dell'utente
        $this->teams_count = $this->db->count('teams', 'user_id = ?', [$this->user_id]);
        
        // Conta le partite nei campionati dell'utente
        $this->matches_count = $this->db->fetchColumn("
            SELECT COUNT(*) FROM matches m
            JOIN championships c ON m.championship_id = c.id
            WHERE c.user_id = ?
        ", [$this->user_id]);
        
        // Salva le nuove statistiche
        return $this->save();
    }
    
    /**
     * Ottiene le statistiche di tutti gli utenti
     * @param int $limit Limite di risultati (opzionale)
     * @param int $offset Offset di partenza (opzionale)
     * @return array
     */
    public static function getAllStats($limit = 0, $offset = 0) {
        $db = Database::getInstance();
        
        $query = "
            SELECT us.*, u.username, u.email, u.role, u.is_approved,
                   sp.name as plan_name, sp.price as plan_price,
                   usub.start_date, usub.end_date, usub.is_active
            FROM user_stats us
            JOIN users u ON us.user_id = u.id
            LEFT JOIN user_subscriptions usub ON u.id = usub.user_id AND usub.is_active = 1
            LEFT JOIN subscription_plans sp ON usub.plan_id = sp.id
            ORDER BY us.championships_count DESC, us.teams_count DESC
        ";
        
        if ($limit > 0) {
            $query .= " LIMIT " . ($offset > 0 ? $offset . ", " : "") . $limit;
        }
        
        return $db->fetchAll($query);
    }
    
    /**
     * Ottiene le statistiche degli utenti più attivi
     * @param int $limit Limite di risultati (default: 10)
     * @return array
     */
    public static function getMostActiveUsers($limit = 10) {
        $db = Database::getInstance();
        
        return $db->fetchAll("
            SELECT us.*, u.username, u.email,
                   sp.name as plan_name,
                   (us.championships_count + us.teams_count + (us.matches_count / 10)) as activity_score
            FROM user_stats us
            JOIN users u ON us.user_id = u.id
            LEFT JOIN user_subscriptions usub ON u.id = usub.user_id AND usub.is_active = 1
            LEFT JOIN subscription_plans sp ON usub.plan_id = sp.id
            WHERE u.is_approved = 1
            ORDER BY activity_score DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Ottiene le statistiche di utilizzo complessivo della piattaforma
     * @return array
     */
    public static function getPlatformStats() {
        $db = Database::getInstance();
        $stats = [];
        
        // Conteggio utenti totali
        $stats['total_users'] = $db->count('users');
        $stats['active_users'] = $db->count('users', 'is_approved = 1');
        $stats['pending_users'] = $db->count('users', 'is_approved = 0');
        
        // Conteggio abbonamenti per tipo
        $stats['subscriptions'] = $db->fetchAll("
            SELECT sp.id, sp.name, sp.price, COUNT(usub.id) as count
            FROM subscription_plans sp
            LEFT JOIN user_subscriptions usub ON sp.id = usub.plan_id AND usub.is_active = 1
            GROUP BY sp.id
            ORDER BY sp.price ASC
        ");
        
        // Conteggio entità totali
        $stats['total_championships'] = $db->count('championships');
        $stats['total_teams'] = $db->count('teams');
        $stats['total_matches'] = $db->count('matches');
        
        // Media entità per utente
        $stats['avg_championships_per_user'] = $stats['active_users'] > 0 
            ? round($stats['total_championships'] / $stats['active_users'], 2) 
            : 0;
        
        $stats['avg_teams_per_user'] = $stats['active_users'] > 0 
            ? round($stats['total_teams'] / $stats['active_users'], 2) 
            : 0;
        
        return $stats;
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
     * Getter per championships_count
     * @return int|null
     */
    public function getChampionshipsCount() {
        return $this->championships_count;
    }
    
    /**
     * Setter per championships_count
     * @param int $championships_count
     */
    public function setChampionshipsCount($championships_count) {
        $this->championships_count = $championships_count;
    }
    
    /**
     * Getter per teams_count
     * @return int|null
     */
    public function getTeamsCount() {
        return $this->teams_count;
    }
    
    /**
     * Setter per teams_count
     * @param int $teams_count
     */
    public function setTeamsCount($teams_count) {
        $this->teams_count = $teams_count;
    }
    
    /**
     * Getter per matches_count
     * @return int|null
     */
    public function getMatchesCount() {
        return $this->matches_count;
    }
    
    /**
     * Setter per matches_count
     * @param int $matches_count
     */
    public function setMatchesCount($matches_count) {
        $this->matches_count = $matches_count;
    }
    
    /**
     * Getter per last_update
     * @return string|null
     */
    public function getLastUpdate() {
        return $this->last_update;
    }
    
    /**
     * Formatta la data ultimo aggiornamento
     * @return string
     */
    public function getFormattedLastUpdate() {
        if (empty($this->last_update)) {
            return 'Mai';
        }
        
        return date('d/m/Y H:i', strtotime($this->last_update));
    }
    
    /**
     * Incrementa il conteggio dei campionati
     * @param int $increment Valore incremento (default: 1)
     * @return bool Successo/fallimento
     */
    public function incrementChampionships($increment = 1) {
        $this->championships_count += $increment;return $this->save();
    }
    
    /**
     * Incrementa il conteggio delle squadre
     * @param int $increment Valore incremento (default: 1)
     * @return bool Successo/fallimento
     */
    public function incrementTeams($increment = 1) {
        $this->teams_count += $increment;
        return $this->save();
    }
    
    /**
     * Incrementa il conteggio delle partite
     * @param int $increment Valore incremento (default: 1)
     * @return bool Successo/fallimento
     */
    public function incrementMatches($increment = 1) {
        $this->matches_count += $increment;
        return $this->save();
    }
    
    /**
     * Decrementa il conteggio dei campionati
     * @param int $decrement Valore decremento (default: 1)
     * @return bool Successo/fallimento
     */
    public function decrementChampionships($decrement = 1) {
        $this->championships_count = max(0, $this->championships_count - $decrement);
        return $this->save();
    }
    
    /**
     * Decrementa il conteggio delle squadre
     * @param int $decrement Valore decremento (default: 1)
     * @return bool Successo/fallimento
     */
    public function decrementTeams($decrement = 1) {
        $this->teams_count = max(0, $this->teams_count - $decrement);
        return $this->save();
    }
    
    /**
     * Decrementa il conteggio delle partite
     * @param int $decrement Valore decremento (default: 1)
     * @return bool Successo/fallimento
     */
    public function decrementMatches($decrement = 1) {
        $this->matches_count = max(0, $this->matches_count - $decrement);
        return $this->save();
    }
    
    /**
     * Verifica se l'utente ha raggiunto il limite di campionati
     * @return bool True se ha raggiunto il limite, false altrimenti
     */
    public function hasReachedChampionshipsLimit() {
        $limits = UserLimits::getEffectiveLimits($this->user_id);
        
        // Se il limite è negativo o >= 999, significa illimitato
        if ($limits['max_championships'] < 0 || $limits['max_championships'] >= 999) {
            return false;
        }
        
        return $this->championships_count >= $limits['max_championships'];
    }
    
    /**
     * Verifica se l'utente ha raggiunto il limite di squadre
     * @return bool True se ha raggiunto il limite, false altrimenti
     */
    public function hasReachedTeamsLimit() {
        $limits = UserLimits::getEffectiveLimits($this->user_id);
        
        // Se il limite è negativo o >= 999, significa illimitato
        if ($limits['max_teams'] < 0 || $limits['max_teams'] >= 999) {
            return false;
        }
        
        return $this->teams_count >= $limits['max_teams'];
    }
    
    /**
     * Ottiene la percentuale di utilizzo dei campionati
     * @return float|null
     */
    public function getChampionshipsUsagePercentage() {
        $limits = UserLimits::getEffectiveLimits($this->user_id);
        
        // Se il limite è negativo o >= 999, significa illimitato
        if ($limits['max_championships'] < 0 || $limits['max_championships'] >= 999) {
            return null;
        }
        
        return min(100, ($this->championships_count / $limits['max_championships']) * 100);
    }
    
    /**
     * Ottiene la percentuale di utilizzo delle squadre
     * @return float|null
     */
    public function getTeamsUsagePercentage() {
        $limits = UserLimits::getEffectiveLimits($this->user_id);
        
        // Se il limite è negativo o >= 999, significa illimitato
        if ($limits['max_teams'] < 0 || $limits['max_teams'] >= 999) {
            return null;
        }
        
        return min(100, ($this->teams_count / $limits['max_teams']) * 100);
    }
    
    /**
     * Ottiene il numero di campionati rimanenti
     * @return int|string
     */
    public function getRemainingChampionships() {
        $limits = UserLimits::getEffectiveLimits($this->user_id);
        
        // Se il limite è negativo o >= 999, significa illimitato
        if ($limits['max_championships'] < 0 || $limits['max_championships'] >= 999) {
            return "Illimitati";
        }
        
        return max(0, $limits['max_championships'] - $this->championships_count);
    }
    
    /**
     * Ottiene il numero di squadre rimanenti
     * @return int|string
     */
    public function getRemainingTeams() {
        $limits = UserLimits::getEffectiveLimits($this->user_id);
        
        // Se il limite è negativo o >= 999, significa illimitato
        if ($limits['max_teams'] < 0 || $limits['max_teams'] >= 999) {
            return "Illimitate";
        }
        
        return max(0, $limits['max_teams'] - $this->teams_count);
    }
    
    /**
     * Ottiene un array con tutte le statistiche di utilizzo e limiti
     * @return array
     */
    public function getFullStats() {
        $limits = UserLimits::getEffectiveLimits($this->user_id);
        
        $stats = [
            'championships' => [
                'count' => $this->championships_count,
                'limit' => $limits['max_championships'],
                'unlimited' => ($limits['max_championships'] < 0 || $limits['max_championships'] >= 999),
                'percentage' => $this->getChampionshipsUsagePercentage(),
                'remaining' => $this->getRemainingChampionships(),
                'reached_limit' => $this->hasReachedChampionshipsLimit()
            ],
            'teams' => [
                'count' => $this->teams_count,
                'limit' => $limits['max_teams'],
                'unlimited' => ($limits['max_teams'] < 0 || $limits['max_teams'] >= 999),
                'percentage' => $this->getTeamsUsagePercentage(),
                'remaining' => $this->getRemainingTeams(),
                'reached_limit' => $this->hasReachedTeamsLimit()
            ],
            'matches' => [
                'count' => $this->matches_count
            ],
            'features' => [
                'has_ads' => $limits['has_ads'],
                'has_predictions' => $limits['has_predictions']
            ],
            'custom_limits' => $limits['has_custom_limits'],
            'last_update' => $this->getFormattedLastUpdate()
        ];
        
        return $stats;
    }
}