import { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

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
                    <h3>{title}</h3>
                    <button type="button" className="btn secondary overlay-close" onClick={onClose}>✕</button>
                </div>
                {children}
            </div>
        </div>
    );
}

// ─── AccountForm ──────────────────────────────────────────────────────────────

function AccountForm({ account, accounts, types, onSuccess, onCancel }) {
    const isEdit = !!account;

    const { data, setData, post, put, processing, errors } = useForm({
        code:         account?.code ?? '',
        name:         account?.name ?? '',
        account_type: account?.account_type ?? '',
        parent_id:    account?.parent_id ? String(account.parent_id) : '',
        is_active:    account?.is_active !== undefined ? String(Number(account.is_active)) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.accounting.catalogo.update', account.id), { onSuccess });
        } else {
            post(route('admin.accounting.catalogo.store'), { onSuccess });
        }
    }

    // Exclude the account itself from parent options (to avoid self-reference)
    const parentOptions = accounts.filter((a) => !isEdit || a.id !== account.id);

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label>
                    Código
                    <input
                        type="text"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        required
                        autoFocus
                    />
                    {errors.code && <p className="field-error">{errors.code}</p>}
                </label>

                <label>
                    Nombre
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    {errors.name && <p className="field-error">{errors.name}</p>}
                </label>

                <label>
                    Tipo de cuenta
                    <select value={data.account_type} onChange={(e) => setData('account_type', e.target.value)} required>
                        <option value="">— Seleccionar —</option>
                        {Object.entries(types).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {errors.account_type && <p className="field-error">{errors.account_type}</p>}
                </label>

                <label>
                    Cuenta padre
                    <select value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                        <option value="">— Sin cuenta padre —</option>
                        {parentOptions.map((a) => (
                            <option key={a.id} value={String(a.id)}>
                                {a.code} — {a.name}
                            </option>
                        ))}
                    </select>
                    {errors.parent_id && <p className="field-error">{errors.parent_id}</p>}
                </label>

                <label>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
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

// ─── Index page ───────────────────────────────────────────────────────────────

export default function Catalogo({ accounts, types }) {
    const [typeFilter, setTypeFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    // Lookup map for parent account name
    const accountMap = Object.fromEntries(accounts.map((a) => [a.id, a]));

    // Client-side filter by type
    const filtered = typeFilter
        ? accounts.filter((a) => a.account_type === typeFilter)
        : accounts;

    function handleDelete(account) {
        if (!confirm(`¿Eliminar la cuenta "${account.code} — ${account.name}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.accounting.catalogo.destroy', account.id));
    }

    return (
        <AppLayout>
            <Head title="Catálogo de cuentas" />

            <div className="top">
                <h1>Catálogo de cuentas</h1>
                <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                    + Nueva cuenta
                </button>
            </div>

            {/* Filtro por tipo */}
            <div className="card" style={{ marginBottom: 16 }}>
                <label style={{ margin: 0, flex: '0 1 260px' }}>
                    Filtrar por tipo
                    <select value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)}>
                        <option value="">— Todos los tipos —</option>
                        {Object.entries(types).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                </label>
            </div>

            {/* Tabla */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Cuenta padre</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={6} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {filtered.map((acc) => {
                            const parent = acc.parent_id ? accountMap[acc.parent_id] : null;
                            return (
                                <tr key={acc.id}>
                                    <td style={{ fontFamily: 'monospace' }}>{acc.code}</td>
                                    <td style={{ paddingLeft: acc.level ? acc.level * 12 : 0 }}>
                                        {acc.name}
                                    </td>
                                    <td>{types[acc.account_type] ?? acc.account_type}</td>
                                    <td>
                                        {parent
                                            ? <span style={{ fontSize: 13 }}>{parent.code} — {parent.name}</span>
                                            : <span className="muted">—</span>
                                        }
                                    </td>
                                    <td>
                                        <span className={`badge ${acc.is_active ? 'active' : 'suspended'}`}>
                                            {acc.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <button
                                                type="button"
                                                className="btn btn-sm secondary"
                                                style={{ fontSize: 13, padding: '6px 10px' }}
                                                onClick={() => setEditItem(acc)}
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-sm danger"
                                                style={{ fontSize: 13, padding: '6px 10px' }}
                                                onClick={() => handleDelete(acc)}
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {/* Modal crear */}
            {showCreate && (
                <Modal title="Nueva cuenta" onClose={() => setShowCreate(false)}>
                    <AccountForm
                        accounts={accounts}
                        types={types}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {/* Modal editar */}
            {editItem && (
                <Modal title={`Editar cuenta — ${editItem.code}`} onClose={() => setEditItem(null)}>
                    <AccountForm
                        account={editItem}
                        accounts={accounts}
                        types={types}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
