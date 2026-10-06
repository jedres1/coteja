import { useState } from 'react';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';

// ─── AccountForm ──────────────────────────────────────────────────────────────

function AccountForm({ account, onSuccess, onCancel }) {
    const isEdit = !!account;

    const { data, setData, post, put, processing, errors } = useForm({
        name:            account?.name ?? '',
        bank_name:       account?.bank_name ?? '',
        account_number:  account?.account_number ?? '',
        account_type:    account?.account_type ?? 'corriente',
        currency:        account?.currency ?? 'USD',
        opening_balance: account?.opening_balance ?? '',
        is_active:       account?.is_active !== undefined ? String(Number(account.is_active)) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.bank-transactions.accounts.update', account.id), { onSuccess });
        } else {
            post(route('admin.bank-transactions.accounts.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label>
                    Nombre de cuenta
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                    />
                    {errors.name && <p className="field-error">{errors.name}</p>}
                </label>

                <label>
                    Banco
                    <input
                        type="text"
                        value={data.bank_name}
                        onChange={(e) => setData('bank_name', e.target.value)}
                        required
                    />
                    {errors.bank_name && <p className="field-error">{errors.bank_name}</p>}
                </label>

                <label>
                    Número de cuenta
                    <input
                        type="text"
                        value={data.account_number}
                        onChange={(e) => setData('account_number', e.target.value)}
                    />
                    {errors.account_number && <p className="field-error">{errors.account_number}</p>}
                </label>

                <label>
                    Tipo de cuenta
                    <select value={data.account_type} onChange={(e) => setData('account_type', e.target.value)}>
                        <option value="corriente">Corriente</option>
                        <option value="ahorros">Ahorro</option>
                        <option value="otro">Otro</option>
                    </select>
                    {errors.account_type && <p className="field-error">{errors.account_type}</p>}
                </label>

                <label>
                    Moneda
                    <select value={data.currency} onChange={(e) => setData('currency', e.target.value)}>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                    {errors.currency && <p className="field-error">{errors.currency}</p>}
                </label>

                <label>
                    Saldo inicial
                    <input
                        type="number"
                        step="0.01"
                        value={data.opening_balance}
                        onChange={(e) => setData('opening_balance', e.target.value)}
                    />
                    {errors.opening_balance && <p className="field-error">{errors.opening_balance}</p>}
                </label>

                <label>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                    {errors.is_active && <p className="field-error">{errors.is_active}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear cuenta'}
                </button>
            </div>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Accounts({ accounts }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem]     = useState(null);

    function handleDelete(account) {
        if (!confirm(`¿Eliminar la cuenta "${account.name}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.bank-transactions.accounts.destroy', account.id));
    }

    const fmt = (n) =>
        n != null
            ? Number(n).toLocaleString('es-SV', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '—';

    return (
        <AppLayout>
            <Head title="Cuentas bancarias" />

            <div className="top">
                <h1>Cuentas bancarias</h1>
                <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                    + Nueva cuenta
                </button>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Cuenta</th>
                            <th>Número</th>
                            <th>Tipo</th>
                            <th>Moneda</th>
                            <th>Saldo inicial</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {accounts.length === 0 && (
                            <tr>
                                <td colSpan={7} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {accounts.map((account) => (
                            <tr key={account.id}>
                                <td>
                                    <span style={{ fontWeight: 600 }}>{account.name}</span>
                                    <br />
                                    <span className="muted">{account.bank_name}</span>
                                </td>
                                <td>{account.account_number || <span className="muted">—</span>}</td>
                                <td style={{ textTransform: 'capitalize' }}>{account.account_type}</td>
                                <td>{account.currency}</td>
                                <td>{fmt(account.opening_balance)}</td>
                                <td>
                                    <span className={`badge ${account.is_active ? 'active' : 'suspended'}`}>
                                        {account.is_active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => setEditItem(account)}
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-sm danger"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => handleDelete(account)}
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {showCreate && (
                <Modal title="Nueva cuenta bancaria" onClose={() => setShowCreate(false)}>
                    <AccountForm
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {editItem && (
                <Modal title={`Editar cuenta — ${editItem.name}`} onClose={() => setEditItem(null)}>
                    <AccountForm
                        account={editItem}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
