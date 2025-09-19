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
                {{-- Super Admin Section --}}
                @if(auth()->user()->user_type === 'super_admin')
                    <li class="px-4 py-2">
                        <h3 class="text-xs font-semibold text-blue-300 uppercase tracking-wider">Sistema</h3>
                    </li>



                    <li>
                        <a href="{{ route('super-admin.tournament-types.index') }}"
                           class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('super-admin.tournament-types.*') ? 'bg-blue-900' : '' }}">
                            <span class="mr-3">🏆</span>
                            Tipi Torneo
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('super-admin.institutional-emails.index') }}"
                           class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('super-admin.institutional-emails.*') ? 'bg-blue-900' : '' }}">
                            <span class="mr-3">📧</span>
                            Email Istituzionali
                        </a>
                    </li>

                    <li class="mt-4 px-4 py-2">
                        <h3 class="text-xs font-semibold text-blue-300 uppercase tracking-wider">Amministrazione</h3>
                    </li>
                @endif

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
                    <a href="{{ route('tournaments.calendar') }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('tournaments.calendar') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">📅</span>
                        Calendario
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
                    <a href="{{ route('admin.referees.curricula') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('referees.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">📋</span>
                        Curriculum
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

                <li>
                    <a href="{{ route('admin.statistics.dashboard') }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.statistics.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">📊</span>
                        Statistiche
                    </a>
                </li>

                {{-- Documenti Section --}}
                <li class="mt-4 px-4 py-2">
                    <h3 class="text-xs font-semibold text-blue-300 uppercase tracking-wider">Documenti</h3>
                </li>

                <li>
                    <a href="{{ route('admin.communications.index') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.communications.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">📢</span>
                        Comunicazioni
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.tournament-notifications.index') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.tournament-notifications.*') || request()->routeIs('admin.notifications.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">🔔</span>
                        Notifiche
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.documents.index') ?? '#' }}"
                       class="flex items-center px-4 py-3 text-blue-100 hover:bg-blue-700 {{ request()->routeIs('admin.documents.*') ? 'bg-blue-900' : '' }}">
                        <span class="mr-3">📁</span>
                        Documenti
                    </a>
                </li>
            </ul>

        </nav>

        {{-- Main Content --}}
        <main class="flex-1">
            {{-- Top Header --}}
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h1>

                    {{-- User Dropdown --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false"
                                class="flex items-center space-x-3 text-gray-700 hover:text-gray-900 focus:outline-none">
                            <span>👤 {{ auth()->user()->name }}</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div x-show="open" x-transition
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <div class="px-4 py-2 border-b border-gray-200">
                                <div class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</div>
                                <div class="text-xs text-gray-500">{{ auth()->user()->email }}</div>
                                <div class="text-xs text-gray-400 mt-1">
                                    @if(auth()->user()->user_type === 'super_admin')
                                        Super Admin
                                    @elseif(auth()->user()->user_type === 'national_admin')
                                        Admin Nazionale
                                    @elseif(auth()->user()->user_type === 'admin')
                                        Admin Zona
                                    @endif
                                </div>
                            </div>

                            <a href="{{ route('profile.edit') }}"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                👤 Il mio profilo
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    🚪 Esci
                                </button>
                            </form>
                        </div>
                    </div>
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

    @stack('scripts')
</body>
</html>
