@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1>TEST FORM</h1>

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf

        <input type="text" name="first_name" placeholder="Nome" required><br>
        <input type="text" name="last_name" placeholder="Cognome" required><br>
        <input type="email" name="email" placeholder="Email" required><br>

        <select name="level" required>
            <option value="">Seleziona livello</option>
            <option value="Aspirante">Aspirante</option>
            <option value="1_livello">1 livello</option>
        </select><br>

        <select name="zone_id" required>
            <option value="">Seleziona zona</option>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
            @endforeach
        </select><br>

        <button type="submit">CREA ORA</button>
    </form>
</div>
@endsection
