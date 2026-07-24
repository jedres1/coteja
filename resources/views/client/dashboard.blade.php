<x-layouts.app title="Mi cuenta | Coteja">
    <div class="top">
        <div>
            <h1>Mi cuenta</h1>
            <span class="muted">{{ $customer?->name }}</span>
        </div>
        <form method="post" action="{{ route('client.companies.switch') }}" style="display:flex;gap:8px;align-items:end;">
            @csrf
            <label>Empresa
                <select name="company_id" onchange="this.form.submit()">
                    <option value="">Seleccione empresa...</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected($activeCompany?->is($company))>{{ $company->business_name }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>
    @if($companies->count() > 1 && !$activeCompany)
        <div class="card" style="margin-bottom:16px;">
            <h3>Seleccione empresa</h3>
            <p class="muted">Elija a qué empresa desea conectarse para ver la información disponible.</p>
        </div>
    @endif
    <div class="card">
        <h3>Descargar app</h3>
        <div class="grid">
            @forelse($releases as $release)
                <div class="card">
                    <strong>{{ strtoupper($release->platform) }} {{ $release->version }}</strong>
                    <p class="muted">{{ $release->filename }}</p>
                    <a class="btn" href="{{ $release->download_url }}" target="_blank">Descargar</a>
                </div>
            @empty
                <p class="muted">Aun no hay instaladores publicados.</p>
            @endforelse
        </div>
    </div>
    <div class="card" style="margin-top:16px;">
        <h3>Mis licencias y backups @if($activeCompany)<span class="muted">· {{ $activeCompany->business_name }}</span>@endif</h3>
        <table><tr><th>Empresa</th><th>Plan</th><th>Estado</th><th>Vence</th><th>Ultimo backup</th><th>Accion</th></tr>
            @forelse($licenses as $license)
                @php($backup = $license->backups->sortByDesc('uploaded_at')->first())
                <tr>
                    <td>{{ $license->company->business_name }}</td><td>{{ $license->plan->name }}</td><td><span class="badge {{ $license->status }}">{{ $license->status }}</span></td><td>{{ optional($license->expires_at)->format('Y-m-d') }}</td>
                    <td>{{ $backup ? $backup->uploaded_at->format('Y-m-d H:i') : 'Sin backup' }}</td>
                    <td>@if($backup)<a class="btn secondary" href="{{ route('client.backups.download', $backup) }}">Descargar backup</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ $activeCompany ? 'No hay licencias para esta empresa.' : 'Seleccione una empresa para continuar.' }}</td></tr>
            @endforelse
        </table>
    </div>
</x-layouts.app>
