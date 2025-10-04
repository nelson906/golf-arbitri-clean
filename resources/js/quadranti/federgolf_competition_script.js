/**
 * Script JavaScript per gestire il caricamento asincrono dei tab nella pagina di dettaglio competizione
 */
// Assicuriamo che ajaxurl sia disponibile globalmente anche nel frontend
if (typeof ajaxurl === 'undefined') {
    var ajaxurl = '/wp-admin/admin-ajax.php';
}

// Funzioni globali per gestire il modal
window.openModal = function(modalId) {
    document.getElementById(modalId).style.display = 'flex';
};

window.closeModal = function(modalId) {
    document.getElementById(modalId).style.display = 'none';
};

// Variabile globale per l'ID della competizione
window.competitionId = null;

jQuery(document).ready(function($) {
    // Inizializza l'ID della competizione dalla pagina
    window.competitionId = $('#competition-id').val();
    // Variabili per tracciare quali tab sono stati caricati
    var tabsLoaded = {
        'info': true,           // Il tab INFO è sempre caricato all'inizio
        'lista-iscritti': false,
        'orari-partenze': false,
        'classifiche': false,
        'dettagli': false,
        'variazioni-hcp': false
    };

    // Funzione per mostrare il loader
    function showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }

    // Funzione per nascondere il loader
    function hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }

    // --- Inizializzazione Chosen ---
    $("#tipologia-classifica, #giro-classifica").chosen({
        disable_search_threshold: 10,
        width: "200px",
        allow_single_deselect: true,
        placeholder_text_single: "Seleziona..."
    });

    // DEFINISCI LA FUNZIONE handleTabChange COMPLETA PRIMA DI USARLA
    window.handleTabChange = function(hash) {
        // Rimuovi la classe "active" da tutte le schede
        $('.crm-gare-dettaglio-tab-item').removeClass('active');
        // Nascondi tutti i contenuti
        $('.crm-gare-dettaglio-tab-content').hide();

        var targetTab = 0; // Default to INFO tab
        var tabId = '';
        
        switch(hash) {
            case '#info':           
                targetTab = 0; 
                tabId = 'info';
                break;
            case '#lista-iscritti': 
                targetTab = 1; 
                tabId = 'lista-iscritti';
                break;
            case '#orari-partenze': 
                targetTab = 2; 
                tabId = 'orari-partenze';
                break;
            case '#classifiche':    
                targetTab = 3; 
                tabId = 'classifiche';
                break;
            case '#dettagli':       
                targetTab = 4; 
                tabId = 'dettagli';
                break;
            case '#variazioni-hcp': 
                targetTab = 5; 
                tabId = 'variazioni-hcp';
                break;
            default:
                hash = '#info'; // Se hash non valido, forza #info
                targetTab = 0;
                tabId = 'info';
        }

        // Aggiorna l'hash nell'URL senza ricaricare (se diverso da quello attuale)
        if(window.location.hash !== hash) {
            window.history.pushState(null, null, hash);
        }

        // Aggiungi la classe "active" alla scheda selezionata
        $('.crm-gare-dettaglio-tab a[href="' + hash + '"]').find('.crm-gare-dettaglio-tab-item').addClass('active');

        // Mostra il contenuto corrispondente
        var targetContent = '#content' + (targetTab + 1);
        
        // Se il tab non è ancora stato caricato, mostra il loader e carica i dati
        if (!tabsLoaded[tabId] && tabId !== 'info') {
            // Mostra il contenuto prima di caricare i dati
            $(targetContent).show();
            
            // Carica i dati del tab specifico in modo asincrono
            switch(tabId) {
                case 'lista-iscritti':
                    // Carica i dati della lista iscritti via AJAX
                    loadListaIscritti(targetContent);
                    break;
                    
                case 'orari-partenze':
                    // Carica i dati degli orari di partenza via AJAX
                    loadOrariPartenze(targetContent);
                    break;
                    
                case 'classifiche':
                    // Carica i dati delle classifiche via AJAX
                    loadClassifiche(targetContent);
                    break;
                    
                case 'dettagli':
                    // Carica i risultati della classifica solo quando richiesto
                    loadClassificaData(targetContent);
                    tabsLoaded[tabId] = true;
                    break;
                    
                case 'variazioni-hcp':
                    // Carica i dati del report HCP via AJAX
                    loadVariazioniHcp(targetContent);
                    break;
                    
                default:
                    break;
            }
        } else {
            // Se il tab è già stato caricato, mostralo subito
            $(targetContent).show();
            
            // Ridisegna le DataTables visibili quando la tab cambia
            $(targetContent).find('.dataTable').each(function() {
                if ($.fn.DataTable.isDataTable(this)) {
                    var table = $(this).DataTable();
                    table.columns.adjust().draw();
                }
            });
        }
    }

    // DOPO aver definito handleTabChange, gestisci l'hash iniziale
    var initialHash = window.location.hash ? window.location.hash : '#info';
    
    // Valida l'hash iniziale
    var validHashes = ['#info', '#lista-iscritti', '#orari-partenze', '#classifiche', '#dettagli', '#variazioni-hcp'];
    if (!validHashes.includes(initialHash)) {
        initialHash = '#info';
    }
    
    // Chiamiamo handleTabChange con l'hash corretto DOPO aver definito la funzione completa
    window.handleTabChange(initialHash);

    // Carica i dati per la scheda dettagli/classifica
    function loadClassificaData(targetContent) {
        // Prima carica i dati per le select list
        loadSelectListData(targetContent);
    }

    // Carica i dati per le select list tipologia-classifica e giro-classifica
    function loadSelectListData(targetContent) {
        var competitionId = $('#competition-id').val();
        
        const formData = new FormData();
        formData.append('action', 'competition-tipologia-classifica');
        formData.append('competition_id', competitionId);

        // Mostra il loading
        showLoading();

        // Assicuriamoci che ajaxurl sia un URL completo
        let ajaxEndpoint = ajaxurl;
        if (ajaxEndpoint.indexOf('http') !== 0 && ajaxEndpoint.indexOf('//') !== 0) {
            ajaxEndpoint = window.location.origin + ajaxEndpoint;
        }

        fetch(ajaxEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data.results) {
                var results = data.data.results;
                
                // Popola la select tipologia-classifica
                var tipologiaSelect = $('#tipologia-classifica');
                var giroSelect = $('#giro-classifica');
                
                // Pulisci le select
                tipologiaSelect.empty();
                giroSelect.empty();
                
                // Popola tipologia-classifica (senza opzione vuota)
                if (results.tipologia_classifica && results.tipologia_classifica.length > 0) {
                    var firstSelected = true;
                    results.tipologia_classifica.forEach(function(option) {
                        var isSelected = firstSelected ? ' selected' : '';
                        tipologiaSelect.append('<option value="' + option.value + '"' + isSelected + '>' + option.text + '</option>');
                        firstSelected = false;
                    });
                }
                
                // Popola giro-classifica (senza opzione vuota, giro 1 come default)
                if (results.giri_totali && results.giri_totali > 0) {
                    for (var i = 1; i <= results.giri_totali; i++) {
                        var isSelected = i === 1 ? ' selected' : '';
                        giroSelect.append('<option value="' + i + '"' + isSelected + '>' + i + '</option>');
                    }
                }
                
                // Re-inizializza Chosen dopo aver popolato le select
                tipologiaSelect.trigger('chosen:updated');
                giroSelect.trigger('chosen:updated');
                
                // Aspetta che Chosen sia completamente inizializzato, poi carica i dati
                setTimeout(function() {
                    // Ora ottieni i valori dopo che le select sono state popolate e inizializzate
                    var tipo = tipologiaSelect.val();
                    var giro = giroSelect.val();
                    
                    if (tipo && giro) {
                        // Carica i dati della classifica con i valori corretti
                        aggiornaClassifica(competitionId, tipo, giro, null);
                    } else {
                        // Inizializza la DataTable vuota
                        if (!$.fn.DataTable.isDataTable('#crm-odm-dettaglio-dettaglio-risultati')) {
                            $('#crm-odm-dettaglio-dettaglio-risultati').DataTable({
                                paging: false,
                                scrollX: true,
                                scrollY: '',
                                scrollCollapse: false,
                                responsive: false,
                                autoWidth: false,
                                columnDefs: [{ targets: 8, visible: false }], // Nascondi colonna ID
                                language: {
                                    emptyTable: "Seleziona tipologia e giro per visualizzare i risultati"
                                }
                            });
                        }
                        // Aggiungi l'event listener comunque
                        addClickListenerToTable();
                        hideLoading();
                    }
                }, 200); // Aumenta il timeout per assicurarsi che Chosen sia completamente inizializzato
                
            } else {
                // In caso di errore, mostra messaggio e inizializza tabella vuota
                var tipologiaSelect = $('#tipologia-classifica');
                var giroSelect = $('#giro-classifica');
                
                tipologiaSelect.empty().append('<option value="">Errore caricamento dati</option>');
                giroSelect.empty().append('<option value="">Errore caricamento dati</option>');
                
                tipologiaSelect.trigger('chosen:updated');
                giroSelect.trigger('chosen:updated');
                
                setTimeout(function() {
                    if (!$.fn.DataTable.isDataTable('#crm-odm-dettaglio-dettaglio-risultati')) {
                        $('#crm-odm-dettaglio-dettaglio-risultati').DataTable({
                            paging: false,
                            scrollX: true,
                            scrollY: '',
                            scrollCollapse: false,
                            responsive: false,
                            autoWidth: false,
                            columnDefs: [{ targets: 8, visible: false }],
                            language: {
                                emptyTable: "Errore nel caricamento dei dati delle classifiche"
                            }
                        });
                    }
                    addClickListenerToTable();
                    hideLoading();
                }, 100);
            }
        })
        .catch(function(error) {
            // Gestione errore di rete
            var tipologiaSelect = $('#tipologia-classifica');
            var giroSelect = $('#giro-classifica');
            
            tipologiaSelect.empty().append('<option value="">Errore di connessione</option>');
            giroSelect.empty().append('<option value="">Errore di connessione</option>');
            
            tipologiaSelect.trigger('chosen:updated');
            giroSelect.trigger('chosen:updated');
            
            setTimeout(function() {
                if (!$.fn.DataTable.isDataTable('#crm-odm-dettaglio-dettaglio-risultati')) {
                    $('#crm-odm-dettaglio-dettaglio-risultati').DataTable({
                        paging: false,
                        scrollX: true,
                        scrollY: '',
                        scrollCollapse: false,
                        responsive: false,
                        autoWidth: false,
                        columnDefs: [{ targets: 8, visible: false }],
                        language: {
                            emptyTable: "Errore di connessione durante il caricamento"
                        }
                    });
                }
                addClickListenerToTable();
                hideLoading();
            }, 100);
        });
    }

    // Carica i dati della lista iscritti via AJAX
    function loadListaIscritti(targetContent) {
        var competitionId = $('#competition-id').val();
        
        const formData = new FormData();
        formData.append('action', 'competition-player-list');
        formData.append('competition_id', competitionId);
        formData.append('page_number', 1);
        formData.append('page_size', 250);

        // Mostra lo spinner modal come per la tabella dettagli
        showLoading();

        // Assicuriamoci che ajaxurl sia un URL completo
        let ajaxEndpoint = ajaxurl;
        if (ajaxEndpoint.indexOf('http') !== 0 && ajaxEndpoint.indexOf('//') !== 0) {
            ajaxEndpoint = window.location.origin + ajaxEndpoint;
        }

        fetch(ajaxEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Popola la legenda
                $(targetContent).find('.crm-gare-dettaglio-content-lista-iscritti-container')
                               .html(data.data.legendaHtml);
                
                // Mostra la tabella corretta e nasconde l'altra
                if (data.data.isGaraASquadre) {
                    $(targetContent).find('#tabella-individuali-container').hide();
                    $(targetContent).find('#tabella-squadre-container').show();
                    
                    // Inizializza DataTable per squadre
                    initializeSquadreDataTable(data.data.processedData);
                } else {
                    $(targetContent).find('#tabella-squadre-container').hide();
                    $(targetContent).find('#tabella-individuali-container').show();
                    
                    // Inizializza DataTable per individuali
                    initializeIndividualiDataTable(data.data.processedData);
                }
                
                // Riaggiungi l'event listener per il toggle della legenda
                $(targetContent).find('.crm-gare-dettaglio-legenda-toggle').off('click').on('click', function() {
                    $(this).toggleClass('active');
                    $(targetContent).find('.crm-gare-dettaglio-legenda-content').slideToggle(300);
                });
                
                // Segna il tab come caricato
                tabsLoaded['lista-iscritti'] = true;
            } else {
                // Mostra messaggio di errore
                $(targetContent).find('#tabella-individuali-container, #tabella-squadre-container')
                               .html('<div style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore nel caricamento della lista iscritti: ' + (data.data.message || 'Errore sconosciuto') + '</div>');
            }
        })
        .catch(function(error) {
            $(targetContent).find('#tabella-individuali-container, #tabella-squadre-container')
                           .html('<div style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore di connessione durante il caricamento della lista iscritti</div>');
        })
        .finally(function() {
            hideLoading();
        });
    }

    // Carica i dati del report HCP via AJAX
    function loadVariazioniHcp(targetContent) {
        var competitionId = $('#competition-id').val();
        
        const formData = new FormData();
        formData.append('action', 'competition-hcp-report');
        formData.append('competition_id', competitionId);

        // Mostra lo spinner
        showLoading();

        // Endpoint AJAX
        let ajaxEndpoint = ajaxurl;
        if (ajaxEndpoint.indexOf('http') !== 0 && ajaxEndpoint.indexOf('//') !== 0) {
            ajaxEndpoint = window.location.origin + ajaxEndpoint;
        }

        fetch(ajaxEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {

                // Aggiorna le informazioni del club
                $(targetContent).find('.crm-gare-variazioni-hcp-content-club-name').text(data.data.clubName);
                
                // Gestisci i dati PCC dinamicamente
                if (data.data.pccData) {
                    const { pcc, giri_totali } = data.data.pccData;
                    
                    // Mostra il totale giri se presente un elemento dedicato
                    const giriTotaliElement = $(targetContent).find('.crm-gare-variazioni-hcp-content-giri-totali');
                    if (giriTotaliElement.length > 0) {
                        giriTotaliElement.text(giri_totali);
                    }
                    
                    // Costruisci la stringa PCC dinamicamente
                    let pccText = 'PCC: ';
                    const pccValues = [];
                    
                    // Itera sui giri disponibili
                    Object.keys(pcc).forEach((giro) => {
                        const pccValue = pcc[giro];
                        const giroLabel = `Giro ${giro}`;
                        pccValues.push(`${giroLabel} = ${pccValue}`);
                    });
                    
                    pccText += pccValues.join(', ');
                    
                    // Aggiorna l'elemento PCC con la stringa completa
                    const pccElement = $(targetContent).find('.crm-gare-variazioni-hcp-content-pcc');
                    if (pccElement.length > 0) {
                        pccElement.text(pccText);
                    } else {
                        // Fallback: cerca ancora i vecchi selettori per compatibilità
                        $(targetContent).find('.crm-gare-variazioni-hcp-content-pcc-r1').text(pcc['1'] || '0');
                        if (giri_totali > 1 && pcc['2'] !== undefined) {
                            $(targetContent).find('.crm-gare-variazioni-hcp-content-pcc-r2').text(pcc['2']);
                            $(targetContent).find('.crm-gare-variazioni-hcp-content-pcc-r2-container').show();
                        }
                    }
                }
                
                // Inizializza DataTable con i dati
                initializeHcpDataTable(data.data.processedData);

                // Segna il tab come caricato
                tabsLoaded['variazioni-hcp'] = true;
            } else {
                $(targetContent).find('.crm-gare-variazioni-hcp-content')
                               .html('<div style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore nel caricamento del report HCP: ' + (data.data.message || 'Errore sconosciuto') + '</div>');
            }
        })
        .catch(function(error) {
            $(targetContent).find('.crm-gare-variazioni-hcp-content')
                           .html('<div style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore di connessione durante il caricamento del report HCP</div>');
        })
        .finally(function() {
            hideLoading();
        });
    }

    // Carica i dati degli orari di partenza via AJAX
    function loadOrariPartenze(targetContent) {
        var competitionId = $('#competition-id').val();
        
        const formData = new FormData();
        formData.append('action', 'competition-orari-partenze');
        formData.append('competition_id', competitionId);

        // Mostra lo spinner
        showLoading();

        // Endpoint AJAX
        let ajaxEndpoint = ajaxurl;
        if (ajaxEndpoint.indexOf('http') !== 0 && ajaxEndpoint.indexOf('//') !== 0) {
            ajaxEndpoint = window.location.origin + ajaxEndpoint;
        }

        fetch(ajaxEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Popola la tabella con i dati ricevuti
                populateOrariPartenzeTable(data.data.processedData, targetContent);
                
                // Segna il tab come caricato
                tabsLoaded['orari-partenze'] = true;
            } else {
                $(targetContent).find('#tabella-orario')
                               .html('<tr><td colspan="2" style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore nel caricamento degli orari di partenza: ' + (data.data.message || 'Errore sconosciuto') + '</td></tr>');
            }
        })
        .catch(function(error) {
            $(targetContent).find('#tabella-orario')
                           .html('<tr><td colspan="2" style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore di connessione durante il caricamento degli orari di partenza</td></tr>');
        })
        .finally(function() {
            hideLoading();
        });
    }

    // Carica i dati delle classifiche via AJAX
    function loadClassifiche(targetContent) {
        var competitionId = $('#competition-id').val();
        
        const formData = new FormData();
        formData.append('action', 'competition-classifiche');
        formData.append('competition_id', competitionId);

        // Mostra lo spinner
        showLoading();

        // Endpoint AJAX
        let ajaxEndpoint = ajaxurl;
        if (ajaxEndpoint.indexOf('http') !== 0 && ajaxEndpoint.indexOf('//') !== 0) {
            ajaxEndpoint = window.location.origin + ajaxEndpoint;
        }

        fetch(ajaxEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Popola la tabella con i dati ricevuti
                populateClassificheTable(data.data.processedData, targetContent);
                
                // Segna il tab come caricato
                tabsLoaded['classifiche'] = true;
            } else {
                $(targetContent).find('#tabella-classifica')
                               .html('<tr><td colspan="2" style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore nel caricamento delle classifiche: ' + (data.data.message || 'Errore sconosciuto') + '</td></tr>');
            }
        })
        .catch(function(error) {
            $(targetContent).find('#tabella-classifica')
                           .html('<tr><td colspan="2" style="padding: 20px; text-align: center; color: #d32f2f;"><i class="fas fa-exclamation-triangle"></i> Errore di connessione durante il caricamento delle classifiche</td></tr>');
        })
        .finally(function() {
            hideLoading();
        });
    }

    // Inizializza DataTable per gare individuali
    function initializeIndividualiDataTable(data) {
        var table = $('#crm-odm-dettaglio-lista-iscritti');
        
        // Distruggi la tabella esistente se presente
        if ($.fn.DataTable.isDataTable(table)) {
            table.DataTable().destroy();
        }
        
        // Pulisci il corpo della tabella
        table.find('tbody').empty();
        
        // Inizializza DataTable con i dati
        table.DataTable({
            data: data,
            paging: false,
            scrollX: true,
            scrollY: '',
            scrollCollapse: false,
            responsive: false,
            autoWidth: false,
            searching: false,
            info: false,
            columnDefs: [
                { orderable: true, targets: [0, 1] },
                { orderable: false, targets: '_all' },
                { width: '50px', targets: 0 }
            ],
            language: {
                emptyTable: "Nessun iscritto trovato",
                zeroRecords: "Nessuna corrispondenza trovata"
            }
        });
    }

    // Inizializza DataTable per gare a squadre
    function initializeSquadreDataTable(data) {
        var table = $('#crm-odm-dettaglio-lista-iscritti-squadre');
        
        // Distruggi la tabella esistente se presente
        if ($.fn.DataTable.isDataTable(table)) {
            table.DataTable().destroy();
        }
        
        // Pulisci il corpo della tabella
        table.find('tbody').empty();
        
        // Inizializza DataTable con i dati
        table.DataTable({
            data: data,
            paging: false,
            scrollX: true,
            scrollY: '',
            scrollCollapse: false,
            responsive: false,
            autoWidth: false,
            searching: false,
            info: false,
            ordering: false,
            columnDefs: [
            { orderable: false, targets: '_all' }
            ],
            language: {
            emptyTable: "Nessun iscritto trovato",
            zeroRecords: "Nessuna corrispondenza trovata"
            },
            rowCallback: function(row, rowData, dataIndex) {
                //
                if (rowData[0]) {
                    $(row).addClass('crm-squadre-team-separator');
                }
            }
        });
    }

    // Funzione per aggiungere l'event listener alla tabella dei risultati
    function addClickListenerToTable() {
        // Rimuovi eventuali listener esistenti per evitare duplicati
        $('#crm-odm-dettaglio-dettaglio-risultati tbody').off('click', 'tr');
        
        // Aggiungi l'event listener per il click sulle righe della tabella
        $('#crm-odm-dettaglio-dettaglio-risultati tbody').on('click', 'tr', function() {
            var table = $('#crm-odm-dettaglio-dettaglio-risultati').DataTable();
            var rowData = table.row(this).data();
            var giro = $('#giro-classifica').val() || '1'; // Default a giro 1 se non specificato
            
            if (rowData) {
                // Cerca l'indice della colonna ID (dovrebbe essere l'ultima colonna nascosta)
                var idIndex = rowData.length - 1; // Assumiamo che l'ID sia sempre l'ultima colonna
                
                // Crea un oggetto con i dati della riga per il modal
                var playerData = {
                    posizione: rowData[0] || '',
                    nome: rowData[1] || '',
                    cat: rowData[2] || '',
                    qual: rowData[3] || '',
                    odm: rowData[4] || '',
                    hcp: rowData[5] || '',
                    tot: rowData[6] || '',
                    par: rowData[7] || '',
                    id: rowData[idIndex] || '',
                    giro: giro
                };
                
                // Carica il dettaglio della classifica
                caricaDettaglioClassifica(playerData);
            }
        });
    }

    // Inizializza DataTables per la lista iscritti (ora gestita da funzioni specifiche)
    function initializeDataTables(containerSelector) {
        // Questa funzione è ora obsoleta per la lista iscritti
        // Le tabelle vengono inizializzate direttamente in loadListaIscritti()
        // Manteniamo la funzione per compatibilità con altri tab
    }

    // Inizializza DataTable per variazioni HCP
    function initializeHcpDataTable(data) {
        var table = $('#crm-odm-dettaglio-variazioni-hcp');
        
        try {
            // Distruggi la tabella esistente se presente
            if ($.fn.DataTable.isDataTable(table)) {
                table.DataTable().destroy();
            }
            
            // Pulisci il corpo della tabella
            table.find('tbody').empty();
            
            // Inizializza DataTable con i dati
            table.DataTable({
                data: data || [],
                paging: false,
                scrollX: true,
                scrollY: '',
                scrollCollapse: false,
                responsive: false,
                autoWidth: false,
                language: {
                    emptyTable: "Nessun dato disponibile per il report HCP",
                },
                rowGroup: {
                    startRender: function (rows, group) {
                        return 'Giocatore: ' + group;
                    },
                    dataSrc: function (row) {
                        return row[1] + ' ' + row[0]; // cognome + nome
                    }
                },
                // Ordinamento iniziale: prima per cognome, poi per nome, infine per data (dal più vecchio al più recente)
                order: [
                    [1, 'asc'],  // cognome
                    [0, 'asc'],  // nome
                    [2, 'asc']   // data (indice 2, formato dd/mm/yyyy)
                ],
                columnDefs: [
                    {
                        // Configurazione per ordinamento corretto delle date in formato dd/mm/yyyy
                        targets: 2,
                        type: 'date-eu'
                    },
                    {
                        // Disabilita completamente l'ordinamento UI
                        targets: '_all',
                        orderable: false,
                        sortable: false
                    }
                ],
                // Disabilita completamente l'interfaccia di ordinamento
                headerCallback: function(thead, data, start, end, display) {
                    $(thead).find('th')
                        .removeClass('sorting sorting_asc sorting_desc dt-orderable-asc dt-orderable-desc')
                        .addClass('dt-orderable-none')
                        .find('.dt-column-order').remove();
                },
                initComplete: function() {
                    $('.dt-orderable-none').removeClass('dt-ordering-asc dt-ordering-desc');
                }
            });
            
        } catch(err) {
            hideLoading();
        }
    }

    // Popola la tabella degli orari di partenza
    function populateOrariPartenzeTable(orariData, targetContent) {
        var tableBody = $(targetContent).find('#tabella-orario');
        tableBody.empty();
        
        if (orariData && orariData.length > 0) {
            orariData.forEach(function(attachment) {
                var row = '<tr>' +
                    '<td>Giro ' + attachment.giro + '</td>' +
                    '<td><a href="#orari-partenze" data-attachment-id="' + attachment.id + '">' + attachment.nome + '</a></td>' +
                    '</tr>';
                tableBody.append(row);
            });
        } else {
            tableBody.html('<tr><td colspan="2">Nessun documento Orario Partenze disponibile</td></tr>');
        }
    }

    // Popola la tabella delle classifiche
    function populateClassificheTable(classificheData, targetContent) {
        var tableBody = $(targetContent).find('#tabella-classifica');
        tableBody.empty();
        
        if (classificheData && classificheData.length > 0) {
            classificheData.forEach(function(attachment) {
                var row = '<tr>' +
                    '<td>Classifica ' + attachment.giro + '</td>' +
                    '<td><a href="#classifiche" data-attachment-id="' + attachment.id + '">' + attachment.nome + '</a></td>' +
                    '</tr>';
                tableBody.append(row);
            });
        } else {
            tableBody.html('<tr><td colspan="2">Nessun documento Classifiche disponibile</td></tr>');
        }
    }

    // Gestisci i click sulle schede
    $('.crm-gare-dettaglio-tab a').click(function(e) {
        e.preventDefault();
        var hash = $(this).attr('href');
        window.handleTabChange(hash);
    });

    // Gestisci i cambiamenti nell'hash dell'URL (es. tasti avanti/indietro browser)
    $(window).on('hashchange', function() {
        var currentHash = window.location.hash ? window.location.hash : '#info';
        if (!['#info', '#lista-iscritti', '#orari-partenze', '#classifiche', '#dettagli', '#variazioni-hcp'].includes(currentHash)) {
            currentHash = '#info';
        }
        window.handleTabChange(currentHash);
    });

    // --- Gestione Filtri Tab Dettagli ---
    // Gestisce filtro per giro nella scheda risultati
    $('#giro-classifica').on('change', function() {
        var giro = $(this).val();
        const tipo = $('#tipologia-classifica').val();
        aggiornaClassifica($('#competition-id').val(), tipo, giro);
    });

    // Gestisce filtro per tipo classifica nella scheda risultati
    $('#tipologia-classifica').on('change', function() {
        var tipo = $(this).val();
        const giro = $('#giro-classifica').val();
        aggiornaClassifica($('#competition-id').val(), tipo, giro);
    });

    // Recupera dettagli classifiche con AJAX
    function aggiornaClassifica(competitionId, tipo, giro, targetContent) {
        const formData = new FormData();

        formData.append('action', 'competition-details-classifica');
        formData.append('competition_id', competitionId);
        formData.append('tipologia_classifica', tipo);
        formData.append('giro', giro);

        var table = $('#crm-odm-dettaglio-dettaglio-risultati').DataTable();

        // Usa sempre lo spinner globale per comportamento coerente
        showLoading();
        
        let ajaxEndpoint = ajaxurl;
        if (ajaxEndpoint.indexOf('http') !== 0 && ajaxEndpoint.indexOf('//') !== 0) {
            ajaxEndpoint = window.location.origin + ajaxEndpoint;
        }

        fetch(ajaxEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            
            // Verifica se la risposta è di successo
            if (!data.success) {
                // Il server ha restituito un errore
                var errorMessage = data.data && data.data.message ? data.data.message : 'Errore sconosciuto nel caricamento dei dati';
                
                // Se la tabella esiste già, distruggila per riconfigurare
                if ($.fn.DataTable.isDataTable('#crm-odm-dettaglio-dettaglio-risultati')) {
                    table.destroy();
                }
                
                // Pulisci l'header della tabella
                var headerRow = $('#crm-odm-dettaglio-dettaglio-risultati thead tr');
                headerRow.empty();
                headerRow.append('<th>Errore</th>');
                
                // Pulisci il corpo della tabella
                var tableBody = $('#crm-odm-dettaglio-dettaglio-risultati tbody');
                tableBody.empty();
                
                // Reinizializza la DataTable vuota con messaggio di errore
                table = $('#crm-odm-dettaglio-dettaglio-risultati').DataTable({
                    paging: false,
                    scrollX: true,
                    scrollY: '',
                    scrollCollapse: false,
                    responsive: false,
                    autoWidth: false,
                    language: {
                        emptyTable: errorMessage
                    }
                });
                
                // Nascondi lo spinner
                hideLoading();
                return; // Esce dalla funzione senza elaborare ulteriormente
            }
            
            // Pulisci la tabella prima di aggiungere nuove righe
            table.clear();
            
            // Ottieni i dati e gli headers dalla risposta
            var allResults = data.data.results.items;
            var headers = data.data.results.header || [];
            
            // Se la tabella esiste già, distruggila per riconfigurare le colonne
            if ($.fn.DataTable.isDataTable('#crm-odm-dettaglio-dettaglio-risultati')) {
                table.destroy();
            }
            
            // Pulisci l'header della tabella
            var headerRow = $('#crm-odm-dettaglio-dettaglio-risultati thead tr');
            headerRow.empty();
            
            // Costruisci le colonne dinamicamente in base agli headers ricevuti
            var columnDefs = [];
            var columns = [];
            
            // Aggiungi le colonne basate sugli headers
            if (headers && headers.length > 0) {
                headers.forEach(function(header, index) {
                    // Aggiungi la colonna all'intestazione HTML
                    headerRow.append('<th>' + header + '</th>');
                    
                    // Configura le definizioni di colonna per DataTables
                    columns.push({ title: header });
                    
                    // Nascondi la colonna ID se presente
                    if (header.toLowerCase() === 'id') {
                        columnDefs.push({ targets: index, visible: false });
                    }
                });
            } else {
                // Fallback agli header predefiniti se non ne riceviamo dal server
                headerRow.append(
                    '<th>Pos.</th>' +
                    '<th>Nome</th>' +
                    '<th>Cat.</th>' +
                    '<th>Qual.</th>' +
                    '<th>Odm</th>' +
                    '<th>Hcp</th>' +
                    '<th>TOT</th>' +
                    '<th>PAR</th>' +
                    '<th>ID</th>'
                );
                
                columnDefs.push({ targets: 8, visible: false }); // Nascondi colonna ID
            }
            
            // Reinizializza la DataTable con le nuove colonne
            table = $('#crm-odm-dettaglio-dettaglio-risultati').DataTable({
                paging: false,
                scrollX: true, 
                scrollY: '',
                scrollCollapse: false,
                responsive: false,
                autoWidth: false,
                columnDefs: columnDefs,
                language: {
                    emptyTable: "Nessun risultato disponibile per la selezione corrente"
                }
            });
            
            // Aggiungi le righe alla tabella
            if (allResults && allResults.length > 0) {
                allResults.forEach(function(result) {
                    // Crea un array di valori nell'ordine delle colonne
                    var rowData = [];
                    if (headers && headers.length > 0) {
                        // Usa i nomi degli header come chiavi per mappare l'ordine corretto
                        headers.forEach(function(header) {
                            // Converti il nome dell'header in una chiave in lowercase per il matching
                            var key = header.toLowerCase();
                            // Cerca nella proprietà result usando la chiave normalizzata
                            var found = false;
                            Object.keys(result).forEach(function(resultKey) {
                                if (resultKey.toLowerCase() === key) {
                                    rowData.push(result[resultKey]);
                                    found = true;
                                }
                            });
                            if (!found) {
                                rowData.push(''); // Se non c'è corrispondenza, inserisci un valore vuoto
                            }
                        });
                    } else {
                        // Fallback all'ordine fisso predefinito se non ci sono headers
                        rowData = [
                            result.posizione || '',
                            result.nome || '',
                            result.cat || '',
                            result.qual || '',
                            result.odm || '',
                            result.hcp || '',
                            result.tot || '',
                            result.par || '',
                            result.id || ''
                        ];
                    }
                    table.row.add(rowData).node();
                });
            }
            
            // Ridisegna la tabella per mostrare i nuovi dati
            table.draw();
            
            // Aggiungi l'event listener per il click sulle righe della tabella
            addClickListenerToTable();
            
            // Nascondi sempre lo spinner globale
            hideLoading();
        })
        .catch(function(error) {
            // In caso di errore di rete o parsing, mostra un messaggio di errore
            
            // Se la tabella esiste già, distruggila per riconfigurare
            if ($.fn.DataTable.isDataTable('#crm-odm-dettaglio-dettaglio-risultati')) {
                table.destroy();
            }
            
            // Pulisci l'header della tabella
            var headerRow = $('#crm-odm-dettaglio-dettaglio-risultati thead tr');
            headerRow.empty();
            headerRow.append('<th>Errore</th>');
            
            // Pulisci il corpo della tabella
            var tableBody = $('#crm-odm-dettaglio-dettaglio-risultati tbody');
            tableBody.empty();
            
            // Reinizializza la DataTable vuota con messaggio di errore
            table = $('#crm-odm-dettaglio-dettaglio-risultati').DataTable({
                paging: false,
                scrollX: true,
                scrollY: '',
                scrollCollapse: false,
                responsive: false,
                autoWidth: false,
                language: {
                    emptyTable: "Errore di connessione durante il caricamento dei dati della classifica"
                }
            });
            
            // Nascondi sempre lo spinner globale
            hideLoading();
        });
    }

    // Carica i dati dettaglio-classifica - resa globale per essere accessibile anche dal template
    window.caricaDettaglioClassifica = function(playerData) {
        // Recupera il modal e il suo body
        const modal = document.getElementById('dettaglio-classifica');
        const modalBody = modal.querySelector('.scorecard-modal-body');
        const modalTitle = modal.querySelector('.scorecard-modal-title');
        
        // Aggiorna il titolo con il nome del giocatore
        modalTitle.textContent = 'Dettaglio Risultato: ' + playerData.nome;
        
        // Mostra lo spinner mentre carica i dati
        showLoading();
        
        // Prepara i parametri per la chiamata AJAX
        const formData = new FormData();
        formData.append('action', 'competition-details-classifica-giri');
        formData.append('competition_id', $('#competition-id').val());
        formData.append('player_id', playerData.id || '0');
        formData.append('giro', playerData.giro || '1');
        
        // Esegui la chiamata AJAX per ottenere i dati dal backend
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            // Utilizza i dati ricevuti dal backend
            const scorecardData = data.data || {
                "giri": [],
                "par": [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                "mt": [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            };
            
            // Aggiorna la tabella scorecard
            const scorecardTable = document.querySelector('.scorecard-table tbody');
            
            // Pulisci la tabella esistente
            scorecardTable.innerHTML = '';
            
            // Aggiungi le righe per i giri (G1, G2, ecc.)
            scorecardData.giri.forEach(giro => {
                const row = document.createElement('tr');
                
                // Cella con nome del giro
                const giroCell = document.createElement('td');
                giroCell.className = 'scorecard-cell scorecard-giro-col';
                giroCell.textContent = giro.nome;
                row.appendChild(giroCell);
                
                // Celle con i valori del giro
                giro.valori.forEach((item, index) => {
                    const cell = document.createElement('td');
                    cell.className = 'scorecard-cell';
                    
                    // Aggiungi classe per totali (Out, In, Tot, To Par)
                    if (index === 9 || index === 19 || index === 20 || index === 21) {
                        cell.classList.add('scorecard-totals-col');
                    }
                    
                    // Applica lo stile in base al tipo
                    if (item.tipo && item.tipo !== '') {
                        // Mappa i tipi alle rispettive classi CSS
                        const classiTipo = {
                            'eagles': 'scorecard-btn-yellow',   
                            'better': 'scorecard-btn-yellow',
                            'birdies': 'scorecard-btn-red',
                            'par': 'scorecard-btn-grey',
                            'bogeys': 'scorecard-btn-blue',
                            'worse': 'scorecard-btn-black'
                        };
                        
                        // Se esiste una classe per questo tipo, applicala
                        if (classiTipo[item.tipo]) {
                            // Estrai il colore dalla classe (scorecard-btn-COLOR)
                            const colorClass = classiTipo[item.tipo];
                            
                            // Crea un contenitore circolare colorato per il valore
                            cell.innerHTML = `<div style="display: inline-block; background-color: ${getBgColorFromClass(colorClass)}; color: ${getTextColorFromClass(colorClass)}; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center;">${item.valore}</div>`;
                        } else {
                            cell.textContent = item.valore;
                        }
                    } else {
                        cell.textContent = item.valore;
                    }
                    
                    row.appendChild(cell);
                });
                
                scorecardTable.appendChild(row);
            });
            
            // Funzione helper per ricavare il colore di sfondo in base alla classe
            function getBgColorFromClass(className) {
                switch(className) {
                    case 'scorecard-btn-yellow': return '#ffeb3b'; // giallo
                    case 'scorecard-btn-red': return '#ff5722';    // rosso
                    case 'scorecard-btn-grey': return '#9e9e9e';   // grigio
                    case 'scorecard-btn-blue': return '#1a4e94';   // blu
                    case 'scorecard-btn-black': return '#333';     // nero
                    default: return 'transparent';
                }
            }

            function getTextColorFromClass(className) {
                switch(className) {
                    case 'scorecard-btn-yellow': return '#000000'; // giallo
                    case 'scorecard-btn-red': return '#ffffff';    // rosso
                    case 'scorecard-btn-grey': return '#ffffff';   // grigio
                    case 'scorecard-btn-blue': return '#ffffff';   // blu
                    case 'scorecard-btn-black': return '#ffffff';  // nero
                    default: return '#000000';
                }
            }
            
            // Aggiungi riga PAR
            const parRow = document.createElement('tr');
            const parLabelCell = document.createElement('td');
            parLabelCell.className = 'scorecard-cell scorecard-giro-col';
            parLabelCell.textContent = 'PAR';
            parRow.appendChild(parLabelCell);
            
            scorecardData.par.forEach((valore, index) => {
                const cell = document.createElement('td');
                cell.className = 'scorecard-cell';
                
                // Aggiungi classe per totali
                if (index === 9 || index === 19 || index === 20) {
                    cell.classList.add('scorecard-totals-col');
                }
                
                cell.textContent = valore;
                parRow.appendChild(cell);
            });
            
            scorecardTable.appendChild(parRow);
            
            // Aggiungi riga MT
            const mtRow = document.createElement('tr');
            const mtLabelCell = document.createElement('td');
            mtLabelCell.className = 'scorecard-cell scorecard-giro-col';
            mtLabelCell.textContent = 'MT';
            mtRow.appendChild(mtLabelCell);
            
            scorecardData.mt.forEach((valore, index) => {
                const cell = document.createElement('td');
                cell.className = 'scorecard-cell';
                
                // Aggiungi classe per totali
                if (index === 9 || index === 19 || index === 20) {
                    cell.classList.add('scorecard-totals-col');
                }
                
                cell.textContent = valore;
                mtRow.appendChild(cell);
            });
            
            scorecardTable.appendChild(mtRow);
            
            // Nascondi lo spinner dopo aver caricato i dati
            hideLoading();
            
            // Apri il modal
            window.openModal('dettaglio-classifica');
        })
        .catch(function(error) {
            // In caso di errore, mostra un messaggio all'utente
            const errorDiv = document.createElement('div');
            errorDiv.style.color = 'red';
            errorDiv.style.padding = '10px';
            errorDiv.style.marginBottom = '15px';
            errorDiv.textContent = 'Si è verificato un errore nel caricamento dei dati della scorecard.';
            
            // Aggiungi l'errore all'inizio del modal body
            modalBody.insertBefore(errorDiv, modalBody.firstChild);
            
            // Nascondi lo spinner
            hideLoading();
            
            // Apri comunque il modal (anche se con un messaggio di errore)
            window.openModal('dettaglio-classifica');
        });
    }

    // Gestione click su file orari partenze
    $('#tabella-orario').on('click', 'tr', function() {
        showLoading();
        var attachmentId = $(this).find('td:nth-child(2) a').data('attachment-id');
        
        // Parametri per la richiesta AJAX
        const params = {
            'action': 'download-allegato-gara',
            'allegato_id': attachmentId
        };

        // Richiesta AJAX per download file
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: params,
            dataType: 'json',
            success: function(response) {
                hideLoading();
                
                if (response.success && response.data) {
                    const fileData = response.data;
                    
                    if (fileData.file_content && fileData.file_name) {
                        // Converte il contenuto base64 in Blob
                        const byteCharacters = atob(fileData.file_content);
                        const byteNumbers = new Array(byteCharacters.length);
                        for (let i = 0; i < byteCharacters.length; i++) {
                            byteNumbers[i] = byteCharacters.charCodeAt(i);
                        }
                        const byteArray = new Uint8Array(byteNumbers);
                        const blob = new Blob([byteArray], { type: fileData.mime_type || 'application/pdf' });
                        
                        // Crea un URL oggetto per il blob
                        const blobUrl = window.URL.createObjectURL(blob);
                        
                        // Crea un link temporaneo e simula il click per il download
                        const link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = fileData.file_name || 'allegato.pdf';
                        link.style.display = 'none';
                        document.body.appendChild(link);
                        link.click();
                        
                        // Rimuovi il link e il blob URL dopo un breve ritardo
                        setTimeout(function() {
                            document.body.removeChild(link);
                            window.URL.revokeObjectURL(blobUrl);
                        }, 100);
                    } else {
                        alert("Si è verificato un errore durante la generazione dell'allegato: dati del file mancanti.");
                    }
                } else {
                    alert("Si è verificato un errore durante la generazione del file. Riprova più tardi.");
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                alert('Si è verificato un errore durante la richiesta: ' + error);
            }
        });
    });
    
    // Gestione click su file classifiche
    $('#tabella-classifica').on('click', 'tr', function() {
        showLoading();
        var attachmentId = $(this).find('td:nth-child(2) a').data('attachment-id');
        
        // Parametri per la richiesta AJAX
        const params = {
            'action': 'download-allegato-gara',
            'allegato_id': attachmentId
        };

        // Richiesta AJAX per download file
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: params,
            dataType: 'json',
            success: function(response) {
                hideLoading();
                
                if (response.success && response.data) {
                    const fileData = response.data;
                    
                    if (fileData.file_content && fileData.file_name) {
                        // Converte il contenuto base64 in Blob
                        const byteCharacters = atob(fileData.file_content);
                        const byteNumbers = new Array(byteCharacters.length);
                        for (let i = 0; i < byteCharacters.length; i++) {
                            byteNumbers[i] = byteCharacters.charCodeAt(i);
                        }
                        const byteArray = new Uint8Array(byteNumbers);
                        const blob = new Blob([byteArray], { type: fileData.mime_type || 'application/pdf' });
                        
                        // Crea un URL oggetto per il blob
                        const blobUrl = window.URL.createObjectURL(blob);
                        
                        // Crea un link temporaneo e simula il click per il download
                        const link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = fileData.file_name || 'allegato.pdf';
                        link.style.display = 'none';
                        document.body.appendChild(link);
                        link.click();
                        
                        // Rimuovi il link e il blob URL dopo un breve ritardo
                        setTimeout(function() {
                            document.body.removeChild(link);
                            window.URL.revokeObjectURL(blobUrl);
                        }, 100);
                    } else {
                        alert("Si è verificato un errore durante la generazione dell'allegato: dati del file mancanti.");
                    }
                } else {
                    alert("Si è verificato un errore durante la generazione del file. Riprova più tardi.");
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                alert('Si è verificato un errore durante la richiesta: ' + error);
            }
        });
    });

    // Funzioni per il modal - definite globalmente per essere accessibili anche da script inline
    window.openModal = function(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    };
    
    window.closeModal = function(modalId) {
        document.getElementById(modalId).style.display = 'none';
    };

    // Gestori eventi per chiudere il modal
    $('.scorecard-modal-close, .scorecard-modal-close-btn').on('click', function() {
        window.closeModal('dettaglio-classifica');
    });
    
    // Chiudi modal se si clicca fuori dal contenitore
    $('#dettaglio-classifica').on('click', function(e) {
        if (e.target === this) {
            window.closeModal('dettaglio-classifica');
        }
    });

    // Chiudi modal con tasto ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#dettaglio-classifica').css('display') === 'flex') {
            window.closeModal('dettaglio-classifica');
        }
    });
});
