<x-layouts.app title="Clientes | Coteja">
    @php
        $documentTypes = [
            '13' => 'DUI',
            '36' => 'NIT',
            '37' => 'Otro / extranjero',
            '03' => 'Pasaporte',
            '02' => 'Carnet residente',
        ];
        $dteTypes = [
            '01' => 'Factura',
            '03' => 'CCF',
            '05' => 'Nota crédito',
            '06' => 'Nota débito',
            '07' => 'Retención',
            '11' => 'Exportación',
            '14' => 'Sujeto excluido',
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

    <div class="top customers-top">
        <div>
            <h1>Clientes</h1>
            <span class="muted">Lista paginada de clientes de portal y facturación electrónica</span>
        </div>
        <details class="overlay-modal" @if($errors->any()) open @endif>
            <summary class="btn">Nuevo cliente</summary>
            <div class="overlay-layer">
                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                <div class="card overlay-panel wide">
                    <div class="overlay-header">
                        <div>
                            <h3>Crear cliente</h3>
                            <span class="muted">Complete los datos de portal y facturación.</span>
                        </div>
                        <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                    </div>
                    <form method="post" action="{{ route('admin.customers.store') }}" class="form-grid customer-form-grid">
                        @csrf
                        <label>Nombre / Razón social<input name="name" value="{{ old('name') }}" required></label>
                    <label>Email portal<input type="email" name="email" value="{{ old('email') }}" required></label>
                    <label>Teléfono portal<input name="phone" value="{{ old('phone') }}"></label>
                    <label>Estado
                        <select name="status">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Clave portal cliente<input name="password" type="password" required minlength="8"></label>
                    <label>Tipo documento
                        <select name="document_type" required>
                            @foreach($documentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type', '13') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Número documento<input name="document_number" value="{{ old('document_number') }}" required></label>
                    <label>NRC<input name="nrc" value="{{ old('nrc') }}"></label>
                    <label>Nombre comercial<input name="trade_name" value="{{ old('trade_name') }}"></label>
                    <label>Tipo DTE preferido
                        <select name="preferred_dte_type" required>
                            @foreach($dteTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('preferred_dte_type', '01') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Actividad económica<input name="business_activity" value="{{ old('business_activity') }}" placeholder="62020"></label>
                    <label>Descripción actividad<input name="activity_description" value="{{ old('activity_description') }}"></label>
                    <label>Departamento
                        <select name="address_department" class="customer-department" required>
                            <option value="">Seleccionar departamento...</option>
                            @foreach($departments as $department)
                                <option value="{{ $department['codigo'] }}" @selected(old('address_department') === $department['codigo'])>
                                    {{ $department['codigo'] }} - {{ $department['nombre'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>Municipio
                        <select name="address_municipality" class="customer-municipality" required>
                            <option value="">Seleccionar municipio...</option>
                            @foreach($municipalitiesFlat as $municipality)
                                <option value="{{ $municipality['codigo'] }}" data-department="{{ $municipality['departamento'] }}" @selected(old('address_municipality') === $municipality['codigo'])>
                                    {{ $municipality['codigo'] }} - {{ $municipality['nombre'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="full-width">Dirección<input name="address" value="{{ old('address') }}" required></label>
                    <label>Email facturación<input type="email" name="billing_email" value="{{ old('billing_email') }}"></label>
                    <label>Teléfono facturación<input name="billing_phone" value="{{ old('billing_phone') }}"></label>
                        <div class="form-actions full-width">
                            <button class="btn">Crear cliente</button>
                        </div>
                    </form>
                </div>
            </div>
        </details>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Listado</h3>
                <span class="muted">
                    Mostrando {{ $customers->count() }} de {{ $customers->total() }} clientes
                    @if($search)
                        para “{{ $search }}”
                    @endif
                </span>
            </div>
            <form method="get" action="{{ route('admin.customers.index') }}" class="customer-search">
                <label class="sr-only" for="customer-search">Buscar clientes</label>
                <input id="customer-search" name="search" type="search" value="{{ $search }}" placeholder="Buscar cliente, email, documento, NRC...">
                <button class="btn" type="submit">Buscar</button>
                @if($search)
                    <a class="btn secondary" href="{{ route('admin.customers.index') }}">Limpiar</a>
                @endif
            </form>
            <span class="badge">{{ $customers->perPage() }} por página</span>
        </div>

        <div class="table-scroll">
            <table class="customers-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Documento</th>
                        <th>Facturación</th>
                        <th>Contacto</th>
                        <th>Estado</th>
                        <th>Empresas</th>
                        <th>Licencias</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>
                                <strong>{{ $customer->name }}</strong>
                                @if($customer->trade_name)
                                    <br><span class="muted">{{ $customer->trade_name }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $documentTypes[$customer->document_type] ?? $customer->document_type }}
                                <br><span class="muted">{{ $customer->document_number }}</span>
                                @if($customer->nrc)
                                    <br><span class="muted">NRC {{ $customer->nrc }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $dteTypes[$customer->preferred_dte_type] ?? $customer->preferred_dte_type }}
                                @if($customer->business_activity)
                                    <br><span class="muted">{{ $customer->business_activity }} {{ $customer->activity_description }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $customer->email }}
                                @if($customer->phone)
                                    <br><span class="muted">{{ $customer->phone }}</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $customer->status }}">{{ $statuses[$customer->status] ?? $customer->status }}</span></td>
                            <td>{{ $customer->companies_count }}</td>
                            <td>{{ $customer->licenses_count }}</td>
                            <td>
                                <details class="overlay-modal">
                                    <summary class="btn secondary">Editar</summary>
                                    <div class="overlay-layer">
                                        <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                        <div class="card overlay-panel wide">
                                        <div class="overlay-header">
                                            <div>
                                                <h3>Editar cliente</h3>
                                                <span class="muted">{{ $customer->name }}</span>
                                            </div>
                                            <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                        </div>
                                        <form method="post" action="{{ route('admin.customers.update', $customer) }}" class="form-grid customer-form-grid">
                                            @csrf
                                            @method('put')
                                            <label>Nombre / Razón social<input name="name" value="{{ old('name', $customer->name) }}" required></label>
                                            <label>Email portal<input type="email" name="email" value="{{ old('email', $customer->email) }}" required></label>
                                            <label>Teléfono portal<input name="phone" value="{{ old('phone', $customer->phone) }}"></label>
                                            <label>Estado
                                                <select name="status">
                                                    @foreach($statuses as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('status', $customer->status) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>Tipo documento
                                                <select name="document_type" required>
                                                    @foreach($documentTypes as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('document_type', $customer->document_type) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>Número documento<input name="document_number" value="{{ old('document_number', $customer->document_number) }}" required></label>
                                            <label>NRC<input name="nrc" value="{{ old('nrc', $customer->nrc) }}"></label>
                                            <label>Nombre comercial<input name="trade_name" value="{{ old('trade_name', $customer->trade_name) }}"></label>
                                            <label>Tipo DTE preferido
                                                <select name="preferred_dte_type" required>
                                                    @foreach($dteTypes as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('preferred_dte_type', $customer->preferred_dte_type) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>Actividad económica<input name="business_activity" value="{{ old('business_activity', $customer->business_activity) }}" placeholder="62020"></label>
                                            <label>Descripción actividad<input name="activity_description" value="{{ old('activity_description', $customer->activity_description) }}"></label>
                                            <label>Departamento
                                                <select name="address_department" class="customer-department" required>
                                                    <option value="">Seleccionar departamento...</option>
                                                    @foreach($departments as $department)
                                                        <option value="{{ $department['codigo'] }}" @selected(old('address_department', $customer->address_department) === $department['codigo'])>
                                                            {{ $department['codigo'] }} - {{ $department['nombre'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>Municipio
                                                <select name="address_municipality" class="customer-municipality" required>
                                                    <option value="">Seleccionar municipio...</option>
                                                    @foreach($municipalitiesFlat as $municipality)
                                                        <option value="{{ $municipality['codigo'] }}" data-department="{{ $municipality['departamento'] }}" @selected(old('address_municipality', $customer->address_municipality) === $municipality['codigo'])>
                                                            {{ $municipality['codigo'] }} - {{ $municipality['nombre'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="full-width">Dirección<input name="address" value="{{ old('address', $customer->address) }}" required></label>
                                            <label>Email facturación<input type="email" name="billing_email" value="{{ old('billing_email', $customer->billing_email) }}"></label>
                                            <label>Teléfono facturación<input name="billing_phone" value="{{ old('billing_phone', $customer->billing_phone) }}"></label>
                                            <div class="form-actions full-width">
                                                <button class="btn">Guardar cambios</button>
                                            </div>
                                        </form>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">No hay clientes registrados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $customers->links() }}
        </div>
    </div>

    <style>
        .customers-top {
            align-items: flex-start;
        }

        .customer-form-grid {
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        }

        .customer-search {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 420px;
            justify-content: flex-end;
        }

        .customer-search input {
            max-width: 360px;
        }

        .customers-table td {
            vertical-align: middle;
        }

        .prospect {
            background: #fef3c7;
            color: #92400e;
        }

        .pagination-wrap nav > div:first-child {
            display: none;
        }

        .pagination-wrap nav > div:last-child,
        .pagination-wrap nav .relative {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

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

        .pagination-wrap span[aria-current] span {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }

        @media (max-width: 800px) {
            .customers-top {
                display: grid;
            }

            .customer-search {
                flex: 1 1 100%;
                justify-content: stretch;
            }

            .customer-search input {
                max-width: none;
            }
        }
    </style>
    <script>
        document.querySelectorAll('.customer-form-grid').forEach((form) => {
            const department = form.querySelector('.customer-department');
            const municipality = form.querySelector('.customer-municipality');
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
    </script>
</x-layouts.app>
