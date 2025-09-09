<div
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Intestazione --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900">
                            {{ $referee->name }}
                        </h2>
                        <p class="text-gray-600">
                            {{ $referee->referee_code }} - {{ $referee->zone->name ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">
                            Livello attuale:
                            <span class="font-semibold">
                                {{ $careerData['career_levels'][now()->year]['level'] ?? 'N/A' }}
                            </span>
                        </p>
                        <p class="text-sm text-gray-600">
                            Tornei totali:
                            <span class="font-semibold">
                                {{ $careerData['career_summary']['total_assignments'] ?? 0 }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Timeline carriera --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    📅 Cronologia Carriera
                </h3>

                <div class="space-y-8">
                    @foreach(array_reverse($careerData['tournaments'] ?? [], true) as $year => $tournaments)
                        <div class="relative">
                            {{-- Anno e livello --}}
                            <div class="flex items-center gap-4 mb-4">
                                <h4 class="text-xl font-bold text-gray-700">{{ $year }}</h4>
                                @if(isset($careerData['career_levels'][$year]))
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $careerData['career_levels'][$year]['level'] === 'Nazionale' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $careerData['career_levels'][$year]['level'] }}
                                    </span>
                                @endif
                            </div>

                            {{-- Statistiche anno --}}
                            <div class="grid grid-cols-3 gap-4 mb-4 text-sm">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="font-medium text-gray-500">Tornei</div>
                                    <div class="text-2xl font-bold text-gray-900">
                                        {{ count($tournaments) }}
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="font-medium text-gray-500">Come Arbitro</div>
                                    <div class="text-2xl font-bold text-gray-900">
                                        {{ count(array_filter($careerData['assignments'][$year] ?? [], fn($a) => $a['role'] === 'Arbitro')) }}
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="font-medium text-gray-500">Come DT</div>
                                    <div class="text-2xl font-bold text-gray-900">
                                        {{ count(array_filter($careerData['assignments'][$year] ?? [], fn($a) => $a['role'] === 'Direttore di Torneo')) }}
                                    </div>
                                </div>
                            </div>

                            {{-- Lista tornei --}}
                            <div class="space-y-4">
                                @foreach($tournaments as $tournament)
                                    <div class="bg-white border rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h5 class="font-medium text-gray-900">
                                                    {{ $tournament['name'] }}
                                                </h5>
                                                <p class="text-sm text-gray-600">
                                                    {{ \Carbon\Carbon::parse($tournament['start_date'])->format('d/m/Y') }}
                                                    @if($tournament['start_date'] !== $tournament['end_date'])
                                                        - {{ \Carbon\Carbon::parse($tournament['end_date'])->format('d/m/Y') }}
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                @php
                                                    $shownRoles = [];
                                                    $tournamentAssignments = array_filter($careerData['assignments'][$year] ?? [], fn($a) => $a['tournament_id'] === $tournament['id']);
                                                @endphp
                                                @foreach($tournamentAssignments as $assignment)
                                                    @if(!in_array($assignment['role'], $shownRoles))
                                                        @php $shownRoles[] = $assignment['role']; @endphp
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                            {{ $assignment['role'] === 'Direttore di Torneo' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' }}">
                                                            {{ $assignment['role'] }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Statistiche carriera --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    📊 Statistiche Carriera
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="bg-gray-50 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-gray-900">
                            {{ $careerData['career_summary']['total_assignments'] ?? 0 }}
                        </div>
                        <div class="text-sm font-medium text-gray-500">Tornei Totali</div>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-gray-900">
                            {{ $careerData['career_summary']['total_years'] ?? 0 }}
                        </div>
                        <div class="text-sm font-medium text-gray-500">Anni di Attività</div>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-gray-900">
                            {{ $careerData['career_summary']['roles_summary']['Direttore di Torneo'] ?? 0 }}
                        </div>
                        <div class="text-sm font-medium text-gray-500">Come DT</div>
                    </div>

                    <div class="bg-gray-50 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-gray-900">
                            {{ number_format(($careerData['career_summary']['avg_tournaments_per_year'] ?? 0), 1) }}
                        </div>
                        <div class="text-sm font-medium text-gray-500">Media Tornei/Anno</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
