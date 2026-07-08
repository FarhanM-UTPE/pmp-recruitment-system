<header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 sticky top-0 z-20">
    <div class="flex flex-col gap-3">
        <!-- Baris Pertama: Menu Toggle + Title + User Info -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <button onclick="toggleSidebar()"
                    class="lg:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 flex-shrink-0">
                    <i class="fas fa-bars text-base"></i>
                </button>
                <button onclick="toggleDesktopSidebar()"
                    class="hidden lg:block p-2 rounded-lg text-gray-600 hover:bg-gray-100 flex-shrink-0">
                    <i class="fas fa-bars text-base"></i>
                </button>
                <h1 class="text-lg font-semibold text-gray-900 truncate">@yield('page-title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
                <div class="hidden lg:flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-sm font-medium text-gray-700 block">{{ Auth::user()->name }}</span>
                        <span class="text-xs text-gray-500">{{ Auth::user()->role_display_name }}</span>
                    </div>
                    <button
                        class="w-9 h-9 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-sm">
                        <span class="text-sm font-semibold text-white">{{ substr(Auth::user()->name, 0, 2) }}</span>
                    </button>
                </div>
                <div class="lg:hidden">
                    <button
                        class="w-8 h-8 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-sm">
                        <span class="text-sm font-semibold text-white">{{ substr(Auth::user()->name, 0, 2) }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Baris Kedua: Header Filters (jika ada) -->
        <div class="w-full">
            @stack('header-filters')
        </div>
    </div>
</header>