<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

                    {{-- Dashboard comune a tutti --}}
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        🏠 Dashboard
                    </x-nav-link>

                    {{-- Lista Tornei comune a tutti --}}
                    <x-nav-link :href="route('tournaments.index')" :active="request()->routeIs('tournaments.index')">
                        📋 Tornei
                    </x-nav-link>

                    {{-- Calendario comune a tutti --}}
                    <x-nav-link :href="route('tournaments.calendar')" :active="request()->routeIs('tournaments.calendar')">
                        📅 Calendario
                    </x-nav-link>

                    {{-- Menu Super Admin --}}
                    @if(auth()->user()->user_type === 'super_admin')
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                    <div>⚙️ Sistema</div>
                                    <div class="ml-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('super-admin.users.index')">
                                    👥 Utenti Sistema
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('super-admin.zones.index')">
                                    🌍 Zone
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('super-admin.tournament-types.index')">
                                    🏆 Tipi Torneo
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('super-admin.institutional-emails.index')">
                                    📧 Email Istituzionali
                                </x-dropdown-link>
                                <div class="border-t border-gray-200"></div>
                                <x-dropdown-link :href="route('super-admin.settings.index')">
                                    ⚙️ Impostazioni
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('super-admin.system.logs')">
                                    📊 Logs Sistema
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    {{-- Menu Admin (per tutti gli admin incluso super) --}}
                    @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                    <div>👨‍💼 Gestione</div>
                                    <div class="ml-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('admin.tournaments.index')">
                                    📋 Tornei
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.users.index')">
                                    👨‍💼 Arbitri
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.assignments.index')">
                                    📝 Assegnazioni
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.clubs.index')">
                                    🏌️ Circoli
                                </x-dropdown-link>
                                <div class="border-t border-gray-200"></div>
                                <x-dropdown-link :href="route('admin.statistics.dashboard')">
                                    📊 Statistiche
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>

                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                    <div>📄 Documenti</div>
                                    <div class="ml-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('communications.index')">
                                    📢 Comunicazioni
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.tournament-notifications.index')">
                                    🔔 Notifiche
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.documents.index')">
                                    📁 Archivio
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    {{-- Menu Arbitri --}}
                    @if(auth()->user()->user_type === 'referee')
                        <x-nav-link :href="route('user.availability.index')" :active="request()->routeIs('user.availability.*')">
                            📝 Disponibilità
                        </x-nav-link>
                        <x-nav-link :href="route('user.availability.calendar')" :active="request()->routeIs('user.availability.calendar')">
                            📅 Il Mio Calendario
                        </x-nav-link>
                        <x-nav-link :href="route('user.quadranti.index')" :active="request()->routeIs('user.quadranti.*')">
                            ⏰ Simulatore
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ml-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        {{-- User Info Header --}}
                        <div class="px-4 py-2 border-b border-gray-200">
                            <div class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</div>
                            <div class="text-xs text-gray-500">{{ Auth::user()->email }}</div>
                            <div class="text-xs text-gray-400 mt-1">
                                @if(Auth::user()->user_type === 'super_admin')
                                    Super Amministratore
                                @elseif(Auth::user()->user_type === 'national_admin')
                                    Amministratore Nazionale
                                @elseif(Auth::user()->user_type === 'admin')
                                    Amministratore di Zona
                                @else
                                    Arbitro
                                @endif
                            </div>
                        </div>

                        <x-dropdown-link :href="route('profile.edit')">
                            👤 Il mio profilo
                        </x-dropdown-link>

                        @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                            <x-dropdown-link :href="route('admin.dashboard')">
                                🏠 Dashboard Admin
                            </x-dropdown-link>
                        @endif

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                🚪 Esci
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Responsive Navigation Menu (Mobile) --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            {{-- Common Links --}}
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                🏠 Dashboard
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tournaments.index')" :active="request()->routeIs('tournaments.index')">
                📋 Tornei
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tournaments.calendar')" :active="request()->routeIs('tournaments.calendar')">
                📅 Calendario
            </x-responsive-nav-link>

            @if(auth()->user()->user_type === 'super_admin')
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Sistema</div>
                </div>
                <x-responsive-nav-link :href="route('super-admin.users.index')" :active="request()->routeIs('super-admin.users.*')">
                    👥 Utenti Sistema
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.zones.index')" :active="request()->routeIs('super-admin.zones.*')">
                    🌍 Zone
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('super-admin.tournament-types.index')" :active="request()->routeIs('super-admin.tournament-types.*')">
                    🏆 Tipi Torneo
                </x-responsive-nav-link>
            @endif

            @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Gestione</div>
                </div>
                <x-responsive-nav-link :href="route('admin.tournaments.index')" :active="request()->routeIs('admin.tournaments.*')">
                    📋 Gestione Tornei
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    👨‍💼 Arbitri
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.assignments.index')" :active="request()->routeIs('admin.assignments.*')">
                    📝 Assegnazioni
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.clubs.index')" :active="request()->routeIs('admin.clubs.*')">
                    🏌️ Circoli
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.statistics.dashboard')" :active="request()->routeIs('admin.statistics.*')">
                    📊 Statistiche
                </x-responsive-nav-link>
            @endif

            @if(auth()->user()->user_type === 'referee')
                <div class="border-t border-gray-200 mt-2 pt-2">
                    <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">Le mie attività</div>
                </div>
                <x-responsive-nav-link :href="route('user.availability.index')" :active="request()->routeIs('user.availability.*')">
                    📝 Disponibilità
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.availability.calendar')" :active="request()->routeIs('user.availability.calendar')">
                    📅 Il Mio Calendario
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('user.quadranti.index')" :active="request()->routeIs('user.quadranti.*')">
                    ⏰ Simulatore
                </x-responsive-nav-link>
            @endif
        </div>

        {{-- User Profile Mobile Section --}}
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    👤 Il mio profilo
                </x-responsive-nav-link>

                @if(in_array(auth()->user()->user_type ?? '', ['admin', 'national_admin', 'super_admin']))
                    <x-responsive-nav-link :href="route('admin.dashboard')">
                        🏠 Dashboard Admin
                    </x-responsive-nav-link>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        🚪 Esci
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
