import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import TenantSelector from '@/Components/Billing/TenantSelector';

const PAYMENT_STATUS_LABEL = { pendiente: 'Pendiente', parcial: 'Parcial', pagado: 'Pagado' };
const PAYMENT_STATUS_STYLE = {
    pendiente: { background: '#fef3c7', color: '#92400e' },
    parcial:   { background: '#eff6ff', color: '#1d4ed8' },
    pagado:    { background: '#f0fdf4', color: '#15803d' },
};

const fmt = (n) => Number(n ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const METHOD_OPTIONS = ['Efectivo', 'Transferencia bancaria', 'Cheque', 'Tarjeta', 'Otro'];

function StatCard({ label, value, mono = false }) {
    return (
        <div className="card" style={{ padding: '12px 16px', minWidth: 120 }}>
            <p style={{ margin: '0 0 4px', fontSize: 11, color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                {label}
            </p>
            <p style={{ margin: 0, fontWeight: 700, fontSize: 18, fontFamily: mono ? 'monospace' : undefined }}>
                {value}
            </p>
        </div>
    );
}

function PaymentForm({ invoice, onClose }) {
    const form = useForm({ amount: '', method: '', reference: '', notes: '' });

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.factura-sv.facturas.pagar', invoice.id), { onSuccess: onClose });
    }

    return (
        <form onSubmit={handleSubmit}>
            <p style={{ fontSize: 13, color: '#6b7280', marginTop: 0, marginBottom: 16 }}>
                {invoice.numberControl} — Saldo pendiente:{' '}
                <strong style={{ fontFamily: 'monospace' }}>${fmt(invoice.balance)}</strong>
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                <div className="field" style={{ margin: 0 }}>
                    <label>Monto recibido <span style={{ color: '#ef4444' }}>*</span></label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        max={invoice.balance}
                        value={form.data.amount}
                        onChange={(e) => form.setData('amount', e.target.value)}
                        required
                        autoFocus
                    />
                    {form.errors.amount && <p className="field-error">{form.errors.amount}</p>}
                </div>

                <div className="field" style={{ margin: 0 }}>
                    <label>Método de pago</label>
                    <select value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                        <option value="">Seleccionar…</option>
                        {METHOD_OPTIONS.map((m) => <option key={m} value={m}>{m}</option>)}
                    </select>
                    {form.errors.method && <p className="field-error">{form.errors.method}</p>}
                </div>

                <div className="field" style={{ margin: 0, gridColumn: '1 / -1' }}>
                    <label>Referencia / No. comprobante</label>
                    <input
                        type="text"
                        value={form.data.reference}
                        onChange={(e) => form.setData('reference', e.target.value)}
                        placeholder="Número de comprobante, cheque o referencia…"
                    />
                    {form.errors.reference && <p className="field-error">{form.errors.reference}</p>}
                </div>

                <div className="field" style={{ margin: 0, gridColumn: '1 / -1' }}>
                    <label>Notas</label>
                    <input
                        type="text"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        placeholder="Observaciones adicionales…"
                    />
                    {form.errors.notes && <p className="field-error">{form.errors.notes}</p>}
                </div>
            </div>

            <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onClose}>Cancelar</button>
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar pago'}
                </button>
            </div>
        </form>
    );
}

export default function CuentasPorCobrar({ stats, invoices, filters, availableCustomers = [] }) {
    const { auth, billingTenant } = usePage().props;
    const [search, setSearch]         = useState(filters?.search ?? '');
    const [from, setFrom]             = useState(filters?.from ?? '');
    const [to, setTo]                 = useState(filters?.to ?? '');
    const [paymentStatus, setPaymentStatus] = useState(filters?.payment_status ?? '');
    const [payModal, setPayModal]     = useState(null);

    function handleFilter(e) {
        e.preventDefault();
        router.get(
            route('admin.factura-sv.billing.cuentas-por-cobrar'),
            { search, from, to, payment_status: paymentStatus },
            { preserveState: true }
        );
    }

    function clearFilters() {
        setSearch(''); setFrom(''); setTo(''); setPaymentStatus('');
        router.get(route('admin.factura-sv.billing.cuentas-por-cobrar'));
    }

    const hasFilter = !!(filters?.search || filters?.from || filters?.to || filters?.payment_status);

    return (
        <AppLayout>
            <Head title="Cuentas por cobrar" />

            {auth?.user?.is_admin && (
                <TenantSelector
                    billingTenant={billingTenant}
                    availableCustomers={availableCustomers}
                    setRoute="billing.empresa.set"
                    clearRoute="billing.empresa.clear"
                />
            )}

            <div className="top">
                <h1>Cuentas por cobrar</h1>
            </div>

            {/* Stats */}
            <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 16 }}>
                <StatCard label="Por cobrar" value={`$${fmt(stats.totalPorCobrar)}`} mono />
                <StatCard label="Cobrado" value={`$${fmt(stats.totalCobrado)}`} mono />
                <StatCard label="Pendientes" value={stats.countPendiente} />
                <StatCard label="Parciales" value={stats.countParcial} />
                <StatCard label="Pagadas" value={stats.countPagado} />
            </div>

            {/* Filters */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleFilter} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Factura o cliente…"
                        />
                    </label>
                    <label style={{ flex: '1 1 140px', margin: 0 }}>
                        Desde
                        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </label>
                    <label style={{ flex: '1 1 140px', margin: 0 }}>
                        Hasta
                        <input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </label>
                    <label style={{ flex: '1 1 150px', margin: 0 }}>
                        Estado de pago
                        <select value={paymentStatus} onChange={(e) => setPaymentStatus(e.target.value)}>
                            <option value="">Todos</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="parcial">Parcial</option>
                            <option value="pagado">Pagado</option>
                        </select>
                    </label>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="submit" className="btn">Filtrar</button>
                        {hasFilter && (
                            <button type="button" className="btn secondary" onClick={clearFilters}>
                                Limpiar
                            </button>
                        )}
                    </div>
                </form>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>No. Control</th>
                            <th>Cliente</th>
                            <th style={{ textAlign: 'right' }}>Total</th>
                            <th style={{ textAlign: 'right' }}>Abonado</th>
                            <th style={{ textAlign: 'right' }}>Saldo</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="empty">
                                    No hay facturas aceptadas en cuentas por cobrar
                                </td>
                            </tr>
                        )}
                        {invoices.data.map((inv) => (
                            <tr key={inv.id}>
                                <td style={{ fontSize: 13 }}>{inv.date ?? '—'}</td>
                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{inv.numberControl}</td>
                                <td style={{ fontWeight: 500 }}>{inv.customerName}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>
                                    ${fmt(inv.total)}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontSize: 13 }}>
                                    ${fmt(inv.amountPaid)}
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 700 }}>
                                    ${fmt(inv.balance)}
                                </td>
                                <td>
                                    <span style={{
                                        fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10,
                                        ...(PAYMENT_STATUS_STYLE[inv.paymentStatus] ?? { background: '#f3f4f6', color: '#374151' }),
                                    }}>
                                        {PAYMENT_STATUS_LABEL[inv.paymentStatus] ?? inv.paymentStatus}
                                    </span>
                                </td>
                                <td>
                                    {inv.paymentStatus !== 'pagado' ? (
                                        <button
                                            type="button"
                                            className="btn"
                                            style={{ fontSize: 12, padding: '3px 10px' }}
                                            onClick={() => setPayModal(inv)}
                                        >
                                            Registrar pago
                                        </button>
                                    ) : (
                                        <span className="muted" style={{ fontSize: 12 }}>{inv.paidAt}</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={invoices.links} />
            </div>

            {payModal && (
                <Modal
                    title="Registrar pago"
                    onClose={() => setPayModal(null)}
                    maxWidth={480}
                >
                    <PaymentForm invoice={payModal} onClose={() => setPayModal(null)} />
                </Modal>
            )}
        </AppLayout>
    );
}
