import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';

const ACCOUNT_TYPES = { activo: 'Activo', pasivo: 'Pasivo', patrimonio: 'Patrimonio', gasto: 'Gasto', ingreso: 'Ingreso' };
const NATURE_TYPES  = { deudora: 'Deudora', acreedora: 'Acreedora' };

function AccountForm({ account, parents, onCancel }) {
    const isEdit = !!account;
    const { data, setData, post, put, processing, errors } = useForm({
        code:      account?.code ?? '',
        name:      account?.name ?? '',
        type:      account?.type ?? '',
        nature:    account?.nature ?? '',
        parent_id: account?.parent_id ? String(account.parent_id) : '',
        is_active: account?.is_active !== undefined ? String(Number(account.is_active)) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.accounting.catalogo.update', account.id));
        } else {
            post(route('admin.accounting.catalogo.store'));
        }
    }

    const filteredParents = (parents ?? []).filter((p) => !isEdit || p.id !== account?.id);

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                <label style={{ margin: 0 }}>
                    Código <span style={{ color: '#ef4444' }}>*</span>
                    <input type="text" value={data.code} onChange={(e) => setData('code', e.target.value)} required autoFocus />
                    {errors.code && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.code}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Tipo <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.type} onChange={(e) => setData('type', e.target.value)} required>
                        <option value="">— Seleccionar —</option>
                        {Object.entries(ACCOUNT_TYPES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                    {errors.type && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.type}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Naturaleza <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.nature} onChange={(e) => setData('nature', e.target.value)} required>
                        <option value="">— Seleccionar —</option>
                        {Object.entries(NATURE_TYPES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                    {errors.nature && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.nature}</span>}
                </label>
                <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                    Cuenta padre
                    <select value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                        <option value="">— Sin cuenta padre —</option>
                        {filteredParents.map((p) => <option key={p.id} value={String(p.id)}>{p.name}</option>)}
                    </select>
                    {errors.parent_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.parent_id}</span>}
                </label>
                {isEdit && (
                    <label style={{ margin: 0 }}>
                        Estado
                        <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </label>
                )}
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear cuenta'}
                </button>
            </div>
        </form>
    );
}

export default function Catalogo({ accounts, parents, stats, filter, search }) {
    const [typeFilter, setTypeFilter] = useState(filter ?? '');
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem]     = useState(null);

    const accountMap = Object.fromEntries((accounts ?? []).map((a) => [a.id, a]));

    const filtered = typeFilter
        ? (accounts ?? []).filter((a) => a.type === typeFilter)
        : (accounts ?? []);

    function handleDelete(account) {
        if (!confirm(`¿Eliminar la cuenta "${account.code} — ${account.name}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.accounting.catalogo.destroy', account.id));
    }

    return (
        <AppLayout>
            <Head title="Catálogo de cuentas" />
            <div className="top">
                <h1>Catálogo de cuentas</h1>
                <button className="btn" type="button" onClick={() => setShowCreate(true)}>+ Nueva cuenta</button>
            </div>

            {stats && (
                <div style={{ display: 'flex', gap: 10, marginBottom: 12, flexWrap: 'wrap' }}>
                    {Object.entries(stats).map(([k, v]) => (
                        <div key={k} className="card" style={{ flex: '0 1 auto', padding: '8px 16px', textAlign: 'center' }}>
                            <div style={{ fontSize: 11, color: '#6b7280', textTransform: 'capitalize' }}>{ACCOUNT_TYPES[k] ?? k}</div>
                            <div style={{ fontWeight: 700, fontSize: 18 }}>{v}</div>
                        </div>
                    ))}
                </div>
            )}

            <div className="card" style={{ marginBottom: 16 }}>
                <label style={{ margin: 0, flex: '0 1 260px' }}>
                    Filtrar por tipo
                    <select value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)}>
                        <option value="">— Todos los tipos —</option>
                        {Object.entries(ACCOUNT_TYPES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </label>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Naturaleza</th>
                            <th>Cuenta padre</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 && (
                            <tr><td colSpan={7} className="empty">Sin registros</td></tr>
                        )}
                        {filtered.map((acc) => {
                            const parent = acc.parent_id ? accountMap[acc.parent_id] : null;
                            return (
                                <tr key={acc.id}>
                                    <td style={{ fontFamily: 'monospace' }}>{acc.code}</td>
                                    <td style={{ paddingLeft: acc.level ? (acc.level - 1) * 16 : 0 }}>{acc.name}</td>
                                    <td style={{ fontSize: 12 }}>{ACCOUNT_TYPES[acc.type] ?? acc.type}</td>
                                    <td style={{ fontSize: 12, color: '#6b7280' }}>{NATURE_TYPES[acc.nature] ?? acc.nature ?? '—'}</td>
                                    <td>
                                        {parent
                                            ? <span style={{ fontSize: 13 }}>{parent.code} — {parent.name}</span>
                                            : <span className="muted">—</span>}
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
                                                className="btn secondary"
                                                style={{ fontSize: 12, padding: '3px 10px' }}
                                                onClick={() => setEditItem(acc)}
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
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

            {showCreate && (
                <Modal title="Nueva cuenta" onClose={() => setShowCreate(false)}>
                    <AccountForm parents={parents ?? []} onCancel={() => setShowCreate(false)} />
                </Modal>
            )}

            {editItem && (
                <Modal title={`Editar cuenta — ${editItem.code}`} onClose={() => setEditItem(null)}>
                    <AccountForm account={editItem} parents={parents ?? []} onCancel={() => setEditItem(null)} />
                </Modal>
            )}
        </AppLayout>
    );
}
