@extends('layouts.admin')

@section('content')
    @if($isAdmin)
        <div class="mb-4 px-6">
            <a href="{{ route('admin.referees.curricula') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Torna alla lista
            </a>
        </div>
    @endif

    @include('shared.curriculum.detail', [
        'referee' => $referee,
        'careerData' => $careerData
    ])
@endsection
