<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'CheckMedia v2' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Instrument Sans', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    @livewireStyles
</head>

<body class="bg-gray-100 font-sans antialiased text-gray-900">
    <!-- Navigation (Optional) -->
    <nav class="bg-[#CE3132] shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex">
                    <div class="shrink-0 flex items-center">
                        <!-- Mobile: Smaller Logo -->
                        <img src="/images/logo-small.png" alt="CheckMedia" class="h-8 w-auto md:h-10 transition-all duration-300">
                    </div>
                </div>

                <!-- Right Side: User Menu -->
                <div class="flex items-center">
                    @auth
                        <div class="relative ml-3" x-data="{ open: false }">
                            <div>
                                <button @click="open = !open" type="button" 
                                    class="flex items-center max-w-xs bg-[#CE3132] rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-400 hover:bg-red-700 transition-colors p-1" 
                                    id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                    <span class="sr-only">Open user menu</span>
                                    <div class="h-8 w-8 rounded-full bg-white flex items-center justify-center text-[#CE3132] font-bold border-2 border-red-200">
                                        {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-white hidden sm:block">
                                        {{ auth()->user()->name ?? 'Usuario' }}
                                    </span>
                                    <svg class="ml-2 h-4 w-4 text-red-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Dropdown -->
                            <div x-show="open" 
                                @click.away="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50" 
                                role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1"
                                style="display: none;">
                                
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-xs text-gray-500">Conectado como</p>
                                    <p class="text-sm font-bold text-gray-900 truncate">{{ auth()->user()->email ?? '' }}</p>
                                </div>

                                <form method="POST" action="{{ route('platform.logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
                                        <div class="flex items-center text-red-600">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                            </svg>
                                            Cerrar Sesión
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>

</html>