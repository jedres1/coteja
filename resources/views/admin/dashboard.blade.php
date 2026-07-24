<x-layouts.app title="Dashboard | Coteja">
    <div class="top"><h1>Dashboard</h1><span class="muted">Control general de plataforma</span></div>
    <div class="grid">
        <div class="card"><div class="muted">Clientes</div><div class="metric">{{ $customersCount }}</div></div>
        <div class="card"><div class="muted">Licencias activas</div><div class="metric">{{ $activeLicensesCount }}</div></div>
        <div class="card"><div class="muted">Licencias vencidas</div><div class="metric">{{ $expiredLicensesCount }}</div></div>
        <div class="card"><div class="muted">Cobrado este mes</div><div class="metric">${{ number_format($monthlyRevenue, 2) }}</div></div>
    </div>
    <div class="grid" style="margin-top:16px;">
        <div class="card">
            <h3>Licencias recientes</h3>
            <table><tr><th>Cliente</th><th>Empresa</th><th>Plan</th><th>Estado</th><th>Vence</th></tr>
                @foreach($recentLicenses as $license)
                    <tr><td>{{ $license->customer->name }}</td><td>{{ $license->company->business_name }}</td><td>{{ $license->plan->name }}</td><td><span class="badge {{ $license->status }}">{{ $license->status }}</span></td><td>{{ optional($license->expires_at)->format('Y-m-d') }}</td></tr>
                @endforeach
            </table>
        </div>
        <div class="card">
            <h3>Backups recientes</h3>
            <table><tr><th>Cliente</th><th>Empresa</th><th>Fecha</th><th>Tamano</th></tr>
                @foreach($recentBackups as $backup)
                    <tr><td>{{ $backup->license->customer->name }}</td><td>{{ $backup->company->business_name }}</td><td>{{ optional($backup->uploaded_at)->format('Y-m-d H:i') }}</td><td>{{ number_format($backup->size_bytes / 1024, 1) }} KB</td></tr>
                @endforeach
            </table>
        </div>
    </div>
</x-layouts.app>
