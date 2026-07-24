<x-layouts.app title="Empresas | Coteja">
    @php($environments = ['production' => 'Produccion', 'testing' => 'Pruebas'])

    <div class="top">
        <div>
            <h1>Empresas</h1>
            <span class="muted">Emisores vinculados a clientes</span>
        </div>
        <details class="overlay-modal" @if($errors->any()) open @endif>
            <summary class="btn">Nueva empresa</summary>
            <div class="overlay-layer">
                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                <div class="card overlay-panel">
                    <div class="overlay-header">
                        <div>
                            <h3>Nueva empresa</h3>
                            <span class="muted">Registre el emisor y vincúlelo a un cliente.</span>
                        </div>
                        <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                    </div>
                    <form method="post" action="{{ route('admin.companies.store') }}" class="form-grid">
                        @csrf
                        @include('admin.companies.form', ['company' => null, 'customers' => $customers, 'environments' => $environments])
                        <div class="form-actions full-width"><button class="btn">Crear empresa</button></div>
                    </form>
                </div>
            </div>
        </details>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Listado</h3>
                <span class="muted">Mostrando {{ $companies->count() }} de {{ $companies->total() }} empresas</span>
            </div>
            <span class="badge">{{ $companies->perPage() }} por página</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Empresa</th><th>Cliente</th><th>NIT/DUI</th><th>NRC</th><th>Contacto</th><th>Ambiente</th><th>Acción</th></tr></thead>
                <tbody>
                @forelse($companies as $company)
                    <tr>
                        <td><strong>{{ $company->business_name }}</strong>@if($company->trade_name)<br><span class="muted">{{ $company->trade_name }}</span>@endif</td>
                        <td>{{ $company->customer->name }}</td>
                        <td>{{ $company->nit_dui }}</td>
                        <td>{{ $company->nrc ?: '—' }}</td>
                        <td>{{ $company->email ?: '—' }}@if($company->phone)<br><span class="muted">{{ $company->phone }}</span>@endif</td>
                        <td><span class="badge">{{ $environments[$company->environment] ?? $company->environment }}</span></td>
                        <td>
                            <details class="overlay-modal">
                                <summary class="btn secondary">Editar</summary>
                                <div class="overlay-layer">
                                    <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                    <div class="card overlay-panel">
                                        <div class="overlay-header">
                                            <div>
                                                <h3>Editar empresa</h3>
                                                <span class="muted">{{ $company->business_name }}</span>
                                            </div>
                                            <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                        </div>
                                        <form method="post" action="{{ route('admin.companies.update', $company) }}" class="form-grid">
                                            @csrf
                                            @method('put')
                                            @include('admin.companies.form', ['company' => $company, 'customers' => $customers, 'environments' => $environments])
                                            <div class="form-actions full-width"><button class="btn">Guardar cambios</button></div>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No hay empresas registradas</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $companies->links() }}</div>
    </div>
</x-layouts.app>
