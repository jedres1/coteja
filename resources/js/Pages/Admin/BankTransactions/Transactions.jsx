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

// ─── TransactionForm ──────────────────────────────────────────────────────────

function TransactionForm({ accounts, onSuccess, onCancel }) {
    const { data, setData, post, processing, errors } = useForm({
        bank_account_id: '',
        date:            '',
        description:     '',
        amount:          '',
        type:            'credit',
        reference:       '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.bank-transactions.store'), { onSuccess });
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
                    Fecha
                    <input
                        type="date"
                        value={data.date}
                        onChange={(e) => setData('date', e.target.value)}
                        required
                    />
                    {errors.date && <p className="field-error">{errors.date}</p>}
                </label>

                <label className="full-width">
                    Descripción
                    <input
                        type="text"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        required
                        autoFocus
                    />
                    {errors.description && <p className="field-error">{errors.description}</p>}
                </label>

                <label>
                    Monto
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        required
                    />
                    {errors.amount && <p className="field-error">{errors.amount}</p>}
                </label>

                <label>
                    Tipo
                    <select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        <option value="credit">Crédito</option>
                        <option value="debit">Débito</option>
                    </select>
                    {errors.type && <p className="field-error">{errors.type}</p>}
                </label>

                <label>
                    Referencia (opcional)
                    <input
                        type="text"
                        value={data.reference}
                        onChange={(e) => setData('reference', e.target.value)}
                    />
                    {errors.reference && <p className="field-error">{errors.reference}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : 'Registrar transacción'}
                </button>
            </div>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Transactions({ transactions, accounts, filters }) {
    const [showCreate, setShowCreate] = useState(false);
    const [accountId, setAccountId]   = useState(filters?.accountId ?? '');
    const [typeVal, setTypeVal]        = useState(filters?.type ?? '');

    function applyFilters(newAccountId, newType) {
        router.get(
            route('admin.bank-transactions.transactions'),
            { accountId: newAccountId, type: newType, search: filters?.search ?? '' },
            { preserveState: true }
        );
    }

    function handleAccountChange(e) {
        setAccountId(e.target.value);
        applyFilters(e.target.value, typeVal);
    }

    function handleTypeChange(e) {
        setTypeVal(e.target.value);
        applyFilters(accountId, e.target.value);
    }

    const fmt = (n) =>
        n != null
            ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';

    const typeLabel = { credit: 'Crédito', debit: 'Débito' };

    return (
        <AppLayout>
            <Head title="Transacciones" />

            <div className="top">
                <h1>Transacciones</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <a href={route('admin.bank-transactions.transactions.export')} className="btn secondary">
                        Exportar
                    </a>
                    <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                        + Nueva transacción
                    </button>
                </div>
            </div>

            {/* Filters */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 200px', margin: 0 }}>
                        Cuenta
                        <select value={accountId} onChange={handleAccountChange}>
                            <option value="">Todas las cuentas</option>
                            {accounts.map((a) => (
                                <option key={a.id} value={a.id}>{a.name}</option>
                            ))}
                        </select>
                    </label>
                    <label style={{ flex: '0 1 160px', margin: 0 }}>
                        Tipo
                        <select value={typeVal} onChange={handleTypeChange}>
                            <option value="">Todos los tipos</option>
                            <option value="credit">Crédito</option>
                            <option value="debit">Débito</option>
                        </select>
                    </label>
                    {(accountId || typeVal) && (
                        <button
                            type="button"
                            className="btn secondary"
                            onClick={() => {
                                setAccountId('');
                                setTypeVal('');
                                applyFilters('', '');
                            }}
                        >
                            Limpiar
                        </button>
                    )}
                </div>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Tipo</th>
                            <th>Cuenta</th>
                            <th>Conciliado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {transactions.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {transactions.data.map((t) => (
                            <tr key={t.id}>
                                <td className="muted">{t.date}</td>
                                <td>{t.description}</td>
                                <td style={{ fontWeight: 600, color: t.type === 'debit' ? '#dc2626' : '#16a34a' }}>
                                    {t.type === 'debit' ? '-' : '+'}{fmt(t.amount)}
                                </td>
                                <td>{typeLabel[t.type] ?? t.type}</td>
                                <td>{t.bank_account?.name ?? <span className="muted">—</span>}</td>
                                <td>
                                    <span className={`badge ${t.is_reconciled ? 'active' : 'suspended'}`}>
                                        {t.is_reconciled ? 'Conciliado' : 'Pendiente'}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={transactions.links} />
            </div>

            {showCreate && (
                <Modal title="Nueva transacción manual" onClose={() => setShowCreate(false)}>
                    <TransactionForm
                        accounts={accounts}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
