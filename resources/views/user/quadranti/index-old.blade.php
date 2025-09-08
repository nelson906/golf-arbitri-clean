@extends('layouts.app')

@section('title', 'Simulatore Tempi di Partenza')

@push('styles')
<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<!-- jQuery UI Theme -->
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
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
                    <div class="p-6">
                        <!-- Date Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Data Gara:</label>
                            <input type="text" id="start" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 datepicker" value="{{ date('d/m/Y') }}">
                        </div>

                        <!-- Geographic Area -->
                        <div class="mb-4">
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
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Alba: <span id="sunrise" class="font-weight-bold">--:--</span></small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Tramonto: <span id="sunset" class="font-weight-bold">--:--</span></small>
                        </div>
                    </div>

                    <!-- Competition Type -->
                    <div class="form-group">
                        <label>Tipo Gara:</label>
                        <select id="gara_NT" class="form-control">
                            <option value="Gara 54 buche">Gara 54 buche</option>
                            <option value="Gara 36 buche">Gara 36 buche</option>
                        </select>
                    </div>

                    <!-- Round -->
                    <div class="form-group">
                        <label>Giornata:</label>
                        <select id="giornata" class="form-control">
                            <option value="prima">Prima Giornata</option>
                            <option value="seconda">Seconda Giornata</option>
                        </select>
                    </div>

                    <!-- Players Configuration -->
                    <div class="form-group">
                        <label>Giocatori Uomini:</label>
                        <input type="number" id="players" class="form-control" value="144" min="0" max="200">
                    </div>

                    <div class="form-group">
                        <label>Giocatrici Donne:</label>
                        <input type="number" id="proette" class="form-control" value="48" min="0" max="100">
                    </div>

                    <div class="form-group">
                        <label>Giocatori per Flight:</label>
                        <select id="players_x_flight" class="form-control">
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>

                    <!-- Tee Configuration -->
                    <div class="form-group">
                        <label>Configurazione Tee:</label>
                        <select id="doppie_partenze" class="form-control">
                            <option value="Doppie Partenze">Doppie Partenze</option>
                            <option value="Tee Unico">Tee Unico</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Layout:</label>
                        <select id="simmetrico" class="form-control">
                            <option value="Asimmetrico">Asimmetrico</option>
                            <option value="Simmetrico">Simmetrico</option>
                        </select>
                    </div>

                    <div class="form-group compatto">
                        <label>Modalità:</label>
                        <select id="compatto" class="form-control">
                            <option value="Early/Late">Early/Late</option>
                            <option value="Early(<12)">Early(<12)</option>
                        </select>
                    </div>

                    <!-- Time Configuration -->
                    <div class="form-group">
                        <label>Orario Prima Partenza:</label>
                        <select id="start_time" class="form-control">
                            @foreach(['07:00', '07:10', '07:20', '07:30', '07:40', '07:50', '08:00', '08:10', '08:20', '08:30', '08:40', '08:50', '09:00'] as $time)
                                <option value="{{ $time }}" {{ $time == '08:00' ? 'selected' : '' }}>{{ $time }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Intervallo tra Partenze:</label>
                        <select id="gap" class="form-control">
                            @foreach(['00:08', '00:09', '00:10', '00:11', '00:12', '00:13', '00:14', '00:15'] as $gap)
                                <option value="{{ $gap }}" {{ $gap == '00:10' ? 'selected' : '' }}>{{ $gap }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tempo di Gioco:</label>
                        <select id="round" class="form-control">
                            @foreach(['04:10', '04:20', '04:30', '04:40', '04:50'] as $round)
                                <option value="{{ $round }}" {{ $round == '04:30' ? 'selected' : '' }}>{{ $round }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <small class="text-muted">Orario Incrocio: <span id="cross" class="font-weight-bold">--:--</span></small>
                    </div>

                    <!-- Player Names Upload -->
                    <div class="form-group">
                        <label>Carica Nomi Giocatori (Excel):</label>
                        <form id="upload-form" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="file" id="file" name="file" class="form-control-file mb-2" accept=".xlsx,.xls,.csv">
                            <button type="submit" id="upload" class="btn btn-sm btn-info">
                                <i class="fas fa-upload"></i> Carica File
                            </button>
                        </form>
                    </div>

                    <!-- Action Buttons -->
                    <div class="btn-group btn-block" role="group">
                        <button id="refresh" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button id="excel" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </div>

                    <div class="btn-group btn-block mt-2" role="group" id="1">
                        <button id="btnClick" class="btn btn-primary">
                            <i class="fas fa-user"></i> Nominativo
                        </button>
                    </div>
                    <div class="btn-group btn-block mt-2" role="group" id="2" style="display:none;">
                        <button id="btnClock" class="btn btn-secondary">
                            <i class="fas fa-hashtag"></i> Numerico
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tee Times Table -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-table"></i> Tabella Orari di Partenza
                        <span id="titolo_giornata" class="ml-2"></span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="first_table">
                            <!-- Table content will be generated by JavaScript -->
                        </table>
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

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery UI for datepicker -->
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
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
