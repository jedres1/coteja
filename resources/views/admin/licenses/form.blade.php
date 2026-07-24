<label>Estado
    <select name="status" required>
        @foreach($licenseStatuses as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $license?->status ?? 'active') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
@if(!$license)
    <label>Inicio<input type="date" name="starts_at" value="{{ old('starts_at', now()->toDateString()) }}" required></label>
@endif
<label>Vence<input type="date" name="expires_at" value="{{ old('expires_at', optional($license?->expires_at)->toDateString() ?? now()->addMonth()->toDateString()) }}" required></label>
<label>Usuarios<input type="number" name="max_users" value="{{ old('max_users', $license?->max_users ?? 1) }}" min="1" required></label>
<label>Dispositivos<input type="number" name="max_devices" value="{{ old('max_devices', $license?->max_devices ?? 1) }}" min="1" required></label>
<label>Dias gracia<input type="number" name="grace_days" value="{{ old('grace_days', $license?->grace_days ?? 7) }}" min="0" required></label>
