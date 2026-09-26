<x-layouts.app title="Extracción pendiente | Coteja">
    @php
        $documentTypes = [
            '01' => 'Factura', '03' => 'CCF', '05' => 'Nota crédito',
            '06' => 'Nota débito', '11' => 'Exportación', '14' => 'Sujeto excluido', '99' => 'Otro',
        ];
        $money = fn ($v) => '$'.number_format((float) $v, 2);
    @endphp

    <style>
        .pa-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .pa-summary-card { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 16px 18px; margin-bottom: 24px; display: inline-flex; align-items: center; gap: 14px; }
        .pa-summary-card .count { font-size: 36px; font-weight: 800; color: var(--warn); line-height: 1; }
        .pa-summary-card .label { font-size: 14px; color: var(--muted); }
        .badge { display: inline-flex; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .extracted { background: #fef3c7; color: #92400e; }
        .pending { background: #fef3c7; color: #92400e; }
        .partial { background: #dbeafe; color: #1e40af; }
        .paid { background: #dcfce7; color: #166534; }
        .void { background: #fee2e2; color: #991b1b; }
        .danger { background: var(--bad); }
        .row-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .empty-state { padding: 48px; text-align: center; color: var(--muted); font-size: 15px; }
        /* overlay */
        .overlay-modal { position: relative; display: inline-block; }
        .overlay-modal > summary { list-style: none; cursor: pointer; }
        .overlay-modal > summary::-webkit-details-marker { display: none; }
        .overlay-layer { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; }
        .overlay-backdrop { position: absolute; inset: 0; background: rgb(0 0 0 / .45); border: 0; cursor: pointer; }
        .overlay-panel { position: relative; z-index: 1; min-width: 320px; max-width: 520px; width: 100%; }
        .overlay-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .overlay-header h3 { margin: 0; font-size: 16px; }
        .document-summary-card { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
        .detail-item { padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); display: flex; flex-direction: column; gap: 4px; }
        .detail-item span { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .detail-item strong { font-size: 13px; word-break: break-word; }
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
        .btn.green { background: var(--ok); }
        .btn.red { background: var(--bad); }
        .pa-table { width: 100%; border-collapse: collapse; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .pa-table th, .pa-table td { padding: 10px 14px; border-bottom: 1px solid var(--line); text-align: left; font-size: 13px; vertical-align: middle; }
        .pa-table th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: #f9fafb; }
        body.dark-mode .pa-table th { background: #0f172a; color: #cbd5e1; }
        .pa-table tr:last-child td { border-bottom: 0; }
        .notes-trunc { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>

    <div class="pa-header">
        <div>
            <h1 style="margin:0;font-size:26px">Extracción pendiente</h1>
            <p class="muted" style="margin:4px 0 0">Facturas extraídas del correo que requieren aprobación antes de pasar a Cuentas por pagar.</p>
        </div>
        <a class="btn secondary" href="{{ route('admin.purchase-invoices.index') }}">Ver todas las facturas</a>
    </div>

    <div class="pa-summary-card">
        <div class="count">{{ $total }}</div>
        <div class="label">factura(s) pendiente(s)<br>de aprobación</div>
    </div>

    @if($invoices->isEmpty())
        <div class="empty-state">No hay facturas pendientes de aprobación.</div>
    @else
        <table class="pa-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Documento</th>
                    <th>Subtotal</th>
                    <th>IVA</th>
                    <th>Total</th>
                    <th>Notas</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->purchase_date?->format('d/m/Y') }}</td>
                        <td>
                            <strong>{{ $invoice->supplier?->name ?? '—' }}</strong>
                            @if($invoice->supplier?->document_number)
                                <br><span class="muted" style="font-size:11px">{{ $invoice->supplier->document_number }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $documentTypes[$invoice->document_type] ?? $invoice->document_type }}
                            <br><span class="muted" style="font-size:11px">{{ $invoice->invoice_number }}</span>
                        </td>
                        <td>{{ $money($invoice->subtotal) }}</td>
                        <td>{{ $money($invoice->iva) }}</td>
                        <td><strong>{{ $money($invoice->total) }}</strong></td>
                        <td>
                            @if($invoice->notes)
                                <span class="notes-trunc" title="{{ $invoice->notes }}">{{ Str::limit($invoice->notes, 80) }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                {{-- Ver documento --}}
                                <details class="overlay-modal">
                                    <summary class="btn secondary sm">Ver doc.</summary>
                                    <div class="overlay-layer">
                                        <button class="overlay-backdrop" type="button" onclick="this.closest('details').removeAttribute('open')" aria-label="Cerrar"></button>
                                        <div class="card overlay-panel">
                                            <div class="overlay-header">
                                                <div>
                                                    <h3>Documento extraído</h3>
                                                    <span class="muted" style="font-size:12px">{{ $invoice->invoice_number }}</span>
                                                </div>
                                                <button class="btn secondary sm" type="button" onclick="this.closest('details').removeAttribute('open')">✕</button>
                                            </div>
                                            @php
                                                $decoded = null;
                                                if (!blank($invoice->extracted_document_body)) {
                                                    try { $decoded = json_decode($invoice->extracted_document_body, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable) {}
                                                }
                                                $ident   = $decoded['identificacion'] ?? [];
                                                $emisor  = $decoded['emisor'] ?? [];
                                                $resumen = $decoded['resumen'] ?? [];
                                            @endphp
                                            @if($decoded)
                                                <div class="document-summary-card">
                                                    <div class="detail-item">
                                                        <span>Proveedor</span>
                                                        <strong>{{ data_get($emisor, 'nombre') ?: ($invoice->supplier?->name ?? '—') }}</strong>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span>NIT Emisor</span>
                                                        <strong>{{ data_get($emisor, 'nit') ?: '—' }}</strong>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span>Tipo DTE</span>
                                                        <strong>{{ $documentTypes[data_get($ident, 'tipoDte')] ?? (data_get($ident, 'tipoDte') ?: '—') }}</strong>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span>N° Control</span>
                                                        <strong>{{ data_get($ident, 'numeroControl') ?: '—' }}</strong>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span>Fecha emisión</span>
                                                        <strong>{{ data_get($ident, 'fecEmi') ?: '—' }}</strong>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span>Subtotal</span>
                                                        <strong>{{ $money(data_get($resumen, 'subTotal') ?? data_get($resumen, 'totalGravada') ?? 0) }}</strong>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span>IVA</span>
                                                        <strong>{{ $money(data_get($resumen, 'totalIva') ?? data_get($resumen, 'ivaPerci1') ?? 0) }}</strong>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span>Total</span>
                                                        <strong>{{ $money(data_get($resumen, 'montoTotalOperacion') ?? data_get($resumen, 'totalPagar') ?? 0) }}</strong>
                                                    </div>
                                                </div>
                                            @else
                                                <p class="muted">No hay cuerpo de documento extraído disponible.</p>
                                            @endif
                                        </div>
                                    </div>
                                </details>

                                {{-- Aprobar --}}
                                <form method="post" action="{{ route('admin.purchase-invoices.approve', $invoice) }}" class="inline">
                                    @csrf
                                    <button class="btn green sm" type="submit">Aprobar</button>
                                </form>

                                {{-- Rechazar --}}
                                <form method="post" action="{{ route('admin.purchase-invoices.reject', $invoice) }}" class="inline" data-confirm-delete="Rechazar factura">
                                    @csrf
                                    <button class="btn red sm" type="submit">Rechazar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($invoices->hasPages())
            <div class="pagination-wrap" style="margin-top:16px">
                <p class="muted" style="margin:0 0 8px;font-size:13px">
                    Mostrando {{ $invoices->firstItem() }}–{{ $invoices->lastItem() }} de {{ $invoices->total() }} facturas
                </p>
                {{ $invoices->links() }}
            </div>
        @endif
    @endif

    <script>
        document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!confirm(`${form.dataset.confirmDelete}. ¿Desea continuar?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
</x-layouts.app>
