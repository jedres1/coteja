<form method="post" action="{{ $action }}" class="form-grid purchase-form-grid">
    @csrf
    @if($method)
        @method($method)
    @endif
    <label>Proveedor
        <select name="supplier_id" required>
            <option value="">Seleccionar proveedor...</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $invoice?->supplier_id) === (string) $supplier->id)>
                    {{ $supplier->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label>Cliente vinculado
        <select name="customer_id">
            <option value="">Sin cliente vinculado</option>
            @foreach($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $invoice?->customer_id) === (string) $customer->id)>
                    {{ $customer->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label>Tipo documento
        <select name="document_type" required>
            @foreach($documentTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('document_type', $invoice?->document_type ?? '03') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Número de factura<input name="invoice_number" value="{{ old('invoice_number', $invoice?->invoice_number) }}" required></label>
    <label>Fecha compra<input type="date" name="purchase_date" value="{{ old('purchase_date', $invoice?->purchase_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></label>
    <label>Fecha vencimiento<input type="date" name="due_date" value="{{ old('due_date', $invoice?->due_date?->format('Y-m-d')) }}"></label>
    <label>Subtotal<input type="number" name="subtotal" value="{{ old('subtotal', $invoice?->subtotal ?? '0.00') }}" min="0" step="0.01" required></label>
    <label>IVA<input type="number" name="iva" value="{{ old('iva', $invoice?->iva ?? '0.00') }}" min="0" step="0.01" required></label>
    <label>Total<input type="number" name="total" value="{{ old('total', $invoice?->total ?? '0.00') }}" min="0" step="0.01" required></label>
    <label>Método de pago<input name="payment_method" value="{{ old('payment_method', $invoice?->payment_method) }}" placeholder="Efectivo, transferencia, crédito..."></label>
    <label>Estado de pago
        <select name="payment_status" required>
            @foreach($paymentStatuses as $value => $label)
                <option value="{{ $value }}" @selected(old('payment_status', $invoice?->payment_status ?? 'pending') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Estado interno
        <select name="status" required>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $invoice?->status ?? 'registered') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="full-width">Notas<textarea name="notes">{{ old('notes', $invoice?->notes) }}</textarea></label>
    <div class="form-actions full-width">
        <button class="btn">{{ $invoice ? 'Guardar cambios' : 'Registrar factura' }}</button>
    </div>
</form>
