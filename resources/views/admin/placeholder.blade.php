@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold">{{ $title }}</h1>
    <p class="mt-4 text-gray-600">Sezione in costruzione - refactoring in corso</p>
    <a href="{{ route('admin.dashboard') }}" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded">
        Torna alla Dashboard
    </a>
</div>
@endsection
