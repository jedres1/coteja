<x-layouts.app title="Movimientos de Inventario">
    @php
        $typeLabels = ['entry' => 'Entrada', 'exit' => 'Salida'];
        $docLabels  = ['billing' => 'Factura', 'purchase' => 'Compra', 'manual' => 'Manual'];
    @endphp

    <style>
        .inv-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .filters { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px; }
        .filters label { display:grid; gap:4px; font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase; }
        .filters select, .filters input { min-width:150px; padding:8px 10px; border:1px solid var(--line); border-radius:8px; font:inherit; background:var(--panel); color:var(--ink); font-size:13px; }
        .badge { display:inline-flex; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
        .entry { background:#dcfce7; color:#166534; } .exit { background:#fee2e2; color:#991b1b; }
        .doc-billing  { background:#dbeafe; color:#1e40af; }
        .doc-purchase { background:#fef3c7; color:#92400e; }
        .doc-manual   { background:#f1f5f9; color:#475569; }
        .empty-state { padding:48px; text-align:center; color:var(--muted); }
        /* overlay */
        .overlay-layer { position:fixed; inset:0; z-index:100; display:flex; align-items:center; justify-content:center; }
        .overlay-backdrop { position:absolute; inset:0; background:rgb(0 0 0/.45); border:0; cursor:pointer; }
        .overlay-panel { position:relative; z-index:1; min-width:340px; max-width:560px; width:100%; max-height:90vh; overflow-y:auto; }
        .overlay-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; position:sticky; top:0; background:var(--panel); padding-bottom:12px; border-bottom:1px solid var(--line); }
        .overlay-header h3 { margin:0; font-size:16px; }
        .form-stack { display:grid; gap:12px; }
        .form-stack label { display:grid; gap:5px; font-size:13px; font-weight:600; }
        .form-stack small { font-weight:400; color:var(--muted); }
        .btn { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 14px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:700; font:inherit; cursor:pointer; font-size:13px; }
        .btn.secondary { background:#4b5563; } .btn.sm { min-height:30px; padding:5px 10px; font-size:12px; }
        .section-divider { border:0; border-top:1px solid var(--line); margin:8px 0; }
        /* pagination */
        .pagination-wrap { margin-top:16px; }
        .pagination-wrap nav > div:first-child { display:none; }
        .pagination-wrap nav > div:last-child,
        .pagination-wrap nav .relative { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .pagination-wrap a,
        .pagination-wrap span[aria-current] span,
        .pagination-wrap span[aria-disabled] span {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:36px; min-height:36px; padding:8px 10px;
            border:1px solid var(--line); border-radius:8px; background:var(--panel);
        }
        .pagination-wrap span[aria-current] span { background:var(--brand); color:#fff; border-color:var(--brand); }
    </style>

    <div class="inv-header">
        <div>
            <h1 style="margin:0;font-size:26px">Movimientos de inventario</h1>
            <p class="muted" style="margin:4px 0 0">Entradas y salidas de productos que controlan existencias.</p>
        </div>
        <button class="btn" type="button" onclick="openMovement()">Registrar movimiento</button>
    </div>

    @if(session('status'))
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--ok);font-size:13px">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--bad);font-size:13px">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Filtros --}}
    <form method="get" class="filters">
        <label>Buscar
            <input type="search" name="search" value="{{ $search }}" placeholder="Código o descripción...">
        </label>
        <label>Tipo
            <select name="type">
                <option value="">Todos</option>
                <option value="entry" {{ $type === 'entry' ? 'selected' : '' }}>Entrada</option>
                <option value="exit"  {{ $type === 'exit'  ? 'selected' : '' }}>Salida</option>
            </select>
        </label>
        <label>Bodega
            <select name="warehouse_id">
                <option value="">Todas</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn secondary sm" type="submit">Filtrar</button>
        <a class="btn secondary sm" href="{{ route('admin.inventory.movements') }}">Limpiar</a>
    </form>

    @if($movements->isEmpty())
        <div class="empty-state">No hay movimientos registrados con los filtros actuales.</div>
    @else
        <div class="card" style="padding:0;overflow:hidden">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Producto</th>
                        <th>Bodega</th>
                        <th>Cantidad</th>
                        <th>Documento</th>
                        <th>N° Documento</th>
                        <th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($movements as $mv)
                        <tr>
                            <td style="white-space:nowrap">{{ $mv->created_at->format('d/m/Y H:i') }}</td>
                            <td><span class="badge {{ $mv->type }}">{{ $typeLabels[$mv->type] }}</span></td>
                            <td>
                                <strong>{{ $mv->product?->description ?? '—' }}</strong>
                                <br><span class="muted" style="font-size:11px">{{ $mv->product?->code }}</span>
                            </td>
                            <td>{{ $mv->warehouse?->name ?? '—' }}</td>
                            <td style="font-weight:700">{{ number_format($mv->quantity, 2) }}</td>
                            <td><span class="badge doc-{{ $mv->document_type }}">{{ $docLabels[$mv->document_type] ?? $mv->document_type }}</span></td>
                            <td style="font-size:12px;max-width:180px;word-break:break-all">
                                {{ $mv->document_number ?: '—' }}
                                @if($mv->purchaseInvoice)
                                    <br><span class="muted">{{ $mv->purchaseInvoice->supplier?->name }}</span>
                                @endif
                            </td>
                            <td style="font-size:12px;max-width:160px">{{ $mv->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($movements->hasPages())
            <div class="pagination-wrap">
                <p class="muted" style="margin:0 0 8px;font-size:13px">
                    Mostrando {{ $movements->firstItem() }}–{{ $movements->lastItem() }} de {{ $movements->total() }} movimientos
                </p>
                {{ $movements->links() }}
            </div>
        @endif
    @endif

    {{-- Overlay: Registrar movimiento --}}
    <div id="mov-overlay" style="display:none" class="overlay-layer">
        <button class="overlay-backdrop" type="button" onclick="closeMovement()" aria-label="Cerrar"></button>
        <div class="card overlay-panel">
            <div class="overlay-header">
                <div>
                    <h3>Registrar movimiento</h3>
                    <span class="muted" style="font-size:12px">Solo productos que controlan existencias</span>
                </div>
                <button class="btn secondary sm" type="button" onclick="closeMovement()">✕</button>
            </div>
            <form method="post" action="{{ route('admin.inventory.movements.store') }}" class="form-stack" id="mov-form">
                @csrf
                <label>Tipo de movimiento
                    <select name="type" id="mov-type" required onchange="toggleDocFields()">
                        <option value="">Seleccionar...</option>
                        <option value="entry">Entrada</option>
                        <option value="exit">Salida</option>
                    </select>
                </label>
                <label>Producto
                    <select name="product_id" required>
                        <option value="">Seleccionar producto...</option>
                        @foreach($inventoryProducts as $prod)
                            <option value="{{ $prod->id }}">{{ $prod->description }} ({{ $prod->code }})</option>
                        @endforeach
                    </select>
                    <small>Solo se muestran productos que controlan existencias</small>
                </label>
                <label>Bodega
                    <select name="warehouse_id" required>
                        <option value="">Seleccionar bodega...</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Cantidad
                    <input type="number" name="quantity" min="0.01" step="0.01" placeholder="Ej: 10" required>
                </label>

                <hr class="section-divider">

                {{-- Campos de documento --}}
                <div id="entry-doc-fields" style="display:none;display:grid;gap:12px">
                    <label>Origen de la entrada
                        <select name="document_type" id="mov-doc-type" onchange="togglePurchaseSelect()">
                            <option value="manual">Manual (ingresar número de documento)</option>
                            <option value="purchase">Factura de compra aprobada</option>
                        </select>
                    </label>

                    <div id="manual-doc" style="display:none">
                        <label>Número de documento <small>(opcional)</small>
                            <input type="text" name="document_number" placeholder="Ej: FAC-001-2026">
                        </label>
                    </div>

                    <div id="purchase-doc" style="display:none">
                        <label>Factura de compra aprobada
                            <select name="purchase_invoice_id">
                                <option value="">Seleccionar factura...</option>
                                @foreach($approvedPurchases as $pi)
                                    <option value="{{ $pi->id }}">
                                        {{ $pi->invoice_number }} — {{ $pi->supplier?->name }} ({{ $pi->purchase_date?->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div id="exit-doc-fields" style="display:none;display:grid;gap:12px">
                    <input type="hidden" name="document_type" value="manual">
                    <label>Número de documento <small>(opcional)</small>
                        <input type="text" name="document_number" id="exit-doc-number" placeholder="Ej: FAC-2026-001">
                    </label>
                </div>

                <label>Notas <small>(opcional)</small>
                    <textarea name="notes" rows="2" maxlength="1000" placeholder="Observaciones del movimiento..."></textarea>
                </label>

                <div style="display:flex;gap:8px;margin-top:4px">
                    <button class="btn secondary" type="button" onclick="closeMovement()">Cancelar</button>
                    <button class="btn" type="submit">Registrar movimiento</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMovement() { document.getElementById('mov-overlay').style.display = 'flex'; }
        function closeMovement() { document.getElementById('mov-overlay').style.display = 'none'; }

        function toggleDocFields() {
            const type = document.getElementById('mov-type').value;
            const entryFields = document.getElementById('entry-doc-fields');
            const exitFields  = document.getElementById('exit-doc-fields');
            entryFields.style.display = type === 'entry' ? 'grid' : 'none';
            exitFields.style.display  = type === 'exit'  ? 'grid' : 'none';
            if (type === 'entry') togglePurchaseSelect();
        }

        function togglePurchaseSelect() {
            const docType = document.getElementById('mov-doc-type').value;
            document.getElementById('manual-doc').style.display   = docType === 'manual'   ? 'block' : 'none';
            document.getElementById('purchase-doc').style.display = docType === 'purchase' ? 'block' : 'none';
        }

        // Abrir automáticamente si hay errores de validación
        @if($errors->any()) openMovement(); @endif
    </script>
</x-layouts.app>
