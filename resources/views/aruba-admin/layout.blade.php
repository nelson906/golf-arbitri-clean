<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Aruba Admin Tools')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-tools text-2xl"></i>
                    <span class="text-xl font-bold">Aruba Admin Tools</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm hover:text-gray-200">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6">
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Sidebar -->
            <aside class="w-full md:w-64 bg-white rounded-lg shadow-md p-4">
                <nav class="space-y-2">
                    <a href="{{ route('aruba.admin.dashboard') }}"
                       class="flex items-center space-x-3 p-3 rounded hover:bg-blue-50 {{ request()->routeIs('aruba.admin.dashboard') ? 'bg-blue-100 text-blue-600' : '' }}">
                        <i class="fas fa-tachometer-alt w-5"></i>
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('aruba.admin.cache.index') }}"
                       class="flex items-center space-x-3 p-3 rounded hover:bg-blue-50 {{ request()->routeIs('aruba.admin.cache.*') ? 'bg-blue-100 text-blue-600' : '' }}">
                        <i class="fas fa-broom w-5"></i>
                        <span>Gestione Cache</span>
                    </a>

                    <a href="{{ route('aruba.admin.permissions') }}"
                       class="flex items-center space-x-3 p-3 rounded hover:bg-blue-50 {{ request()->routeIs('aruba.admin.permissions') ? 'bg-blue-100 text-blue-600' : '' }}">
                        <i class="fas fa-lock w-5"></i>
                        <span>Permessi</span>
                    </a>

                    <a href="{{ route('aruba.admin.logs') }}"
                       class="flex items-center space-x-3 p-3 rounded hover:bg-blue-50 {{ request()->routeIs('aruba.admin.logs') ? 'bg-blue-100 text-blue-600' : '' }}">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Logs</span>
                    </a>

                    <a href="{{ route('aruba.admin.phpinfo') }}"
                       class="flex items-center space-x-3 p-3 rounded hover:bg-blue-50 {{ request()->routeIs('aruba.admin.phpinfo') ? 'bg-blue-100 text-blue-600' : '' }}">
                        <i class="fab fa-php w-5"></i>
                        <span>PHP Info</span>
                    </a>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1">
                <!-- Messaggi Flash -->
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-check-circle"></i> {!! session('success') !!}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                    </div>
                @endif

                @if(session('info'))
                    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-info-circle"></i> {!! session('info') !!}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
