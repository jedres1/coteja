import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { route } from 'ziggy-js';

const periods = {
    monthly: 'Mensual',
    annual: 'Anual',
    implementation: 'Implementación',
    additional: 'Usuario adicional',
};

const paymentStatuses = {
    paid: 'Pagado',
    pending: 'Pendiente',
    void: 'Anulado',
};

function PaymentForm({ payment, licenses, onSuccess }) {
    const form = useForm({
        license_id: payment?.license_id ?? '',
        period: payment?.period ?? 'monthly',
        amount: payment?.amount ?? '',
        iva: payment?.iva ?? '',
        method: payment?.method ?? '',
        reference: payment?.reference ?? '',
        status: payment?.status ?? 'pending',
        paid_at: payment?.paid_at ? payment.paid_at.substring(0, 10) : '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (payment) {
            form.put(route('admin.payments.update', payment.id), { onSuccess });
        } else {
            form.post(route('admin.payments.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="field">
                <label>Licencia</label>
                <select
                    value={form.data.license_id}
                    onChange={(e) => form.setData('license_id', e.target.value)}
                >
                    <option value="">— Seleccionar —</option>
                    {licenses.map((l) => (
                        <option key={l.id} value={l.id}>
                            {l.customer?.name} — {l.company?.business_name} — {l.license_key}
                        </option>
                    ))}
                </select>
                {form.errors.license_id && <p className="field-error">{form.errors.license_id}</p>}
            </div>
            <div className="field">
                <label>Período</label>
                <select
                    value={form.data.period}
                    onChange={(e) => form.setData('period', e.target.value)}
                >
                    {Object.entries(periods).map(([k, v]) => (
                        <option key={k} value={k}>{v}</option>
                    ))}
                </select>
                {form.errors.period && <p className="field-error">{form.errors.period}</p>}
            </div>
            <div className="field">
                <label>Monto</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)}
                />
                {form.errors.amount && <p className="field-error">{form.errors.amount}</p>}
            </div>
            <div className="field">
                <label>IVA</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.data.iva}
                    onChange={(e) => form.setData('iva', e.target.value)}
                />
                {form.errors.iva && <p className="field-error">{form.errors.iva}</p>}
            </div>
            <div className="field">
                <label>Método de pago</label>
                <input
                    type="text"
                    value={form.data.method}
                    onChange={(e) => form.setData('method', e.target.value)}
                    placeholder="Transferencia, efectivo, tarjeta…"
                />
                {form.errors.method && <p className="field-error">{form.errors.method}</p>}
            </div>
            <div className="field">
                <label>Referencia</label>
                <input
                    type="text"
                    value={form.data.reference}
                    onChange={(e) => form.setData('reference', e.target.value)}
                />
                {form.errors.reference && <p className="field-error">{form.errors.reference}</p>}
            </div>
            <div className="field">
                <label>Estado</label>
                <select
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    {Object.entries(paymentStatuses).map(([k, v]) => (
                        <option key={k} value={k}>{v}</option>
                    ))}
                </select>
                {form.errors.status && <p className="field-error">{form.errors.status}</p>}
            </div>
            <div className="field">
                <label>Fecha de pago</label>
                <input
                    type="date"
                    value={form.data.paid_at}
                    onChange={(e) => form.setData('paid_at', e.target.value)}
                />
                {form.errors.paid_at && <p className="field-error">{form.errors.paid_at}</p>}
            </div>
            <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </button>
            </div>
        </form>
    );
}

export default function PaymentsIndex({ payments, licenses }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    return (
        <AppLayout>
            <Head title="Cobros" />
            <div className="top">
                <h1>Cobros</h1>
                <button className="btn" onClick={() => setShowCreate(true)}>
                    Nuevo cobro
                </button>
            </div>

            <div className="card">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente / Empresa</th>
                            <th>Período</th>
                            <th>Total</th>
                            <th>Método / Referencia</th>
                            <th>Estado</th>
                            <th>Fecha pago</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {payments.data.length === 0 && (
                            <tr>
                                <td colSpan={7} style={{ textAlign: 'center' }} className="muted">
                                    Sin registros
                                </td>
                            </tr>
                        )}
                        {payments.data.map((payment) => (
                            <tr key={payment.id}>
                                <td>
                                    <div>{payment.license?.customer?.name ?? '—'}</div>
                                    <div className="muted" style={{ fontSize: '0.85em' }}>
                                        {payment.company?.business_name ?? '—'}
                                    </div>
                                </td>
                                <td>{periods[payment.period] ?? payment.period}</td>
                                <td>
                                    <div>${Number(payment.total ?? (Number(payment.amount) + Number(payment.iva))).toFixed(2)}</div>
                                    <div className="muted" style={{ fontSize: '0.80em' }}>
                                        ${Number(payment.amount).toFixed(2)} + ${Number(payment.iva).toFixed(2)} IVA
                                    </div>
                                </td>
                                <td>
                                    <div>{payment.method ?? '—'}</div>
                                    {payment.reference && (
                                        <div className="muted" style={{ fontSize: '0.85em' }}>{payment.reference}</div>
                                    )}
                                </td>
                                <td>
                                    <span className={`badge ${payment.status}`}>
                                        {paymentStatuses[payment.status] ?? payment.status}
                                    </span>
                                </td>
                                <td>
                                    {payment.paid_at ? payment.paid_at.substring(0, 10) : '—'}
                                </td>
                                <td>
                                    <button
                                        className="btn btn-sm"
                                        onClick={() => setEditItem(payment)}
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={payments.links} />
            </div>

            {showCreate && (
                <Modal title="Nuevo cobro" onClose={() => setShowCreate(false)}>
                    <PaymentForm
                        licenses={licenses}
                        onSuccess={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {editItem && (
                <Modal title="Editar cobro" onClose={() => setEditItem(null)}>
                    <PaymentForm
                        payment={editItem}
                        licenses={licenses}
                        onSuccess={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
