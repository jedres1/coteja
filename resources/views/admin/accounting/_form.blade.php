<div class="form-grid">
    <div class="form-group">
        <label class="form-label">Código <span class="required">*</span></label>
        <input type="text" name="code" class="input" required maxlength="20"
               value="{{ old('code', $account?->code) }}"
               placeholder="Ej: 4.1.01">
        <small class="form-hint">Use puntos para la jerarquía: 1 → 1.1 → 1.1.01</small>
    </div>

    <div class="form-group">
        <label class="form-label">Nombre <span class="required">*</span></label>
        <input type="text" name="name" class="input" required maxlength="200"
               value="{{ old('name', $account?->name) }}"
               placeholder="Nombre de la cuenta">
    </div>

    <div class="form-group">
        <label class="form-label">Tipo <span class="required">*</span></label>
        <select name="type" class="input" required>
            <option value="">Seleccione…</option>
            @foreach(['activo' => 'Activo (1)', 'pasivo' => 'Pasivo (2)', 'patrimonio' => 'Patrimonio (3)', 'gasto' => 'Gasto (4)', 'ingreso' => 'Ingreso (5)'] as $val => $label)
                <option value="{{ $val }}" {{ old('type', $account?->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">Naturaleza <span class="required">*</span></label>
        <select name="nature" class="input" required>
            <option value="">Seleccione…</option>
            <option value="deudora"   {{ old('nature', $account?->nature) === 'deudora'   ? 'selected' : '' }}>Deudora (Activos y Gastos)</option>
            <option value="acreedora" {{ old('nature', $account?->nature) === 'acreedora' ? 'selected' : '' }}>Acreedora (Pasivos, Patrimonio e Ingresos)</option>
        </select>
    </div>

    <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Cuenta padre</label>
        <select name="parent_id" class="input">
            <option value="">Sin padre (cuenta de nivel 1)</option>
            @foreach($parents as $p)
                @if(!$account || $p['id'] != $account->id)
                    <option value="{{ $p['id'] }}" {{ old('parent_id', $account?->parent_id) == $p['id'] ? 'selected' : '' }}>
                        {{ $p['name'] }}
                    </option>
                @endif
            @endforeach
        </select>
    </div>

    @if($account)
    <div class="form-group">
        <label class="form-label">Estado</label>
        <label class="toggle-label">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }}>
            Cuenta activa
        </label>
    </div>
    @endif

    <div class="form-group" style="grid-column:1/-1">
        <label class="form-label">Notas</label>
        <textarea name="notes" class="input" rows="2" maxlength="1000"
                  placeholder="Descripción opcional…">{{ old('notes', $account?->notes) }}</textarea>
    </div>
</div>
