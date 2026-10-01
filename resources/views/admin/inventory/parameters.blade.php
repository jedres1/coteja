<x-layouts.app title="Parámetros de Inventario">
    <style>
        .param-card { max-width:560px; }
        .form-stack { display:grid; gap:14px; }
        .form-stack label { display:grid; gap:5px; font-size:13px; font-weight:600; }
        .form-stack small { font-weight:400; color:var(--muted); }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:700; font:inherit; cursor:pointer; font-size:13px; }
        .alert { padding:12px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; }
        .alert.warn { background:#fef3c7; color:#92400e; border-left:4px solid var(--warn); }
        .alert.ok   { background:#ecfdf5; color:#166534; border-left:4px solid var(--ok); }
    </style>

    <div style="margin-bottom:20px">
        <h1 style="margin:0;font-size:26px">Parámetros de Inventario</h1>
        <p class="muted" style="margin:4px 0 0">Configure la bodega de ventas que se usará al registrar salidas desde facturación.</p>
    </div>

    @if(session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert" style="background:#fef2f2;color:#991b1b;border-left:4px solid var(--bad)">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if($warehouses->isEmpty())
        <div class="alert warn">
            No hay bodegas registradas. Primero debe <a href="{{ route('admin.inventory.warehouses') }}" style="font-weight:700;text-decoration:underline">crear al menos una bodega</a> para configurar los parámetros de inventario.
        </div>
    @else
        <div class="card param-card">
            <form method="post" action="{{ route('admin.inventory.parameters.save') }}" class="form-stack">
                @csrf
                <label>Bodega de ventas
                    <small>Esta bodega registrará automáticamente las salidas cuando se genere una factura electrónica.</small>
                    <select name="sales_warehouse_id" required>
                        <option value="">Seleccionar bodega...</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ $salesWarehouseId === $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} — {{ Str::limit($wh->address, 60) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div>
                    <button class="btn" type="submit">Guardar parámetros</button>
                </div>
            </form>
        </div>

        @if(!$salesWarehouseId)
            <div class="alert warn" style="margin-top:16px">
                Sin bodega de ventas configurada, las salidas de inventario no se registrarán automáticamente al facturar.
            </div>
        @endif
    @endif
</x-layouts.app>
