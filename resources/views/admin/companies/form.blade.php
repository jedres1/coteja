<label>Cliente
    <select name="customer_id" required>
        @foreach($customers as $customer)
            <option value="{{ $customer->id }}" @selected((string) old('customer_id', $company?->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
        @endforeach
    </select>
</label>
<label>Razon social<input name="business_name" value="{{ old('business_name', $company?->business_name) }}" required></label>
<label>Nombre comercial<input name="trade_name" value="{{ old('trade_name', $company?->trade_name) }}"></label>
<label>NIT/DUI<input name="nit_dui" value="{{ old('nit_dui', $company?->nit_dui) }}" required></label>
<label>NRC<input name="nrc" value="{{ old('nrc', $company?->nrc) }}"></label>
<label>Email<input type="email" name="email" value="{{ old('email', $company?->email) }}"></label>
<label>Telefono<input name="phone" value="{{ old('phone', $company?->phone) }}"></label>
<label>Ambiente
    <select name="environment" required>
        @foreach($environments as $value => $label)
            <option value="{{ $value }}" @selected(old('environment', $company?->environment ?? 'production') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
