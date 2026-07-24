<x-layouts.app title="Proveedores | Coteja">
    @php
        $documentTypes = [
            '13' => 'DUI',
            '36' => 'NIT',
            '37' => 'Otro / extranjero',
            '03' => 'Pasaporte',
            '02' => 'Carnet residente',
        ];
        $statuses = [
            'active' => 'Activo',
            'prospect' => 'Prospecto',
            'suspended' => 'Suspendido',
        ];
        $departments = $geography['departamentos'] ?? [];
        $municipalitiesByDepartment = $geography['municipios'] ?? [];
        $municipalitiesFlat = collect($municipalitiesByDepartment)
            ->flatMap(fn ($items, $departmentCode) => collect($items)->map(fn ($item) => [
                'codigo' => $item['codigo'],
                'nombre' => $item['nombre'],
                'departamento' => $departmentCode,
            ]))
            ->values();
    @endphp

    <div class="top suppliers-top">
        <div>
            <h1>Proveedores</h1>
            <span class="muted">CRUD de proveedores con datos fiscales y de contacto</span>
        </div>
        <details class="overlay-modal" @if($errors->any()) open @endif>
            <summary class="btn">Nuevo proveedor</summary>
            <div class="overlay-layer">
                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                <div class="card overlay-panel wide">
                    <div class="overlay-header">
                        <div>
                            <h3>Crear proveedor</h3>
                            <span class="muted">Complete los datos comerciales y fiscales.</span>
                        </div>
                        <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                    </div>
                    @include('admin.suppliers.partials.form', [
                        'supplier' => null,
                        'action' => route('admin.suppliers.store'),
                        'method' => null,
                        'documentTypes' => $documentTypes,
                        'statuses' => $statuses,
                        'departments' => $departments,
                        'municipalitiesFlat' => $municipalitiesFlat,
                    ])
                </div>
            </div>
        </details>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Listado</h3>
                <span class="muted">
                    Mostrando {{ $suppliers->count() }} de {{ $suppliers->total() }} proveedores
                    @if($search)
                        para "{{ $search }}"
                    @endif
                </span>
            </div>
            <form method="get" action="{{ route('admin.suppliers.index') }}" class="supplier-search">
                <label class="sr-only" for="supplier-search">Buscar proveedores</label>
                <input id="supplier-search" name="search" type="search" value="{{ $search }}" placeholder="Buscar proveedor, email, documento, NRC...">
                <button class="btn" type="submit">Buscar</button>
                @if($search)
                    <a class="btn secondary" href="{{ route('admin.suppliers.index') }}">Limpiar</a>
                @endif
            </form>
            <span class="badge">{{ $suppliers->perPage() }} por página</span>
        </div>

        <div class="table-scroll">
            <table class="suppliers-table">
                <thead>
                    <tr>
                        <th>Proveedor</th>
                        <th>Documento</th>
                        <th>Actividad</th>
                        <th>Contacto</th>
                        <th>Estado</th>
                        <th>Facturas</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>
                                <strong>{{ $supplier->name }}</strong>
                                @if($supplier->trade_name)
                                    <br><span class="muted">{{ $supplier->trade_name }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $documentTypes[$supplier->document_type] ?? $supplier->document_type }}
                                <br><span class="muted">{{ $supplier->document_number }}</span>
                                @if($supplier->nrc)
                                    <br><span class="muted">NRC {{ $supplier->nrc }}</span>
                                @endif
                            </td>
                            <td>
                                @if($supplier->business_activity)
                                    {{ $supplier->business_activity }}
                                    <br><span class="muted">{{ $supplier->activity_description }}</span>
                                @else
                                    <span class="muted">Sin actividad</span>
                                @endif
                            </td>
                            <td>
                                {{ $supplier->email ?: 'Sin email' }}
                                @if($supplier->phone)
                                    <br><span class="muted">{{ $supplier->phone }}</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $supplier->status }}">{{ $statuses[$supplier->status] ?? $supplier->status }}</span></td>
                            <td>
                                <a href="{{ route('admin.purchase-invoices.index', ['supplier_id' => $supplier->id]) }}">{{ $supplier->purchase_invoices_count }}</a>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <details class="overlay-modal">
                                        <summary class="btn secondary">Editar</summary>
                                        <div class="overlay-layer">
                                            <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                            <div class="card overlay-panel wide">
                                                <div class="overlay-header">
                                                    <div>
                                                        <h3>Editar proveedor</h3>
                                                        <span class="muted">{{ $supplier->name }}</span>
                                                    </div>
                                                    <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                                </div>
                                                @include('admin.suppliers.partials.form', [
                                                    'supplier' => $supplier,
                                                    'action' => route('admin.suppliers.update', $supplier),
                                                    'method' => 'put',
                                                    'documentTypes' => $documentTypes,
                                                    'statuses' => $statuses,
                                                    'departments' => $departments,
                                                    'municipalitiesFlat' => $municipalitiesFlat,
                                                ])
                                            </div>
                                        </div>
                                    </details>
                                    <form method="post" action="{{ route('admin.suppliers.destroy', $supplier) }}" class="inline" data-confirm-delete="Eliminar proveedor">
                                        @csrf
                                        @method('delete')
                                        <button class="btn danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No hay proveedores registrados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $suppliers->links() }}
        </div>
    </div>

    <style>
        .suppliers-top { align-items: flex-start; }
        .supplier-form-grid { grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }
        .supplier-search { display: flex; align-items: center; gap: 8px; flex: 1 1 420px; justify-content: flex-end; }
        .supplier-search input { max-width: 360px; }
        .suppliers-table td { vertical-align: middle; }
        .prospect { background: #fef3c7; color: #92400e; }
        .danger { background: var(--bad); }
        .row-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pagination-wrap nav > div:first-child { display: none; }
        .pagination-wrap nav > div:last-child,
        .pagination-wrap nav .relative { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pagination-wrap a,
        .pagination-wrap span[aria-current] span,
        .pagination-wrap span[aria-disabled] span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            min-height: 36px;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
        }
        .pagination-wrap span[aria-current] span { background: var(--brand); color: #fff; border-color: var(--brand); }
        @media (max-width: 800px) {
            .suppliers-top { display: grid; }
            .supplier-search { flex: 1 1 100%; justify-content: stretch; }
            .supplier-search input { max-width: none; }
        }
    </style>
    <script>
        document.querySelectorAll('.supplier-form-grid').forEach((form) => {
            const department = form.querySelector('.supplier-department');
            const municipality = form.querySelector('.supplier-municipality');
            if (!department || !municipality) return;

            const syncMunicipalities = () => {
                const departmentCode = department.value;
                let firstVisibleValue = '';

                municipality.querySelectorAll('option').forEach((option) => {
                    const optionDepartment = option.dataset.department;
                    const visible = !optionDepartment || optionDepartment === departmentCode;
                    option.hidden = !visible;
                    if (visible && option.value && !firstVisibleValue) firstVisibleValue = option.value;
                });

                const selectedOption = municipality.selectedOptions[0];
                if (selectedOption?.hidden) {
                    municipality.value = firstVisibleValue;
                }
            };

            department.addEventListener('change', syncMunicipalities);
            syncMunicipalities();
        });

        document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!confirm(`${form.dataset.confirmDelete}. ¿Desea continuar?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
</x-layouts.app>
