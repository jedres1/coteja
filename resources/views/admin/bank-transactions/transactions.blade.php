<x-layouts.app title="Transacciones bancarias | Coteja">
    @php
        $money = fn($v) => '$'.number_format((float)$v, 2);
        $typeLabels = ['deposito'=>'Depósito','pago'=>'Pago','transferencia'=>'Transferencia','cheque'=>'Cheque','otro'=>'Otro'];
        $typeColors = ['deposito'=>'background:#dcfce7;color:#166534','pago'=>'background:#dbeafe;color:#1e40af','transferencia'=>'background:#f3e8ff;color:#6b21a8','cheque'=>'background:#fef3c7;color:#92400e','otro'=>'background:#f3f4f6;color:#374151'];
    @endphp
    <style>
        .page-header{margin-bottom:24px}.page-header h1{margin:0;font-size:26px}.page-header p{margin:4px 0 0}
        .section-title{font-size:17px;font-weight:700;margin:0 0 16px}
        .bt-table{width:100%;border-collapse:collapse}
        .bt-table th,.bt-table td{padding:10px 14px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:middle}
        .bt-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);background:#f9fafb}
        body.dark-mode .bt-table th{background:#0f172a;color:#cbd5e1}
        .bt-table tr:last-child td{border-bottom:0}
        .empty-state{padding:40px;text-align:center;color:var(--muted);font-size:15px}
        .badge{display:inline-flex;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700}
        .source-compras{background:#fff7ed;color:#9a3412}.source-facturacion{background:#eff6ff;color:#1d4ed8}.source-manual{background:#f9fafb;color:#374151}
        .reconciled-badge{background:#dcfce7;color:#166534}
        .filters-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px}
        .filters-bar label{display:grid;gap:4px;font-size:12px;font-weight:600;color:var(--muted)}
        .filters-bar input,.filters-bar select{padding:8px 10px;border:1px solid var(--line);border-radius:8px;font:inherit;background:var(--panel);color:var(--ink);font-size:13px}
        .overlay-layer{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center}
        .overlay-backdrop{position:absolute;inset:0;background:rgb(0 0 0/.45);border:0;cursor:pointer}
        .overlay-panel{position:relative;z-index:1;min-width:340px;max-width:540px;width:100%;max-height:90vh;overflow-y:auto}
        .overlay-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}
        .overlay-header h3{margin:0;font-size:16px}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
        .form-grid label{display:grid;gap:6px;font-size:13px;color:#374151;font-weight:600}
        body.dark-mode .form-grid label{color:#d1d5db}
        .span-full{grid-column:1/-1}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 14px;border-radius:8px;border:0;background:var(--brand);color:#fff;font-weight:700;font:inherit;cursor:pointer;font-size:13px}
        .btn.secondary{background:#4b5563}.btn.sm{min-height:30px;padding:5px 10px;font-size:12px}
        .actions-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
        .summary-bar{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px}
        .summary-item{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:12px 16px;min-width:140px}
        .summary-item span{display:block;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
        .summary-item strong{font-size:20px;font-weight:800}
    </style>

    <div class="page-header">
        <h1>Transacciones bancarias</h1>
        <p class="muted">Movimientos de compras aprobadas y facturación cobrada.</p>
    </div>

    {{-- Filters --}}
    <form method="get" action="{{ route('admin.bank-transactions.transactions') }}">
        <div class="filters-bar">
            <label>Desde<input type="date" name="from" value="{{ $from }}"></label>
            <label>Hasta<input type="date" name="to" value="{{ $to }}"></label>
            <label>Cuenta bancaria
                <select name="bank_account_id">
                    <option value="">Todas las cuentas</option>
                    @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}" {{ $bankAccountId == $account->id ? 'selected' : '' }}>{{ $account->name }} — {{ $account->bank_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Tipo
                <select name="type">
                    <option value="">Todos los tipos</option>
                    <option value="deposito" {{ request('type') === 'deposito' ? 'selected' : '' }}>Depósito</option>
                    <option value="pago" {{ request('type') === 'pago' ? 'selected' : '' }}>Pago</option>
                    <option value="transferencia" {{ request('type') === 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="cheque" {{ request('type') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                </select>
            </label>
            <button class="btn" type="submit">Filtrar</button>
            <button class="btn secondary" type="button" onclick="document.getElementById('new-tx-overlay').style.display='flex'">+ Nueva transacción</button>
        </div>
    </form>

    {{-- Summary --}}
    @php
        $totalAmount = $transactions->sum('amount');
        $countCompras = $transactions->where('source', 'compras')->count();
        $countFacturacion = $transactions->where('source', 'facturacion')->count();
        $countManual = $transactions->where('source', 'manual')->count();
    @endphp
    <div class="summary-bar">
        <div class="summary-item"><span>Total movimientos</span><strong>{{ $money($totalAmount) }}</strong></div>
        <div class="summary-item"><span>Transacciones</span><strong>{{ $transactions->count() }}</strong></div>
        <div class="summary-item"><span>De compras</span><strong>{{ $countCompras }}</strong></div>
        <div class="summary-item"><span>De facturación</span><strong>{{ $countFacturacion }}</strong></div>
    </div>

    <div class="card">
        <h2 class="section-title">Movimientos del período</h2>
        @if($transactions->isEmpty())
            <div class="empty-state">No hay transacciones para el período seleccionado.</div>
        @else
            <div style="overflow-x:auto">
                <table class="bt-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Origen</th>
                            <th>Cuenta</th>
                            <th>Monto</th>
                            <th>Referencia</th>
                            <th>Documento</th>
                            <th>Estado</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($tx['date'])->format('d/m/Y') }}</td>
                                <td>
                                    @php $tLabel = $typeLabels[$tx['type']] ?? ucfirst($tx['type']); $tStyle = $typeColors[$tx['type']] ?? ''; @endphp
                                    <span class="badge" style="{{ $tStyle }}">{{ $tLabel }}</span>
                                </td>
                                <td><span class="badge source-{{ $tx['source'] }}">{{ ucfirst($tx['source']) }}</span></td>
                                <td>{{ $tx['bank_account'] }}</td>
                                <td><strong>{{ $money($tx['amount']) }}</strong></td>
                                <td>{{ $tx['reference'] ?: '—' }}</td>
                                <td>
                                    @if($tx['document'])
                                        <span style="font-size:11px;color:var(--muted)">{{ $tx['document_label'] }}</span><br>
                                        <strong>{{ $tx['document'] }}</strong>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($tx['is_reconciled'])
                                        <span class="badge reconciled-badge">Conciliado</span>
                                    @else
                                        <span class="muted" style="font-size:12px">Pendiente</span>
                                    @endif
                                </td>
                                <td style="max-width:160px;font-size:12px;color:var(--muted)">{{ $tx['notes'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- New manual transaction overlay --}}
    <div id="new-tx-overlay" class="overlay-layer" style="display:none">
        <button class="overlay-backdrop" type="button" onclick="document.getElementById('new-tx-overlay').style.display='none'"></button>
        <div class="card overlay-panel">
            <div class="overlay-header">
                <div><h3>Nueva transacción manual</h3><span class="muted" style="font-size:12px">Registra un movimiento bancario sin factura asociada.</span></div>
                <button class="btn secondary sm" type="button" onclick="document.getElementById('new-tx-overlay').style.display='none'">Cerrar</button>
            </div>
            <form method="post" action="{{ route('admin.bank-transactions.transactions.store') }}">
                @csrf
                <div class="form-grid">
                    <label>Fecha <span style="color:var(--bad)">*</span><input type="date" name="transaction_date" value="{{ today()->toDateString() }}" required></label>
                    <label>Cuenta bancaria <span style="color:var(--bad)">*</span>
                        <select name="bank_account_id" required>
                            <option value="">Seleccionar cuenta...</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} — {{ $account->bank_name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Tipo <span style="color:var(--bad)">*</span>
                        <select name="type" required>
                            <option value="deposito">Depósito</option>
                            <option value="pago" selected>Pago</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="cheque">Cheque</option>
                            <option value="otro">Otro</option>
                        </select>
                    </label>
                    <label>Monto <span style="color:var(--bad)">*</span><input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00"></label>
                    <label>Referencia<input type="text" name="reference" maxlength="200" placeholder="REF-001, CHQ-123…"></label>
                    <label class="span-full">Notas<textarea name="notes" rows="2" maxlength="2000"></textarea></label>
                </div>
                <div class="actions-row">
                    <button class="btn" type="submit">Registrar transacción</button>
                    <button class="btn secondary" type="button" onclick="document.getElementById('new-tx-overlay').style.display='none'">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
