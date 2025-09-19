@props(['referees', 'title' => 'Arbitri'])

<div class="referees-section">
    <h3>{{ $title }}:</h3>

    @foreach(['Direttore di Torneo', 'Arbitro', 'Osservatore'] as $role)
        @php
            $roleReferees = $referees->where('role', $role);
        @endphp

        @if($roleReferees->isNotEmpty())
            <div class="role-group">
                <div class="role-title">{{ $role }}</div>

                @foreach($roleReferees as $assignment)
                    <div class="referee-item">
                        • {{ $assignment->user->name }}
                        @isset($assignment->user->referee_code)
                            <small>({{ $assignment->user->referee_code }})</small>
                        @endisset
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
