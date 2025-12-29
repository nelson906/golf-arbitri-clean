@extends('layouts.admin')

@section('title', 'Nuovo Club')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Nuovo Club</h1>
                <p class="mt-2 text-gray-600">Crea un nuovo club nella tua zona</p>
            </div>
            <a href="{{ route('admin.clubs.index') }}"
               class="text-gray-600 hover:text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Torna all'elenco
            </a>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Errori di validazione!</p>
            <ul class="mt-2 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('admin.clubs.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white shadow-sm rounded-lg p-6">
            @include('admin.clubs._form')
        </div>

        {{-- Actions --}}
        <div class="flex justify-end space-x-4">
            <a href="{{ route('admin.clubs.index') }}"
               class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Annulla
            </a>
            <button type="submit"
                    class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Crea Club
            </button>
        </div>
    </form>
</div>
@endsection
