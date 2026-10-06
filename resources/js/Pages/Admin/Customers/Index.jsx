import { useState, useEffect } from 'react';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

// ─── CustomerForm ─────────────────────────────────────────────────────────────

function CustomerForm({ customer, geography, documentTypes, dteTypes, statuses, onSuccess, onCancel }) {
    const isEdit = !!customer;

    // Resolve initial department from municipality on mount (edit only)
    function resolveInitialDeptId() {
        if (!customer?.municipality_id || !geography?.departments) return '';
        for (const dept of geography.departments) {
            const found = (dept.municipalities ?? []).find((m) => m.id === customer.municipality_id);
            if (found) return String(dept.id);
        }
        return '';
    }

    const [departmentId, setDepartmentId] = useState(resolveInitialDeptId);
    const [municipalities, setMunicipalities] = useState([]);

    const { data, setData, post, put, processing, errors } = useForm({
        name:              customer?.name ?? '',
        email:             customer?.email ?? '',
        phone:             customer?.phone ?? '',
        status:            customer?.status ?? 'active',
        document_type:     customer?.document_type ?? '',
        document_number:   customer?.document_number ?? '',
        nrc:               customer?.nrc ?? '',
        trade_name:        customer?.trade_name ?? '',
        dte_type:          customer?.dte_type ?? '',
        business_activity: customer?.business_activity ?? '',
        municipality_id:   customer?.municipality_id ? String(customer.municipality_id) : '',
    });

    // Filter municipalities whenever department changes
    useEffect(() => {
        if (!departmentId) {
            setMunicipalities([]);
            setData('municipality_id', '');
            return;
        }
        const dept = (geography?.departments ?? []).find((d) => String(d.id) === String(departmentId));
        const munis = dept?.municipalities ?? [];
        setMunicipalities(munis);
        // If current municipality_id doesn't belong to this dept, clear it
        const validIds = munis.map((m) => String(m.id));
        if (data.municipality_id && !validIds.includes(data.municipality_id)) {
            setData('municipality_id', '');
        }
    }, [departmentId]);

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.customers.update', customer.id), { onSuccess });
        } else {
            post(route('admin.customers.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                {/* Name */}
                <label>
                    Nombre / Razón social
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                    />
                    {errors.name && <p className="field-error">{errors.name}</p>}
                </label>

                {/* Trade name */}
                <label>
                    Nombre comercial
                    <input
                        type="text"
                        value={data.trade_name}
                        onChange={(e) => setData('trade_name', e.target.value)}
                    />
                    {errors.trade_name && <p className="field-error">{errors.trade_name}</p>}
                </label>

                {/* Email */}
                <label>
                    Correo electrónico
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && <p className="field-error">{errors.email}</p>}
                </label>

                {/* Phone */}
                <label>
                    Teléfono
                    <input
                        type="text"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                    {errors.phone && <p className="field-error">{errors.phone}</p>}
                </label>

                {/* Status */}
                <label>
                    Estado
                    <select value={data.status} onChange={(e) => setData('status', e.target.value)}>
                        {Object.entries(statuses).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {errors.status && <p className="field-error">{errors.status}</p>}
                </label>

                {/* Document type */}
                <label>
                    Tipo de documento
                    <select value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}>
                        <option value="">— Seleccionar —</option>
                        {Object.entries(documentTypes).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {errors.document_type && <p className="field-error">{errors.document_type}</p>}
                </label>

                {/* Document number */}
                <label>
                    Número de documento
                    <input
                        type="text"
                        value={data.document_number}
                        onChange={(e) => setData('document_number', e.target.value)}
                    />
                    {errors.document_number && <p className="field-error">{errors.document_number}</p>}
                </label>

                {/* NRC */}
                <label>
                    NRC
                    <input
                        type="text"
                        value={data.nrc}
                        onChange={(e) => setData('nrc', e.target.value)}
                    />
                    {errors.nrc && <p className="field-error">{errors.nrc}</p>}
                </label>

                {/* DTE type */}
                <label>
                    Tipo de DTE
                    <select value={data.dte_type} onChange={(e) => setData('dte_type', e.target.value)}>
                        <option value="">— Seleccionar —</option>
                        {Object.entries(dteTypes).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {errors.dte_type && <p className="field-error">{errors.dte_type}</p>}
                </label>

                {/* Business activity */}
                <label>
                    Actividad económica
                    <input
                        type="text"
                        value={data.business_activity}
                        onChange={(e) => setData('business_activity', e.target.value)}
                    />
                    {errors.business_activity && <p className="field-error">{errors.business_activity}</p>}
                </label>

                {/* Department (filter only – not saved) */}
                <label>
                    Departamento
                    <select value={departmentId} onChange={(e) => setDepartmentId(e.target.value)}>
                        <option value="">— Seleccionar departamento —</option>
                        {(geography?.departments ?? []).map((d) => (
                            <option key={d.id} value={String(d.id)}>{d.name}</option>
                        ))}
                    </select>
                </label>

                {/* Municipality */}
                <label>
                    Municipio
                    <select
                        value={data.municipality_id}
                        onChange={(e) => setData('municipality_id', e.target.value)}
                        disabled={!departmentId}
                    >
                        <option value="">— Seleccionar municipio —</option>
                        {municipalities.map((m) => (
                            <option key={m.id} value={String(m.id)}>{m.name}</option>
                        ))}
                    </select>
                    {errors.municipality_id && <p className="field-error">{errors.municipality_id}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>
                    Cancelar
                </button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear cliente'}
                </button>
            </div>
        </form>
    );
}

// ─── Modal overlay ────────────────────────────────────────────────────────────

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
                    <div>
                        <h3>{title}</h3>
                    </div>
                    <button type="button" className="btn secondary overlay-close" onClick={onClose}>
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

// ─── Index page ───────────────────────────────────────────────────────────────

export default function CustomersIndex({ customers, search, geography, documentTypes, dteTypes, statuses }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);
    const [searchVal, setSearchVal] = useState(search ?? '');

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('admin.customers.index'), { search: searchVal }, { preserveState: true });
    }

    function handleDelete(customer) {
        if (!confirm(`¿Eliminar al cliente "${customer.name}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.customers.destroy', customer.id), {
            onSuccess: () => {},
        });
    }

    return (
        <AppLayout>
            <Head title="Clientes" />

            <div className="top">
                <h1>Clientes</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <a href={route('admin.customers.export')} className="btn secondary">
                        Exportar
                    </a>
                    <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                        + Nuevo cliente
                    </button>
                </div>
            </div>

            {/* Search bar */}
            <div className="card" style={{ marginBottom: 16 }}>
                <form onSubmit={handleSearch} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <label style={{ flex: '1 1 220px', margin: 0 }}>
                        Buscar
                        <input
                            type="text"
                            value={searchVal}
                            onChange={(e) => setSearchVal(e.target.value)}
                            placeholder="Nombre, correo, documento…"
                        />
                    </label>
                    <button type="submit" className="btn">Buscar</button>
                    {search && (
                        <Link
                            href={route('admin.customers.index')}
                            className="btn secondary"
                        >
                            Limpiar
                        </Link>
                    )}
                </form>
            </div>

            {/* Table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Documento</th>
                            <th>DTE</th>
                            <th>Actividad</th>
                            <th>Estado</th>
                            <th>Empresas</th>
                            <th>Licencias</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {customers.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {customers.data.map((customer) => (
                            <tr key={customer.id}>
                                {/* Cliente */}
                                <td>
                                    <span style={{ fontWeight: 600 }}>{customer.name}</span>
                                    {customer.trade_name && (
                                        <><br /><span className="muted">{customer.trade_name}</span></>
                                    )}
                                    {customer.email && (
                                        <><br /><span className="muted">{customer.email}</span></>
                                    )}
                                    {customer.phone && (
                                        <><br /><span className="muted">{customer.phone}</span></>
                                    )}
                                </td>

                                {/* Documento */}
                                <td>
                                    {customer.document_type && (
                                        <span style={{ fontWeight: 600 }}>
                                            {documentTypes[customer.document_type] ?? customer.document_type}
                                        </span>
                                    )}
                                    {customer.document_number && (
                                        <><br />{customer.document_number}</>
                                    )}
                                    {customer.nrc && (
                                        <><br /><span className="muted">NRC: {customer.nrc}</span></>
                                    )}
                                    {!customer.document_type && !customer.document_number && (
                                        <span className="muted">—</span>
                                    )}
                                </td>

                                {/* DTE */}
                                <td>
                                    {customer.dte_type
                                        ? (dteTypes[customer.dte_type] ?? customer.dte_type)
                                        : <span className="muted">—</span>
                                    }
                                </td>

                                {/* Actividad */}
                                <td style={{ maxWidth: 180 }}>
                                    {customer.business_activity || <span className="muted">—</span>}
                                </td>

                                {/* Estado */}
                                <td>
                                    <span className={`badge ${customer.status === 'active' ? 'active' : 'suspended'}`}>
                                        {statuses[customer.status] ?? customer.status}
                                    </span>
                                </td>

                                {/* Empresas */}
                                <td style={{ textAlign: 'center' }}>
                                    {customer.companies?.length ?? 0}
                                </td>

                                {/* Licencias */}
                                <td style={{ textAlign: 'center' }}>
                                    {customer.licenses?.length ?? 0}
                                </td>

                                {/* Acción */}
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => setEditItem(customer)}
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-sm danger"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => handleDelete(customer)}
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={customers.links} />
            </div>

            {/* Create modal */}
            {showCreate && (
                <Modal title="Nuevo cliente" onClose={() => setShowCreate(false)}>
                    <CustomerForm
                        geography={geography}
                        documentTypes={documentTypes}
                        dteTypes={dteTypes}
                        statuses={statuses}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {/* Edit modal */}
            {editItem && (
                <Modal title={`Editar cliente — ${editItem.name}`} onClose={() => setEditItem(null)}>
                    <CustomerForm
                        customer={editItem}
                        geography={geography}
                        documentTypes={documentTypes}
                        dteTypes={dteTypes}
                        statuses={statuses}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
