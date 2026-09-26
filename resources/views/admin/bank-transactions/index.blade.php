<x-layouts.app title="Control bancario | Coteja">
    @php
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $documentTypes = [
            '01' => 'Factura', '03' => 'CCF', '05' => 'Nota crédito',
            '06' => 'Nota débito', '11' => 'Exportación', '14' => 'Sujeto excluido', '99' => 'Otro',
        ];
    @endphp

    <style>
        .page-header { margin-bottom: 24px; }
        .page-header h1 { margin: 0; font-size: 26px; }
        .page-header p { margin: 4px 0 0; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .form-grid label { display: grid; gap: 6px; font-size: 13px; color: #374151; font-weight: 600; }
        body.dark-mode .form-grid label { color: #d1d5db; }
        .form-grid input, .form-grid select, .form-grid textarea { width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; font: inherit; background: var(--panel); color: var(--ink); }
        .form-grid textarea { min-height: 70px; }
        .form-grid .span-full { grid-column: 1 / -1; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 8px 14px; border-radius: 8px; border: 0; background: var(--brand); color: #fff; font-weight: 700; font: inherit; cursor: pointer; font-size: 13px; }
        .btn.secondary { background: #4b5563; }
        .btn.sm { min-height: 30px; padding: 5px 10px; font-size: 12px; }
        .badge { display: inline-flex; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .pending { background: #fef3c7; color: #92400e; }
        .partial { background: #dbeafe; color: #1e40af; }
        .paid { background: #dcfce7; color: #166534; }
        .void { background: #fee2e2; color: #991b1b; }
        .section-title { font-size: 17px; font-weight: 700; margin: 0 0 16px; }
        .bt-table { width: 100%; border-collapse: collapse; }
        .bt-table th, .bt-table td { padding: 10px 14px; border-bottom: 1px solid var(--line); text-align: left; font-size: 13px; vertical-align: middle; }
        .bt-table th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: #f9fafb; }
        body.dark-mode .bt-table th { background: #0f172a; color: #cbd5e1; }
        .bt-table tr:last-child td { border-bottom: 0; }
        .empty-state { padding: 40px; text-align: center; color: var(--muted); font-size: 15px; }
        /* overlay */
        .overlay-modal { position: relative; display: inline-block; }
        .overlay-modal > summary { list-style: none; cursor: pointer; }
        .overlay-modal > summary::-webkit-details-marker { display: none; }
        .overlay-layer { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; }
        .overlay-backdrop { position: absolute; inset: 0; background: rgb(0 0 0 / .45); border: 0; cursor: pointer; }
        .overlay-panel { position: relative; z-index: 1; min-width: 320px; max-width: 520px; width: 100%; }
        .overlay-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .overlay-header h3 { margin: 0; font-size: 16px; }
        .linked-invoices { display: grid; gap: 8px; }
        .linked-invoice-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); }
        .linked-invoice-row .inv-info { display: grid; gap: 2px; }
        .linked-invoice-row .inv-num { font-size: 13px; font-weight: 700; }
        .linked-invoice-row .inv-sup { font-size: 11px; color: var(--muted); }
        .linked-invoice-row .inv-amt { font-size: 14px; font-weight: 800; white-space: nowrap; }
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
    </style>

    <div class="page-header">
        <h1>Control bancario</h1>
        <p class="muted">Registro de transacciones bancarias vinculadas a facturas de compra.</p>
    </div>

    {{-- Section A: New bank transaction form --}}
    <div class="card" style="margin-bottom:24px">
        <h2 class="section-title">Nueva transacción bancaria</h2>
        <form method="post" action="{{ route('admin.bank-transactions.store') }}">
            @csrf
            <div class="form-grid">
                <label>
                    Fecha de pago <span style="color:var(--bad)">*</span>
                    <input type="date" name="transaction_date" value="{{ now()->toDateString() }}" required>
                </label>
                <label>
                    Banco / Cuenta
                    <input type="text" name="bank_account" placeholder="Banco Agrícola / Cta 1234…" maxlength="200">
                </label>
                <label>
                    Monto <span style="color:var(--bad)">*</span>
                    <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                </label>
                <label>
                    Referencia / N° cheque
                    <input type="text" name="reference" placeholder="REF-001, CHQ-123…" maxlength="200">
                </label>
                <label class="span-full">
                    Notas
                    <textarea name="notes" placeholder="Observaciones adicionales…" maxlength="2000"></textarea>
                </label>
                <label class="span-full">
                    Facturas a pagar <span style="color:var(--bad)">*</span>
                    <select name="invoice_ids[]" multiple required style="min-height:120px">
                        @forelse($pendingInvoices as $inv)
                            <option value="{{ $inv->id }}">
                                {{ $inv->invoice_number }} — {{ $inv->supplier?->name ?? 'Sin proveedor' }} (${{ number_format((float)$inv->total, 2) }})
                            </option>
                        @empty
                            <option disabled>No hay facturas pendientes de pago</option>
                        @endforelse
                    </select>
                    <span style="font-size:12px;color:var(--muted)">Mantén Ctrl (Windows) o Cmd (Mac) para seleccionar varias.</span>
                </label>
            </div>
            <div style="margin-top:16px">
                <button class="btn" type="submit">Registrar pago</button>
            </div>
        </form>
    </div>

    {{-- Section B: History table --}}
    <div class="card">
        <h2 class="section-title">Historial de transacciones</h2>

        @if($transactions->isEmpty())
            <div class="empty-state">No hay transacciones bancarias registradas.</div>
        @else
            <div style="overflow-x:auto">
                <table class="bt-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Banco / Cuenta</th>
                            <th>Referencia</th>
                            <th>Monto</th>
                            <th>Facturas pagadas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                            <tr>
                                <td>{{ $tx->transaction_date->format('d/m/Y') }}</td>
                                <td>{{ $tx->bank_account ?: '—' }}</td>
                                <td>{{ $tx->reference ?: '—' }}</td>
                                <td><strong>{{ $money($tx->amount) }}</strong></td>
                                <td>
                                    @if($tx->purchaseInvoices->count())
                                        <span class="badge paid">{{ $tx->purchaseInvoices->count() }} factura(s)</span>
                                    @else
                                        <span class="muted">Sin facturas</span>
                                    @endif
                                </td>
                                <td>
                                    <details class="overlay-modal">
                                        <summary class="btn secondary sm">Ver detalle</summary>
                                        <div class="overlay-layer">
                                            <button class="overlay-backdrop" type="button" onclick="this.closest('details').removeAttribute('open')" aria-label="Cerrar"></button>
                                            <div class="card overlay-panel">
                                                <div class="overlay-header">
                                                    <div>
                                                        <h3>Transacción del {{ $tx->transaction_date->format('d/m/Y') }}</h3>
                                                        <span class="muted" style="font-size:12px">
                                                            {{ $tx->bank_account ?: 'Sin banco' }}
                                                            @if($tx->reference) · Ref: {{ $tx->reference }} @endif
                                                        </span>
                                                    </div>
                                                    <button class="btn secondary sm" type="button" onclick="this.closest('details').removeAttribute('open')">✕</button>
                                                </div>
                                                <div style="margin-bottom:12px">
                                                    <strong>Monto total:</strong> {{ $money($tx->amount) }}
                                                    @if($tx->notes)
                                                        <br><span class="muted" style="font-size:12px">{{ $tx->notes }}</span>
                                                    @endif
                                                </div>
                                                @if($tx->purchaseInvoices->isNotEmpty())
                                                    <div class="linked-invoices">
                                                        @foreach($tx->purchaseInvoices as $inv)
                                                            <div class="linked-invoice-row">
                                                                <div class="inv-info">
                                                                    <div class="inv-num">{{ $inv->invoice_number }}</div>
                                                                    <div class="inv-sup">{{ $inv->supplier?->name ?? '—' }}</div>
                                                                </div>
                                                                <div class="inv-amt">
                                                                    {{ $money($inv->pivot->amount_applied ?? $inv->total) }}
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="muted" style="margin:0">Sin facturas vinculadas.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($transactions->hasPages())
                <div class="pagination-wrap">
                    <p class="muted" style="margin:0 0 8px;font-size:13px">
                        Mostrando {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} de {{ $transactions->total() }} transacciones
                    </p>
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
