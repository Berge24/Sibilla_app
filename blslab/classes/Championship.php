<?php
// classes/Championship.php

class Championship {
    private $db;
    private $id;
    private $user_id;
    private $name;
    private $type;
    private $seasonId;
    private $startDate;
    private $endDate;
    private $description;
    private $is_public;
    
    /**
     * Costruttore
     * @param int|null $id ID campionato (opzionale)
     */
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        
        if ($id) {
            $this->load($id);
        }
    }
    
    /**
     * Carica i dati del campionato dal database
     * @param int $id ID campionato
     * @return bool Successo/fallimento
     */
    public function load($id) {
        $championship = $this->db->fetchOne("SELECT * FROM championships WHERE id = ?", [$id]);
        
        if ($championship) {
            $this->id = $championship['id'];
            $this->user_id = $championship['user_id'];
            $this->name = $championship['name'];
            $this->type = $championship['type'];
            $this->seasonId = $championship['season_id'];
            $this->startDate = $championship['start_date'];
            $this->endDate = $championship['end_date'];
            $this->is_public = $championship['is_public'];
            // Verifica se description esiste prima di assegnarlo
            $this->description = isset($championship['description']) ? $championship['description'] : '';
            return true;
        }
        
        return false;
    }
    
    /**
     * Crea un nuovo campionato
     * @param int $userId ID dell'utente proprietario
     * @param string $name Nome del campionato
     * @param string $type Tipo del campionato (CSI o UISP)
     * @param int $seasonId ID della stagione
     * @param string $startDate Data di inizio
     * @param string $endDate Data di fine
     * @param bool $isPublic Visibilità pubblica
     * @return int|false ID del campionato creato o false in caso di errore
     */
    public function create($userId, $name, $type, $seasonId, $startDate, $endDate, $isPublic = false) {
        // Verifica il tipo di campionato
        if ($type !== CHAMPIONSHIP_TYPE_CSI && $type !== CHAMPIONSHIP_TYPE_UISP) {
            return false;
        }
        
        // Verifica che l'utente non abbia superato il limite di campionati
        $user = User::findById($userId);
        if (!$user || !$user->canCreateChampionship()) {
            return false;
        }
        
        // Verifica che la stagione esista e sia accessibile dall'utente
        $season = Season::findById($seasonId);
        if (!$season || !$season->isAccessibleByUser($userId)) {
            return false;
        }
        
        // Inserimento campionato
        $championshipId = $this->db->insert('championships', [
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'season_id' => $seasonId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_public' => $isPublic ? 1 : 0
        ]);
        
        if ($championshipId) {
            $this->id = $championshipId;
            $this->user_id = $userId;
            $this->name = $name;
            $this->type = $type;
            $this->seasonId = $seasonId;
            $this->startDate = $startDate;
            $this->endDate = $endDate;
            $this->is_public = $isPublic;
            
            // Aggiorna statistiche utente
            $stats = new UserStats($userId);
            $stats->incrementChampionships();
        }
        
        return $championshipId;
    }
    
    /**
     * Aggiorna i dati del campionato
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
        
        $result = $this->db->update('championships', $data, 'id = ?', [$this->id]);
        
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
     * Elimina il campionato
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
            // Elimina le relazioni con le squadre
            $this->db->delete('championships_teams', 'championship_id = ?', [$this->id]);
            
            // Elimina le probabilità delle partite
            $this->db->delete('match_probabilities', 
                'match_id IN (SELECT id FROM matches WHERE championship_id = ?)', 
                [$this->id]
            );
            
            // Elimina le partite
            $this->db->delete('matches', 'championship_id = ?', [$this->id]);
            
            // Elimina le classifiche
            $this->db->delete('standings', 'championship_id = ?', [$this->id]);
            
            // Elimina la simulazione
            $this->db->delete('simulation_history', 'championship_id = ?', [$this->id]);
            
            // Elimina il campionato
            $result = $this->db->delete('championships', 'id = ?', [$this->id]);
            
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
                $this->type = null;
                $this->seasonId = null;
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
     * Aggiunge una squadra al campionato
     * @param int $teamId ID della squadra
     * @return bool Successo/fallimento
     */
    public function addTeam($teamId) {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente corrente sia il proprietario
        $currentUser = User::getCurrentUser();
        if (!$currentUser || ($this->user_id != $currentUser->getId() && !$currentUser->isAdmin())) {
            return false;
        }
        
        // Verifica se la squadra è già nel campionato
        if ($this->db->count('championships_teams', 'championship_id = ? AND team_id = ?', [$this->id, $teamId]) > 0) {
            return false;
        }
        
        // Verifica che la squadra esista e sia accessibile dall'utente
        $team = Team::findById($teamId);
        if (!$team || !$team->isAccessibleByUser($this->user_id)) {
            return false;
        }
        
        // Aggiungi la squadra al campionato
        $result = $this->db->insert('championships_teams', [
            'championship_id' => $this->id,
            'team_id' => $teamId
        ]);
        
        // Crea anche una riga nella tabella standings
        if ($result) {
            $this->db->insert('standings', [
                'championship_id' => $this->id,
                'team_id' => $teamId
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Rimuove una squadra dal campionato
     * @param int $teamId ID della squadra
     * @return bool Successo/fallimento
     */
    public function removeTeam($teamId) {
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
            // Rimuovi dalla tabella standings
            $this->db->delete('standings', 'championship_id = ? AND team_id = ?', [$this->id, $teamId]);
            
            // Rimuovi la relazione
            $result = $this->db->delete('championships_teams', 'championship_id = ? AND team_id = ?', [$this->id, $teamId]);
            
            $this->db->commit();
            return $result > 0;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Ottiene tutte le squadre del campionato
     * @return array
     */
    public function getTeams() {
        if (!$this->id) {
            return [];
        }
        
        return $this->db->fetchAll("
            SELECT t.* 
            FROM teams t
            JOIN championships_teams ct ON t.id = ct.team_id
            WHERE ct.championship_id = ?
            ORDER BY t.name
        ", [$this->id]);
    }
    
    /**
     * Ottiene tutte le partite del campionato
     * @param string|null $status Filtro per lo stato della partita (opzionale)
     * @return array
     */
    public function getMatches($status = null) {
        if (!$this->id) {
            return [];
        }
        
        $query = "
            SELECT m.*, 
                   home.name as home_team_name, 
                   away.name as away_team_name
            FROM matches m
            JOIN teams home ON m.home_team_id = home.id
            JOIN teams away ON m.away_team_id = away.id
            WHERE m.championship_id = ?
        ";
        
        $params = [$this->id];
        
        if ($status) {
            $query .= " AND m.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY m.match_date";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Ottiene la classifica del campionato
     * @return array
     */
    public function getStandings() {
        if (!$this->id) {
            return [];
        }
        
        return $this->db->fetchAll("
            SELECT s.*, t.name as team_name
            FROM standings s
            JOIN teams t ON s.team_id = t.id
            WHERE s.championship_id = ?
            ORDER BY s.points DESC, s.won DESC, (s.scored - s.conceded) DESC
        ", [$this->id]);
    }
    
    /**
     * Calcola/aggiorna la classifica del campionato
     * @return bool Successo/fallimento
     */
    public function calculateStandings() {
        if (!$this->id) {
            return false;
        }
        
        // Ottieni tutte le squadre del campionato
        $teams = $this->db->fetchAll("
            SELECT team_id FROM championships_teams WHERE championship_id = ?
        ", [$this->id]);
        
        if (empty($teams)) {
            return false;
        }
        
        // Inizializza la transazione
        $this->db->beginTransaction();
        
        try {
            // Per ogni squadra, calcola le statistiche
            foreach ($teams as $team) {
                $teamId = $team['team_id'];
                
                // Ottieni tutte le partite completate per questa squadra in questo campionato
                $matches = $this->db->fetchAll("
                    SELECT * FROM matches 
                    WHERE championship_id = ? 
                    AND (home_team_id = ? OR away_team_id = ?)
                    AND status = ?
                ", [$this->id, $teamId, $teamId, MATCH_STATUS_COMPLETED]);
                
                // Inizializza le statistiche
                $played = count($matches);
                $won = 0;
                $lost = 0;
                $points = 0;
                $scored = 0;
                $conceded = 0;
                
                // Calcola le statistiche basate sulle partite
                foreach ($matches as $match) {
                    if ($match['home_team_id'] == $teamId) {
                        // Squadra gioca in casa
                        $scored += $match['home_score'];
                        $conceded += $match['away_score'];
                        
                        if ($match['home_score'] > $match['away_score']) {
                            // Vittoria
                            $won++;
                            // Assegna i punti in base al tipo di campionato
                            if ($this->type == CHAMPIONSHIP_TYPE_CSI) {
                                $points += CSI_POINTS_WIN;
                            } else {
                                // Per UISP dobbiamo contare i punti dei periodi, ma per ora usiamo una semplificazione
                                $points += 2; // Punti base per vittoria
                            }
                        } else {
                            // Sconfitta (nel basket non ci sono pareggi)
                            $lost++;
                            // Nel basket anche chi perde prende punti in classifica
                            if ($this->type == CHAMPIONSHIP_TYPE_UISP) {
                                $points += 0; // Punto di partecipazione
                            }
                        }
                    } else {
                        // Squadra gioca fuori casa
                        $scored += $match['away_score'];
                        $conceded += $match['home_score'];
                        
                        if ($match['away_score'] > $match['home_score']) {
                            // Vittoria
                            $won++;
                            // Assegna i punti in base al tipo di campionato
                            if ($this->type == CHAMPIONSHIP_TYPE_CSI) {
                                $points += CSI_POINTS_WIN;
                            } else {
                                $points += 2;
                            }
                        } else {
                            // Sconfitta
                            $lost++;
                            if ($this->type == CHAMPIONSHIP_TYPE_UISP) {
                                $points += 0;
                            }
                        }
                    }
                }
                
                // Aggiorna la tabella standings
                $this->db->update('standings', [
                    'played' => $played,
                    'won' => $won,
                    // Per il basket, imposta drawn a 0 sempre
                    'drawn' => 0,
                    'lost' => $lost,
                    'points' => $points,
                    'scored' => $scored,
                    'conceded' => $conceded,
                    'last_calculated' => date('Y-m-d H:i:s')
                ], 'championship_id = ? AND team_id = ?', [$this->id, $teamId]);
            }
            
            // Dopo l'aggiornamento di tutte le classifiche, calcola le probabilità di vittoria
            $this->calculateWinProbabilities();
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Errore nel calcolo della classifica: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola le probabilità di vittoria del campionato per tutte le squadre
     * @return bool Successo/fallimento
     */
    public function calculateWinProbabilities() {
        if (!$this->id) {
            return false;
        }
        
        // Ottieni tutte le squadre con le loro statistiche nella classifica
        $standings = $this->getStandings();
        if (empty($standings)) {
            return false;
        }
        
        // Ottieni le partite rimanenti
        $remainingMatches = $this->db->fetchAll("
            SELECT * FROM matches
            WHERE championship_id = ? AND status = ?
        ", [$this->id, MATCH_STATUS_SCHEDULED]);
        
        // Se non ci sono partite rimanenti e almeno una squadra ha giocato partite
        // la squadra in testa ha 100% di probabilità di vincere
        if (empty($remainingMatches) && $standings[0]['played'] > 0) {
            // Prepara un array per le probabilità
            $probabilities = [];
            foreach ($standings as $standing) {
                $probabilities[$standing['team_id']] = ($standing['team_id'] == $standings[0]['team_id']) ? 100.0 : 0.0;
            }
            
            // Aggiorna le probabilità nel database
            $this->updateWinProbabilities($probabilities);
            return true;
        }
        
        // Verifica che l'utente possa accedere alle predizioni
        $currentUser = User::getCurrentUser();
        if (!$currentUser || !$currentUser->canAccessPredictions()) {
            // Se non può accedere, imposta probabilità uguali per tutte le squadre
            $probabilities = [];
            $equalProbability = 100.0 / count($standings);
            foreach ($standings as $standing) {
                $probabilities[$standing['team_id']] = $equalProbability;
            }
            
            // Aggiorna le probabilità nel database
            $this->updateWinProbabilities($probabilities);
            return true;
        }
        
        // Altrimenti, esegui una simulazione Monte Carlo per calcolare le probabilità
        return $this->runMonteCarlo($standings, $remainingMatches);
    }
    
    /**
     * Esegui una simulazione Monte Carlo per calcolare le probabilità di vittoria
     * @param array $standings Classifiche attuali
     * @param array $remainingMatches Partite rimanenti
     * @return bool Successo/fallimento
     */
    private function runMonteCarlo($standings, $remainingMatches) {
        $numSimulations = 1000; // Numero di simulazioni
        $winCounts = [];
        
        // Inizializza il contatore vittorie per ogni squadra
        foreach ($standings as $standing) {
            $winCounts[$standing['team_id']] = 0;
        }
        
        // Crea un array delle forze relative delle squadre
        $teamStrengths = [];
        foreach ($standings as $standing) {
            // Calcola un punteggio di forza della squadra
            if ($standing['played'] > 0) {
                $winPercentage = $standing['won'] / $standing['played'];
                $scoreDiff = $standing['scored'] - $standing['conceded'];
                $scoreFactor = ($scoreDiff >= 0) ? (1 + ($scoreDiff / 100)) : (1 / (1 + abs($scoreDiff) / 100));
                $teamStrengths[$standing['team_id']] = $winPercentage * $scoreFactor;
            } else {
                $teamStrengths[$standing['team_id']] = 0.5; // Valore di default per squadre senza partite
            }
        }
        
        // Determina i punti per vittoria in base al tipo di campionato
        $pointsForWin = ($this->type == CHAMPIONSHIP_TYPE_CSI) ? 3 : 2;
        
        // Esegui le simulazioni
        for ($sim = 0; $sim < $numSimulations; $sim++) {
            // Copia la classifica attuale
            $simStandings = [];
            foreach ($standings as $standing) {
                $simStandings[$standing['team_id']] = [
                    'points' => $standing['points'],
                    'won' => $standing['won'],
                    'drawn' => $standing['drawn'],
                    'lost' => $standing['lost'],
                    'scored' => $standing['scored'],
                    'conceded' => $standing['conceded']
                ];
            }
            
            // Simula i risultati delle partite rimanenti
            foreach ($remainingMatches as $match) {
                // Ottieni le forze delle squadre
                $homeStrength = $teamStrengths[$match['home_team_id']] ?? 0.5;
                $awayStrength = $teamStrengths[$match['away_team_id']] ?? 0.5;
                
                // Aggiungi fattore casa (10% vantaggio)
                $homeStrength *= 1.1;
                
                // Simula il risultato
                $homeWinProb = $homeStrength / ($homeStrength + $awayStrength);
                $rand = mt_rand() / mt_getrandmax();
                
                if ($rand < $homeWinProb) {
                    // Vittoria casa
                    $simStandings[$match['home_team_id']]['points'] += $pointsForWin;
                    $simStandings[$match['home_team_id']]['won'] += 1;
                    $simStandings[$match['away_team_id']]['lost'] += 1;
                    
                    // Simula anche i gol
                    $homeScore = mt_rand(1, 5);
                    $awayScore = mt_rand(0, $homeScore - 1);
                    
                    $simStandings[$match['home_team_id']]['scored'] += $homeScore;
                    $simStandings[$match['home_team_id']]['conceded'] += $awayScore;
                    $simStandings[$match['away_team_id']]['scored'] += $awayScore;
                    $simStandings[$match['away_team_id']]['conceded'] += $homeScore;
                } elseif ($rand < $homeWinProb + 0.2) { // 20% di probabilità di pareggio
                    // Pareggio
                    $simStandings[$match['home_team_id']]['points'] += 1;
                    $simStandings[$match['away_team_id']]['points'] += 1;
                    $simStandings[$match['home_team_id']]['drawn'] += 1;
                    $simStandings[$match['away_team_id']]['drawn'] += 1;
                    
                    // Simula anche i gol (stesso punteggio)
                    $score = mt_rand(0, 3);
                    
                    $simStandings[$match['home_team_id']]['scored'] += $score;
                    $simStandings[$match['home_team_id']]['conceded'] += $score;
                    $simStandings[$match['away_team_id']]['scored'] += $score;
                    $simStandings[$match['away_team_id']]['conceded'] += $score;
                } else {
                    // Vittoria trasferta
                    $simStandings[$match['away_team_id']]['points'] += $pointsForWin;
                    $simStandings[$match['away_team_id']]['won'] += 1;
                    $simStandings[$match['home_team_id']]['lost'] += 1;
                    
                    // Simula anche i gol
                    $awayScore = mt_rand(1, 5);
                    $homeScore = mt_rand(0, $awayScore - 1);
                    
                    $simStandings[$match['home_team_id']]['scored'] += $homeScore;
                    $simStandings[$match['home_team_id']]['conceded'] += $awayScore;
                    $simStandings[$match['away_team_id']]['scored'] += $awayScore;
                    $simStandings[$match['away_team_id']]['conceded'] += $homeScore;
                }
            }
            
            // Determina il vincitore di questa simulazione
            $winner = null;
            $maxPoints = -1;
            $tiedTeams = [];
            
            // Trova il punteggio massimo
            foreach ($simStandings as $teamId => $stats) {
                if ($stats['points'] > $maxPoints) {
                    $maxPoints = $stats['points'];
                    $tiedTeams = [$teamId];
                    $winner = $teamId;
                } elseif ($stats['points'] == $maxPoints) {
                    $tiedTeams[] = $teamId;
                }
            }
            
            // Se c'è un pareggio, risolvi usando la differenza reti
            if (count($tiedTeams) > 1) {
                $maxGoalDiff = -999;
                foreach ($tiedTeams as $teamId) {
                    $goalDiff = $simStandings[$teamId]['scored'] - $simStandings[$teamId]['conceded'];
                    if ($goalDiff > $maxGoalDiff) {
                        $maxGoalDiff = $goalDiff;
                        $winner = $teamId;
                    }
                }
            }
            
            // Incrementa il contatore per il vincitore
            if ($winner) {
                $winCounts[$winner]++;
            }
        }
        
        // Calcola le probabilità
        $probabilities = [];
        foreach ($winCounts as $teamId => $count) {
            $probabilities[$teamId] = ($count / $numSimulations) * 100.0;
        }
        
        // Aggiorna le probabilità nel database e salva il record della simulazione
        $this->updateWinProbabilities($probabilities);
        
        // Salva i dettagli della simulazione
        $this->db->insert('simulation_history', [
            'championship_id' => $this->id,
            'simulation_date' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    /**
     * Aggiorna le probabilità di vittoria nel database
     * @param array $probabilities Probabilità di vittoria per ogni squadra
     * @return bool Successo/fallimento
     */
    private function updateWinProbabilities($probabilities) {
        $this->db->beginTransaction();
        
        try {
            foreach ($probabilities as $teamId => $probability) {
                $this->db->update('standings', [
                    'win_probability' => $probability,
                    'last_calculated' => date('Y-m-d H:i:s')
                ], 'championship_id = ? AND team_id = ?', [$this->id, $teamId]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Errore nell'aggiornamento delle probabilità: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calcola le probabilità di vittoria per tutte le partite future
     * @return bool Successo/fallimento
     */
    public function calculateMatchProbabilities() {
        if (!$this->id) {
            return false;
        }
        
        // Verifica che l'utente possa accedere alle predizioni
        $currentUser = User::getCurrentUser();
        if (!$currentUser || !$currentUser->canAccessPredictions()) {
            return false;
        }
        
        // Ottieni tutte le partite programmate
        $scheduledMatches = $this->db->fetchAll("
            SELECT * FROM matches
            WHERE championship_id = ? AND status = ?
        ", [$this->id, MATCH_STATUS_SCHEDULED]);
        
        if (empty($scheduledMatches)) {
            return true; // nessuna partita da calcolare
        }
        
        // Ottieni statistiche delle squadre per calcolare la forza relativa
        $teamStats = [];
        $standings = $this->getStandings();
        
        foreach ($standings as $standing) {
            $teamStats[$standing['team_id']] = $standing;
        }
        
        // Calcola le probabilità per ogni partita
        foreach ($scheduledMatches as $match) {
            $match = new Match($match['id']);
            $match->calculateWinProbabilities();
        }
        
        return true;
    }
    
    /**
     * Verifica se l'utente specificato ha accesso al campionato
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
        
        // Tutti hanno accesso se il campionato è pubblico
        return $this->is_public == 1;
    }
    
    /**
     * Verifica se l'utente corrente può modificare questo campionato
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
     * Ottiene tutti i campionati
     * @param int|null $seasonId Filtra per stagione (opzionale)
     * @param int|null $userId Filtra per ID utente (opzionale)
     * @param bool $includePublic Include campionati pubblici
     * @return array
     */
    public static function getAll($seasonId = null, $userId = null, $includePublic = true) {
        $db = Database::getInstance();
        
        $query = "
            SELECT c.*, s.name as season_name
            FROM championships c
            JOIN seasons s ON c.season_id = s.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($seasonId) {
            $query .= " AND c.season_id = ?";
            $params[] = $seasonId;
        }
        
        // Filtra per accessibilità
        $currentUser = User::getCurrentUser();
        
        if ($userId !== null) {
            // Filtra per proprietario specifico
            $query .= " AND c.user_id = ?";
            $params[] = $userId;
        } else if ($currentUser) {
            if (!$currentUser->isAdmin()) {
                // Utente normale: mostra i suoi campionati e quelli pubblici
                if ($includePublic) {
                    $query .= " AND (c.user_id = ? OR c.is_public = 1)";
                    $params[] = $currentUser->getId();
                } else {
                    $query .= " AND c.user_id = ?";
                    $params[] = $currentUser->getId();
                }
            }
        } else {
            // Utente non loggato: mostra solo campionati pubblici
            $query .= " AND c.is_public = 1";
        }
        
        $query .= " ORDER BY c.start_date DESC";
        
        return $db->fetchAll($query, $params);
    }
    
    /**
     * Trova un campionato per ID
     * @param int $id ID campionato
     * @return Championship|null
     */
    public static function findById($id) {
        $championship = new self();
        if ($championship->load($id)) {
            // Verifica l'accesso dell'utente corrente
            $currentUser = User::getCurrentUser();
            if ($currentUser) {
                if ($championship->isAccessibleByUser($currentUser->getId())) {
                    return $championship;
                }
            } else if ($championship->is_public) {
                return $championship;
            }
            return null;
        }
        return null;
    }
    
    // Getter e setter
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getSeasonId() { return $this->seasonId; }
    public function getStartDate() { return $this->startDate; }
    public function getEndDate() { return $this->endDate; }
    public function getDescription() { return $this->description; }
    public function getIsPublic() { return $this->is_public; }
    
    public function setUserId($userId) { $this->user_id = $userId; }
    public function setName($name) { $this->name = $name; }
    public function setType($type) { $this->type = $type; }
    public function setSeasonId($seasonId) { $this->seasonId = $seasonId; }
    public function setStartDate($startDate) { $this->startDate = $startDate; }
    public function setEndDate($endDate) { $this->endDate = $endDate; }
    public function setDescription($description) { $this->description = $description; }
    public function setIsPublic($isPublic) { $this->is_public = $isPublic; }
}