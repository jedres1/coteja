import { useState, useEffect } from 'react';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

// ─── Modal ────────────────────────────────────────────────────────────────────

function Modal({ title, onClose, children }) {
    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    return (
        <div className="overlay-layer" role="dialog" aria-modal="true" aria-label={title}>
            <button className="overlay-backdrop" type="button" aria-label="Cerrar" onClick={onClose} />
            <div className="overlay-panel card">
                <div className="overlay-header">
                    <div><h3>{title}</h3></div>
                    <button type="button" className="btn secondary overlay-close" onClick={onClose}>✕</button>
                </div>
                {children}
            </div>
        </div>
    );
}

// ─── ReconciliationForm ───────────────────────────────────────────────────────

function ReconciliationForm({ accounts, onSuccess, onCancel }) {
    const { data, setData, post, processing, errors } = useForm({
        bank_account_id: '',
        name:            '',
        period_start:    '',
        period_end:      '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.bank-transactions.reconciliations.store'), { onSuccess });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label>
                    Cuenta bancaria
                    <select
                        value={data.bank_account_id}
                        onChange={(e) => setData('bank_account_id', e.target.value)}
                        required
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>{a.name}</option>
                        ))}
                    </select>
                    {errors.bank_account_id && <p className="field-error">{errors.bank_account_id}</p>}
                </label>

                <label>
                    Nombre
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        placeholder="Ej. Conciliación enero 2026"
                    />
                    {errors.name && <p className="field-error">{errors.name}</p>}
                </label>

                <label>
                    Inicio del período
                    <input
                        type="date"
                        value={data.period_start}
                        onChange={(e) => setData('period_start', e.target.value)}
                        required
                    />
                    {errors.period_start && <p className="field-error">{errors.period_start}</p>}
                </label>

                <label>
                    Fin del período
                    <input
                        type="date"
                        value={data.period_end}
                        onChange={(e) => setData('period_end', e.target.value)}
                        required
                    />
                    {errors.period_end && <p className="field-error">{errors.period_end}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : 'Crear conciliación'}
                </button>
            </div>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Reconciliations({ reconciliations, accounts }) {
    const [showCreate, setShowCreate] = useState(false);

    const statusLabel = { open: 'Abierta', completed: 'Completada' };
    const statusBadge = { open: 'suspended', completed: 'active' };

    const fmt = (n) =>
        n != null
            ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';

    return (
        <AppLayout>
            <Head title="Conciliación bancaria" />

            <div className="top">
                <h1>Conciliación bancaria</h1>
                <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                    + Nueva conciliación
                </button>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cuenta</th>
                            <th>Período</th>
                            <th>Estado</th>
                            <th>Diferencia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {reconciliations.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {reconciliations.data.map((r) => (
                            <tr key={r.id}>
                                <td style={{ fontWeight: 600 }}>{r.name}</td>
                                <td>{r.bank_account?.name ?? <span className="muted">—</span>}</td>
                                <td className="muted">
                                    {r.period_start} — {r.period_end}
                                </td>
                                <td>
                                    <span className={`badge ${statusBadge[r.status] ?? ''}`}>
                                        {statusLabel[r.status] ?? r.status}
                                    </span>
                                </td>
                                <td style={{ fontWeight: 600, color: Number(r.difference) === 0 ? '#16a34a' : '#dc2626' }}>
                                    {fmt(r.difference)}
                                </td>
                                <td>
                                    <Link
                                        href={route('admin.bank-transactions.reconciliations.show', r.id)}
                                        className="btn btn-sm secondary"
                                        style={{ fontSize: 13, padding: '6px 10px' }}
                                    >
                                        Ver detalle
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={reconciliations.links} />
            </div>

            {showCreate && (
                <Modal title="Nueva conciliación" onClose={() => setShowCreate(false)}>
                    <ReconciliationForm
                        accounts={accounts}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
