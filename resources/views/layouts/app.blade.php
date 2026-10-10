<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="base-url" content="{{ url('/') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Assets compilati (CSS + Alpine + React via Vite) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{-- Esito delle azioni (decisione 2026-10-04: ritorno sicuro all'arbitro).
                     Prima questo layout non mostrava ne' conferme ne' errori. --}}
                @if (session('success') || session('warning') || session('error') || $errors->any())
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-3">
                        @if (session('success'))
                            <div class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-800" role="status">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if (session('warning'))
                            <div class="rounded-md bg-yellow-50 border border-yellow-300 p-4 text-sm text-yellow-800" role="alert">
                                ⚠️ {{ session('warning') }}
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800" role="alert">
                                {{ session('error') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800" role="alert">
                                @foreach ($errors->all() as $message)
                                    <p>{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
@yield('content')

</main>
        </div>
        
        @include('layouts.partials.page-loading')
        @stack('scripts')
    </body>
</html>
