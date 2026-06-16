<!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>@yield('title', 'Warehouse IOT Admin')</title>

        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: Segoe UI, sans-serif;
            }

            html,
            body {
                min-height: 100%;
            }

            body {
                background: #f4f6f9;
                overflow-x: hidden;
            }

            .wrapper {
                min-height: 100vh;
            }

            /* Sidebar */

            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                width: 250px;
                height: 100vh;
                background: #0f172a;
                transition: .25s ease;
                z-index: 100;
                box-shadow: 10px 0 30px rgba(2, 6, 23, 0.25);
                border-right: 1px solid rgba(255, 255, 255, .06);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }


            .sidebar.collapsed {
                width: 80px;
            }

            .logo {
                height: 70px;
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 20px;
                color: white;
                border-bottom: 1px solid rgba(255, 255, 255, .1);
            }

            .sidebar.collapsed .logo-text,
            .sidebar.collapsed .menu-text {
                display: none;
            }

            .toggle-btn {
                background: none;
                border: none;
                color: white;
                font-size: 22px;
                cursor: pointer;
            }

            .menu {
                list-style: none;
                padding: 15px 0;
                margin: 0;
                flex: 1 1 auto;
                min-height: 0;
                display: flex;
                flex-direction: column;
                overflow-y: auto;
                scrollbar-width: none;
                /* Firefox */
                -ms-overflow-style: none;
                /* IE/Edge */
            }

            .menu::-webkit-scrollbar {
                display: none;
                /* Chrome, Safari, newer Edge */
            }

            .menu li {
                margin-bottom: 5px;
                flex-shrink: 0;
            }

            .menu li a {
                text-decoration: none;
                color: #d1d5db;
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 15px;
                padding: 15px 20px;
                transition: .3s;
                white-space: nowrap;
            }

            .sidebar.collapsed .menu li a {
                justify-content: center;
                gap: 0;
                padding: 14px 0;
            }

            .menu li a:hover {
                background: #1f2937;
                color: white;
            }

            .icon {
                min-width: 28px;
                width: 28px;
                height: 28px;
                text-align: center;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                font-size: 17px;
                line-height: 1;
            }

            /* Main */

            .main {
                margin-left: 0;
                padding-left: 250px;
                width: 100%;
                min-height: 100vh;
                transition: .3s;
                min-width: 0;
            }

            .main.expand {
                padding-left: 80px;
                width: 100%;
            }

            /* Navbar */

            .navbar {
                position: sticky;
                top: 0;
                z-index: 20;
                background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 18px 28px;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            }

            .page-heading {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                padding: 4px 10px;
                border-radius: 999px;
                background: #eff6ff;
                color: #2563eb;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .08em;
            }

            .page-heading h3 {
                margin: 0;
                font-size: 1.15rem;
                font-weight: 700;
                color: #111827;
            }

            .page-heading small {
                color: #6b7280;
                font-size: 0.92rem;
            }

            .navbar-actions {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .header-pill {
                display: none;
            }

            .content {
                padding: 24px 28px 80px;
                margin: 0 10px;
            }

            .content-shell {
                width: 100%;
                max-width: none;
                padding: 0;
            }

            .logout-btn {
                background: #ef4444;
                border: none;
                color: white;
                padding: 10px 14px;
                border-radius: 10px;
                cursor: pointer;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-weight: 600;
            }

            .sidebar-footer {
                flex-shrink: 0;
                padding: 12px 14px 16px;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
            }

            /* Cards */

            .card {
                background: white;
                padding: 20px;
                border-radius: 15px;
                box-shadow: 0 3px 10px rgba(0, 0, 0, .08);
            }

            /* Custom severity badge colors (Bootstrap doesn't include bg-severe) */
            .bg-severe {
                background-color: #f59e0b !important;
            }

            .menu li a.active {
                background: #1e293b;
                color: #fff;
                border-left: 4px solid #3b82f6;
                font-weight: 600;
            }

            .menu li a.active .icon {
                color: #3b82f6;
            }
        </style>
    </head>

    <body>

        <div class="wrapper">

            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">

                <div class="logo">
                    <span class="logo-text" style="font-weight: 800; margin-left: 10px;">Warehouse IOT</span>

                    <button class="toggle-btn" id="toggleBtn">
                        ☰
                    </button>
                </div>

                <ul class="menu">

                    <li>
                        <a href="{{ route('admin.dashboard') }}"
                            class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <span class="icon">📊</span>
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('regions.index') }}"
                            class="{{ request()->routeIs('regions.*') ? 'active' : '' }}">
                            <span class="icon">🌎</span>
                            <span class="menu-text">Regions</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('hierarchy.index') }}"
                            class="{{ request()->routeIs('hierarchy.*') ? 'active' : '' }}">
                            <span class="icon">🧩</span>
                            <span class="menu-text">Hierarchy</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('warehouses.index') }}"
                            class="{{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
                            <span class="icon">🏬</span>
                            <span class="menu-text">Warehouses</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('readings.index') }}"
                            class="{{ request()->routeIs('readings.*') ? 'active' : '' }}">
                            <span class="icon">📈</span>
                            <span class="menu-text">Readings</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('devices.index') }}"
                            class="{{ request()->routeIs('devices.*') ? 'active' : '' }}">
                            <span class="icon">📟</span>
                            <span class="menu-text">Devices</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('alerts.index') }}"
                            class="{{ request()->routeIs('alerts.*') ? 'active' : '' }}">
                            <span class="icon">🚨</span>
                            <span class="menu-text">Alerts</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('reports.index') }}"
                            class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
                            <span class="icon">🧾</span>
                            <span class="menu-text">Reports</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('admin.settings') }}"
                            class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                            <span class="icon">⚙️</span>
                            <span class="menu-text">Settings</span>
                        </a>
                    </li>

                </ul>

                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="logout-btn">⎋ Logout</button>
                    </form>
                </div>

            </div>

            <!-- Main -->
            <div class="main" id="main">

                <!-- Header removed as requested -->

                <!-- Content -->
                <div class="content">

                    @yield('content')

                </div>

            </div>

        </div>

        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

        <!-- DataTables CSS -->
        <link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">

        <!-- Buttons CSS — version 3.x -->
        <link href="https://cdn.datatables.net/buttons/3.2.0/css/buttons.bootstrap5.min.css" rel="stylesheet">

        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- Bootstrap JS (modal ke liye zaroori) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <!-- DataTables JS -->
        <script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>

        <!-- Buttons JS — version 3.x -->
        <script src="https://cdn.datatables.net/buttons/3.2.0/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.colVis.min.js"></script>


        <script>
            let sidebar = document.getElementById('sidebar');
            let main = document.getElementById('main');
            const STORAGE_KEY = 'warehouseSidebarCollapsed';

            function applySidebarState(isCollapsed) {
                sidebar.classList.toggle('collapsed', isCollapsed);
                main.classList.toggle('expand', isCollapsed);
            }

            document.getElementById('toggleBtn').onclick = function() {
                const isCollapsed = !sidebar.classList.contains('collapsed');
                applySidebarState(isCollapsed);
                localStorage.setItem(STORAGE_KEY, String(isCollapsed));
            };

            document.addEventListener('DOMContentLoaded', function() {
                const savedState = localStorage.getItem(STORAGE_KEY) === 'true';
                applySidebarState(savedState);

                if (window.jQuery && $.fn.DataTable) {
                    if ($('#regionsTable').length) {
                        $('#regionsTable').DataTable({
                            responsive: true,
                            paging: false,
                            info: false,
                            searching: true,
                            order: [
                                [0, 'asc']
                            ],
                            language: {
                                search: 'Search regions:'
                            }
                        });
                    }
                }
            });
        </script>

        @yield('scripts')
    </body>

    </html>