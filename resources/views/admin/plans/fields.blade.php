<label>Codigo<input name="code" value="{{ old('code', $plan->code ?? '') }}" required></label>
<label>Nombre<input name="name" value="{{ old('name', $plan->name ?? '') }}" required></label>
<label>Mensual<input type="number" step="0.01" name="monthly_price" value="{{ old('monthly_price', $plan->monthly_price ?? '0.00') }}"></label>
<label>Anual<input type="number" step="0.01" name="annual_price" value="{{ old('annual_price', $plan->annual_price ?? '0.00') }}"></label>
<label>Implementacion<input type="number" step="0.01" name="implementation_fee" value="{{ old('implementation_fee', $plan->implementation_fee ?? '0.00') }}"></label>
<label>Usuario adicional<input type="number" step="0.01" name="additional_user_price" value="{{ old('additional_user_price', $plan->additional_user_price ?? '0.00') }}"></label>
<label>Usuarios incluidos<input type="number" name="included_users" value="{{ old('included_users', $plan->included_users ?? 1) }}"></label>
<label>Dispositivos<input type="number" name="max_devices" value="{{ old('max_devices', $plan->max_devices ?? 1) }}"></label>
<label><span>Soporte</span><select name="support_included"><option value="0">No</option><option value="1" @selected((bool) old('support_included', $plan->support_included ?? false))>Si</option></select></label>
<label><span>Backup nube</span><select name="cloud_backup"><option value="0">No</option><option value="1" @selected((bool) old('cloud_backup', $plan->cloud_backup ?? false))>Si</option></select></label>
<label><span>Docs ilimitados</span><select name="unlimited_documents"><option value="1">Si</option><option value="0" @selected(! (bool) old('unlimited_documents', $plan->unlimited_documents ?? true))>No</option></select></label>
<label><span>Activo</span><select name="is_active"><option value="1">Si</option><option value="0" @selected(! (bool) old('is_active', $plan->is_active ?? true))>No</option></select></label>
