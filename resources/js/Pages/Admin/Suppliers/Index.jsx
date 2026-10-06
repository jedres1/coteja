import { useState, useEffect } from 'react';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

// ─── SupplierForm ─────────────────────────────────────────────────────────────

function SupplierForm({ supplier, geography, documentTypes, statuses, onSuccess, onCancel }) {
    const isEdit = !!supplier;

    // Resolve initial department from municipality on mount (edit only)
    function resolveInitialDeptId() {
        if (!supplier?.municipality_id || !geography?.departments) return '';
        for (const dept of geography.departments) {
            const found = (dept.municipalities ?? []).find((m) => m.id === supplier.municipality_id);
            if (found) return String(dept.id);
        }
        return '';
    }

    const [departmentId, setDepartmentId] = useState(resolveInitialDeptId);
    const [municipalities, setMunicipalities] = useState([]);

    const { data, setData, post, put, processing, errors } = useForm({
        name:              supplier?.name ?? '',
        email:             supplier?.email ?? '',
        phone:             supplier?.phone ?? '',
        status:            supplier?.status ?? 'active',
        document_type:     supplier?.document_type ?? '',
        document_number:   supplier?.document_number ?? '',
        nrc:               supplier?.nrc ?? '',
        business_activity: supplier?.business_activity ?? '',
        municipality_id:   supplier?.municipality_id ? String(supplier.municipality_id) : '',
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
        // Clear municipality if it no longer belongs to the selected department
        const validIds = munis.map((m) => String(m.id));
        if (data.municipality_id && !validIds.includes(data.municipality_id)) {
            setData('municipality_id', '');
        }
    }, [departmentId]);

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.suppliers.update', supplier.id), { onSuccess });
        } else {
            post(route('admin.suppliers.store'), { onSuccess });
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
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear proveedor'}
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

export default function SuppliersIndex({ suppliers, search, geography, documentTypes, statuses }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);
    const [searchVal, setSearchVal] = useState(search ?? '');

    function handleSearch(e) {
        e.preventDefault();
        router.get(route('admin.suppliers.index'), { search: searchVal }, { preserveState: true });
    }

    function handleDelete(supplier) {
        if (!confirm(`¿Eliminar al proveedor "${supplier.name}"? Esta acción no se puede deshacer.`)) return;
        router.delete(route('admin.suppliers.destroy', supplier.id), {
            onSuccess: () => {},
        });
    }

    return (
        <AppLayout>
            <Head title="Proveedores" />

            <div className="top">
                <h1>Proveedores</h1>
                <div style={{ display: 'flex', gap: 8 }}>
                    <a href={route('admin.suppliers.export')} className="btn secondary">
                        Exportar
                    </a>
                    <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                        + Nuevo proveedor
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
                            href={route('admin.suppliers.index')}
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
                            <th>Proveedor</th>
                            <th>Documento</th>
                            <th>Actividad</th>
                            <th>Estado</th>
                            <th>Facturas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {suppliers.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {suppliers.data.map((supplier) => (
                            <tr key={supplier.id}>
                                {/* Proveedor */}
                                <td>
                                    <span style={{ fontWeight: 600 }}>{supplier.name}</span>
                                    {supplier.email && (
                                        <><br /><span className="muted">{supplier.email}</span></>
                                    )}
                                    {supplier.phone && (
                                        <><br /><span className="muted">{supplier.phone}</span></>
                                    )}
                                </td>

                                {/* Documento */}
                                <td>
                                    {supplier.document_type && (
                                        <span style={{ fontWeight: 600 }}>
                                            {documentTypes[supplier.document_type] ?? supplier.document_type}
                                        </span>
                                    )}
                                    {supplier.document_number && (
                                        <><br />{supplier.document_number}</>
                                    )}
                                    {supplier.nrc && (
                                        <><br /><span className="muted">NRC: {supplier.nrc}</span></>
                                    )}
                                    {!supplier.document_type && !supplier.document_number && (
                                        <span className="muted">—</span>
                                    )}
                                </td>

                                {/* Actividad */}
                                <td style={{ maxWidth: 180 }}>
                                    {supplier.business_activity || <span className="muted">—</span>}
                                </td>

                                {/* Estado */}
                                <td>
                                    <span className={`badge ${supplier.status === 'active' ? 'active' : 'suspended'}`}>
                                        {statuses[supplier.status] ?? supplier.status}
                                    </span>
                                </td>

                                {/* Facturas */}
                                <td style={{ textAlign: 'center' }}>
                                    {supplier.purchase_invoices_count ?? 0}
                                </td>

                                {/* Acción */}
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => setEditItem(supplier)}
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-sm danger"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => handleDelete(supplier)}
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={suppliers.links} />
            </div>

            {/* Create modal */}
            {showCreate && (
                <Modal title="Nuevo proveedor" onClose={() => setShowCreate(false)}>
                    <SupplierForm
                        geography={geography}
                        documentTypes={documentTypes}
                        statuses={statuses}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {/* Edit modal */}
            {editItem && (
                <Modal title={`Editar proveedor — ${editItem.name}`} onClose={() => setEditItem(null)}>
                    <SupplierForm
                        supplier={editItem}
                        geography={geography}
                        documentTypes={documentTypes}
                        statuses={statuses}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
