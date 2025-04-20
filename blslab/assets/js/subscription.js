/* assets/js/subscription.js */

document.addEventListener('DOMContentLoaded', function() {
    // Gestione delle tab per i piani di abbonamento
    const subscriptionTabs = document.querySelectorAll('[data-subscription-tab]');
    const subscriptionPanes = document.querySelectorAll('[data-subscription-pane]');
    
    if (subscriptionTabs.length > 0) {
        subscriptionTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Rimuovi la classe active da tutte le tab
                subscriptionTabs.forEach(t => t.classList.remove('active'));
                
                // Aggiungi la classe active alla tab cliccata
                this.classList.add('active');
                
                // Nascondi tutti i panes
                subscriptionPanes.forEach(pane => pane.classList.add('d-none'));
                
                // Mostra il pane associato alla tab
                const targetPane = document.querySelector(`[data-subscription-pane="${this.dataset.subscriptionTab}"]`);
                if (targetPane) {
                    targetPane.classList.remove('d-none');
                }
            });
        });
    }
    
    // Gestione modali di upgrade
    const upgradeButtons = document.querySelectorAll('[data-toggle="modal-upgrade"]');
    
    if (upgradeButtons.length > 0) {
        upgradeButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetModal = document.querySelector(this.dataset.target);
                if (targetModal) {
                    const bsModal = new bootstrap.Modal(targetModal);
                    bsModal.show();
                }
            });
        });
    }
    
    // Gestione degli checkbox per il confronto piani
    const comparePlansCheckboxes = document.querySelectorAll('.compare-plan-checkbox');
    
    if (comparePlansCheckboxes.length > 0) {
        // Limita la selezione a massimo 3 piani
        comparePlansCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedCount = document.querySelectorAll('.compare-plan-checkbox:checked').length;
                
                if (checkedCount > 3) {
                    this.checked = false;
                    alert('Puoi confrontare al massimo 3 piani');
                }
                
                // Abilita/disabilita il pulsante di confronto
                const compareButton = document.getElementById('compare-plans-btn');
                if (compareButton) {
                    compareButton.disabled = checkedCount < 2;
                }
            });
        });
    }
    
    // Gestione filtri elementi pubblici/privati
    const visibilityFilter = document.getElementById('visibility-filter');
    const contentItems = document.querySelectorAll('.content-item');
    
    if (visibilityFilter && contentItems.length > 0) {
        visibilityFilter.addEventListener('change', function() {
            const selectedValue = this.value;
            
            contentItems.forEach(item => {
                if (selectedValue === 'all' || item.dataset.visibility === selectedValue) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        });
    }
    
    // Controllo limiti lato client
    function checkUsageLimits() {
        const usageIndicators = document.querySelectorAll('.usage-indicator');
        
        usageIndicators.forEach(indicator => {
            const progress = indicator.querySelector('.progress-bar');
            const currentValue = parseInt(progress.getAttribute('aria-valuenow'));
            const maxValue = parseInt(progress.getAttribute('aria-valuemax'));
            
            // Aggiorna colore della barra in base all'utilizzo
            if (currentValue / maxValue > 0.9) {
                progress.classList.remove('bg-success', 'bg-warning');
                progress.classList.add('bg-danger');
            } else if (currentValue / maxValue > 0.7) {
                progress.classList.remove('bg-success', 'bg-danger');
                progress.classList.add('bg-warning');
            } else {
                progress.classList.remove('bg-warning', 'bg-danger');
                progress.classList.add('bg-success');
            }
            
            // Se il limite è raggiunto, aggiungi classe al div padre
            if (currentValue >= maxValue) {
                indicator.classList.add('limit-reached');
            } else {
                indicator.classList.remove('limit-reached');
            }
        });
    }
    
    // Controlla i limiti all'inizializzazione
    checkUsageLimits();
    
    // Gestione form di upgrade/downgrade
    const planSelectionForm = document.getElementById('plan-selection-form');
    
    if (planSelectionForm) {
        planSelectionForm.addEventListener('submit', function(e) {
            // Verifica che sia stato selezionato un piano
            const selectedPlan = document.querySelector('input[name="plan_id"]:checked');
            
            if (!selectedPlan) {
                e.preventDefault();
                alert('Seleziona un piano di abbonamento');
                return false;
            }
            
            // Conferma per il downgrade
            const isDowngrade = selectedPlan.hasAttribute('data-is-downgrade') && 
                              selectedPlan.getAttribute('data-is-downgrade') === 'true';
            
            if (isDowngrade) {
                if (!confirm('Sei sicuro di voler effettuare il downgrade? Alcune funzionalità potrebbero non essere più disponibili.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    // Funzione per mostrare il modal di limite raggiunto
    function showLimitReachedModal(resourceType) {
        const limitModal = document.getElementById('limit-reached-modal');
        if (limitModal) {
            // Aggiorna il contenuto del modal in base al tipo di risorsa
            const resourceTitle = limitModal.querySelector('.resource-title');
            if (resourceTitle) {
                resourceTitle.textContent = resourceType;
            }
            
            // Mostra il modal
            const bsModal = new bootstrap.Modal(limitModal);
            bsModal.show();
        }
    }
    
    // Gestione pulsanti che potrebbero raggiungere limiti
    const limitButtons = document.querySelectorAll('[data-check-limit]');
    
    if (limitButtons.length > 0) {
        limitButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                const limitType = this.dataset.checkLimit;
                const limitReached = this.dataset.limitReached === 'true';
                
                if (limitReached) {
                    e.preventDefault();
                    showLimitReachedModal(limitType);
                }
            });
        });
    }
});

// Funzioni utilizzabili globalmente per la gestione degli abbonamenti
window.subscriptionManager = {
    // Verifica se una funzionalità è disponibile per l'abbonamento corrente
    isFeatureAvailable: function(featureCode) {
        // Ottieni l'abbonamento corrente dall'elemento nascosto nella pagina
        const currentPlanElement = document.getElementById('current-subscription-plan');
        if (!currentPlanElement) return false;
        
        const currentPlan = currentPlanElement.value;
        
        // Mappa delle funzionalità disponibili per piano
        const featuresByPlan = {
            'free': ['basic_view', 'public_championships'],
            'basic': ['basic_view', 'public_championships', 'create_championship', 'create_team', 'create_match'],
            'premium': ['basic_view', 'public_championships', 'create_championship', 'create_team', 'create_match', 'statistics', 'probability', 'private_championships'],
            'enterprise': ['basic_view', 'public_championships', 'create_championship', 'create_team', 'create_match', 'statistics', 'probability', 'private_championships', 'api_access', 'custom_branding']
        };
        
        // Verifica se la funzionalità è disponibile per il piano corrente
        return featuresByPlan[currentPlan] && featuresByPlan[currentPlan].includes(featureCode);
    },
    
    // Mostra modal per funzionalità premium
    showUpgradeModal: function(featureCode) {
        const upgradeModal = document.getElementById('feature-upgrade-modal');
        if (upgradeModal) {
            // Aggiorna il contenuto del modal in base alla funzionalità
            const featureTitle = upgradeModal.querySelector('.feature-title');
            const featurePlan = upgradeModal.querySelector('.feature-plan');
            
            if (featureTitle && featurePlan) {
                const featureInfo = {
                    'create_championship': {
                        title: 'Creazione Campionati',
                        plan: 'Basic'
                    },
                    'create_team': {
                        title: 'Creazione Squadre',
                        plan: 'Basic'
                    },
                    'statistics': {
                        title: 'Statistiche Avanzate',
                        plan: 'Premium'
                    },
                    'probability': {
                        title: 'Calcolo Probabilità',
                        plan: 'Premium'
                    },
                    'private_championships': {
                        title: 'Campionati Privati',
                        plan: 'Premium'
                    },
                    'api_access': {
                        title: 'Accesso API',
                        plan: 'Enterprise'
                    },
                    'custom_branding': {
                        title: 'Branding Personalizzato',
                        plan: 'Enterprise'
                    }
                };
                
                if (featureInfo[featureCode]) {
                    featureTitle.textContent = featureInfo[featureCode].title;
                    featurePlan.textContent = featureInfo[featureCode].plan;
                } else {
                    featureTitle.textContent = 'Funzionalità Premium';
                    featurePlan.textContent = 'un piano superiore';
                }
            }
            
            // Mostra il modal
            const bsModal = new bootstrap.Modal(upgradeModal);
            bsModal.show();
        }
    }
};