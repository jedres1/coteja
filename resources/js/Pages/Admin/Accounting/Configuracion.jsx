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

// ─── PackageForm ──────────────────────────────────────────────────────────────

function PackageForm({ pkg, onSuccess, onCancel }) {
    const isEdit = !!pkg;

    const { data, setData, post, put, processing, errors } = useForm({
        name:        pkg?.name ?? '',
        description: pkg?.description ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.accounting.paquetes.update', pkg.id), { onSuccess });
        } else {
            post(route('admin.accounting.paquetes.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label className="full-width">
                    Nombre
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                    />
                    {errors.name && <p className="field-error">{errors.name}</p>}
                </label>
                <label className="full-width">
                    Descripción
                    <input
                        type="text"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                    />
                    {errors.description && <p className="field-error">{errors.description}</p>}
                </label>
            </div>
            <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear paquete'}
                </button>
            </div>
        </form>
    );
}

// ─── PackageAccountForm ───────────────────────────────────────────────────────

function PackageAccountForm({ pkgAccount, packageId, accounts, onSuccess, onCancel }) {
    const isEdit = !!pkgAccount;

    const { data, setData, post, put, processing, errors } = useForm({
        package_id:        packageId,
        debit_account_id:  pkgAccount?.debit_account_id  ? String(pkgAccount.debit_account_id)  : '',
        credit_account_id: pkgAccount?.credit_account_id ? String(pkgAccount.credit_account_id) : '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.accounting.paquetes.cuentas.update', pkgAccount.id), { onSuccess });
        } else {
            post(route('admin.accounting.paquetes.cuentas.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <input type="hidden" value={data.package_id} />
            <div className="form-grid">
                <label>
                    Cuenta débito
                    <select
                        value={data.debit_account_id}
                        onChange={(e) => setData('debit_account_id', e.target.value)}
                        required
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={String(a.id)}>{a.code} — {a.name}</option>
                        ))}
                    </select>
                    {errors.debit_account_id && <p className="field-error">{errors.debit_account_id}</p>}
                </label>
                <label>
                    Cuenta crédito
                    <select
                        value={data.credit_account_id}
                        onChange={(e) => setData('credit_account_id', e.target.value)}
                        required
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={String(a.id)}>{a.code} — {a.name}</option>
                        ))}
                    </select>
                    {errors.credit_account_id && <p className="field-error">{errors.credit_account_id}</p>}
                </label>
            </div>
            <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Agregar cuenta'}
                </button>
            </div>
        </form>
    );
}

// ─── CostCenterForm ───────────────────────────────────────────────────────────

function CostCenterForm({ cc, onSuccess, onCancel }) {
    const isEdit = !!cc;

    const { data, setData, post, put, processing, errors } = useForm({
        name:      cc?.name ?? '',
        code:      cc?.code ?? '',
        is_active: cc?.is_active !== undefined ? String(Number(cc.is_active)) : '1',
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.accounting.centros-costo.update', cc.id), { onSuccess });
        } else {
            post(route('admin.accounting.centros-costo.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label>
                    Nombre
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
                    Código
                    <input
                        type="text"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        required
                    />
                    {errors.code && <p className="field-error">{errors.code}</p>}
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
            <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear centro'}
                </button>
            </div>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Configuracion({ packages, costCenters, accounts }) {
    // Paquetes
    const [showCreatePkg, setShowCreatePkg] = useState(false);
    const [editPkg, setEditPkg]             = useState(null);
    // Cuentas de paquete
    const [addAccountToPkg, setAddAccountToPkg]     = useState(null);   // package object
    const [editPkgAccount, setEditPkgAccount]       = useState(null);   // {pkgAccount, packageId}
    // Centros de costo
    const [showCreateCC, setShowCreateCC] = useState(false);
    const [editCC, setEditCC]             = useState(null);

    function handleDeletePkg(pkg) {
        if (!confirm(`¿Eliminar el paquete "${pkg.name}"?`)) return;
        router.delete(route('admin.accounting.paquetes.destroy', pkg.id));
    }

    function handleDeletePkgAccount(pkgAccount) {
        if (!confirm('¿Eliminar esta cuenta del paquete?')) return;
        router.delete(route('admin.accounting.paquetes.cuentas.destroy', pkgAccount.id));
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

            {/* ── Sección 1: Paquetes ─────────────────────────────────────── */}
            <div style={{ marginBottom: 32 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <h2 style={{ margin: 0, fontSize: 17 }}>Paquetes contables</h2>
                    <button className="btn" type="button" onClick={() => setShowCreatePkg(true)}>
                        + Nuevo paquete
                    </button>
                </div>

                {packages.length === 0 && (
                    <div className="card">
                        <p className="empty">No hay paquetes registrados.</p>
                    </div>
                )}

                {packages.map((pkg) => (
                    <div key={pkg.id} className="card" style={{ marginBottom: 16 }}>
                        {/* Encabezado del paquete */}
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                            <div>
                                <span style={{ fontWeight: 700, fontSize: 15 }}>{pkg.name}</span>
                                {pkg.description && (
                                    <span className="muted" style={{ marginLeft: 10, fontSize: 13 }}>{pkg.description}</span>
                                )}
                            </div>
                            <div style={{ display: 'flex', gap: 6 }}>
                                <button
                                    type="button"
                                    className="btn btn-sm"
                                    style={{ fontSize: 12, padding: '4px 10px' }}
                                    onClick={() => setAddAccountToPkg(pkg)}
                                >
                                    + Cuenta
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-sm secondary"
                                    style={{ fontSize: 12, padding: '4px 10px' }}
                                    onClick={() => setEditPkg(pkg)}
                                >
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-sm danger"
                                    style={{ fontSize: 12, padding: '4px 10px' }}
                                    onClick={() => handleDeletePkg(pkg)}
                                >
                                    Eliminar
                                </button>
                            </div>
                        </div>

                        {/* Cuentas del paquete */}
                        {(pkg.accounts ?? []).length > 0 ? (
                            <table>
                                <thead>
                                    <tr>
                                        <th>Cuenta</th>
                                        <th>Cuenta débito</th>
                                        <th>Cuenta crédito</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pkg.accounts.map((pa) => (
                                        <tr key={pa.id}>
                                            <td>
                                                <span style={{ fontFamily: 'monospace', fontSize: 12 }}>{pa.code}</span>
                                                {' '}{pa.name}
                                            </td>
                                            <td style={{ fontSize: 13 }}>
                                                {pa.debit_account
                                                    ? `${pa.debit_account.code} — ${pa.debit_account.name}`
                                                    : <span className="muted">—</span>
                                                }
                                            </td>
                                            <td style={{ fontSize: 13 }}>
                                                {pa.credit_account
                                                    ? `${pa.credit_account.code} — ${pa.credit_account.name}`
                                                    : <span className="muted">—</span>
                                                }
                                            </td>
                                            <td>
                                                <div style={{ display: 'flex', gap: 4 }}>
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm secondary"
                                                        style={{ fontSize: 12, padding: '3px 8px' }}
                                                        onClick={() => setEditPkgAccount({ pkgAccount: pa, packageId: pkg.id })}
                                                    >
                                                        Editar
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm danger"
                                                        style={{ fontSize: 12, padding: '3px 8px' }}
                                                        onClick={() => handleDeletePkgAccount(pa)}
                                                    >
                                                        Quitar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <p className="muted" style={{ margin: 0, fontSize: 13 }}>Sin cuentas asignadas.</p>
                        )}
                    </div>
                ))}
            </div>

            {/* ── Sección 2: Centros de costo ─────────────────────────────── */}
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
                            {costCenters.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="empty">Sin registros</td>
                                </tr>
                            )}
                            {costCenters.map((cc) => (
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
                                                className="btn btn-sm secondary"
                                                style={{ fontSize: 13, padding: '6px 10px' }}
                                                onClick={() => setEditCC(cc)}
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-sm danger"
                                                style={{ fontSize: 13, padding: '6px 10px' }}
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

            {/* ── Modales paquetes ─────────────────────────────────────────── */}
            {showCreatePkg && (
                <Modal title="Nuevo paquete" onClose={() => setShowCreatePkg(false)}>
                    <PackageForm
                        onSuccess={() => setShowCreatePkg(false)}
                        onCancel={() => setShowCreatePkg(false)}
                    />
                </Modal>
            )}

            {editPkg && (
                <Modal title={`Editar paquete — ${editPkg.name}`} onClose={() => setEditPkg(null)}>
                    <PackageForm
                        pkg={editPkg}
                        onSuccess={() => setEditPkg(null)}
                        onCancel={() => setEditPkg(null)}
                    />
                </Modal>
            )}

            {addAccountToPkg && (
                <Modal title={`Agregar cuenta a "${addAccountToPkg.name}"`} onClose={() => setAddAccountToPkg(null)}>
                    <PackageAccountForm
                        packageId={addAccountToPkg.id}
                        accounts={accounts}
                        onSuccess={() => setAddAccountToPkg(null)}
                        onCancel={() => setAddAccountToPkg(null)}
                    />
                </Modal>
            )}

            {editPkgAccount && (
                <Modal title="Editar cuenta de paquete" onClose={() => setEditPkgAccount(null)}>
                    <PackageAccountForm
                        pkgAccount={editPkgAccount.pkgAccount}
                        packageId={editPkgAccount.packageId}
                        accounts={accounts}
                        onSuccess={() => setEditPkgAccount(null)}
                        onCancel={() => setEditPkgAccount(null)}
                    />
                </Modal>
            )}

            {/* ── Modales centros de costo ─────────────────────────────────── */}
            {showCreateCC && (
                <Modal title="Nuevo centro de costo" onClose={() => setShowCreateCC(false)}>
                    <CostCenterForm
                        onSuccess={() => setShowCreateCC(false)}
                        onCancel={() => setShowCreateCC(false)}
                    />
                </Modal>
            )}

            {editCC && (
                <Modal title={`Editar centro — ${editCC.name}`} onClose={() => setEditCC(null)}>
                    <CostCenterForm
                        cc={editCC}
                        onSuccess={() => setEditCC(null)}
                        onCancel={() => setEditCC(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
