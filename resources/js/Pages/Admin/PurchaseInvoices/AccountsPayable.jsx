import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';

// ─── PayForm ──────────────────────────────────────────────────────────────────

function PayForm({ invoice, onSuccess, onCancel }) {
    const { data, setData, post, processing, errors } = useForm({
        amount:  '',
        paid_at: '',
        method:  '',
        notes:   '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.purchase-invoices.pay', invoice.id), { onSuccess });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label>
                    Monto pagado
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        required
                        autoFocus
                    />
                    {errors.amount && <p className="field-error">{errors.amount}</p>}
                </label>

                <label>
                    Fecha de pago
                    <input
                        type="date"
                        value={data.paid_at}
                        onChange={(e) => setData('paid_at', e.target.value)}
                        required
                    />
                    {errors.paid_at && <p className="field-error">{errors.paid_at}</p>}
                </label>

                <label>
                    Método de pago
                    <input
                        type="text"
                        value={data.method}
                        onChange={(e) => setData('method', e.target.value)}
                        placeholder="Transferencia, cheque, efectivo…"
                    />
                    {errors.method && <p className="field-error">{errors.method}</p>}
                </label>

                <label>
                    Notas
                    <input
                        type="text"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    {errors.notes && <p className="field-error">{errors.notes}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Registrando…' : 'Registrar pago'}
                </button>
            </div>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function AccountsPayable({ invoices, search: searchProp = '', from: fromProp = '', to: toProp = '' }) {
    const today        = new Date().toISOString().split('T')[0];
    const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

    const [payItem, setPayItem] = useState(null);
    const [search, setSearch]   = useState(searchProp);
    const [from, setFrom]       = useState(fromProp || firstOfMonth);
    const [to, setTo]           = useState(toProp   || today);

    function applyFilters(e) {
        e.preventDefault();
        router.get(route('admin.purchase-invoices.accounts-payable'), { search, from, to }, { preserveState: true });
    }

    function clearFilters() {
        setSearch(''); setFrom(firstOfMonth); setTo(today);
        router.get(route('admin.purchase-invoices.accounts-payable'), { from: firstOfMonth, to: today });
    }

    const fmt = (n) =>
        n != null
            ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';

    const paymentBadge = {
        pending: 'suspended',
        partial: '',
        paid:    'active',
    };
    const paymentLabel = {
        pending: 'Pendiente',
        partial: 'Parcial',
        paid:    'Pagado',
    };

    return (
        <AppLayout>
            <Head title="Cuentas por pagar" />

            <div className="top">
                <h1>Cuentas por pagar</h1>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={applyFilters} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Buscar
                        <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Proveedor, número…" />
                    </label>
                    <label style={{ flex: '0 1 160px', margin: 0 }}>
                        Desde
                        <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </label>
                    <label style={{ flex: '0 1 160px', margin: 0 }}>
                        Hasta
                        <input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </label>
                    <button type="submit" className="btn">Filtrar</button>
                    <button type="button" className="btn secondary" onClick={clearFilters}>Limpiar</button>
                </form>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Factura</th>
                            <th>Fecha / Vence</th>
                            <th>Total</th>
                            <th>Pagado</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {invoices.data.map((invoice) => {
                            const balance = Number(invoice.total ?? 0) - Number(invoice.paid_amount ?? 0);
                            return (
                                <tr key={invoice.id}>
                                    <td style={{ fontWeight: 600 }}>{invoice.supplier?.name}</td>
                                    <td className="muted">{invoice.invoice_number}</td>
                                    <td>
                                        <span>{invoice.date}</span>
                                        {invoice.due_date && (
                                            <><br /><span className="muted" style={{ fontSize: 12 }}>Vence: {invoice.due_date}</span></>
                                        )}
                                    </td>
                                    <td style={{ fontWeight: 600 }}>{fmt(invoice.total)}</td>
                                    <td>{fmt(invoice.paid_amount)}</td>
                                    <td style={{ fontWeight: 600, color: balance > 0 ? '#dc2626' : '#16a34a' }}>
                                        {fmt(balance)}
                                    </td>
                                    <td>
                                        <span className={`badge ${paymentBadge[invoice.payment_status] ?? ''}`}>
                                            {paymentLabel[invoice.payment_status] ?? invoice.payment_status}
                                        </span>
                                    </td>
                                    <td>
                                        {invoice.payment_status !== 'paid' && (
                                            <button
                                                type="button"
                                                className="btn btn-sm secondary"
                                                style={{ fontSize: 13, padding: '6px 10px' }}
                                                onClick={() => setPayItem(invoice)}
                                            >
                                                Registrar pago
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                <Pagination links={invoices.links} />
            </div>

            {payItem && (
                <Modal
                    title={`Registrar pago — Factura ${payItem.invoice_number}`}
                    onClose={() => setPayItem(null)}
                >
                    <PayForm
                        invoice={payItem}
                        onSuccess={() => setPayItem(null)}
                        onCancel={() => setPayItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
