<x-layouts.app title="Usuarios y acceso | Coteja">
    @php
        $roles = [
            'admin' => 'Administrador',
            'customer' => 'Cliente',
            'consultant' => 'Consultor',
        ];
        $roleBadges = [
            'admin' => 'role-admin',
            'customer' => 'role-customer',
            'consultant' => 'role-consultant',
        ];
    @endphp

    <div class="top users-top">
        <div>
            <h1>Usuarios y acceso</h1>
            <span class="muted">Administración de cuentas, roles y estado de acceso</span>
        </div>
        <details class="overlay-modal" @if($errors->any()) open @endif>
            <summary class="btn">Nuevo usuario</summary>
            <div class="overlay-layer">
                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                <div class="card overlay-panel wide">
                    <div class="overlay-header">
                        <div>
                            <h3>Crear usuario</h3>
                            <span class="muted">Asigne rol, acceso y cliente si aplica.</span>
                        </div>
                        <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                    </div>
                    @include('admin.users.partials.form', [
                        'managedUser' => null,
                        'action' => route('admin.users.store'),
                        'method' => null,
                        'roles' => $roles,
                        'modules' => $modules,
                        'customers' => $customers,
                        'companies' => $companies,
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
                    Mostrando {{ $users->count() }} de {{ $users->total() }} usuarios
                    @if($search)
                        para "{{ $search }}"
                    @endif
                </span>
            </div>
            <form method="get" action="{{ route('admin.users.index') }}" class="user-search">
                <label class="sr-only" for="user-search">Buscar usuarios</label>
                <input id="user-search" name="search" type="search" value="{{ $search }}" placeholder="Buscar usuario, email o cliente...">
                <select name="role" aria-label="Filtrar por rol">
                    <option value="">Todos los roles</option>
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn" type="submit">Buscar</button>
                @if($search || $role)
                    <a class="btn secondary" href="{{ route('admin.users.index') }}">Limpiar</a>
                @endif
            </form>
            <span class="badge">{{ $users->perPage() }} por página</span>
        </div>

        <div class="table-scroll">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Cliente vinculado</th>
                        <th>Empresas</th>
                        <th>Módulos</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $managedUser)
                        <tr>
                            <td>
                                <strong>{{ $managedUser->name }}</strong>
                                <br><span class="muted">{{ $managedUser->email }}</span>
                            </td>
                            <td><span class="badge {{ $roleBadges[$managedUser->role] ?? '' }}">{{ $roles[$managedUser->role] ?? $managedUser->role }}</span></td>
                            <td>
                                @if($managedUser->customer)
                                    {{ $managedUser->customer->name }}
                                    <br><span class="muted">{{ $managedUser->customer->email }}</span>
                                @else
                                    <span class="muted">No aplica</span>
                                @endif
                            </td>
                            <td>
                                @if($managedUser->accessibleCompanies->isNotEmpty())
                                    {{ $managedUser->accessibleCompanies->pluck('business_name')->join(', ') }}
                                @else
                                    <span class="muted">Sin empresas</span>
                                @endif
                            </td>
                            <td>
                                @if($managedUser->canAccessAdmin())
                                    <span class="muted">Todos</span>
                                @elseif(!empty($managedUser->module_accesses))
                                    <div class="module-badges">
                                        @foreach($managedUser->module_accesses as $mod)
                                            <span class="badge module-badge">{{ $modules[$mod] ?? $mod }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="muted">Ninguno</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $managedUser->is_active ? 'active' : 'suspended' }}">
                                    {{ $managedUser->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>{{ $managedUser->created_at?->format('d/m/Y') }}</td>
                            <td>
                                <div class="row-actions">
                                    <details class="overlay-modal">
                                        <summary class="btn secondary">Editar</summary>
                                        <div class="overlay-layer">
                                            <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                            <div class="card overlay-panel wide">
                                                <div class="overlay-header">
                                                    <div>
                                                        <h3>Editar usuario</h3>
                                                        <span class="muted">{{ $managedUser->email }}</span>
                                                    </div>
                                                    <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                                </div>
                                                @include('admin.users.partials.form', [
                                                    'managedUser' => $managedUser,
                                                    'action' => route('admin.users.update', $managedUser),
                                                    'method' => 'put',
                                                    'roles' => $roles,
                                                    'modules' => $modules,
                                                    'customers' => $customers,
                                                    'companies' => $companies,
                                                ])
                                            </div>
                                        </div>
                                    </details>
                                    <form method="post" action="{{ route('admin.users.destroy', $managedUser) }}" class="inline" data-confirm-delete="Eliminar usuario">
                                        @csrf
                                        @method('delete')
                                        <button class="btn danger" type="submit" @disabled($managedUser->is(auth()->user()))>Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">No hay usuarios registrados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $users->links() }}
        </div>
    </div>

    <style>
        .users-top { align-items: flex-start; }
        .user-form-grid { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .user-search { display: flex; align-items: center; gap: 8px; flex: 1 1 620px; justify-content: flex-end; }
        .user-search input { max-width: 320px; }
        .user-search select { max-width: 210px; }
        .users-table td { vertical-align: middle; }
        .danger { background: var(--bad); }
        .row-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .role-admin { background: #dbeafe; color: #1e40af; }
        .role-customer { background: #dcfce7; color: #166534; }
        .role-consultant { background: #fef3c7; color: #92400e; }
        .field-label { display: block; margin-bottom: 8px; font-size: 13px; color: #374151; font-weight: 600; }
        .module-access-options { display: flex; flex-wrap: wrap; gap: 10px; }
        .check-row { display: inline-flex; grid-template-columns: auto 1fr; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); }
        .check-row input { width: auto; }
        .module-badges { display: flex; flex-wrap: wrap; gap: 4px; }
        .module-badge { background: #ede9fe; color: #5b21b6; font-size: 11px; }
        .company-access-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
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
            .users-top { display: grid; }
            .user-search { flex: 1 1 100%; justify-content: stretch; }
            .user-search input,
            .user-search select { max-width: none; }
        }
    </style>
    <script>
        document.querySelectorAll('.user-form-grid').forEach((form) => {
            const role = form.querySelector('[name="role"]');
            const customerWrap = form.querySelector('[data-customer-field]');
            const customer = form.querySelector('[name="customer_id"]');
            const moduleWrap = form.querySelector('[data-module-access-field]');
            const moduleInputs = form.querySelectorAll('[name="module_accesses[]"]');
            const companyWrap = form.querySelector('[data-company-access-field]');
            const companyInputs = form.querySelectorAll('[name="company_ids[]"]');
            if (!role || !customerWrap || !customer || !moduleWrap || !companyWrap) return;

            const syncCustomerField = () => {
                const isCustomer = role.value === 'customer';
                customerWrap.hidden = !isCustomer;
                customer.disabled = !isCustomer;
                moduleWrap.hidden = !isCustomer;
                companyWrap.hidden = !isCustomer;
                moduleInputs.forEach((input) => input.disabled = !isCustomer);
                companyInputs.forEach((input) => {
                    const matchesCustomer = input.dataset.customerId === customer.value;
                    input.closest('label').hidden = isCustomer && !matchesCustomer;
                    input.disabled = !isCustomer || !matchesCustomer;
                    if (!matchesCustomer) input.checked = false;
                });
                if (!isCustomer) customer.value = '';
            };

            role.addEventListener('change', syncCustomerField);
            customer.addEventListener('change', syncCustomerField);
            syncCustomerField();
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
