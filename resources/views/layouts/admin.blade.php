<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Golf Arbitri Admin')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        {{-- Sidebar Menu --}}
        <nav class="bg-blue-800 w-64 min-h-screen">
            <div class="p-4">
                <h1 class="text-white text-xl font-bold">Golf Arbitri</h1>
            </div>

            <ul class="mt-6">
                <li>
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.dashboard') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">🏠</span>
                        Dashboard
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.tournaments.index') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.tournaments.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">🏆</span>
                        Tornei
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.users.index') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.users.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">👥</span>
                        Arbitri
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.assignments.index') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.assignments.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">📋</span>
                        Assegnazioni
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.clubs.index') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.clubs.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">🏌️</span>
                        Circoli
                    </a>
                </li>
            </ul>

            {{-- User Menu --}}
            <div class="absolute bottom-0 w-64 p-4 border-t border-blue-700">
                <div class="flex items-center text-blue-100">
                    <span class="mr-2">👤</span>
                    <span class="flex-1">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-blue-300 hover:text-white text-sm">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        {{-- Main Content --}}
        <main class="flex-1">
            {{-- Top Header --}}
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4">
                    <h1 class="text-2xl font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h1>
                    @if(isset($breadcrumbs))

                    @endif
                </div>
            </header>

            {{-- Page Content --}}
            <div class="p-6">
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
