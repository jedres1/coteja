<x-layouts.app title="Conciliación {{ $reconciliation->periodLabel() }} | Coteja">
    @php
        $money = fn($v) => '$'.number_format((float)$v, 2);
        $typeLabels = ['deposito'=>'Depósito','pago'=>'Pago','transferencia'=>'Transferencia','cheque'=>'Cheque','otro'=>'Otro'];
    @endphp
    <style>
        .page-header{margin-bottom:24px}.page-header h1{margin:0;font-size:26px}.page-header p{margin:4px 0 0}
        .section-title{font-size:17px;font-weight:700;margin:0 0 16px}
        .bt-table{width:100%;border-collapse:collapse}
        .bt-table th,.bt-table td{padding:10px 14px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:middle}
        .bt-table th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);background:#f9fafb}
        body.dark-mode .bt-table th{background:#0f172a;color:#cbd5e1}
        .bt-table tr:last-child td{border-bottom:0}
        .bt-table tr.reconciled-row{background:#f0fdf4}
        body.dark-mode .bt-table tr.reconciled-row{background:#052e16}
        .empty-state{padding:40px;text-align:center;color:var(--muted);font-size:15px}
        .badge{display:inline-flex;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700}
        .reconciled-badge{background:#dcfce7;color:#166534}.pending-badge{background:#fef3c7;color:#92400e}
        .source-compras{background:#fff7ed;color:#9a3412}.source-facturacion{background:#eff6ff;color:#1d4ed8}.source-manual{background:#f9fafb;color:#374151}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 14px;border-radius:8px;border:0;background:var(--brand);color:#fff;font-weight:700;font:inherit;cursor:pointer;font-size:13px}
        .btn.secondary{background:#4b5563}.btn.success{background:var(--ok)}.btn.sm{min-height:30px;padding:5px 10px;font-size:12px}
        .summary-bar{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px}
        .summary-item{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:12px 16px;min-width:140px}
        .summary-item span{display:block;font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
        .summary-item strong{font-size:20px;font-weight:800}
        .recon-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap}
        .upload-area{background:var(--panel);border:2px dashed var(--line);border-radius:8px;padding:20px;text-align:center;margin-bottom:20px}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
        .form-grid label{display:grid;gap:6px;font-size:13px;color:#374151;font-weight:600}
        body.dark-mode .form-grid label{color:#d1d5db}
        .actions-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
    </style>

    <div class="page-header">
        <h1>Conciliación — {{ $reconciliation->periodLabel() }}</h1>
        <p class="muted">{{ $reconciliation->bankAccount->name }} · {{ $reconciliation->bankAccount->bank_name }}</p>
    </div>

    @php
        $reconciledTotal = $transactions->where('is_reconciled', true)->sum('amount');
        $pendingTotal = $transactions->where('is_reconciled', false)->sum('amount');
        $totalAll = $transactions->sum('amount');
        $statementBalance = $reconciliation->statement_balance;
        $difference = $statementBalance !== null ? ($statementBalance - $reconciledTotal) : null;
    @endphp

    <div class="recon-header">
        <div class="summary-bar">
            <div class="summary-item"><span>Total período</span><strong>{{ $money($totalAll) }}</strong></div>
            <div class="summary-item"><span>Conciliado</span><strong style="color:var(--ok)">{{ $money($reconciledTotal) }}</strong></div>
            <div class="summary-item"><span>Por conciliar</span><strong style="color:var(--warn)">{{ $money($pendingTotal) }}</strong></div>
            @if($statementBalance !== null)
                <div class="summary-item">
                    <span>Saldo estado de cuenta</span>
                    <strong>{{ $money($statementBalance) }}</strong>
                </div>
                <div class="summary-item">
                    <span>Diferencia</span>
                    <strong style="color:{{ $difference == 0 ? 'var(--ok)' : 'var(--bad)' }}">{{ $money(abs($difference)) }} {{ $difference > 0 ? '↑' : ($difference < 0 ? '↓' : '✓') }}</strong>
                </div>
            @endif
        </div>
        <div style="display:flex;gap:8px;align-items:flex-start">
            @if($reconciliation->status === 'borrador')
                <form method="post" action="{{ route('admin.bank-transactions.reconciliations.complete', $reconciliation) }}" onsubmit="return confirm('¿Marcar esta conciliación como completada?')">
                    @csrf
                    <button class="btn success" type="submit">Marcar completada</button>
                </form>
            @else
                <span class="badge reconciled-badge" style="padding:8px 14px;font-size:13px">✓ Completada</span>
            @endif
            <a class="btn secondary" href="{{ route('admin.bank-transactions.reconciliations') }}">← Volver</a>
        </div>
    </div>

    {{-- Upload statement --}}
    @if($reconciliation->status === 'borrador')
        <div class="card" style="margin-bottom:20px">
            <h2 class="section-title">Estado de cuenta bancario</h2>
            @if($reconciliation->statement_file_path)
                <p style="margin:0 0 12px">Archivo cargado: <a href="{{ Storage::url($reconciliation->statement_file_path) }}" target="_blank" style="color:var(--brand)">Ver estado de cuenta</a></p>
            @endif
            <form method="post" action="{{ route('admin.bank-transactions.reconciliations.upload', $reconciliation) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid">
                    <label>Cargar estado de cuenta (PDF, imagen)
                        <input type="file" name="statement_file" accept=".pdf,.jpg,.jpeg,.png" required>
                    </label>
                    <label>Saldo final según estado de cuenta
                        <input type="number" name="statement_balance" step="0.01" min="0" value="{{ $reconciliation->statement_balance }}" placeholder="0.00">
                    </label>
                </div>
                <div class="actions-row">
                    <button class="btn" type="submit">Cargar documento</button>
                </div>
            </form>
        </div>
    @elseif($reconciliation->statement_file_path)
        <div class="card" style="margin-bottom:20px">
            <p style="margin:0">Estado de cuenta: <a href="{{ Storage::url($reconciliation->statement_file_path) }}" target="_blank" style="color:var(--brand)">Ver archivo cargado</a></p>
        </div>
    @endif

    {{-- Transactions table --}}
    <div class="card">
        <h2 class="section-title">Transacciones del período</h2>
        @if($transactions->isEmpty())
            <div class="empty-state">No hay transacciones registradas para este período en esta cuenta.</div>
        @else
            <div style="overflow-x:auto">
                <table class="bt-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Origen</th>
                            <th>Monto</th>
                            <th>Referencia</th>
                            <th>Documento</th>
                            <th>Estado</th>
                            @if($reconciliation->status === 'borrador')<th>Acción</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                            <tr class="{{ $tx['is_reconciled'] ? 'reconciled-row' : '' }}">
                                <td>{{ \Carbon\Carbon::parse($tx['date'])->format('d/m/Y') }}</td>
                                <td>{{ $typeLabels[$tx['type']] ?? ucfirst($tx['type']) }}</td>
                                <td><span class="badge source-{{ $tx['source'] }}">{{ ucfirst($tx['source']) }}</span></td>
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
                                        <span class="badge pending-badge">Pendiente</span>
                                    @endif
                                </td>
                                @if($reconciliation->status === 'borrador')
                                    <td>
                                        <form method="post" action="{{ route('admin.bank-transactions.reconciliations.toggle', $reconciliation) }}">
                                            @csrf
                                            <input type="hidden" name="tx_id" value="{{ $tx['raw_id'] }}">
                                            <input type="hidden" name="tx_source" value="{{ $tx['source'] }}">
                                            <input type="hidden" name="reconciled" value="{{ $tx['is_reconciled'] ? '0' : '1' }}">
                                            <button class="btn sm {{ $tx['is_reconciled'] ? 'secondary' : '' }}" type="submit">
                                                {{ $tx['is_reconciled'] ? 'Desmarcar' : 'Conciliado' }}
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
