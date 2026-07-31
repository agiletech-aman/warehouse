<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Warehouse Monitoring system Admin')</title>

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
            z-index: 1040;
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

        /* Header toggle positioning */
        .topbar .toggle-btn {
            padding: 6px 10px;
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

        .menu li a,
        .menu-toggle {
            text-decoration: none;
            color: #d1d5db;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 15px;
            padding: 15px 20px;
            transition: .3s;
            white-space: nowrap;
            border: 0;
            background: transparent;
            width: 100%;
            cursor: pointer;
            text-align: left;
        }

        .sidebar.collapsed .menu li a,
        .sidebar.collapsed .menu-toggle {
            justify-content: center;
            gap: 0;
            padding: 14px 0;
        }

        .menu li a:hover,
        .menu-toggle:hover,
        .menu li a.active,
        .menu-toggle.active {
            background: #1f2937;
            color: white;
        }

        .submenu-chevron {
            margin-left: auto;
            font-size: 12px;
            transition: transform .2s ease;
        }

        .menu-toggle[aria-expanded="true"] .submenu-chevron {
            transform: rotate(180deg);
        }

        .submenu {
            display: none;
            list-style: none;
            padding: 4px 0 2px;
            margin: 0;
        }

        .submenu.open {
            display: block;
        }

        .submenu li {
            margin-bottom: 2px;
        }

        .submenu li a {
            padding: 10px 20px 10px 55px;
            font-size: 14px;
            gap: 10px;
        }

        .submenu .icon {
            min-width: 22px;
            width: 22px;
            height: 22px;
            font-size: 14px;
        }

        .sidebar.collapsed .submenu,
        .sidebar.collapsed .submenu-chevron {
            display: none;
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
            position: relative;
            z-index: auto;
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
            /* Space so sticky .topbar (72px) never overlaps page content */
            padding: 20px 28px 80px;

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
            padding: 15px;
            margin-top: auto;
        }

        .user-info {
            background: #101727;
            border-radius: 18px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid rgba(255, 255, 255, .05);
        }

        .avatar {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            background: #848181;
            color: #fff;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-details {
            flex: 1;
            overflow: hidden;
        }

        .user-name {
            color: #fff;
            font-size: 18px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            color: #8b8b8b;
            font-size: 14px;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            width: 38px;
            height: 38px;
            border: none;
            background: transparent;
            color: #8b8b8b;
            border-radius: 10px;
            cursor: pointer;
            transition: .3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .logout-btn i {
            font-size: 18px;
        }

        /* =========================
   HEADER
========================= */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 1000;

            transition: all .3s ease;

            height: 72px;
            padding: 0 30px;


            display: flex;
            align-items: center;

            background: rgba(255, 255, 255, .85);
            backdrop-filter: blur(6px);

            border-bottom: 1px solid #ececec;
        }

        /* When sidebar collapsed, increase left offset to match sidebar width */
        .wrapper.collapsed .topbar {
            margin-left: 80px;
        }


        /* Search */
        .search-box {
            width: 600px;
            height: 40px;

            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 16px;

            display: flex;
            align-items: center;
            gap: 12px;

            padding: 0 8px;
        }

        .search-box i {
            color: #9ca3af;
            font-size: 16px;
        }

        .search-box input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 15px;
        }

        /* Default */
        .logout-btn {
            display: none;
        }

        /* Collapse mode */
        .sidebar.collapsed .user-details {
            display: none;
        }

        .sidebar.collapsed .user-info {
            flex-direction: column;
            justify-content: center;
            gap: 12px;
            padding: 14px 0;
        }

        .sidebar.collapsed .logout-btn {
            display: flex;
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, .08);
            color: #fff;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
        }

        .sidebar.collapsed .logout-btn:hover {
            background: #ef4444;
        }

        /* Right side */
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-left: auto;
        }

        /* Bell */
        .notification-btn {
            width: 46px;
            height: 46px;


            border: none;
            background: transparent;

            display: flex;
            align-items: center;
            justify-content: center;

            position: relative;
        }

        .notification-btn i {
            font-size: 22px;
            color: #111827;
        }

        .notification-badge {
            position: absolute;
            top: 2px;
            right: 0;

            width: 20px;
            height: 20px;

            background: #ef4444;
            color: white;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 11px;
            font-weight: 600;
        }

        .logo {
            height: 72px;


            display: flex;
            align-items: center;
            /* gap: 14px; */

            border-bottom: 1px solid rgba(255, 255, 255, .08);
            flex-shrink: 0;
        }

        .logo-avatar {
            width: 42px;
            height: 42px;
            min-width: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: linear-gradient(135deg, #3b82f6, #615cf6);
            color: #fff;
            margin-right: 12px;

            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1px;

            box-shadow: 0 8px 20px rgba(59, 130, 246, .25);
        }

        .logo-content {
            display: flex;
            flex-direction: column;
        }

        .logo-text {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.2;
        }

        .logo-subtitle {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 2px;
        }

        .sidebar.collapsed .logo-content {
            display: none;
        }

        .sidebar.collapsed .logo {
            justify-content: center;
            padding: 0;
        }

        /* Only logo image visible when sidebar collapsed */
        .sidebar.collapsed .logo img {
            margin-right: 0 !important;
        }

        .sidebar.collapsed .logo > div:first-child {
            margin-right: 0;
        }

        .sidebar.collapsed .logo {
            padding-left: 0;
            padding-right: 0;
        }

    </style>

    @yield('styles')
</head>

<body>




    <div class="wrapper">

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo" style="display:flex;align-items:center;">

                <div>
                    <img src="{{ asset('logo1.png') }}"
                        alt="Warehouse Monitoring system Logo"
                        style="width:200px;height:50px;margin-right:10px;">
                </div>


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
                    <a href="{{ route('warehouses.index') }}"
                        class="{{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
                        <span class="icon">🏬</span>
                        <span class="menu-text">Warehouses</span>
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
                    <a href="{{ route('fns-detections.index') }}"
                        class="{{ request()->routeIs('fns-detections.*') ? 'active' : '' }}">
                        <span class="icon">📷</span>
                        <span class="menu-text">FNS Detections</span>
                    </a>
                </li>

                @php
                    $iotMenuActive = request()->routeIs('readings.*', 'devices.*', 'alerts.*', 'reports.*');
                @endphp
                <li>
                    <button type="button"
                        id="iotMenuToggle"
                        class="menu-toggle {{ $iotMenuActive ? 'active' : '' }}"
                        aria-expanded="{{ $iotMenuActive ? 'true' : 'false' }}"
                        aria-controls="iotSubmenu">
                        <span class="icon">📡</span>
                        <span class="menu-text">IOT Sensors</span>
                        <span class="menu-text submenu-chevron">▼</span>
                    </button>

                    <ul id="iotSubmenu" class="submenu {{ $iotMenuActive ? 'open' : '' }}">
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
                                class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">
                                <span class="icon">🧾</span>
                                <span class="menu-text">Reports</span>
                            </a>
                        </li>
                    </ul>
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
                <div class="user-info">
                    @if(session()->has('admin_id'))

                    <div class="avatar">
                        {{ strtoupper(substr(session('admin_name') ?? '?', 0, 1)) }}
                    </div>

                    <div class="user-details">
                        <div class="user-name">
                            {{ session('admin_name') ?? 'NA' }}
                        </div>

                        <div class="user-email">
                            {{ session('admin_email') ?? 'NA' }}
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>

                    @else

                    <div class="avatar">?</div>

                    <div class="user-details">
                        <div class="user-name">Guest</div>
                        <div class="user-email">guest@example.com</div>
                    </div>

                    @endif
                </div>
            </div>
        </div>

        <!-- Main -->
        <div class="main" id="main">

            <header class="topbar">
                <div style="margin-right: 100px;">
                    <button
                        class="toggle-btn"
                        id="toggleBtn"
                        type="button"
                        aria-label="Toggle sidebar"
                        style="color:#111827;">
                        ☰
                    </button>
                </div>

                <!-- Search -->
                <div class="search-box" style="margin: 0 auto; position: relative;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="globalSearchInput" type="text" placeholder="Search..." class="search-input" autocomplete="off">

                    <div id="globalSearchDropdown"
                        class="dropdown-menu"
                        style="display:none; position:absolute; top:52px; left:0; right:0; max-height:320px; overflow:auto; z-index:2000;">
                        <div class="px-3 py-2 text-muted" style="font-size:12px;">Type to search...</div>
                    </div>
                </div>


                <!-- Right Actions -->




                <div class="navbar-actions">

                    <!-- Notification -->
                    <div class="dropdown" style="margin-left:auto;">


                        <button
                            class="btn btn-link p-0 text-decoration-none position-relative"
                            type="button"
                            id="notificationDropdown"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            style="width:35px;height:35px;display:flex;align-items:center;justify-content:center;border-radius:999px;border:1px solid rgb(35, 72, 206);background:#fff;">

                            <i class="fa-solid fa-bell" style="font-size:18px;color:#111827;"></i>

                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li class="px-3 py-2 text-muted" style="font-size:12px;">Notifications</li>
                            <li><a class="dropdown-item" href="{{ route('alerts.index') }}">🚨 New alert received</a></li>
                            <li><a class="dropdown-item" href="{{ route('readings.index') }}">📈 New reading logged</a></li>
                            <li><a class="dropdown-item" href="{{ route('devices.index') }}">📟 Device status updated</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li class="px-3 py-2">
                                <a class="dropdown-item text-center" href="{{ route('alerts.index') }}">View all</a>
                            </li>
                        </ul>
                    </div>

                    <!-- Settings dropdown + user icon -->
                    <div class="dropdown">
                        <button
                            class="btn btn-link p-0 text-decoration-none position-relative"
                            type="button"
                            id="userDropdown"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            style="width:35px;height:35px;display:flex;align-items:center;justify-content:center;border-radius:999px;border:1px solid rgba(35, 72, 206);background:#fff;">
                            <i class="fa-solid fa-user" style="font-size:18px;color:#111827;"></i>

                        </button>


                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.settings') }}">
                                    <i class="fa-solid fa-gear me-2"></i>
                                    Settings
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="dropdown-item" style="width:100%;text-align:left;">
                                        <i class="fa-solid fa-right-from-bracket me-2"></i>
                                        Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>



                </div>
            </header>

            <!-- Content -->
            <div class="content">

                @yield('content')

            </div>

            <!-- Footer -->
            <footer
                id="footer"
                style="
        padding:18px 28px;
        color:#6b7280;
        border-top:1px solid #e5e7eb;
        background:#ffffff;
        transition:.3s;
    ">

                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div>© {{ date('Y') }} Agiletech Solutions</div>
                    <div>All rights reserved.</div>
                </div>

            </footer>

        </div>

    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="deleteConfirmModalMessage">
                    Are you sure you want to delete this item?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="deleteConfirmModalButton">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
        // Keep this static to avoid Blade/JS parse issues on the client
        const currentAlertCount = Number(0);






        const currentRoute = "{{ request()->route()->getName() }}";


        // Agar Alerts page open hai to current count ko save kar do
        if (currentRoute === 'alerts.index') {
            localStorage.setItem('lastSeenAlertCount', currentAlertCount);
        }

        const lastSeenAlertCount = parseInt(localStorage.getItem('lastSeenAlertCount') || 0);
        const unreadCount = currentAlertCount - lastSeenAlertCount;

        const badge = document.getElementById('notificationBadge');

        if (unreadCount > 0 && currentRoute !== 'alerts.index' && badge) {
            badge.style.display = 'block';
            badge.innerText = unreadCount;
        }
    </script>


    <script>
        (function() {
            const input = document.getElementById('globalSearchInput');
            const dropdown = document.getElementById('globalSearchDropdown');
            if (!input || !dropdown) return;

            let lastReq = 0;

            function render(results) {
                dropdown.innerHTML = '';
                const header = document.createElement('div');
                header.className = 'px-3 py-2 text-muted';
                header.style.fontSize = '12px';
                header.textContent = results.length ? 'Search results' : 'No results';
                dropdown.appendChild(header);

                results.forEach(r => {
                    const item = document.createElement('a');
                    item.className = 'dropdown-item';
                    item.href = r.route || '#';

                    const badge = document.createElement('span');
                    badge.className = 'me-2';
                    badge.style.fontSize = '11px';
                    badge.style.padding = '2px 6px';
                    badge.style.borderRadius = '999px';
                    badge.style.background = '#eef2ff';
                    badge.style.color = '#3730a3';
                    badge.textContent = (r.type || '').toString().toUpperCase();

                    const text = document.createElement('span');
                    text.textContent = r.label;

                    item.appendChild(badge);
                    item.appendChild(text);
                    dropdown.appendChild(item);

                    item.addEventListener('click', function(e) {
                        if (!r.route) e.preventDefault();
                    });
                });
            }

            function show() {
                dropdown.style.display = 'block';
            }

            function hide() {
                dropdown.style.display = 'none';
            }

            let timer = null;
            input.addEventListener('input', function() {
                const q = input.value.trim();
                if (q.length < 1) {
                    dropdown.style.display = 'none';
                    return;
                }

                clearTimeout(timer);
                timer = setTimeout(async function() {
                    const reqId = ++lastReq;
                    try {
                        const res = await fetch(`{{ route('admin.global-search') }}?q=${encodeURIComponent(q)}`);
                        const payload = await res.json();
                        if (reqId !== lastReq) return;

                        render(payload.results || []);
                        show();
                    } catch (e) {
                        dropdown.style.display = 'none';
                    }
                }, 250);
            });

            document.addEventListener('click', function(e) {
                if (e.target === input || input.contains(e.target) || dropdown.contains(e.target)) return;
                hide();
            });
        })();

        let sidebar = document.getElementById('sidebar');

        let main = document.getElementById('main');
        const STORAGE_KEY = 'warehouseSidebarCollapsed';

        function applySidebarState(isCollapsed) {
            sidebar.classList.toggle('collapsed', isCollapsed);
            main.classList.toggle('expand', isCollapsed);
        }

        const iotMenuToggle = document.getElementById('iotMenuToggle');
        const iotSubmenu = document.getElementById('iotSubmenu');

        iotMenuToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('collapsed')) {
                applySidebarState(false);
                localStorage.setItem(STORAGE_KEY, 'false');
            }

            const isOpen = iotSubmenu.classList.toggle('open');
            iotMenuToggle.setAttribute('aria-expanded', String(isOpen));
        });

        document.getElementById('toggleBtn').onclick = function() {
            const isCollapsed = !sidebar.classList.contains('collapsed');
            applySidebarState(isCollapsed);
            localStorage.setItem(STORAGE_KEY, String(isCollapsed));
        };

        document.addEventListener('DOMContentLoaded', function() {
            const savedState = localStorage.getItem(STORAGE_KEY) === 'true';
            applySidebarState(savedState);

            const deleteModalElement = document.getElementById('deleteConfirmModal');
            const deleteModalTitle = document.getElementById('deleteConfirmModalLabel');
            const deleteModalMessage = document.getElementById('deleteConfirmModalMessage');
            const deleteModalButton = document.getElementById('deleteConfirmModalButton');
            let pendingDeleteForm = null;

            document.addEventListener('submit', function(event) {
                const form = event.target;

                if (!form.matches('form[data-confirm-delete]')) {
                    return;
                }

                event.preventDefault();
                pendingDeleteForm = form;
                deleteModalTitle.textContent = form.dataset.confirmTitle || 'Confirm delete';
                deleteModalMessage.textContent = form.dataset.confirmMessage || 'Are you sure you want to delete this item?';

                bootstrap.Modal.getOrCreateInstance(deleteModalElement).show();
            });

            deleteModalButton.addEventListener('click', function() {
                if (!pendingDeleteForm) return;

                const form = pendingDeleteForm;
                pendingDeleteForm = null;
                bootstrap.Modal.getOrCreateInstance(deleteModalElement).hide();
                form.submit();
            });
        });

        window.initWarehouseDataTable = function(selector, options) {
            if (!window.jQuery || !$.fn.DataTable || !$(selector).length) {
                return null;
            }

            const $table = $(selector);

            if ($.fn.DataTable.isDataTable($table)) {
                return $table.DataTable();
            }

            const userInitComplete = options && options.initComplete;
            const settings = Object.assign({
                responsive: true,
                paging: false,
                info: false,
                searching: true,
                order: [],
                language: {
                    search: 'Search:'
                }
            }, options || {});

            settings.initComplete = function() {
                if (typeof userInitComplete === 'function') {
                    userInitComplete.call(this, settings);
                }
            };

            return $table.DataTable(settings);
        };
    </script>

    @yield('scripts')
</body>

</html>
