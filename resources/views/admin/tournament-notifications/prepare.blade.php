@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Prepara Notifiche - {{ $tournament->name }}</h1>

    <form method="POST" action="{{ route('admin.tournament-notifications.store', $tournament) }}">
        @csrf

        <div class="bg-white shadow rounded-lg p-6">
            {{-- Selezione Template --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Template Email
                </label>
                <select name="email_template" class="w-full rounded-md border-gray-300">
                    <option value="tournament_assignment_generic">Template Standard</option>
                    <option value="tournament_assignment_formal">Template Formale</option>
                </select>
            </div>

            {{-- Destinatari --}}
            <div class="mb-6">
                <h3 class="text-lg font-medium mb-4">Destinatari</h3>

                {{-- Arbitri --}}
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="send_to_referees" value="1" checked class="mr-2">
                        <span>Invia agli arbitri assegnati ({{ $tournament->assignments->count() }})</span>
                    </label>
                </div>

                {{-- Circolo --}}
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="send_to_club" value="1" checked class="mr-2">
                        <span>Invia al circolo ({{ $tournament->club->name }})</span>
                    </label>
                </div>

                {{-- Email Istituzionali --}}
                @if($fixedEmails->count() > 0)
                <div class="mb-4">
                    <h4 class="font-medium mb-2">Email Istituzionali</h4>
                    @foreach($fixedEmails as $category => $emails)
                        <div class="ml-4 mb-2">
                            <p class="text-sm font-medium text-gray-600">{{ ucfirst($category) }}</p>
                            @foreach($emails as $email)
                                <label class="flex items-center mt-1">
                                    <input type="checkbox" name="institutional_emails[]"
                                           value="{{ $email->id }}"
                                           {{ $email->is_default ? 'checked' : '' }}
                                           class="mr-2">
                                    <span class="text-sm">{{ $email->name }} ({{ $email->email }})</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @endif

                {{-- Email Aggiuntive --}}
                <div class="mb-4">
                    <h4 class="font-medium mb-2">Email Aggiuntive</h4>
                    <div id="additional-emails">
                        <div class="flex mb-2">
                            <input type="email" name="additional_emails[]"
                                   placeholder="email@esempio.com"
                                   class="flex-1 rounded-md border-gray-300 mr-2">
                            <button type="button" onclick="addEmailField()"
                                    class="px-3 py-2 bg-blue-500 text-white rounded">
                                +
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Documenti --}}
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="generate_documents" value="1" checked class="mr-2">
                    <span>Genera automaticamente i documenti</span>
                </label>
            </div>

            <div class="flex justify-end space-x-4">
                <a href="{{ route('admin.tournament-notifications.index') }}"
                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded">
                    Annulla
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
                    Prepara Notifiche
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function addEmailField() {
    const container = document.getElementById('additional-emails');
    const div = document.createElement('div');
    div.className = 'flex mb-2';
    div.innerHTML = `
        <input type="email" name="additional_emails[]"
               placeholder="email@esempio.com"
               class="flex-1 rounded-md border-gray-300 mr-2">
        <button type="button" onclick="this.parentElement.remove()"
                class="px-3 py-2 bg-red-500 text-white rounded">
            -
        </button>
    `;
    container.appendChild(div);
}
</script>
@endsection
