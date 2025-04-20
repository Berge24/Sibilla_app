/* assets/js/script.js */

document.addEventListener('DOMContentLoaded', function() {
    // Abilita i tooltip di Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Gestione dei campi dinamici per i periodi della partita UISP
    const championshipTypeSelect = document.getElementById('championship_type');
    const periodsContainer = document.getElementById('periods_container');
    const addPeriodBtn = document.getElementById('add_period_btn');
    const removePeriodBtn = document.getElementById('remove_period_btn');
    
    // Mostra/nascondi container periodi in base al tipo di campionato
    if (championshipTypeSelect && periodsContainer) {
        championshipTypeSelect.addEventListener('change', function() {
            if (this.value === 'UISP') {
                periodsContainer.classList.remove('d-none');
            } else {
                periodsContainer.classList.add('d-none');
            }
        });
        
        // Trigger all'inizializzazione della pagina
        if (championshipTypeSelect.value === 'UISP') {
            periodsContainer.classList.remove('d-none');
        }
    }
    
    // Gestione del bottone per aggiungere un periodo
    if (addPeriodBtn && periodsContainer) {
        addPeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const periodsItems = periodsContainer.querySelectorAll('.period-item');
            const newPeriodNumber = periodsItems.length + 1;
            
            const newPeriod = document.createElement('div');
            newPeriod.className = 'period-item card mb-3';
            newPeriod.innerHTML = `
                <div class="card-header">
                    <h5 class="mb-0">Periodo ${newPeriodNumber}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="home_result_${newPeriodNumber}">Esito squadra casa</label>
                                <select class="form-control" id="home_result_${newPeriodNumber}" name="periods[${newPeriodNumber-1}][home_result]" required>
                                    <option value="win">Vittoria</option>
                                    <option value="draw">Pareggio</option>
                                    <option value="loss">Sconfitta</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="away_result_${newPeriodNumber}">Esito squadra ospite</label>
                                <select class="form-control" id="away_result_${newPeriodNumber}" name="periods[${newPeriodNumber-1}][away_result]" required>
                                    <option value="win">Vittoria</option>
                                    <option value="draw">Pareggio</option>
                                    <option value="loss">Sconfitta</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="home_score_${newPeriodNumber}">Punti squadra casa</label>
                                <input type="number" class="form-control" id="home_score_${newPeriodNumber}" name="periods[${newPeriodNumber-1}][home_score]" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="away_score_${newPeriodNumber}">Punti squadra ospite</label>
                                <input type="number" class="form-control" id="away_score_${newPeriodNumber}" name="periods[${newPeriodNumber-1}][away_score]" min="0">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            periodsContainer.appendChild(newPeriod);
            
            // Sincronizza i risultati dei periodi
            syncPeriodResults();
        });
    }
    
    // Gestione del bottone per rimuovere un periodo
    if (removePeriodBtn && periodsContainer) {
        removePeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const periodsItems = periodsContainer.querySelectorAll('.period-item');
            if (periodsItems.length > 1) {
                periodsContainer.removeChild(periodsItems[periodsItems.length - 1]);
            }
        });
    }
    
    // Funzione per sincronizzare i risultati dei periodi (se vincita casa, l'ospite perde e viceversa)
    function syncPeriodResults() {
        const periodsItems = periodsContainer.querySelectorAll('.period-item');
        
        periodsItems.forEach((item, index) => {
            const homeResultSelect = item.querySelector(`[id^="home_result_"]`);
            const awayResultSelect = item.querySelector(`[id^="away_result_"]`);
            
            if (homeResultSelect && awayResultSelect) {
                homeResultSelect.addEventListener('change', function() {
                    if (this.value === 'win') {
                        awayResultSelect.value = 'loss';
                    } else if (this.value === 'loss') {
                        awayResultSelect.value = 'win';
                    } else {
                        awayResultSelect.value = 'draw';
                    }
                });
                
                awayResultSelect.addEventListener('change', function() {
                    if (this.value === 'win') {
                        homeResultSelect.value = 'loss';
                    } else if (this.value === 'loss') {
                        homeResultSelect.value = 'win';
                    } else {
                        homeResultSelect.value = 'draw';
                    }
                });
            }
        });
    }
    
    // Avvia la sincronizzazione all'inizializzazione
    if (periodsContainer) {
        syncPeriodResults();
    }
    
    // Conferma eliminazione elementi
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Sei sicuro di voler eliminare questo elemento?')) {
                e.preventDefault();
            }
        });
    });
});