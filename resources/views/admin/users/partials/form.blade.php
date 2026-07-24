<form method="post" action="{{ $action }}" class="form-grid user-form-grid">
    @csrf
    @if($method)
        @method($method)
    @endif
    <label>Nombre<input name="name" value="{{ old('name', $managedUser?->name) }}" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $managedUser?->email) }}" required></label>
    <label>Rol
        <select name="role" required>
            @foreach($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $managedUser?->role ?? 'customer') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label data-customer-field>Cliente vinculado
        <select name="customer_id">
            <option value="">Seleccionar cliente...</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $managedUser?->customer_id) === (string) $customer->id)>
                    {{ $customer->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label>Estado
        <select name="is_active">
            <option value="1" @selected((string) old('is_active', $managedUser?->is_active ?? true) === '1')>Activo</option>
            <option value="0" @selected((string) old('is_active', $managedUser?->is_active ?? true) === '0')>Inactivo</option>
        </select>
    </label>
    <div class="module-access-field full-width" data-module-access-field>
        <span class="field-label">Acceso a módulos del cliente</span>
        <div class="module-access-options">
            @foreach($modules as $value => $label)
                <label class="check-row">
                    <input type="checkbox" name="module_accesses[]" value="{{ $value }}" @checked(in_array($value, old('module_accesses', $managedUser?->module_accesses ?? ['billing', 'purchases']), true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div class="company-access-field full-width" data-company-access-field>
        <span class="field-label">Empresas a las que puede conectarse</span>
        <div class="company-access-options">
            @foreach($companies as $company)
                <label class="check-row">
                    <input
                        type="checkbox"
                        name="company_ids[]"
                        value="{{ $company->id }}"
                        data-customer-id="{{ $company->customer_id }}"
                        @checked(in_array($company->id, old('company_ids', $managedUser?->accessibleCompanies?->pluck('id')->all() ?? [])))
                    >
                    <span>{{ $company->business_name }} <small class="muted">· {{ $company->customer?->name }}</small></span>
                </label>
            @endforeach
        </div>
    </div>
    <label>
        {{ $managedUser ? 'Nueva contraseña' : 'Contraseña' }}
        <input type="password" name="password" @required(!$managedUser) minlength="8" autocomplete="new-password">
    </label>
    @if($managedUser)
        <p class="muted full-width">Deje la contraseña en blanco para conservar la actual.</p>
    @endif
    <div class="form-actions full-width">
        <button class="btn">{{ $managedUser ? 'Guardar cambios' : 'Crear usuario' }}</button>
    </div>
</form>
