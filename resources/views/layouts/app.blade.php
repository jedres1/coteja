<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Coteja' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --bg:#f6f7fb; --panel:#fff; --ink:#172033; --muted:#6b7280; --line:#e5e7eb; --brand:#2563eb; --ok:#059669; --warn:#d97706; --bad:#dc2626; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter, system-ui, -apple-system, Segoe UI, sans-serif; background:var(--bg); color:var(--ink); }
        body.dark-mode { --bg:#0b1220; --panel:#111827; --ink:#f9fafb; --muted:#9ca3af; --line:#263244; --brand:#3b82f6; --ok:#34d399; --warn:#f59e0b; --bad:#f87171; }
        a { color:inherit; text-decoration:none; }
        .shell { display:grid; grid-template-columns:240px 1fr; min-height:100vh; }
        .side { background:#111827; color:#f9fafb; padding:22px 16px; }
        body.dark-mode .side { background:#020617; }
        .brand { display:grid; align-items:center; justify-items:center; margin-bottom:24px; }
        .brand img { width:136px; max-width:100%; max-height:136px; height:auto; display:block; object-fit:contain; }
        .nav { display:grid; gap:6px; align-items:stretch; }
        .nav a, .logout-btn { width:100%; min-height:40px; display:flex; align-items:center; justify-content:flex-start; padding:10px 12px; border-radius:8px; color:#d1d5db; background:transparent; border:0; text-align:left; font:inherit; line-height:1.2; cursor:pointer; }
        .nav a:hover, .logout-btn:hover { background:#1f2937; color:#fff; }
        .nav a.active-link { background:#2563eb; color:#fff; }
        .nav form { margin:0; }
        .nav-group { display:grid; gap:6px; margin-top:8px; padding-top:8px; border-top:1px solid rgb(255 255 255 / .12); }
        .nav-toggle { width:100%; min-height:40px; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border:0; border-radius:8px; background:transparent; color:#9ca3af; font:inherit; line-height:1.2; cursor:pointer; }
        .nav-toggle:hover { background:#1f2937; color:#fff; }
        .nav-label { flex:1; min-width:0; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
        .nav-caret { flex:0 0 auto; display:grid; place-items:center; width:16px; height:16px; transition:transform .18s ease; font-size:12px; line-height:1; }
        .nav-group.open .nav-caret { transform:rotate(90deg); }
        .nav-group:not(.open) .nav-sub { display:none; }
        .nav-sub { display:grid; gap:4px; }
        .nav-sub a { min-height:36px; padding-left:24px; font-size:13px; }
        .main { padding:24px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; }
        .top h1 { margin:0; font-size:26px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:18px; box-shadow:0 1px 2px rgb(0 0 0 / .04); }
        .card h2, .card h3 { margin:0 0 14px; }
        .metric { font-size:30px; font-weight:800; }
        .muted { color:var(--muted); font-size:14px; }
        table { width:100%; border-collapse:collapse; background:var(--panel); border:1px solid var(--line); border-radius:10px; overflow:hidden; }
        th, td { padding:11px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; font-size:14px; }
        th { background:#f9fafb; color:#4b5563; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
        body.dark-mode th { background:#0f172a; color:#cbd5e1; }
        tr:last-child td { border-bottom:0; }
        form.inline { display:inline; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }
        label { display:grid; gap:6px; font-size:13px; color:#374151; font-weight:600; }
        input, select, textarea { width:100%; padding:10px 11px; border:1px solid var(--line); border-radius:8px; font:inherit; background:#fff; color:var(--ink); }
        body.dark-mode input, body.dark-mode select, body.dark-mode textarea { background:#0f172a; color:#f9fafb; }
        textarea { min-height:80px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:9px 13px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:700; cursor:pointer; }
        .btn.secondary { background:#4b5563; }
        .badge { display:inline-flex; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:700; background:#e5e7eb; }
        .active { background:#dcfce7; color:#166534; }
        .suspended, .expired { background:#fee2e2; color:#991b1b; }
        .notice { padding:10px 12px; border-radius:8px; background:#ecfdf5; color:#166534; margin-bottom:14px; }
        .errors { padding:10px 12px; border-radius:8px; background:#fef2f2; color:#991b1b; margin-bottom:14px; }
        .theme-toggle { margin-top:14px; display:flex; justify-content:center; }
        .theme-toggle button { width:52px; height:30px; display:flex; align-items:center; padding:3px; border:1px solid rgb(255 255 255 / .16); border-radius:999px; background:#1f2937; color:#f9fafb; cursor:pointer; font:inherit; }
        .theme-toggle button span { width:22px; height:22px; display:grid; place-items:center; border-radius:50%; background:#f9fafb; color:#111827; transition:transform .18s ease; }
        body.dark-mode .theme-toggle button span { transform:translateX(20px); background:#111827; color:#fbbf24; }
        @media (max-width:800px){ .shell{grid-template-columns:1fr}.side{position:static}.main{padding:16px} }
    </style>
</head>
<body>
@auth
    <div class="shell">
        <aside class="side">
            <div class="brand">
                <img src="/images/facturacion-electron-logo.png" alt="CONSULTING AND TECH JANDRES">
            </div>
            @php($facturaView = request('view'))
            <nav class="nav">
                @if(auth()->user()->canAccessAdmin())
                    <a class="{{ request()->routeIs('admin.dashboard') ? 'active-link' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
                    @if(auth()->user()->isAdmin())
                        <div class="nav-group {{ request()->routeIs('admin.users.*', 'admin.companies.*', 'admin.plans.*', 'admin.licenses.*', 'admin.payments.*', 'admin.releases.*', 'admin.backups.*') ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.users.*', 'admin.companies.*', 'admin.plans.*', 'admin.licenses.*', 'admin.payments.*', 'admin.releases.*', 'admin.backups.*') ? 'true' : 'false' }}">
                                <span class="nav-label">Administración de negocio</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.users.*') ? 'active-link' : '' }}" href="{{ route('admin.users.index') }}">Usuarios y acceso</a>
                                <a class="{{ request()->routeIs('admin.companies.*') ? 'active-link' : '' }}" href="{{ route('admin.companies.index') }}">Empresas</a>
                                <a class="{{ request()->routeIs('admin.plans.*') ? 'active-link' : '' }}" href="{{ route('admin.plans.index') }}">Planes</a>
                                <a class="{{ request()->routeIs('admin.licenses.*') ? 'active-link' : '' }}" href="{{ route('admin.licenses.index') }}">Licencias</a>
                                <a class="{{ request()->routeIs('admin.payments.*') ? 'active-link' : '' }}" href="{{ route('admin.payments.index') }}">Cobros</a>
                                <a class="{{ request()->routeIs('admin.releases.*') ? 'active-link' : '' }}" href="{{ route('admin.releases.index') }}">Descargas</a>
                                <a class="{{ request()->routeIs('admin.backups.*') ? 'active-link' : '' }}" href="{{ route('admin.backups.index') }}">Backups</a>
                            </div>
                        </div>
                        <div class="nav-group {{ request()->routeIs('admin.purchase-invoices.*', 'admin.suppliers.*') ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.purchase-invoices.*', 'admin.suppliers.*') ? 'true' : 'false' }}">
                                <span class="nav-label">Compras</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.purchase-invoices.pending-approval') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.pending-approval') }}">Extraer facturas</a>
                                <a class="{{ request()->routeIs('admin.purchase-invoices.index') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.index') }}">Facturas de compra</a>
                                <a class="{{ request()->routeIs('admin.purchase-invoices.accounts-payable') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.accounts-payable') }}">Cuentas por pagar</a>
                                <a class="{{ request()->routeIs('admin.suppliers.*') ? 'active-link' : '' }}" href="{{ route('admin.suppliers.index') }}">Proveedores</a>
                                <a class="{{ request()->routeIs('admin.purchase-invoices.settings') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.settings') }}">Configuración</a>
                            </div>
                        </div>
                        <div class="nav-group {{ request()->routeIs('admin.bank-transactions.*') ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.bank-transactions.*') ? 'true' : 'false' }}">
                                <span class="nav-label">Control de bancos</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.bank-transactions.*') ? 'active-link' : '' }}" href="{{ route('admin.bank-transactions.index') }}">Control bancario</a>
                            </div>
                        </div>
                        <div class="nav-group">
                            <button class="nav-toggle" type="button" aria-expanded="false">
                                <span class="nav-label">Contabilidad</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a href="#">Diario contable</a>
                                <a href="#">Catálogo de cuentas</a>
                            </div>
                        </div>
                        <div class="nav-group {{ request()->routeIs('admin.factura-sv') && $facturaView === 'productos' ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'productos' ? 'true' : 'false' }}">
                                <span class="nav-label">Inventarios</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'productos' ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'productos']) }}">Productos</a>
                            </div>
                        </div>
                        <div class="nav-group {{ request()->routeIs('admin.factura-sv', 'admin.customers.*') ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.factura-sv', 'admin.customers.*') ? 'true' : 'false' }}">
                                <span class="nav-label">Facturación</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.factura-sv') && (!$facturaView || $facturaView === 'dashboard') ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'dashboard']) }}">Dashboard</a>
                                <a class="{{ request()->routeIs('admin.customers.*') ? 'active-link' : '' }}" href="{{ route('admin.customers.index') }}">Clientes</a>
                                <a class="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'nueva-factura' ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'nueva-factura']) }}">Nueva Factura</a>
                                <a class="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'facturas' ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'facturas']) }}">Facturas</a>
                                <a class="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'configuracion' ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'configuracion']) }}">Configuración</a>
                            </div>
                        </div>
                    @endif
                @else
                    <a class="{{ request()->routeIs('client.dashboard') ? 'active-link' : '' }}" href="{{ route('client.dashboard') }}">Mi cuenta</a>
                    @if(auth()->user()->hasModuleAccess('purchases'))
                        <div class="nav-group {{ request()->routeIs('admin.purchase-invoices.*', 'admin.suppliers.*') ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.purchase-invoices.*', 'admin.suppliers.*') ? 'true' : 'false' }}">
                                <span class="nav-label">Compras</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.purchase-invoices.pending-approval') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.pending-approval') }}">Extraer facturas</a>
                                <a class="{{ request()->routeIs('admin.purchase-invoices.index') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.index') }}">Facturas de compra</a>
                                <a class="{{ request()->routeIs('admin.purchase-invoices.accounts-payable') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.accounts-payable') }}">Cuentas por pagar</a>
                                <a class="{{ request()->routeIs('admin.suppliers.*') ? 'active-link' : '' }}" href="{{ route('admin.suppliers.index') }}">Proveedores</a>
                                <a class="{{ request()->routeIs('admin.purchase-invoices.settings') ? 'active-link' : '' }}" href="{{ route('admin.purchase-invoices.settings') }}">Configuración</a>
                            </div>
                        </div>
                        <div class="nav-group {{ request()->routeIs('admin.bank-transactions.*') ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.bank-transactions.*') ? 'true' : 'false' }}">
                                <span class="nav-label">Control de bancos</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.bank-transactions.*') ? 'active-link' : '' }}" href="{{ route('admin.bank-transactions.index') }}">Control bancario</a>
                            </div>
                        </div>
                    @endif
                    @if(auth()->user()->hasModuleAccess('billing'))
                        <div class="nav-group {{ request()->routeIs('admin.factura-sv') && $facturaView === 'productos' ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'productos' ? 'true' : 'false' }}">
                                <span class="nav-label">Inventarios</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'productos' ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'productos']) }}">Productos</a>
                            </div>
                        </div>
                        <div class="nav-group {{ request()->routeIs('admin.factura-sv', 'admin.factura-sv.*') ? 'open' : '' }}">
                            <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('admin.factura-sv', 'admin.factura-sv.*') ? 'true' : 'false' }}">
                                <span class="nav-label">Facturación</span>
                                <span class="nav-caret">›</span>
                            </button>
                            <div class="nav-sub">
                                <a class="{{ request()->routeIs('admin.factura-sv') && (!$facturaView || $facturaView === 'dashboard') ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'dashboard']) }}">Dashboard</a>
                                <a class="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'nueva-factura' ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'nueva-factura']) }}">Nueva Factura</a>
                                <a class="{{ request()->routeIs('admin.factura-sv.facturas') ? 'active-link' : '' }}" href="{{ route('admin.factura-sv.facturas') }}">Facturas</a>
                                <a class="{{ request()->routeIs('admin.factura-sv') && $facturaView === 'configuracion' ? 'active-link' : '' }}" href="{{ route('admin.factura-sv', ['view' => 'configuracion']) }}">Configuración</a>
                            </div>
                        </div>
                    @endif
                @endif
                <form method="post" action="{{ route('logout') }}" id="logout-form">@csrf<button class="logout-btn">Salir</button></form>
                <div class="theme-toggle">
                    <button type="button" id="theme-toggle" aria-label="Cambiar modo oscuro" title="Cambiar modo oscuro"><span id="theme-icon">☾</span></button>
                </div>
            </nav>
        </aside>
        <main class="main">
            @if(session('status')) <div class="notice">{{ session('status') }}</div> @endif
            @if($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif
            {{ $slot }}
        </main>
    </div>
@else
    {{ $slot }}
@endauth
<script>
    const storedTheme = localStorage.getItem('coteja-theme');
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    document.body.classList.toggle('dark-mode', storedTheme ? storedTheme === 'dark' : prefersDark);

    function syncThemeIcon() {
        const icon = document.getElementById('theme-icon');
        if (icon) icon.textContent = document.body.classList.contains('dark-mode') ? '☀' : '☾';
    }

    syncThemeIcon();

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        const isDark = document.body.classList.toggle('dark-mode');
        localStorage.setItem('coteja-theme', isDark ? 'dark' : 'light');
        syncThemeIcon();
    });

    document.getElementById('logout-form')?.addEventListener('submit', (event) => {
        if (!confirm('Se va a cerrar la sesión. ¿Desea continuar?')) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('.nav-toggle').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const group = toggle.closest('.nav-group');
            const isOpen = !group.classList.contains('open');
            document.querySelectorAll('.nav-group.open').forEach((openGroup) => {
                openGroup.classList.remove('open');
                openGroup.querySelector('.nav-toggle')?.setAttribute('aria-expanded', 'false');
            });
            group.classList.toggle('open', isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    });
</script>
</body>
</html>
