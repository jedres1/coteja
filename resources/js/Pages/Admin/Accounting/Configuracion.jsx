import { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

function Modal({ title, onClose, children }) {
    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);
    return (
        <div className="overlay-layer" role="dialog" aria-modal="true" aria-label={title}>
            <button className="overlay-backdrop" type="button" aria-label="Cerrar" onClick={onClose} />
            <div className="overlay-panel card" style={{ maxWidth: 640 }}>
                <div className="overlay-header">
                    <h3>{title}</h3>
                    <button type="button" className="btn secondary overlay-close" onClick={onClose}>✕</button>
                </div>
                {children}
            </div>
        </div>
    );
}

// accounts = [{id, label, type}]
function AccountSelect({ value, onChange, accounts, label, required = false }) {
    return (
        <label style={{ margin: 0 }}>
            {label}{required && <span style={{ color: '#ef4444' }}> *</span>}
            <select value={value} onChange={onChange}>
                <option value="">— Sin cuenta —</option>
                {(accounts ?? []).map((a) => (
                    <option key={a.id} value={String(a.id)}>{a.label}</option>
                ))}
            </select>
        </label>
    );
}

function PackageForm({ pkg, accounts, costCenters, onCancel }) {
    const isEdit = !!pkg;
    const { data, setData, post, put, processing, errors } = useForm({
        code:                        pkg?.code ?? '',
        name:                        pkg?.name ?? '',
        description:                 pkg?.description ?? '',
        type:                        pkg?.type ?? 'manual',
        debit_account_id:            pkg?.debit_account_id            ? String(pkg.debit_account_id)            : '',
        credit_account_id:           pkg?.credit_account_id           ? String(pkg.credit_account_id)           : '',
        secondary_debit_account_id:  pkg?.secondary_debit_account_id  ? String(pkg.secondary_debit_account_id)  : '',
        secondary_credit_account_id: pkg?.secondary_credit_account_id ? String(pkg.secondary_credit_account_id) : '',
        cost_center_id:              pkg?.cost_center_id              ? String(pkg.cost_center_id)              : '',
        is_active:                   pkg ? String(Number(pkg.is_active ?? 1)) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.accounting.paquetes.update', pkg.id));
        } else {
            post(route('admin.accounting.paquetes.store'));
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                {!isEdit && (
                    <label style={{ margin: 0 }}>
                        Código <span style={{ color: '#ef4444' }}>*</span>
                        <input
                            type="text"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.toUpperCase())}
                            required
                            maxLength={10}
                            autoFocus
                        />
                        {errors.code && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.code}</span>}
                    </label>
                )}
                <label style={{ margin: 0, gridColumn: isEdit ? '1 / -1' : 'auto' }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        maxLength={100}
                    />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>
                <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                    Descripción
                    <input type="text" value={data.description} onChange={(e) => setData('description', e.target.value)} maxLength={500} />
                </label>
                <label style={{ margin: 0 }}>
                    Tipo <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.type} onChange={(e) => setData('type', e.target.value)} required>
                        <option value="manual">Manual</option>
                        <option value="automatico">Automático</option>
                    </select>
                </label>
                <label style={{ margin: 0 }}>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </label>

                <div style={{ gridColumn: '1 / -1', fontWeight: 600, fontSize: 13, borderBottom: '1px solid #e5e7eb', paddingBottom: 4, marginTop: 4 }}>
                    Cuentas contables
                </div>

                <AccountSelect
                    label="Cuenta débito"
                    value={data.debit_account_id}
                    onChange={(e) => setData('debit_account_id', e.target.value)}
                    accounts={accounts}
                />
                <AccountSelect
                    label="Cuenta crédito"
                    value={data.credit_account_id}
                    onChange={(e) => setData('credit_account_id', e.target.value)}
                    accounts={accounts}
                />
                <AccountSelect
                    label="Cuenta débito secundaria"
                    value={data.secondary_debit_account_id}
                    onChange={(e) => setData('secondary_debit_account_id', e.target.value)}
                    accounts={accounts}
                />
                <AccountSelect
                    label="Cuenta crédito secundaria"
                    value={data.secondary_credit_account_id}
                    onChange={(e) => setData('secondary_credit_account_id', e.target.value)}
                    accounts={accounts}
                />

                {(costCenters ?? []).length > 0 && (
                    <label style={{ margin: 0 }}>
                        Centro de costo
                        <select value={data.cost_center_id} onChange={(e) => setData('cost_center_id', e.target.value)}>
                            <option value="">— Sin centro —</option>
                            {costCenters.map((cc) => (
                                <option key={cc.id} value={String(cc.id)}>{cc.code} — {cc.name}</option>
                            ))}
                        </select>
                    </label>
                )}
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear paquete'}
                </button>
            </div>
        </form>
    );
}

function CostCenterForm({ cc, onCancel }) {
    const isEdit = !!cc;
    const { data, setData, post, put, processing, errors } = useForm({
        name:      cc?.name ?? '',
        code:      cc?.code ?? '',
        is_active: cc?.is_active !== undefined ? String(Number(cc.is_active)) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.accounting.centros-costo.update', cc.id));
        } else {
            post(route('admin.accounting.centros-costo.store'));
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                <label style={{ margin: 0 }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Código <span style={{ color: '#ef4444' }}>*</span>
                    <input type="text" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} required />
                    {errors.code && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.code}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear centro'}
                </button>
            </div>
        </form>
    );
}

function AccountLabel({ account }) {
    if (!account) return <span className="muted">—</span>;
    return <span style={{ fontSize: 13 }}>{account.code} — {account.name}</span>;
}

export default function Configuracion({ packages, accounts, costCenters }) {
    const [showCreatePkg, setShowCreatePkg] = useState(false);
    const [editPkg, setEditPkg]             = useState(null);
    const [showCreateCC, setShowCreateCC]   = useState(false);
    const [editCC, setEditCC]               = useState(null);

    function handleDeletePkg(pkg) {
        if (!confirm(`¿Eliminar el paquete "${pkg.name}"?`)) return;
        router.delete(route('admin.accounting.paquetes.destroy', pkg.id));
    }

    function handleDeleteCC(cc) {
        if (!confirm(`¿Eliminar el centro de costo "${cc.name}"?`)) return;
        router.delete(route('admin.accounting.centros-costo.destroy', cc.id));
    }

    return (
        <AppLayout>
            <Head title="Configuración contable" />
            <div className="top">
                <h1>Configuración contable</h1>
            </div>

            {/* ── Paquetes contables ─────────────────────────────────────── */}
            <div style={{ marginBottom: 32 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <h2 style={{ margin: 0, fontSize: 17 }}>Paquetes contables</h2>
                    <button className="btn" type="button" onClick={() => setShowCreatePkg(true)}>
                        + Nuevo paquete
                    </button>
                </div>

                {(packages ?? []).length === 0 && (
                    <div className="card">
                        <p className="empty">No hay paquetes registrados.</p>
                    </div>
                )}

                <div className="card table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Cuenta débito</th>
                                <th>Cuenta crédito</th>
                                <th>Cta. débito 2</th>
                                <th>Cta. crédito 2</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {(packages ?? []).map((pkg) => (
                                <tr key={pkg.id}>
                                    <td style={{ fontFamily: 'monospace', fontWeight: 600 }}>{pkg.code}</td>
                                    <td>
                                        <div style={{ fontWeight: 500 }}>{pkg.name}</div>
                                        {pkg.description && <div style={{ fontSize: 12, color: '#6b7280' }}>{pkg.description}</div>}
                                    </td>
                                    <td style={{ fontSize: 12, color: '#6b7280', textTransform: 'capitalize' }}>{pkg.type}</td>
                                    <td><AccountLabel account={pkg.debit_account} /></td>
                                    <td><AccountLabel account={pkg.credit_account} /></td>
                                    <td><AccountLabel account={pkg.secondary_debit_account} /></td>
                                    <td><AccountLabel account={pkg.secondary_credit_account} /></td>
                                    <td>
                                        <span className={`badge ${pkg.is_active ? 'active' : 'suspended'}`}>
                                            {pkg.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <button
                                                type="button"
                                                className="btn secondary"
                                                style={{ fontSize: 12, padding: '3px 10px' }}
                                                onClick={() => setEditPkg(pkg)}
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                                onClick={() => handleDeletePkg(pkg)}
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
            </div>

            {/* ── Centros de costo ───────────────────────────────────────── */}
            <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <h2 style={{ margin: 0, fontSize: 17 }}>Centros de costo</h2>
                    <button className="btn" type="button" onClick={() => setShowCreateCC(true)}>
                        + Nuevo centro
                    </button>
                </div>

                <div className="card table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {(costCenters ?? []).length === 0 && (
                                <tr><td colSpan={4} className="empty">Sin centros de costo</td></tr>
                            )}
                            {(costCenters ?? []).map((cc) => (
                                <tr key={cc.id}>
                                    <td style={{ fontFamily: 'monospace' }}>{cc.code}</td>
                                    <td>{cc.name}</td>
                                    <td>
                                        <span className={`badge ${cc.is_active ? 'active' : 'suspended'}`}>
                                            {cc.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <button
                                                type="button"
                                                className="btn secondary"
                                                style={{ fontSize: 12, padding: '3px 10px' }}
                                                onClick={() => setEditCC(cc)}
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                                onClick={() => handleDeleteCC(cc)}
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
            </div>

            {/* Modales paquetes */}
            {showCreatePkg && (
                <Modal title="Nuevo paquete contable" onClose={() => setShowCreatePkg(false)}>
                    <PackageForm
                        accounts={accounts ?? []}
                        costCenters={costCenters ?? []}
                        onCancel={() => setShowCreatePkg(false)}
                    />
                </Modal>
            )}

            {editPkg && (
                <Modal title={`Editar paquete — ${editPkg.code}`} onClose={() => setEditPkg(null)}>
                    <PackageForm
                        pkg={editPkg}
                        accounts={accounts ?? []}
                        costCenters={costCenters ?? []}
                        onCancel={() => setEditPkg(null)}
                    />
                </Modal>
            )}

            {/* Modales centros de costo */}
            {showCreateCC && (
                <Modal title="Nuevo centro de costo" onClose={() => setShowCreateCC(false)}>
                    <CostCenterForm onCancel={() => setShowCreateCC(false)} />
                </Modal>
            )}

            {editCC && (
                <Modal title={`Editar centro — ${editCC.name}`} onClose={() => setEditCC(null)}>
                    <CostCenterForm cc={editCC} onCancel={() => setEditCC(null)} />
                </Modal>
            )}
        </AppLayout>
    );
}
