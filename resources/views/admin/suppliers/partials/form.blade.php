<form method="post" action="{{ $action }}" class="form-grid supplier-form-grid">
    @csrf
    @if($method)
        @method($method)
    @endif
    <label>Nombre / Razón social<input name="name" value="{{ old('name', $supplier?->name) }}" required></label>
    <label>Email<input type="email" name="email" value="{{ old('email', $supplier?->email) }}"></label>
    <label>Teléfono<input name="phone" value="{{ old('phone', $supplier?->phone) }}"></label>
    <label>Estado
        <select name="status">
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $supplier?->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Tipo documento
        <select name="document_type" required>
            @foreach($documentTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('document_type', $supplier?->document_type ?? '36') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Número documento<input name="document_number" value="{{ old('document_number', $supplier?->document_number) }}" required></label>
    <label>NRC<input name="nrc" value="{{ old('nrc', $supplier?->nrc) }}"></label>
    <label>Nombre comercial<input name="trade_name" value="{{ old('trade_name', $supplier?->trade_name) }}"></label>
    <label>Actividad económica<input name="business_activity" value="{{ old('business_activity', $supplier?->business_activity) }}" placeholder="62020"></label>
    <label>Descripción actividad<input name="activity_description" value="{{ old('activity_description', $supplier?->activity_description) }}"></label>
    <label>Departamento
        <select name="address_department" class="supplier-department" required>
            <option value="">Seleccionar departamento...</option>
            @foreach($departments as $department)
                <option value="{{ $department['codigo'] }}" @selected(old('address_department', $supplier?->address_department) === $department['codigo'])>
                    {{ $department['codigo'] }} - {{ $department['nombre'] }}
                </option>
            @endforeach
        </select>
    </label>
    <label>Municipio
        <select name="address_municipality" class="supplier-municipality" required>
            <option value="">Seleccionar municipio...</option>
            @foreach($municipalitiesFlat as $municipality)
                <option value="{{ $municipality['codigo'] }}" data-department="{{ $municipality['departamento'] }}" @selected(old('address_municipality', $supplier?->address_municipality) === $municipality['codigo'])>
                    {{ $municipality['codigo'] }} - {{ $municipality['nombre'] }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="full-width">Dirección<input name="address" value="{{ old('address', $supplier?->address) }}" required></label>
    <label>Email facturación<input type="email" name="billing_email" value="{{ old('billing_email', $supplier?->billing_email) }}"></label>
    <label>Teléfono facturación<input name="billing_phone" value="{{ old('billing_phone', $supplier?->billing_phone) }}"></label>
    <label class="full-width">Notas<textarea name="notes">{{ old('notes', $supplier?->notes) }}</textarea></label>
    <div class="form-actions full-width">
        <button class="btn">{{ $supplier ? 'Guardar cambios' : 'Crear proveedor' }}</button>
    </div>
</form>
