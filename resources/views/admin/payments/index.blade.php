<x-layouts.app title="Cobros | Coteja">
    @php
        $periods = ['monthly' => 'Mensual', 'annual' => 'Anual', 'implementation' => 'Implementacion', 'additional' => 'Adicional'];
        $paymentStatuses = ['paid' => 'Pagado', 'pending' => 'Pendiente', 'void' => 'Anulado'];
    @endphp

    <div class="top">
        <div>
            <h1>Cobros</h1>
            <span class="muted">Pagos de implementacion, mensualidad y anualidad</span>
        </div>
        <details class="overlay-modal" @if($errors->any()) open @endif>
            <summary class="btn">Registrar pago</summary>
            <div class="overlay-layer">
                <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                <div class="card overlay-panel">
                    <div class="overlay-header">
                        <div>
                            <h3>Registrar pago</h3>
                            <span class="muted">Asocie el cobro a una licencia.</span>
                        </div>
                        <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                    </div>
                    <form method="post" action="{{ route('admin.payments.store') }}" class="form-grid">
                        @csrf
                        @include('admin.payments.form', ['payment' => null, 'licenses' => $licenses, 'periods' => $periods, 'paymentStatuses' => $paymentStatuses])
                        <div class="form-actions full-width"><button class="btn">Registrar pago</button></div>
                    </form>
                </div>
            </div>
        </details>
    </div>

    <div class="card">
        <div class="list-header">
            <div>
                <h3>Listado</h3>
                <span class="muted">Mostrando {{ $payments->count() }} de {{ $payments->total() }} cobros</span>
            </div>
            <span class="badge">{{ $payments->perPage() }} por página</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Cliente</th><th>Empresa</th><th>Periodo</th><th>Total</th><th>Metodo</th><th>Estado</th><th>Fecha</th><th>Accion</th></tr></thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>{{ $payment->license->customer->name }}</td>
                        <td>{{ $payment->company->business_name }}</td>
                        <td>{{ $periods[$payment->period] ?? $payment->period }}</td>
                        <td>${{ number_format($payment->total, 2) }}<br><span class="muted">Base ${{ number_format($payment->amount, 2) }} · IVA ${{ number_format($payment->iva, 2) }}</span></td>
                        <td>{{ $payment->method ?: '—' }}@if($payment->reference)<br><span class="muted">{{ $payment->reference }}</span>@endif</td>
                        <td><span class="badge {{ $payment->status }}">{{ $paymentStatuses[$payment->status] ?? $payment->status }}</span></td>
                        <td>{{ optional($payment->paid_at)->format('Y-m-d') }}</td>
                        <td>
                            <details class="overlay-modal">
                                <summary class="btn secondary">Editar</summary>
                                <div class="overlay-layer">
                                    <button class="overlay-backdrop" type="button" data-overlay-close aria-label="Cerrar"></button>
                                    <div class="card overlay-panel">
                                        <div class="overlay-header">
                                            <div>
                                                <h3>Editar cobro</h3>
                                                <span class="muted">{{ $payment->license->customer->name }} - ${{ number_format($payment->total, 2) }}</span>
                                            </div>
                                            <button class="btn secondary overlay-close" type="button" data-overlay-close>Cerrar</button>
                                        </div>
                                        <form method="post" action="{{ route('admin.payments.update', $payment) }}" class="form-grid">
                                            @csrf
                                            @method('put')
                                            @include('admin.payments.form', ['payment' => $payment, 'licenses' => $licenses, 'periods' => $periods, 'paymentStatuses' => $paymentStatuses])
                                            <div class="form-actions full-width"><button class="btn">Guardar cambios</button></div>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No hay cobros registrados</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $payments->links() }}</div>
    </div>
</x-layouts.app>
