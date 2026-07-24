<x-layouts.app title="Facturación | Coteja">
    @php
        $documentTypes = [
            '01' => 'Factura',
            '03' => 'CCF',
            '05' => 'Nota crédito',
            '06' => 'Nota débito',
            '07' => 'Retención',
            '11' => 'Exportación',
            '14' => 'Sujeto excluido',
        ];
        $money = fn ($value) => '$'.number_format((float) $value, 2);
    @endphp

    <div class="top">
        <div>
            <h1>Facturación</h1>
            <span class="muted">{{ $customer->name }} · {{ $activeCompany->business_name }}</span>
        </div>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Mis facturas</h3>
                <span class="muted">Mostrando {{ $invoices->count() }} de {{ $invoices->total() }} documentos</span>
            </div>
            <form method="get" action="{{ route('client.billing') }}" class="client-search">
                <label class="sr-only" for="billing-search">Buscar facturas</label>
                <input id="billing-search" name="search" type="search" value="{{ $search }}" placeholder="Buscar factura, código o estado...">
                <button class="btn" type="submit">Buscar</button>
                @if($search)
                    <a class="btn secondary" href="{{ route('client.billing') }}">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Documento</th>
                        <th>Código generación</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->issued_at?->format('d/m/Y') }}</td>
                            <td>
                                {{ $documentTypes[$invoice->document_type] ?? $invoice->document_type }}
                                <br><span class="muted">{{ $invoice->number_control }}</span>
                            </td>
                            <td><span class="muted">{{ $invoice->generation_code ?: 'Sin código' }}</span></td>
                            <td>{{ $money($invoice->subtotal) }}</td>
                            <td>{{ $money($invoice->iva) }}</td>
                            <td><strong>{{ $money($invoice->total) }}</strong></td>
                            <td><span class="badge {{ $invoice->accepted ? 'active' : ($invoice->has_error ? 'suspended' : '') }}">{{ $invoice->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No hay facturas disponibles.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $invoices->links() }}
        </div>
    </div>

    <style>
        .client-search { display: flex; align-items: center; justify-content: flex-end; flex: 1 1 420px; gap: 8px; }
        .client-search input { max-width: 340px; }
        .pagination-wrap nav > div:first-child { display: none; }
        .pagination-wrap nav > div:last-child,
        .pagination-wrap nav .relative { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pagination-wrap a,
        .pagination-wrap span[aria-current] span,
        .pagination-wrap span[aria-disabled] span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            min-height: 36px;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
        }
        .pagination-wrap span[aria-current] span { background: var(--brand); color: #fff; border-color: var(--brand); }
        @media (max-width: 800px) {
            .client-search { flex: 1 1 100%; justify-content: stretch; }
            .client-search input { max-width: none; }
        }
    </style>
</x-layouts.app>
