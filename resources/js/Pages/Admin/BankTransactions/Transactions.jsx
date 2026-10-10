import { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const SOURCE_LABEL = { compras: 'Compras', manual: 'Manual', facturacion: 'Facturación' };
const SOURCE_COLOR = {
    compras:      { background: '#eff6ff', color: '#1d4ed8' },
    manual:       { background: '#f3f4f6', color: '#374151' },
    facturacion:  { background: '#fdf4ff', color: '#7e22ce' },
};
const TYPE_TYPES = ['deposito', 'pago', 'transferencia', 'cheque', 'otro'];
const TYPE_LABEL = {
    deposito: 'Depósito', pago: 'Pago', transferencia: 'Transferencia',
    cheque: 'Cheque', otro: 'Otro',
    credit: 'Crédito', debit: 'Débito',
};

function TransactionForm({ bankAccounts, onCancel }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        transaction_date: '',
        bank_account_id:  '',
        type:             'deposito',
        amount:           '',
        reference:        '',
        notes:            '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.bank-transactions.transactions.store'), {
            onSuccess: () => { reset(); onCancel(); },
        });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                <label style={{ margin: 0 }}>
                    Fecha <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="date"
                        value={data.transaction_date}
                        onChange={(e) => setData('transaction_date', e.target.value)}
                        required
                    />
                    {errors.transaction_date && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.transaction_date}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Cuenta bancaria <span style={{ color: '#ef4444' }}>*</span>
                    <select
                        value={data.bank_account_id}
                        onChange={(e) => setData('bank_account_id', e.target.value)}
                        required
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {bankAccounts.map((a) => (
                            <option key={a.id} value={a.id}>{a.name} — {a.bank_name}</option>
                        ))}
                    </select>
                    {errors.bank_account_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.bank_account_id}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Tipo <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        {TYPE_TYPES.map((t) => (
                            <option key={t} value={t}>{TYPE_LABEL[t]}</option>
                        ))}
                    </select>
                    {errors.type && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.type}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Monto <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        required
                    />
                    {errors.amount && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.amount}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Referencia
                    <input
                        type="text"
                        value={data.reference}
                        onChange={(e) => setData('reference', e.target.value)}
                        maxLength={200}
                    />
                    {errors.reference && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.reference}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Notas
                    <input
                        type="text"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        maxLength={500}
                    />
                    {errors.notes && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.notes}</span>}
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando...' : 'Registrar transacción'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

export default function Transactions({ transactions, bankAccounts, from, to, bankAccountId }) {
    const [showCreate, setShowCreate] = useState(false);
    const today         = new Date().toISOString().split('T')[0];
    const firstOfMonth  = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const [fromVal, setFromVal]       = useState(from ?? firstOfMonth);
    const [toVal, setToVal]           = useState(to ?? today);
    const [accountVal, setAccountVal] = useState(bankAccountId ? String(bankAccountId) : '');
    const [typeVal, setTypeVal]       = useState('');

    const fmt = (n) => n != null
        ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2 })
        : '—';

    function applyFilters(params = {}) {
        router.get(
            route('admin.bank-transactions.transactions'),
            {
                from:            params.from    ?? fromVal,
                to:              params.to      ?? toVal,
                bank_account_id: params.account ?? accountVal,
                type:            params.type    ?? typeVal,
            },
            { preserveState: true }
        );
    }

    function handleFilterSubmit(e) {
        e.preventDefault();
        applyFilters();
    }

    const totalCredit = (transactions ?? [])
        .filter((t) => ['deposito', 'credit'].includes(t.type))
        .reduce((s, t) => s + t.amount, 0);
    const totalDebit = (transactions ?? [])
        .filter((t) => !['deposito', 'credit'].includes(t.type))
        .reduce((s, t) => s + t.amount, 0);

    return (
        <AppLayout>
            <Head title="Transacciones" />

            <div className="top">
                <h1>Transacciones</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <a href={route('admin.bank-transactions.transactions.export')} className="btn secondary">
                        Exportar
                    </a>
                    <button type="button" className="btn" onClick={() => setShowCreate((v) => !v)}>
                        {showCreate ? 'Cancelar' : '+ Nueva transacción'}
                    </button>
                </div>
            </div>

            {showCreate && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ margin: '0 0 16px', fontSize: 15, fontWeight: 600 }}>Nueva transacción manual</h2>
                    <TransactionForm bankAccounts={bankAccounts ?? []} onCancel={() => setShowCreate(false)} />
                </div>
            )}

            {/* Filters */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleFilterSubmit} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ margin: 0 }}>
                        Desde
                        <input type="date" value={fromVal} onChange={(e) => setFromVal(e.target.value)} />
                    </label>
                    <label style={{ margin: 0 }}>
                        Hasta
                        <input type="date" value={toVal} onChange={(e) => setToVal(e.target.value)} />
                    </label>
                    <label style={{ flex: '1 1 160px', margin: 0 }}>
                        Cuenta
                        <select value={accountVal} onChange={(e) => setAccountVal(e.target.value)}>
                            <option value="">Todas las cuentas</option>
                            {(bankAccounts ?? []).map((a) => (
                                <option key={a.id} value={a.id}>{a.name}</option>
                            ))}
                        </select>
                    </label>
                    <label style={{ flex: '0 1 160px', margin: 0 }}>
                        Tipo
                        <select value={typeVal} onChange={(e) => setTypeVal(e.target.value)}>
                            <option value="">Todos</option>
                            {TYPE_TYPES.map((t) => (
                                <option key={t} value={t}>{TYPE_LABEL[t]}</option>
                            ))}
                        </select>
                    </label>
                    <button type="submit" className="btn">Filtrar</button>
                </form>
            </div>

            {/* Summary */}
            {(transactions ?? []).length > 0 && (
                <div style={{ display: 'flex', gap: 12, marginBottom: 12 }}>
                    <div className="card" style={{ flex: 1, padding: '12px 16px' }}>
                        <div style={{ fontSize: 12, color: '#6b7280' }}>Registros</div>
                        <div style={{ fontWeight: 700, fontSize: 18 }}>{transactions.length}</div>
                    </div>
                    <div className="card" style={{ flex: 1, padding: '12px 16px' }}>
                        <div style={{ fontSize: 12, color: '#6b7280' }}>Depósitos</div>
                        <div style={{ fontWeight: 700, fontSize: 18, color: '#15803d', fontFamily: 'monospace' }}>{fmt(totalCredit)}</div>
                    </div>
                    <div className="card" style={{ flex: 1, padding: '12px 16px' }}>
                        <div style={{ fontSize: 12, color: '#6b7280' }}>Pagos / salidas</div>
                        <div style={{ fontWeight: 700, fontSize: 18, color: '#dc2626', fontFamily: 'monospace' }}>{fmt(totalDebit)}</div>
                    </div>
                </div>
            )}

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Tipo</th>
                            <th>Cuenta</th>
                            <th>Documento</th>
                            <th>Referencia</th>
                            <th style={{ textAlign: 'right' }}>Monto</th>
                            <th>Conciliado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(transactions ?? []).length === 0 && (
                            <tr>
                                <td colSpan={8} className="empty">Sin transacciones en el período seleccionado</td>
                            </tr>
                        )}
                        {(transactions ?? []).map((t) => (
                            <tr key={t.id}>
                                <td style={{ fontSize: 13, whiteSpace: 'nowrap' }}>{t.date}</td>
                                <td>
                                    <span style={{
                                        fontSize: 11, fontWeight: 600, padding: '2px 8px',
                                        borderRadius: 10, whiteSpace: 'nowrap',
                                        ...(SOURCE_COLOR[t.source] ?? {}),
                                    }}>
                                        {SOURCE_LABEL[t.source] ?? t.source}
                                    </span>
                                </td>
                                <td style={{ fontSize: 13 }}>{TYPE_LABEL[t.type] ?? t.type}</td>
                                <td style={{ fontSize: 13 }}>{t.bank_account ?? '—'}</td>
                                <td style={{ fontSize: 12, color: '#6b7280' }}>
                                    {t.document
                                        ? <><span style={{ color: '#374151' }}>{t.document}</span><br /><span>{t.document_label}</span></>
                                        : '—'}
                                </td>
                                <td style={{ fontSize: 12, color: '#6b7280', fontFamily: 'monospace' }}>{t.reference ?? '—'}</td>
                                <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>
                                    {fmt(t.amount)}
                                </td>
                                <td>
                                    <span style={{
                                        fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 10,
                                        background: t.is_reconciled ? '#f0fdf4' : '#fef3c7',
                                        color: t.is_reconciled ? '#15803d' : '#92400e',
                                    }}>
                                        {t.is_reconciled ? 'Conciliado' : 'Pendiente'}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
