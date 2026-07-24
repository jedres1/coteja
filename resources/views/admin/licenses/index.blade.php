<x-layouts.app title="Licencias | Coteja">
    @php($licenseStatuses = ['active' => 'Activa', 'expired' => 'Vencida', 'suspended' => 'Suspendida'])

    <div class="top">
        <div>
            <h1>Licencias</h1>
            <span class="muted">Control de acceso, vigencia y limites</span>
        </div>
        <details class="overlay-modal" @if($errors->any()) open @endif>
            <summary class="btn">Nueva licencia</summary>
            <div class="overlay-layer">
                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                <div class="card overlay-panel">
                    <div class="overlay-header">
                        <div>
                            <h3>Nueva licencia</h3>
                            <span class="muted">Asigne empresa, plan, fechas y límites.</span>
                        </div>
                        <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                    </div>
                    <form method="post" action="{{ route('admin.licenses.store') }}" class="form-grid">
                        @csrf
                        <label>Empresa
                            <select name="company_id" required>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>{{ $company->business_name }} - {{ $company->customer->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Plan
                            <select name="plan_id" required>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected((string) old('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        @include('admin.licenses.form', ['license' => null, 'licenseStatuses' => $licenseStatuses])
                        <div class="form-actions full-width"><button class="btn">Crear licencia</button></div>
                    </form>
                </div>
            </div>
        </details>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Listado</h3>
                <span class="muted">Mostrando {{ $licenses->count() }} de {{ $licenses->total() }} licencias</span>
            </div>
            <span class="badge">{{ $licenses->perPage() }} por página</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Licencia</th><th>Cliente</th><th>Empresa</th><th>Plan</th><th>Estado</th><th>Vence</th><th>Limites</th><th>Accion</th></tr></thead>
                <tbody>
                @forelse($licenses as $license)
                    <tr>
                        <td><code>{{ $license->license_key }}</code></td>
                        <td>{{ $license->customer->name }}</td>
                        <td>{{ $license->company->business_name }}</td>
                        <td>{{ $license->plan->name }}</td>
                        <td><span class="badge {{ $license->status }}">{{ $licenseStatuses[$license->status] ?? $license->status }}</span></td>
                        <td>{{ optional($license->expires_at)->toDateString() }}</td>
                        <td>{{ $license->max_users }} usuarios · {{ $license->max_devices }} equipos · {{ $license->grace_days }} gracia</td>
                        <td>
                            <details class="overlay-modal">
                                <summary class="btn secondary">Editar</summary>
                                <div class="overlay-layer">
                                    <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                    <div class="card overlay-panel">
                                        <div class="overlay-header">
                                            <div>
                                                <h3>Editar licencia</h3>
                                                <span class="muted">{{ $license->license_key }}</span>
                                            </div>
                                            <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                        </div>
                                        <form method="post" action="{{ route('admin.licenses.update', $license) }}" class="form-grid">
                                            @csrf
                                            @method('put')
                                            @include('admin.licenses.form', ['license' => $license, 'licenseStatuses' => $licenseStatuses])
                                            <div class="form-actions full-width"><button class="btn">Guardar cambios</button></div>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No hay licencias registradas</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $licenses->links() }}</div>
    </div>
</x-layouts.app>
