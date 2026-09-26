<x-layouts.app title="Cuentas por pagar | Coteja">
    @php
        $documentTypes = [
            '01' => 'Factura', '03' => 'CCF', '05' => 'Nota crédito',
            '06' => 'Nota débito', '11' => 'Exportación', '14' => 'Sujeto excluido', '99' => 'Otro',
        ];
        $paymentStatuses = ['pending' => 'Pendiente', 'partial' => 'Parcial', 'paid' => 'Pagada', 'void' => 'Anulada'];
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $today = now()->startOfDay();
    @endphp

    <style>
        .ap-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .ap-filter { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .ap-filter select { padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; font: inherit; background: var(--panel); color: var(--ink); min-width: 240px; }
        .ap-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .ap-metric { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 16px 18px; }
        .ap-metric .label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin-bottom: 6px; }
        .ap-metric .value { font-size: 28px; font-weight: 800; line-height: 1.1; }
        .ap-metric.bad  .value { color: var(--bad); }
        .ap-metric.warn .value { color: var(--warn); }
        .supplier-block { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
        .supplier-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--line); background: #f8fafc; }
        body.dark-mode .supplier-header { background: #0f172a; }
        .supplier-header h3 { margin: 0; font-size: 15px; }
        .supplier-header .meta { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .supplier-total { font-size: 20px; font-weight: 800; color: var(--bad); white-space: nowrap; }
        .ap-table { width: 100%; border-collapse: collapse; }
        .ap-table th, .ap-table td { padding: 10px 14px; border-bottom: 1px solid var(--line); text-align: left; font-size: 13px; vertical-align: middle; }
        .ap-table th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: transparent; }
        .ap-table tr:last-child td { border-bottom: 0; }
        .due-overdue { color: var(--bad); font-weight: 700; }
        .due-soon    { color: var(--warn); font-weight: 600; }
        .badge { display: inline-flex; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .pending, .registered { background: #fef3c7; color: #92400e; }
        .partial, .reviewed   { background: #dbeafe; color: #1e40af; }
        .paid, .accounted     { background: #dcfce7; color: #166534; }
        .void                 { background: #fee2e2; color: #991b1b; }
        .empty-state { padding: 48px; text-align: center; color: var(--muted); font-size: 15px; }
        /* overlay */
        .overlay-modal { position: relative; display: inline-block; }
        .overlay-modal > summary { list-style: none; cursor: pointer; }
        .overlay-modal > summary::-webkit-details-marker { display: none; }
        .overlay-layer { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; }
        .overlay-backdrop { position: absolute; inset: 0; background: rgb(0 0 0 / .45); border: 0; cursor: pointer; }
        .overlay-panel { position: relative; z-index: 1; min-width: 320px; max-width: 480px; width: 100%; }
        .overlay-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .overlay-header h3 { margin: 0; font-size: 16px; }
        .pay-form { display: grid; gap: 14px; }
        .pay-form label { display: grid; gap: 6px; font-size: 13px; font-weight: 600; color: #374151; }
        body.dark-mode .pay-form label { color: #d1d5db; }
        .pay-form select, .pay-form input { width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; font: inherit; background: var(--panel); color: var(--ink); }
        .pay-form .actions { display: flex; gap: 10px; }
        /* pagination */
        .pagination-wrap { margin-top: 16px; }
        .pagination-wrap nav > div:first-child { display: none; }
        .pagination-wrap nav > div:last-child,
        .pagination-wrap nav .relative { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pagination-wrap a,
        .pagination-wrap span[aria-current] span,
        .pagination-wrap span[aria-disabled] span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 36px; min-height: 36px; padding: 8px 10px;
            border: 1px solid var(--line); border-radius: 8px; background: var(--panel);
        }
        .pagination-wrap span[aria-current] span { background: var(--brand); color: #fff; border-color: var(--brand); }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 8px 14px; border-radius: 8px; border: 0; background: var(--brand); color: #fff; font-weight: 700; font: inherit; cursor: pointer; font-size: 13px; }
        .btn.secondary { background: #4b5563; }
        .btn.sm { min-height: 30px; padding: 5px 10px; font-size: 12px; }
    </style>

    {{-- Cabecera con filtro --}}
    <div class="ap-header">
        <div>
            <h1 style="margin:0;font-size:26px">Cuentas por pagar</h1>
            <p class="muted" style="margin:4px 0 0">Facturas pendientes de pago, organizadas por proveedor.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
            <form method="get" action="{{ route('admin.purchase-invoices.accounts-payable') }}" class="ap-filter">
                <select name="supplier_id" aria-label="Filtrar por proveedor" onchange="this.form.submit()">
                    <option value="">Todos los proveedores</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected((string)$supplierId === (string)$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                @if($supplierId)
                    <a class="btn secondary" href="{{ route('admin.purchase-invoices.accounts-payable') }}">Limpiar</a>
                @endif
            </form>
            <a class="btn secondary" href="{{ route('admin.purchase-invoices.index') }}">Ver todas las facturas</a>
        </div>
    </div>

    {{-- Métricas (sobre el dataset filtrado, sin afectar paginación) --}}
    <div class="ap-summary">
        <div class="ap-metric bad">
            <div class="label">Total por pagar</div>
            <div class="value">{{ $money($totals['total']) }}</div>
        </div>
        <div class="ap-metric warn">
            <div class="label">Facturas pendientes</div>
            <div class="value">{{ $totals['invoices'] }}</div>
        </div>
        <div class="ap-metric">
            <div class="label">Proveedores con saldo</div>
            <div class="value">{{ $totals['suppliers'] }}</div>
        </div>
    </div>

    {{-- Bloques por proveedor (agrupados dentro de la página actual) --}}
    @forelse($grouped as $supplierId => $rows)
        @php $supplier = $rows->first()->supplier; $supplierTotal = $rows->sum('total'); @endphp
        <div class="supplier-block">
            <div class="supplier-header">
                <div>
                    <h3>{{ $supplier?->name }}</h3>
                    <div class="meta">
                        {{ $supplier?->document_number }}
                        @if($supplier?->nrc) · NRC {{ $supplier->nrc }} @endif
                        @if($supplier?->email) · {{ $supplier->email }} @endif
                    </div>
                </div>
                <div class="supplier-total">{{ $money($supplierTotal) }}</div>
            </div>
            <table class="ap-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Documento</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $invoice)
                        @php
                            $daysUntilDue = $invoice->due_date ? $today->diffInDays($invoice->due_date, false) : null;
                            $dueClass = '';
                            $dueLabel = $invoice->due_date?->format('d/m/Y') ?? '—';
                            if ($daysUntilDue !== null) {
                                if ($daysUntilDue < 0)       { $dueClass = 'due-overdue'; $dueLabel .= ' (vencida)'; }
                                elseif ($daysUntilDue <= 7)  { $dueClass = 'due-soon'; }
                            }
                        @endphp
                        <tr>
                            <td>{{ $invoice->purchase_date?->format('d/m/Y') }}</td>
                            <td>
                                {{ $documentTypes[$invoice->document_type] ?? $invoice->document_type }}
                                <br><span class="muted" style="font-size:11px">{{ $invoice->invoice_number }}</span>
                            </td>
                            <td>{{ $money($invoice->subtotal) }}</td>
                            <td>{{ $money($invoice->iva) }}</td>
                            <td><strong>{{ $money($invoice->total) }}</strong></td>
                            <td class="{{ $dueClass }}">{{ $dueLabel }}</td>
                            <td><span class="badge {{ $invoice->payment_status }}">{{ $paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status }}</span></td>
                            <td>
                                <details class="overlay-modal">
                                    <summary class="btn sm">Registrar pago</summary>
                                    <div class="overlay-layer">
                                        <button class="overlay-backdrop" type="button" onclick="this.closest('details').removeAttribute('open')" aria-label="Cerrar"></button>
                                        <div class="card overlay-panel">
                                            <div class="overlay-header">
                                                <div>
                                                    <h3>Registrar pago</h3>
                                                    <span class="muted" style="font-size:12px">{{ $invoice->invoice_number }} · {{ $money($invoice->total) }}</span>
                                                </div>
                                                <button class="btn secondary sm" type="button" onclick="this.closest('details').removeAttribute('open')">✕</button>
                                            </div>
                                            <form method="post" action="{{ route('admin.purchase-invoices.pay', $invoice) }}" class="pay-form">
                                                @csrf
                                                <label>
                                                    Estado de pago
                                                    <select name="payment_status" required>
                                                        <option value="paid"    @selected($invoice->payment_status === 'paid')>Pagada</option>
                                                        <option value="partial" @selected($invoice->payment_status === 'partial')>Parcial</option>
                                                        <option value="pending" @selected($invoice->payment_status === 'pending')>Pendiente</option>
                                                    </select>
                                                </label>
                                                <label>
                                                    Método de pago
                                                    <input type="text" name="payment_method" value="{{ $invoice->payment_method }}" placeholder="Efectivo, Transferencia, Tarjeta…" maxlength="100">
                                                </label>
                                                <div class="actions">
                                                    <button class="btn" type="submit">Guardar</button>
                                                    <button class="btn secondary" type="button" onclick="this.closest('details').removeAttribute('open')">Cancelar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="empty-state">
            No hay facturas pendientes de pago. ¡Todo al día!
        </div>
    @endforelse

    @if($invoices->hasPages())
        <div class="pagination-wrap">
            <p class="muted" style="margin:0 0 8px;font-size:13px">
                Mostrando {{ $invoices->firstItem() }}–{{ $invoices->lastItem() }} de {{ $invoices->total() }} facturas
            </p>
            {{ $invoices->links() }}
        </div>
    @endif
</x-layouts.app>
