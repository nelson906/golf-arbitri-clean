@extends('layouts.app')

@section('title', 'Simulatore Tempi di Partenza')

@push('styles')
<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<!-- jQuery UI Theme -->
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<style>
    /* Custom styles to integrate jQuery UI with Tailwind */
    .ui-datepicker {
        @apply bg-white shadow-lg rounded-lg p-4;
    }
    .ui-datepicker-header {
        @apply bg-indigo-600 text-white rounded-t-lg p-2 mb-2;
    }
    .ui-datepicker-title {
        @apply text-center font-semibold;
    }
    .ui-datepicker-calendar th {
        @apply text-gray-600 font-medium text-sm;
    }
    .ui-datepicker-calendar td a {
        @apply text-gray-700 hover:bg-indigo-100 rounded p-1;
    }
    .ui-datepicker-current-day a {
        @apply bg-indigo-600 text-white;
    }
</style>
@endpush

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-3xl font-semibold text-gray-900">
                <i class="fas fa-clock mr-2"></i> Simulatore Tempi di Partenza (Quadranti)
            </h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Configuration Panel -->
            <div class="lg:col-span-1">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-indigo-600 px-4 py-3">
                        <h5 class="text-lg font-medium text-white"><i class="fas fa-cog mr-2"></i> Configurazione</h5>
                    </div>
                    <div class="p-6 space-y-4">
                        <!-- Date Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Data Gara:</label>
                            <input type="text" id="start" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 datepicker" value="{{ date('d/m/Y') }}">
                        </div>

                        <!-- Geographic Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Area Geografica:</label>
                            <select id="geo_area" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="NORD OVEST">Nord Ovest</option>
                                <option value="NORD">Nord</option>
                                <option value="NORD EST">Nord Est</option>
                                <option value="CENTRO">Centro</option>
                                <option value="CENTRO SUD">Centro Sud</option>
                                <option value="SUD EST">Sud Est</option>
                                <option value="SUD OVEST">Sud Ovest</option>
                                <option value="SARDEGNA">Sardegna</option>
                            </select>
                        </div>

                        <!-- Ephemeris Data -->
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <span>Alba: <span id="sunrise" class="font-bold">--:--</span></span>
                            </div>
                            <div>
                                <span>Tramonto: <span id="sunset" class="font-bold">--:--</span></span>
                            </div>
                        </div>

                        <!-- Competition Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo Gara:</label>
                            <select id="gara_NT" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="Gara 54 buche">Gara 54 buche</option>
                                <option value="Gara 36 buche">Gara 36 buche</option>
                            </select>
                        </div>

                        <!-- Round -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giornata:</label>
                            <select id="giornata" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="prima">Prima Giornata</option>
                                <option value="seconda">Seconda Giornata</option>
                            </select>
                        </div>

                        <!-- Players Configuration -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giocatori Uomini:</label>
                            <input type="number" id="players" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="144" min="0" max="200">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giocatrici Donne:</label>
                            <input type="number" id="proette" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="48" min="0" max="100">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giocatori per Flight:</label>
                            <select id="players_x_flight" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>

                        <!-- Tee Configuration -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Configurazione Tee:</label>
                            <select id="doppie_partenze" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="Doppie Partenze">Doppie Partenze</option>
                                <option value="Tee Unico">Tee Unico</option>
                            </select>
                        </div>


                        <div class="compatto">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Modalità:</label>
                            <select id="compatto" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="Early/Late">Early/Late</option>
                                <option value="Early(<12)">Early(<12)</option>
                            </select>
                        </div>

                        <!-- Time Configuration -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Orario Prima Partenza:</label>
                            <select id="start_time" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach(['07:00', '07:10', '07:20', '07:30', '07:40', '07:50', '08:00', '08:10', '08:20', '08:30', '08:40', '08:50', '09:00'] as $time)
                                    <option value="{{ $time }}" {{ $time == '08:00' ? 'selected' : '' }}>{{ $time }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Intervallo tra Partenze:</label>
                            <select id="gap" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach(['00:08', '00:09', '00:10', '00:11', '00:12', '00:13', '00:14', '00:15'] as $gap)
                                    <option value="{{ $gap }}" {{ $gap == '00:10' ? 'selected' : '' }}>{{ $gap }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tempo di Gioco:</label>
                            <select id="round" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach(['04:10', '04:20', '04:30', '04:40', '04:50'] as $round)
                                    <option value="{{ $round }}" {{ $round == '04:30' ? 'selected' : '' }}>{{ $round }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="text-sm text-gray-600">
                            <span>Orario Incrocio: <span id="cross" class="font-bold">--:--</span></span>
                        </div>

                        <!-- Player Names Upload -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Carica Nomi Giocatori (Excel):</label>
                            <form id="upload-form" method="POST" enctype="multipart/form-data" action="{{ route('user.quadranti.upload-excel') }}">
                                @csrf
                                <input type="file" id="file" name="file" class="mb-2 text-sm text-gray-600" accept=".xlsx,.xls,.csv">
                                <button type="submit" id="upload" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-md">
                                    <i class="fas fa-upload mr-2"></i> Carica File
                                </button>
                            </form>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-2 gap-2">
                            <button id="refresh" class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded-md">
                                <i class="fas fa-redo mr-2"></i> Reset
                            </button>
                            <button id="excel" class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-md">
                                <i class="fas fa-file-excel mr-2"></i> Excel
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div id="1">
                                <button id="btnClick" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-md">
                                    <i class="fas fa-user mr-2"></i> Nominativo
                                </button>
                            </div>
                            <div id="2" style="display:none;">
                                <button id="btnClock" class="w-full bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md">
                                    <i class="fas fa-hashtag mr-2"></i> Numerico
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tee Times Table -->
            <div class="lg:col-span-2">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="bg-green-600 px-4 py-3">
                        <h5 class="text-lg font-medium text-white">
                            <i class="fas fa-table mr-2"></i> Tabella Orari di Partenza
                            <span id="titolo_giornata" class="ml-2"></span>
                        </h5>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="first_table">
                                <!-- Table content will be generated by JavaScript -->
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- jQuery UI for datepicker -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

<!-- Table2Excel for export functionality -->
<script src="//cdn.rawgit.com/rainabba/jquery-table2excel/1.1.0/dist/jquery.table2excel.min.js"></script>

<!-- Quadranti Scripts -->
@vite('resources/js/quadranti/quadranti.js')

<script>
    // Initialize Quadranti app when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // The app will initialize itself when the module loads
    });
</script>
@endpush
