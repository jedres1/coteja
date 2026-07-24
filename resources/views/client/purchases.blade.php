<x-layouts.app title="Compras | Coteja">
    @php
        $documentTypes = [
            '01' => 'Factura',
            '03' => 'CCF',
            '05' => 'Nota crédito',
            '06' => 'Nota débito',
            '11' => 'Exportación',
            '14' => 'Sujeto excluido',
            '99' => 'Otro',
        ];
        $paymentStatuses = [
            'pending' => 'Pendiente',
            'partial' => 'Parcial',
            'paid' => 'Pagada',
            'void' => 'Anulada',
        ];
        $money = fn ($value) => '$'.number_format((float) $value, 2);
    @endphp

    <div class="top">
        <div>
            <h1>Compras</h1>
            <span class="muted">{{ $customer->name }} · {{ $activeCompany->business_name }}</span>
        </div>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Mis compras</h3>
                <span class="muted">Mostrando {{ $invoices->count() }} de {{ $invoices->total() }} facturas</span>
            </div>
            <form method="get" action="{{ route('client.purchases') }}" class="client-search">
                <label class="sr-only" for="purchase-search">Buscar compras</label>
                <input id="purchase-search" name="search" type="search" value="{{ $search }}" placeholder="Buscar factura, proveedor o estado...">
                <button class="btn" type="submit">Buscar</button>
                @if($search)
                    <a class="btn secondary" href="{{ route('client.purchases') }}">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Documento</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Pago</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td>
                                {{ $invoice->purchase_date?->format('d/m/Y') }}
                                @if($invoice->due_date)
                                    <br><span class="muted">Vence {{ $invoice->due_date->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $invoice->supplier?->name ?: 'Sin proveedor' }}
                                @if($invoice->supplier?->document_number)
                                    <br><span class="muted">{{ $invoice->supplier->document_number }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $documentTypes[$invoice->document_type] ?? $invoice->document_type }}
                                <br><span class="muted">{{ $invoice->invoice_number }}</span>
                            </td>
                            <td>{{ $money($invoice->subtotal) }}</td>
                            <td>{{ $money($invoice->iva) }}</td>
                            <td><strong>{{ $money($invoice->total) }}</strong></td>
                            <td>
                                <span class="badge">{{ $paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status }}</span>
                                @if($invoice->payment_method)
                                    <br><span class="muted">{{ $invoice->payment_method }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No hay compras disponibles.</td></tr>
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
