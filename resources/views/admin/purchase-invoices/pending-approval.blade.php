<x-layouts.app title="Extraer facturas | Coteja">
    @php
        $documentTypes = [
            '01' => 'Factura', '03' => 'CCF', '05' => 'Nota crédito',
            '06' => 'Nota débito', '11' => 'Exportación', '14' => 'Sujeto excluido', '99' => 'Otro',
        ];
        $statusLabels = [
            'extracted'   => 'Pendiente',
            'approved'    => 'Aprobada',
            'rejected'    => 'Rechazada',
            'registered'  => 'Registrada',
            'reviewed'    => 'Revisada',
            'accounted'   => 'Contabilizada',
            'void'        => 'Anulada',
        ];
        $money = fn ($v) => '$'.number_format((float) $v, 2);
        $today = now()->format('Y-m-d');
    @endphp

    <style>
        .pa-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .pa-summary-card { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 16px 18px; margin-bottom: 24px; display: inline-flex; align-items: center; gap: 14px; }
        .pa-summary-card .count { font-size: 36px; font-weight: 800; color: var(--warn); line-height: 1; }
        .pa-summary-card .label { font-size: 14px; color: var(--muted); }
        .badge { display: inline-flex; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-extracted  { background: #fef3c7; color: #92400e; }
        .badge-approved   { background: #dcfce7; color: #166534; }
        .badge-rejected   { background: #fee2e2; color: #991b1b; }
        .badge-registered { background: #e0e7ff; color: #3730a3; }
        .badge-reviewed   { background: #dbeafe; color: #1e40af; }
        .badge-accounted  { background: #f3e8ff; color: #6b21a8; }
        .badge-void       { background: #f1f5f9; color: #475569; }
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
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 8px 14px; border-radius: 8px; border: 0; background: var(--brand); color: #fff; font-weight: 700; font: inherit; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn.secondary { background: #4b5563; }
        .btn.sm { min-height: 30px; padding: 5px 10px; font-size: 12px; }
        .btn.green { background: var(--ok); }
        .btn.red { background: var(--bad); }
        .btn.outline { background: transparent; border: 1px solid var(--line); color: var(--text); }
        .pa-table { width: 100%; border-collapse: collapse; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .pa-table th, .pa-table td { padding: 10px 14px; border-bottom: 1px solid var(--line); text-align: left; font-size: 13px; vertical-align: middle; }
        .pa-table th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); background: #f9fafb; }
        body.dark-mode .pa-table th { background: #0f172a; color: #cbd5e1; }
        .pa-table tr:last-child td { border-bottom: 0; }
        .notes-trunc { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        /* bulk bar */
        .bulk-bar { display: none; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; background: var(--panel); border: 1px solid var(--brand); border-radius: 10px; margin-bottom: 12px; }
        .bulk-bar.visible { display: flex; }
        .bulk-bar .bulk-count { font-size: 13px; font-weight: 700; color: var(--brand); margin-right: 4px; }
        /* checkbox column */
        .pa-table th.col-check,
        .pa-table td.col-check { width: 40px; padding-left: 14px; padding-right: 6px; }
        input[type="checkbox"] { cursor: pointer; width: 16px; height: 16px; }
    </style>

    <div class="pa-header">
        <div>
            <h1 style="margin:0;font-size:26px">Extraer facturas</h1>
            <p class="muted" style="margin:4px 0 0">Facturas extraídas del correo que requieren aprobación antes de pasar a Cuentas por pagar.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <form method="post" action="{{ route('admin.purchase-invoices.extract') }}" data-confirm-extract="Se revisarán los correos del rango seleccionado y se procesarán sus adjuntos JSON; las facturas duplicadas se omitirán." style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                @csrf
                <label style="font-size:13px">Desde <input type="date" name="from" value="{{ old('from', now()->subMonthNoOverflow()->startOfMonth()->toDateString()) }}"></label>
                <label style="font-size:13px">Hasta <input type="date" name="to" value="{{ old('to', now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}"></label>
                <button class="btn" type="submit">Extraer</button>
            </form>
            @if($showAll)
                <a class="btn outline" href="{{ route('admin.purchase-invoices.pending-approval') }}">Solo pendientes</a>
            @else
                <a class="btn secondary" href="{{ route('admin.purchase-invoices.pending-approval', ['ver_todas' => 1]) }}">Ver todas</a>
            @endif
        </div>
    </div>

    <div class="pa-summary-card">
        <div class="count">{{ $total }}</div>
        <div class="label">factura(s) pendiente(s)<br>de aprobación</div>
    </div>

    @if(session('status'))
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--ok);font-size:13px;white-space:pre-line">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="card" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid var(--bad);font-size:13px">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Formulario oculto para acciones masivas --}}
    <form id="bulk-form" method="post" action="" style="display:none">
        @csrf
        <div id="bulk-ids"></div>
    </form>

    @if($invoices->isEmpty())
        <div class="empty-state">
            {{ $showAll ? 'No hay facturas registradas.' : 'No hay facturas pendientes de aprobación.' }}
        </div>
    @else
        {{-- Barra de acciones masivas --}}
        <div class="bulk-bar" id="bulk-bar">
            <span class="bulk-count" id="selected-count">0 seleccionadas</span>
            <button type="button" class="btn green sm" onclick="submitBulk('{{ route('admin.purchase-invoices.approve-bulk') }}')">
                Aprobar seleccionadas
            </button>
            <button type="button" class="btn red sm" onclick="submitBulk('{{ route('admin.purchase-invoices.reject-bulk') }}', true)">
                Rechazar seleccionadas
            </button>
        </div>

        <table class="pa-table">
            <thead>
                <tr>
                    <th class="col-check">
                        <input type="checkbox" id="check-all" title="Seleccionar todas las pendientes">
                    </th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Documento</th>
                    <th>Subtotal</th>
                    <th>IVA</th>
                    <th>Total</th>
                    <th>Notas</th>
                    @if($showAll)<th>Estado</th>@endif
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
                    <tr>
                        <td class="col-check">
                            @if($invoice->status === 'extracted')
                                <input type="checkbox" class="row-check" value="{{ $invoice->id }}">
                            @endif
                        </td>
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
                        @if($showAll)
                            <td>
                                <span class="badge badge-{{ $invoice->status }}">
                                    {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                                </span>
                            </td>
                        @endif
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
                                                $receptor = $decoded['receptor'] ?? [];
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
                                                <div style="margin-top:16px">
                                                    <h4 style="margin:0 0 10px;font-size:14px">Receptor</h4>
                                                    @if($receptor)
                                                        <div class="document-summary-card">
                                                            <div class="detail-item"><span>Nombre</span><strong>{{ data_get($receptor, 'nombre') ?: '—' }}</strong></div>
                                                            <div class="detail-item"><span>Tipo de documento</span><strong>{{ data_get($receptor, 'tipoDocumento') ?: '—' }}</strong></div>
                                                            <div class="detail-item"><span>NIT / DUI</span><strong>{{ data_get($receptor, 'numDocumento') ?: data_get($receptor, 'nit') ?: '—' }}</strong></div>
                                                            <div class="detail-item"><span>NRC</span><strong>{{ data_get($receptor, 'nrc') ?: '—' }}</strong></div>
                                                            <div class="detail-item"><span>Correo</span><strong>{{ data_get($receptor, 'correo') ?: '—' }}</strong></div>
                                                            <div class="detail-item"><span>Teléfono</span><strong>{{ data_get($receptor, 'telefono') ?: '—' }}</strong></div>
                                                            <div class="detail-item"><span>Actividad</span><strong>{{ data_get($receptor, 'descActividad') ?: data_get($receptor, 'codActividad') ?: '—' }}</strong></div>
                                                            <div class="detail-item"><span>Dirección</span><strong>{{ data_get($receptor, 'direccion.complemento') ?: '—' }}</strong></div>
                                                        </div>
                                                    @else
                                                        <p class="muted">El JSON de esta factura no incluye datos del receptor.</p>
                                                    @endif
                                                </div>
                                            @else
                                                <p class="muted">No hay cuerpo de documento extraído disponible.</p>
                                            @endif
                                        </div>
                                    </div>
                                </details>

                                @if($invoice->status === 'extracted')
                                    {{-- Aprobar individual --}}
                                    <form method="post" action="{{ route('admin.purchase-invoices.approve', $invoice) }}" class="inline">
                                        @csrf
                                        <button class="btn green sm" type="submit">Aprobar</button>
                                    </form>
                                    {{-- Rechazar individual --}}
                                    <form method="post" action="{{ route('admin.purchase-invoices.reject', $invoice) }}" class="inline" data-confirm-delete="Rechazar factura">
                                        @csrf
                                        <button class="btn red sm" type="submit">Rechazar</button>
                                    </form>
                                @else
                                    <span class="badge badge-{{ $invoice->status }}">
                                        {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                                    </span>
                                @endif
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
        // --- Selección masiva ---
        const checkAll = document.getElementById('check-all');
        const bulkBar  = document.getElementById('bulk-bar');
        const countEl  = document.getElementById('selected-count');

        function getChecked() {
            return Array.from(document.querySelectorAll('.row-check:checked'));
        }

        function updateBulkBar() {
            const n = getChecked().length;
            countEl.textContent = n + ' seleccionada(s)';
            bulkBar.classList.toggle('visible', n > 0);
            if (checkAll) {
                const all = document.querySelectorAll('.row-check');
                checkAll.indeterminate = n > 0 && n < all.length;
                checkAll.checked = all.length > 0 && n === all.length;
            }
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
                updateBulkBar();
            });
        }

        document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateBulkBar));

        function submitBulk(action, needsConfirm) {
            const checked = getChecked();
            if (checked.length === 0) return;
            if (needsConfirm && !confirm('Rechazar ' + checked.length + ' factura(s). ¿Desea continuar?')) return;

            const form = document.getElementById('bulk-form');
            form.action = action;
            const container = document.getElementById('bulk-ids');
            container.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            form.submit();
        }

        // --- Confirmaciones ---
        document.querySelectorAll('[data-confirm-delete]').forEach(form => {
            form.addEventListener('submit', e => {
                if (!confirm(form.dataset.confirmDelete + '. ¿Desea continuar?')) e.preventDefault();
            });
        });
        document.querySelectorAll('[data-confirm-extract]').forEach(form => {
            form.addEventListener('submit', e => {
                if (!confirm(form.dataset.confirmExtract + ' ¿Desea continuar?')) e.preventDefault();
            });
        });
    </script>
</x-layouts.app>
