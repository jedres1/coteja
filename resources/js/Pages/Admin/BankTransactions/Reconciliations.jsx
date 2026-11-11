import { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import { IconEye } from '@/Components/Icons';

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

const STATUS_LABEL = { abierta: 'Abierta', en_firme: 'En firme', anulada: 'Anulada' };
const STATUS_COLOR = {
    abierta:  { background: '#fef3c7', color: '#92400e' },
    en_firme: { background: '#f0fdf4', color: '#15803d' },
    anulada:  { background: '#fef2f2', color: '#991b1b' },
};

const fmt = (n) =>
    n != null ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';

// ─── New reconciliation form ───────────────────────────────────────────────────

function NewReconciliationForm({ bankAccounts, onCancel }) {
    const { data, setData, post, processing, errors } = useForm({
        bank_account_id:   '',
        period_year:       String(new Date().getFullYear()),
        period_month:      String(new Date().getMonth() + 1),
        cut_date:          '',
        statement_balance: '',
        notes:             '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.bank-transactions.reconciliations.store'));
    }

    // Auto-fill cut_date as last day of month when year/month change
    function handleMonthChange(field, value) {
        const year  = field === 'period_year'  ? value : data.period_year;
        const month = field === 'period_month' ? value : data.period_month;
        const last  = new Date(Number(year), Number(month), 0);
        const iso   = `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2,'0')}-${String(last.getDate()).padStart(2,'0')}`;
        setData({ ...data, [field]: value, cut_date: iso });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label style={{ gridColumn: '1 / -1' }}>
                    Cuenta bancaria <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.bank_account_id} onChange={(e) => setData('bank_account_id', e.target.value)} required>
                        <option value="">— Seleccionar cuenta —</option>
                        {(bankAccounts ?? []).map((a) => (
                            <option key={a.id} value={a.id}>{a.name} — {a.bank_name}</option>
                        ))}
                    </select>
                    {errors.bank_account_id && <p className="field-error">{errors.bank_account_id}</p>}
                </label>

                <label>
                    Año <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="number" value={data.period_year} min="2020" max="2100" required
                        onChange={(e) => handleMonthChange('period_year', e.target.value)}
                    />
                    {errors.period_year && <p className="field-error">{errors.period_year}</p>}
                </label>

                <label>
                    Mes <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.period_month} onChange={(e) => handleMonthChange('period_month', e.target.value)} required>
                        {MONTHS.map((m, i) => <option key={i + 1} value={i + 1}>{m}</option>)}
                    </select>
                    {errors.period_month && <p className="field-error">{errors.period_month}</p>}
                </label>

                <label>
                    Fecha de corte <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="date" value={data.cut_date} required
                        onChange={(e) => setData('cut_date', e.target.value)}
                    />
                    {errors.cut_date && <p className="field-error">{errors.cut_date}</p>}
                </label>

                <label>
                    Saldo según banco
                    <input
                        type="number" step="0.01" placeholder="0.00"
                        value={data.statement_balance}
                        onChange={(e) => setData('statement_balance', e.target.value)}
                    />
                    {errors.statement_balance && <p className="field-error">{errors.statement_balance}</p>}
                </label>

                <label style={{ gridColumn: '1 / -1' }}>
                    Notas
                    <input type="text" value={data.notes} onChange={(e) => setData('notes', e.target.value)} maxLength={1000} />
                    {errors.notes && <p className="field-error">{errors.notes}</p>}
                </label>
            </div>

            {errors.recon && <p className="field-error" style={{ marginTop: 8 }}>{errors.recon}</p>}

            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Creando…' : 'Crear conciliación'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

// ─── Main page ─────────────────────────────────────────────────────────────────

export default function Reconciliations({ reconciliations, bankAccounts }) {
    const [showCreate, setShowCreate] = useState(false);

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
                    <NewReconciliationForm bankAccounts={bankAccounts} onCancel={() => setShowCreate(false)} />
                </div>
            )}

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Fecha de corte</th>
                            <th>Cuenta</th>
                            <th>Estado</th>
                            <th style={{ textAlign: 'right' }}>Saldo banco</th>
                            <th>Puesta en firme</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!reconciliations?.length) && (
                            <tr><td colSpan={7} className="empty">Sin conciliaciones registradas</td></tr>
                        )}
                        {(reconciliations ?? []).map((r) => (
                            <tr key={r.id} style={{ opacity: r.status === 'anulada' ? 0.5 : 1 }}>
                                <td style={{ fontWeight: 600 }}>
                                    {MONTHS[(r.period_month ?? 1) - 1]} {r.period_year}
                                    {r.is_opening && (
                                        <span style={{ marginLeft: 6, fontSize: 10, background: '#e0f2fe', color: '#0369a1', padding: '1px 6px', borderRadius: 10, fontWeight: 600 }}>
                                            Apertura
                                        </span>
                                    )}
                                </td>
                                <td style={{ fontSize: 13, color: '#374151' }}>
                                    {r.cut_date ?? '—'}
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
                                    {r.confirmed_at ? r.confirmed_at.substring(0, 10) : '—'}
                                </td>
                                <td>
                                    <Link
                                        href={route('admin.bank-transactions.reconciliations.show', r.id)}
                                        className="btn secondary btn-icon"
                                        title="Ver detalle"
                                    >
                                        <IconEye />
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
