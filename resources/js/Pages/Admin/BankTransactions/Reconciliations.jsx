import { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const STATUS_LABEL = { open: 'Abierta', completed: 'Completada' };
const STATUS_COLOR = {
    open:      { background: '#fef3c7', color: '#92400e' },
    completed: { background: '#f0fdf4', color: '#15803d' },
};

function periodLabel(year, month) {
    return `${MONTHS[(month ?? 1) - 1] ?? ''} ${year}`;
}

function ReconciliationForm({ bankAccounts, onCancel }) {
    const currentYear  = new Date().getFullYear();
    const currentMonth = new Date().getMonth() + 1;

    const { data, setData, post, processing, errors } = useForm({
        bank_account_id:   '',
        period_year:       String(currentYear),
        period_month:      String(currentMonth),
        statement_balance: '',
        notes:             '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.bank-transactions.reconciliations.store'));
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                    Cuenta bancaria <span style={{ color: '#ef4444' }}>*</span>
                    <select
                        value={data.bank_account_id}
                        onChange={(e) => setData('bank_account_id', e.target.value)}
                        required
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {(bankAccounts ?? []).map((a) => (
                            <option key={a.id} value={a.id}>{a.name} — {a.bank_name}</option>
                        ))}
                    </select>
                    {errors.bank_account_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.bank_account_id}</span>}
                </label>

                <label style={{ margin: 0 }}>
                    Año <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="number"
                        value={data.period_year}
                        onChange={(e) => setData('period_year', e.target.value)}
                        min="2020"
                        max="2100"
                        required
                    />
                    {errors.period_year && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.period_year}</span>}
                </label>

                <label style={{ margin: 0 }}>
                    Mes <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.period_month} onChange={(e) => setData('period_month', e.target.value)} required>
                        {MONTHS.map((m, i) => (
                            <option key={i + 1} value={i + 1}>{m}</option>
                        ))}
                    </select>
                    {errors.period_month && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.period_month}</span>}
                </label>

                <label style={{ margin: 0 }}>
                    Saldo del estado de cuenta
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.statement_balance}
                        onChange={(e) => setData('statement_balance', e.target.value)}
                        placeholder="0.00"
                    />
                    {errors.statement_balance && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.statement_balance}</span>}
                </label>

                <label style={{ margin: 0 }}>
                    Notas
                    <input
                        type="text"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        maxLength={1000}
                    />
                    {errors.notes && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.notes}</span>}
                </label>
            </div>

            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Creando...' : 'Crear conciliación'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

export default function Reconciliations({ reconciliations, bankAccounts }) {
    const [showCreate, setShowCreate] = useState(false);

    const fmt = (n) => n != null
        ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2 })
        : '—';

    return (
        <AppLayout>
            <Head title="Conciliación bancaria" />

            <div className="top">
                <h1>Conciliación bancaria</h1>
                <button type="button" className="btn" onClick={() => setShowCreate((v) => !v)}>
                    {showCreate ? 'Cancelar' : '+ Nueva conciliación'}
                </button>
            </div>

            {showCreate && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ margin: '0 0 16px', fontSize: 15, fontWeight: 600 }}>Nueva conciliación</h2>
                    <ReconciliationForm bankAccounts={bankAccounts} onCancel={() => setShowCreate(false)} />
                </div>
            )}

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Cuenta</th>
                            <th>Estado</th>
                            <th style={{ textAlign: 'right' }}>Saldo estado cuenta</th>
                            <th>Completada</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!reconciliations || reconciliations.length === 0) && (
                            <tr>
                                <td colSpan={6} className="empty">Sin conciliaciones registradas</td>
                            </tr>
                        )}
                        {(reconciliations ?? []).map((r) => (
                            <tr key={r.id}>
                                <td style={{ fontWeight: 600 }}>
                                    {periodLabel(r.period_year, r.period_month)}
                                </td>
                                <td>{r.bank_account?.name ?? '—'}</td>
                                <td>
                                    <span style={{
                                        fontSize: 11, fontWeight: 600, padding: '2px 8px',
                                        borderRadius: 10, ...(STATUS_COLOR[r.status] ?? {}),
                                    }}>
                                        {STATUS_LABEL[r.status] ?? r.status}
                                    </span>
                                </td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                    {fmt(r.statement_balance)}
                                </td>
                                <td style={{ fontSize: 13, color: '#6b7280' }}>
                                    {r.completed_at ? r.completed_at.substring(0, 10) : '—'}
                                </td>
                                <td>
                                    <Link
                                        href={route('admin.bank-transactions.reconciliations.show', r.id)}
                                        className="btn secondary"
                                        style={{ fontSize: 12, padding: '3px 10px' }}
                                    >
                                        Ver detalle
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
