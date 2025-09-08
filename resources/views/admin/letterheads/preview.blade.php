<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anteprima: {{ $letterhead->title }}</title>
    <style>
        /* Reset e stili base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: {{ $letterhead->settings['font']['family'] ?? 'Arial' }}, sans-serif;
            font-size: {{ $letterhead->settings['font']['size'] ?? 11 }}pt;
            color: {{ $letterhead->settings['font']['color'] ?? '#000000' }};
            line-height: 1.4;
            background: #f5f5f5;
            padding: 20px;
        }

        /* Contenitore principale - simula foglio A4 */
        .page-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
            padding-top: {{ $letterhead->settings['margins']['top'] ?? 20 }}mm;
            padding-bottom: {{ $letterhead->settings['margins']['bottom'] ?? 20 }}mm;
            padding-left: {{ $letterhead->settings['margins']['left'] ?? 25 }}mm;
            padding-right: {{ $letterhead->settings['margins']['right'] ?? 25 }}mm;
        }

        /* Header */
        .letterhead-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: {{ $letterhead->settings['margins']['top'] ?? 20 }}mm;
            background: white;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 {{ $letterhead->settings['margins']['left'] ?? 25 }}mm;
            z-index: 100;
        }

        .header-content {
            display: flex;
            align-items: center;
            width: 100%;
        }

        .header-logo {
            max-height: {{ ($letterhead->settings['margins']['top'] ?? 20) - 5 }}mm;
            max-width: 50mm;
            margin-right: 15mm;
        }

        .header-text {
            flex: 1;
            text-align: center;
        }

        .header-text h1 {
            font-size: {{ ($letterhead->settings['font']['size'] ?? 11) + 4 }}pt;
            font-weight: bold;
            margin-bottom: 2mm;
            color: {{ $letterhead->settings['font']['color'] ?? '#000000' }};
        }

        .header-text h2 {
            font-size: {{ ($letterhead->settings['font']['size'] ?? 11) + 1 }}pt;
            font-weight: normal;
            color: #666;
        }

        /* Footer */
        .letterhead-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: {{ $letterhead->settings['margins']['bottom'] ?? 20 }}mm;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 {{ $letterhead->settings['margins']['left'] ?? 25 }}mm;
            z-index: 100;
        }

        .footer-content {
            text-align: center;
            font-size: {{ ($letterhead->settings['font']['size'] ?? 11) - 1 }}pt;
            color: #666;
            line-height: 1.3;
        }

        /* Contenuto principale */
        .document-content {
            margin-top: 15mm;
            margin-bottom: 15mm;
        }

        /* Stili per il contenuto del documento */
        .document-header {
            text-align: right;
            margin-bottom: 15mm;
            font-size: {{ ($letterhead->settings['font']['size'] ?? 11) - 1 }}pt;
            color: #666;
        }

        .document-title {
            text-align: center;
            font-weight: bold;
            font-size: {{ ($letterhead->settings['font']['size'] ?? 11) + 2 }}pt;
            margin: 15mm 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .document-body {
            text-align: justify;
            margin-bottom: 15mm;
        }

        .document-body p {
            margin-bottom: 5mm;
        }

        .signature-area {
            margin-top: 20mm;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 60mm;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 2mm;
            height: 15mm;
        }

        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 48pt;
            color: rgba(255, 0, 0, 0.1);
            font-weight: bold;
            z-index: 1;
            pointer-events: none;
        }

        /* Toolbar di controllo */
        .preview-toolbar {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            z-index: 1000;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .preview-toolbar button {
            background: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
        }

        .preview-toolbar button:hover {
            background: #0056b3;
        }

        /* Media query per stampa */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .page-container {
                box-shadow: none;
                margin: 0;
                width: 100%;
                height: 100vh;
            }

            .preview-toolbar {
                display: none;
            }

            .watermark {
                display: none;
            }
        }

        /* Responsive */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }

            .page-container {
                width: 100%;
                min-height: auto;
                transform: scale(0.7);
                transform-origin: top center;
            }

            .preview-toolbar {
                position: static;
                margin-bottom: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Toolbar di controllo -->
    <div class="preview-toolbar">
        <strong>Anteprima: {{ $letterhead->title }}</strong>
        <br>
        <button onclick="window.print()">🖨️ Stampa</button>
        <button onclick="closePreview()">❌ Chiudi</button>
        <button onclick="toggleWatermark()">💧 Watermark</button>
        <button onclick="window.location.href='{{ route('admin.letterheads.edit', $letterhead) }}'">✏️ Modifica</button>
    </div>

    <!-- Watermark -->
    <div id="watermark" class="watermark">ANTEPRIMA</div>

    <!-- Contenitore pagina -->
    <div class="page-container">
        <!-- Header fisso -->
        <div class="letterhead-header">
            <div class="header-content">
                @if($letterhead->logo_path)
                    <img src="{{ $letterhead->logo_url }}" alt="Logo" class="header-logo">
                @endif

                <div class="header-text">
                    @if($letterhead->header_text)
                        @foreach(explode("\n", $letterhead->header_text) as $index => $line)
                            @if($index === 0)
                                <h1>{{ trim($line) }}</h1>
                            @else
                                <h2>{{ trim($line) }}</h2>
                            @endif
                        @endforeach
                    @else
                        <h1>{{ $letterhead->zone?->name ?? 'FEDERAZIONE ITALIANA GOLF' }}</h1>
                        <h2>Commissione Regole e Competizioni</h2>
                    @endif
                </div>
            </div>
        </div>

        <!-- Contenuto del documento -->
        <div class="document-content">
            <!-- Data e luogo -->
            <div class="document-header">
                {{ $letterhead->zone?->name ?? 'Roma' }}, {{ $sampleData['date'] }}
            </div>

            <!-- Titolo del documento -->
            <div class="document-title">
                CONVOCAZIONE UFFICIALE
            </div>

            <!-- Corpo del documento -->
            <div class="document-body">
                <p><strong>Oggetto:</strong> Convocazione per {{ $sampleData['tournament_name'] }}</p>

                <p>Egregio Arbitro <strong>{{ $sampleData['referee_name'] }}</strong>,</p>

                <p>
                    Con la presente siamo a comunicarLe che è stato convocato per arbitrare il torneo
                    <strong>{{ $sampleData['tournament_name'] }}</strong> che si svolgerà in data
                    <strong>{{ $sampleData['tournament_date'] }}</strong> presso il
                    <strong>{{ $sampleData['club_name'] }}</strong>.
                </p>

                <p>
                    La Sua presenza è richiesta con almeno 30 minuti di anticipo rispetto all'orario
                    di inizio delle competizioni per le opportune verifiche preliminari e per ricevere
                    le istruzioni specifiche relative al torneo.
                </p>

                <p>
                    Si prega di confermare la propria partecipazione entro 48 ore dalla ricezione della
                    presente comunicazione utilizzando i consueti canali di comunicazione.
                </p>

                <p>
                    In caso di impedimento improvviso, è pregato di avvisare tempestivamente la segreteria
                    per consentire l'eventuale sostituzione.
                </p>

                <p>Ringraziando per la consueta collaborazione, porgiamo distinti saluti.</p>
            </div>

            <!-- Area firme -->
            <div class="signature-area">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div>Il Responsabile di Zona</div>
                    <div><strong>{{ $sampleData['zone_name'] }}</strong></div>
                </div>

                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div>Il Segretario</div>
                </div>
            </div>

            <!-- Informazioni aggiuntive -->
            <div style="margin-top: 20mm; font-size: {{ ($letterhead->settings['font']['size'] ?? 11) - 1 }}pt; color: #666;">
                <p><strong>Note:</strong></p>
                <ul style="margin-left: 5mm;">
                    <li>Portare con sé il regolamento aggiornato e il materiale di lavoro</li>
                    <li>È obbligatorio l'abbigliamento conforme al dress code federale</li>
                    <li>Per informazioni: {{ $letterhead->contact_info['phone'] ?? 'XXX XXXXXXX' }}</li>
                </ul>
            </div>
        </div>

        <!-- Footer fisso -->
        <div class="letterhead-footer">
            <div class="footer-content">
                @if($letterhead->footer_text)
                    {!! nl2br(e($letterhead->footer_text)) !!}
                @elseif($letterhead->formatted_contact_info)
                    {{ $letterhead->formatted_contact_info }}
                @else
                    {{ $letterhead->zone?->name ?? 'Federazione Italiana Golf' }} |
                    Tel: {{ $letterhead->contact_info['phone'] ?? '+39 06 12345678' }} |
                    Email: {{ $letterhead->contact_info['email'] ?? 'info@figc.it' }}
                @endif
            </div>
        </div>
    </div>
    
    <script>
        // Funzione per chiudere la preview
        function closePreview() {
            // Se è una finestra popup (aperta con target="_blank"), chiudila
            if (window.opener || window.history.length <= 1) {
                window.close();
                // Se window.close() non funziona (alcuni browser lo bloccano)
                setTimeout(function() {
                    if (!window.closed) {
                        // Fallback: torna alla pagina precedente se esiste
                        if (document.referrer && document.referrer.includes('letterheads')) {
                            window.location.href = document.referrer;
                        } else {
                            window.location.href = '{{ route("admin.letterheads.index") }}';
                        }
                    }
                }, 100);
            } else if (document.referrer && document.referrer.includes('letterheads')) {
                // Non è popup, torna indietro
                history.back();
            } else {
                // Fallback: vai alla lista
                window.location.href = '{{ route("admin.letterheads.index") }}';
            }
        }
        
        function toggleWatermark() {
            const watermark = document.getElementById('watermark');
            if (watermark.style.display === 'none') {
                watermark.style.display = 'block';
            } else {
                watermark.style.display = 'none';
            }
        }

        // Auto-chiudi dopo stampa
        window.addEventListener('afterprint', function() {
            // Opzionale: chiudi la finestra dopo la stampa
            // window.close();
        });

        // Gestione tasti rapidi
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        window.print();
                        break;
                    case 'w':
                        e.preventDefault();
                        window.close();
                        break;
                }
            }

            if (e.key === 'Escape') {
                closePreview();
            }
        });

        // Mostra informazioni di debug in console
        console.log('Letterhead Preview Debug Info:', {
            title: '{{ $letterhead->title }}',
            zone: '{{ $letterhead->zone?->name ?? "Globale" }}',
            margins: @json($letterhead->settings['margins'] ?? []),
            font: @json($letterhead->settings['font'] ?? []),
            logo: '{{ $letterhead->logo_path ? "Present" : "Not present" }}',
            sampleData: @json($sampleData)
        });
    </script>
</body>
</html>
