<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>@yield('title', 'Dashboard') - Patria Maritim Perkasa</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Recruitment System">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-TileColor" content="#3b82f6">
    <meta name="msapplication-tap-highlight" content="no">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon.png">
    <link rel="apple-touch-icon" href="/images/favicon.png">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Prevent sidebar flashing on page load -->
    <script>
        if (window.innerWidth >= 1024 && localStorage.getItem('sidebarState') === 'collapsed') {
            document.documentElement.classList.add('sidebar-collapsed-preload');
        }
    </script>
    
    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Inter', sans-serif;
        }
        
        /* Apply collapsed state immediately on page load to prevent flashing */
        html.sidebar-collapsed-preload body {
            --apply-sidebar-collapsed: 1;
        }

        /* Ensure content follows sidebar */
        html, body {
            overflow-x: hidden;
        }
        
        /* Hide scrollbar on sidebar nav */
        #sidebar nav {
            scrollbar-width: none;
        }
        
        #sidebar nav::-webkit-scrollbar {
            display: none;
        }

        /* Sidebar Styling */
        #sidebar {
            will-change: transform;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }

        /* Desktop sidebar - Fixed positioning */
        @media (min-width: 1024px) {
            /* Apply collapsed state immediately on preload */
            html.sidebar-collapsed-preload #sidebar {
                width: 5rem;
                min-width: 5rem;
                max-width: 5rem;
            }
            
            html.sidebar-collapsed-preload .sidebar-text {
                display: none;
            }
            
            /* Icon color when inactive in collapsed preload */
            html.sidebar-collapsed-preload #sidebar nav a:not(.bg-gradient-to-r) i,
            html.sidebar-collapsed-preload #sidebar button:not(.bg-gradient-to-r) i {
                color: rgba(0, 165, 173) !important;
            }
            
            html.sidebar-collapsed-preload .user-info-container {
                display: none;
            }
            
            html.sidebar-collapsed-preload #sidebarPattern {
                display: block !important;
            }
            
            html.sidebar-collapsed-preload #main-content {
                margin-left: 5rem;
                width: calc(100% - 5rem);
            }
            
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 16rem;
                min-width: 16rem;
                max-width: 16rem;
                overflow-y: auto;
                overflow-x: hidden;
                z-index: 30;
                transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            }
            
            /* Collapsed state - show mini sidebar with icons only */
            body.sidebar-collapsed #sidebar {
                width: 5rem;
                min-width: 5rem;
                max-width: 5rem;
            }
            
            /* Hide text when sidebar is collapsed */
            body.sidebar-collapsed .sidebar-text {
                display: none;
            }
            
            /* Icon color when inactive in collapsed state */
            body.sidebar-collapsed #sidebar nav a:not(.bg-gradient-to-r) i,
            body.sidebar-collapsed #sidebar button:not(.bg-gradient-to-r) i {
                color: rgba(0, 165, 173) !important;
            }
            
            /* Hide user info, show pattern when collapsed */
            body.sidebar-collapsed .user-info-container {
                display: none;
            }
            
            body.sidebar-collapsed #sidebarPattern {
                display: block !important;
            }
            
            /* Adjust nav items for icon-only layout */
            body.sidebar-collapsed #sidebar nav a,
            body.sidebar-collapsed #sidebar nav div > div > a {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            /* Apply nav centering for preload */
            html.sidebar-collapsed-preload #sidebar nav a,
            html.sidebar-collapsed-preload #sidebar nav div > div > a {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            /* Hide logo text, show icon only */
            body.sidebar-collapsed #sidebar > div:first-child {
                padding-right: 0.5rem;
                padding-left: 0.5rem;
            }
            
            /* Logo visibility toggle */
            body.sidebar-collapsed #logoFull {
                display: none;
            }
            
            body.sidebar-collapsed #logoIcon {
                display: block !important;
            }
            
            /* Logo visibility for preload */
            html.sidebar-collapsed-preload #logoFull {
                display: none;
            }
            
            html.sidebar-collapsed-preload #logoIcon {
                display: block !important;
            }
            
            /* Adjust user info container */
            body.sidebar-collapsed .user-info-container {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            body.sidebar-collapsed .user-info-container .sidebar-text {
                display: none;
            }
            
            /* Preload user info container */
            html.sidebar-collapsed-preload .user-info-container {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            html.sidebar-collapsed-preload .user-info-container .sidebar-text {
                display: none;
            }
            
            /* Adjust logout button for mini sidebar */
            body.sidebar-collapsed #sidebar button[type="submit"] {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            /* Preload logout button */
            html.sidebar-collapsed-preload #sidebar button[type="submit"] {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            #main-content {
                margin-left: 16rem;
                transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1), width 0.3s cubic-bezier(0.4,0,0.2,1);
                min-height: 100vh;
                width: calc(100% - 16rem);
            }
            
            body.sidebar-collapsed #main-content {
                margin-left: 5rem;
                width: calc(100% - 5rem);
            }
        }


        /* Mobile sidebar */
        @media (max-width: 1023px) {
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 16rem;
                overflow-y: auto;
                overflow-x: hidden;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            }
            
            #sidebar:not(.-translate-x-full) {
                transform: translateX(0);
            }
            
            #main-content {
                margin-left: 0;
                min-height: 100vh;
            }
        }

        /* Custom scrollbar untuk sidebar */
        #sidebar::-webkit-scrollbar {
            display: none;
        }

        /* Firefox scrollbar hide */
        #sidebar {
            scrollbar-width: none;
        }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
    <!-- Sidebar -->
    @include('layouts.sidebar')

    <!-- Main Content Wrapper -->
    <div id="main-content" class="flex flex-col min-h-screen">
        <!-- Header -->
        @include('layouts.header')

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto overflow-x-hidden w-full">
            <div class="p-4 sm:p-6 w-full max-w-full">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 transition-opacity duration-300 ease-in-out opacity-0 pointer-events-none z-40 lg:hidden"></div>

    @stack('scripts')
    
    <script>
        // Mobile sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                // Close sidebar
                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.classList.add('pointer-events-none');
                }, 300);
                document.body.classList.remove('overflow-hidden');
            } else {
                // Open sidebar
                overlay.classList.remove('pointer-events-none');
                overlay.style.opacity = '0.5';
                document.body.classList.add('overflow-hidden');
            }
        }

        // Close sidebar when clicking overlay
        document.getElementById('sidebar-overlay')?.addEventListener('click', toggleSidebar);

        // Desktop sidebar toggle
        function toggleDesktopSidebar() {
            document.body.classList.toggle('sidebar-collapsed');
            
            if (document.body.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('sidebarState', 'collapsed');
            } else {
                localStorage.setItem('sidebarState', 'expanded');
            }
        }

        // On page load, check sidebar state from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            // Transfer preload state to body class
            if (document.documentElement.classList.contains('sidebar-collapsed-preload')) {
                document.documentElement.classList.remove('sidebar-collapsed-preload');
                document.body.classList.add('sidebar-collapsed');
            }
        });

        // Handle sidebar visibility on window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            if (window.innerWidth >= 1024) {
                // Desktop mode
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.add('pointer-events-none');
                overlay.style.opacity = '0';
                document.body.classList.remove('overflow-hidden');
            } else {
                // Mobile mode - close sidebar
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('pointer-events-none');
                overlay.style.opacity = '0';
                document.body.classList.remove('overflow-hidden');
            }
        });
    </script>
</body>
</html>