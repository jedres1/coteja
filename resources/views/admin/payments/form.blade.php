<label class="full-width">Licencia
    <select name="license_id" required>
        @foreach($licenses as $license)
            <option value="{{ $license->id }}" @selected((string) old('license_id', $payment?->license_id) === (string) $license->id)>
                {{ $license->customer->name }} - {{ $license->company->business_name }} - {{ $license->license_key }}
            </option>
        @endforeach
    </select>
</label>
<label>Monto<input type="number" step="0.01" name="amount" value="{{ old('amount', $payment?->amount) }}" required></label>
<label>IVA<input type="number" step="0.01" name="iva" value="{{ old('iva', $payment?->iva ?? '0.00') }}" required></label>
<label>Periodo
    <select name="period" required>
        @foreach($periods as $value => $label)
            <option value="{{ $value }}" @selected(old('period', $payment?->period ?? 'monthly') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Metodo<input name="method" value="{{ old('method', $payment?->method) }}"></label>
<label>Referencia<input name="reference" value="{{ old('reference', $payment?->reference) }}"></label>
<label>Estado
    <select name="status" required>
        @foreach($paymentStatuses as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $payment?->status ?? 'paid') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Fecha<input type="date" name="paid_at" value="{{ old('paid_at', optional($payment?->paid_at)->toDateString() ?? now()->toDateString()) }}"></label>
