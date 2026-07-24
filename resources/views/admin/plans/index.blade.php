<x-layouts.app title="Planes | Coteja">
    <div class="top">
        <div>
            <h1>Planes</h1>
            <span class="muted">Precios, usuarios, soporte y backup</span>
        </div>
        <details class="overlay-modal" @if($errors->any()) open @endif>
            <summary class="btn">Nuevo plan</summary>
            <div class="overlay-layer">
                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                <div class="card overlay-panel">
                    <div class="overlay-header">
                        <div>
                            <h3>Nuevo plan</h3>
                            <span class="muted">Configure precios, límites y beneficios.</span>
                        </div>
                        <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                    </div>
                    <form method="post" action="{{ route('admin.plans.store') }}" class="form-grid">
                        @csrf
                        @include('admin.plans.fields', ['plan' => null])
                        <div class="form-actions full-width"><button class="btn">Crear plan</button></div>
                    </form>
                </div>
            </div>
        </details>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Listado</h3>
                <span class="muted">{{ $plans->count() }} planes configurados</span>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Plan</th><th>Precio</th><th>Implementacion</th><th>Limites</th><th>Beneficios</th><th>Estado</th><th>Accion</th></tr></thead>
                <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td><strong>{{ $plan->name }}</strong><br><span class="muted">{{ $plan->code }}</span></td>
                        <td>${{ number_format($plan->monthly_price, 2) }} mensual<br><span class="muted">${{ number_format($plan->annual_price, 2) }} anual</span></td>
                        <td>${{ number_format($plan->implementation_fee, 2) }}<br><span class="muted">${{ number_format($plan->additional_user_price, 2) }} usuario adicional</span></td>
                        <td>{{ $plan->included_users }} usuarios · {{ $plan->max_devices }} equipos</td>
                        <td>
                            {{ $plan->support_included ? 'Soporte' : 'Sin soporte' }} ·
                            {{ $plan->cloud_backup ? 'Backup' : 'Sin backup' }} ·
                            {{ $plan->unlimited_documents ? 'Docs ilimitados' : 'Docs limitados' }}
                        </td>
                        <td><span class="badge {{ $plan->is_active ? 'active' : 'suspended' }}">{{ $plan->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td>
                            <details class="overlay-modal">
                                <summary class="btn secondary">Editar</summary>
                                <div class="overlay-layer">
                                    <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                    <div class="card overlay-panel">
                                        <div class="overlay-header">
                                            <div>
                                                <h3>Editar plan</h3>
                                                <span class="muted">{{ $plan->name }}</span>
                                            </div>
                                            <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                        </div>
                                        <form method="post" action="{{ route('admin.plans.update', $plan) }}" class="form-grid">
                                            @csrf
                                            @method('put')
                                            @include('admin.plans.fields', ['plan' => $plan])
                                            <div class="form-actions full-width"><button class="btn">Guardar cambios</button></div>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No hay planes configurados</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
