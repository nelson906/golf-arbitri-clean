<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Gestione Arbitri Golf</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div class="relative min-h-screen bg-gradient-to-b from-green-50 to-green-100">
            <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen">
                @if (Route::has('login'))
                    <div class="fixed top-0 right-0 px-6 py-4 sm:block z-10">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-green-600 hover:text-green-800 font-semibold">
                                <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-green-600 hover:text-green-800 font-semibold">
                                <i class="fas fa-sign-in-alt mr-1"></i>Accedi
                            </a>
                        @endauth
                    </div>
                @endif

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center pt-8 sm:pt-0">

                        <!-- Header -->
                        <div class="flex items-center justify-center mb-8">
                            <div class="text-6xl text-green-600 mr-4">
                                <i class="fas fa-golf-ball"></i>
                            </div>
                            <div>
                                <h1 class="text-4xl font-bold text-green-700">Dashboard Arbitri Golf</h1>
                                <p class="text-lg text-green-600 mt-2">Sistema di gestione tornei e assegnazioni</p>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="bg-white rounded-xl shadow-lg overflow-hidden w-full max-w-4xl">
                            <div class="p-6 sm:p-8">

                                <!-- Introduzione -->
                                <div class="text-center mb-8">
                                    <h2 class="text-2xl font-bold text-green-700 mb-4">
                                        Benvenuto nel sistema di gestione arbitri
                                    </h2>
                                    <p class="text-gray-700">
                                        Gestione completa di tornei di golf, arbitri e assegnazioni per tutto il territorio nazionale
                                    </p>
                                </div>

                                <!-- Features Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

                                    <!-- Feature 1 -->
                                    <div class="text-center p-4 bg-green-50 rounded-lg">
                                        <div class="text-3xl text-green-600 mb-3">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <h3 class="font-semibold text-green-700 mb-2">Gestione Disponibilità</h3>
                                        <p class="text-sm text-gray-600">
                                            Gli arbitri possono indicare le proprie disponibilità per i tornei
                                        </p>
                                    </div>

                                    <!-- Feature 2 -->
                                    <div class="text-center p-4 bg-green-50 rounded-lg">
                                        <div class="text-3xl text-green-600 mb-3">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <h3 class="font-semibold text-green-700 mb-2">Gestione Tornei</h3>
                                        <p class="text-sm text-gray-600">
                                            Creazione e gestione completa dei tornei di golf
                                        </p>
                                    </div>

                                    <!-- Feature 3 -->
                                    <div class="text-center p-4 bg-green-50 rounded-lg">
                                        <div class="text-3xl text-green-600 mb-3">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h3 class="font-semibold text-green-700 mb-2">Assegnazioni</h3>
                                        <p class="text-sm text-gray-600">
                                            Assegnazione automatica e manuale degli arbitri ai tornei
                                        </p>
                                    </div>

                                    <!-- Feature 4 -->
                                    <div class="text-center p-4 bg-green-50 rounded-lg">
                                        <div class="text-3xl text-green-600 mb-3">
                                            <i class="fas fa-map-marked-alt"></i>
                                        </div>
                                        <h3 class="font-semibold text-green-700 mb-2">Gestione Zone</h3>
                                        <p class="text-sm text-gray-600">
                                            Organizzazione per zone territoriali (SZR1-SZR7, CRC)
                                        </p>
                                    </div>

                                    <!-- Feature 5 -->
                                    <div class="text-center p-4 bg-green-50 rounded-lg">
                                        <div class="text-3xl text-green-600 mb-3">
                                            <i class="fas fa-file-word"></i>
                                        </div>
                                        <h3 class="font-semibold text-green-700 mb-2">Documenti</h3>
                                        <p class="text-sm text-gray-600">
                                            Generazione automatica lettere di convocazione
                                        </p>
                                    </div>

                                    <!-- Feature 6 -->
                                    <div class="text-center p-4 bg-green-50 rounded-lg">
                                        <div class="text-3xl text-green-600 mb-3">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <h3 class="font-semibold text-green-700 mb-2">Report</h3>
                                        <p class="text-sm text-gray-600">
                                            Statistiche e report dettagliati sulle attività
                                        </p>
                                    </div>
                                </div>

                                <!-- Roles Section -->
                                <div class="border-t border-gray-200 pt-6">
                                    <h3 class="text-xl font-semibold mb-4 text-green-700 text-center">
                                        <i class="fas fa-user-shield mr-2"></i>Ruoli nel Sistema
                                    </h3>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                                        <!-- Arbitri -->
                                        <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-user text-blue-600 mr-2"></i>
                                                <h4 class="font-semibold text-blue-700">Arbitri</h4>
                                            </div>
                                            <ul class="text-sm text-blue-600 space-y-1">
                                                <li>• Inserire disponibilità</li>
                                                <li>• Visualizzare assegnazioni</li>
                                                <li>• Consultare storico tornei</li>
                                                <li>• Aggiornare profilo</li>
                                            </ul>
                                        </div>

                                        <!-- Admin -->
                                        <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-user-cog text-yellow-600 mr-2"></i>
                                                <h4 class="font-semibold text-yellow-700">Amministratori</h4>
                                            </div>
                                            <ul class="text-sm text-yellow-600 space-y-1">
                                                <li>• Gestire tornei</li>
                                                <li>• Assegnare arbitri</li>
                                                <li>• Gestire circoli</li>
                                                <li>• Generare documenti</li>
                                            </ul>
                                        </div>

                                        <!-- Super Admin -->
                                        <div class="bg-red-50 border border-red-200 p-4 rounded-lg">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-crown text-red-600 mr-2"></i>
                                                <h4 class="font-semibold text-red-700">Super Admin</h4>
                                            </div>
                                            <ul class="text-sm text-red-600 space-y-1">
                                                <li>• Accesso completo</li>
                                                <li>• Gestire zone</li>
                                                <li>• Configurare sistema</li>
                                                <li>• Gestire utenti</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- CTA Section -->
                                <div class="mt-8 text-center">
                                    @auth
                                        <a href="{{ route('dashboard') }}"
                                           class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition duration-150 ease-in-out">
                                            <i class="fas fa-tachometer-alt mr-2"></i>
                                            Vai alla Dashboard
                                        </a>
                                    @else
                                        <a href="{{ route('login') }}"
                                           class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition duration-150 ease-in-out">
                                            <i class="fas fa-sign-in-alt mr-2"></i>
                                            Accedi al Sistema
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex justify-center mt-8 text-gray-600 text-sm">
                            <p>© {{ date('Y') }} Dashboard Arbitri Golf. Sistema di gestione tornei.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
