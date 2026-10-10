import { useState, useEffect } from 'react';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
// ─── Constants ────────────────────────────────────────────────────────────────

const DOCUMENT_TYPES = {
    '13': 'DUI',
    '36': 'NIT',
    '03': 'Pasaporte',
    '02': 'Carnet de residente',
    '37': 'Otro',
};

const STATUSES = {
    active:    'Activo',
    suspended: 'Suspendido',
    prospect:  'Prospecto',
};

// ─── SupplierForm ─────────────────────────────────────────────────────────────

function SupplierForm({ supplier, geography, onSuccess, onCancel }) {
    const isEdit = !!supplier;

    const { data, setData, post, put, processing, errors } = useForm({
        name:                 supplier?.name ?? '',
        email:                supplier?.email ?? '',
        phone:                supplier?.phone ?? '',
        status:               supplier?.status ?? 'active',
        document_type:        supplier?.document_type ?? '',
        document_number:      supplier?.document_number ?? '',
        nrc:                  supplier?.nrc ?? '',
        business_activity:    supplier?.business_activity ?? '',
        address_department:   supplier?.address_department ?? '',
        address_municipality: supplier?.address_municipality ?? '',
    });

    const [activities, setActivities] = useState([]);
    useEffect(() => {
        fetch('/catalogs/actividades-economicas.json')
            .then((r) => r.json())
            .then((d) => setActivities(Array.isArray(d) ? d : []))
            .catch(() => {});
    }, []);

    const depts = geography?.departamentos ?? [];
    const munis = data.address_department ? (geography?.municipios?.[data.address_department] ?? []) : [];

    function handleDeptChange(e) {
        setData({ ...data, address_department: e.target.value, address_municipality: '' });
    }

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
                        {Object.entries(STATUSES).map(([k, v]) => (
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
                        {Object.entries(DOCUMENT_TYPES).map(([k, v]) => (
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
                        list="supplier-activities-list"
                        placeholder="Buscar por código o descripción…"
                    />
                    <datalist id="supplier-activities-list">
                        {activities.map((a) => (
                            <option key={a.codigo} value={`${a.codigo} - ${a.descripcion}`} />
                        ))}
                    </datalist>
                    {errors.business_activity && <p className="field-error">{errors.business_activity}</p>}
                </label>

                {/* Department */}
                <label>
                    Departamento
                    <select value={data.address_department} onChange={handleDeptChange}>
                        <option value="">— Seleccionar departamento —</option>
                        {depts.map((d) => (
                            <option key={d.codigo} value={d.codigo}>{d.codigo} - {d.nombre}</option>
                        ))}
                    </select>
                    {errors.address_department && <p className="field-error">{errors.address_department}</p>}
                </label>

                {/* Municipality */}
                <label>
                    Municipio
                    <select
                        value={data.address_municipality}
                        onChange={(e) => setData('address_municipality', e.target.value)}
                        disabled={!data.address_department}
                    >
                        <option value="">— Seleccionar municipio —</option>
                        {munis.map((m) => (
                            <option key={m.codigo} value={m.codigo}>{m.codigo} - {m.nombre}</option>
                        ))}
                    </select>
                    {errors.address_municipality && <p className="field-error">{errors.address_municipality}</p>}
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

// ─── Index page ───────────────────────────────────────────────────────────────

export default function SuppliersIndex({ suppliers, search, geography }) {
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
                                            {DOCUMENT_TYPES[supplier.document_type] ?? supplier.document_type}
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
                                        {STATUSES[supplier.status] ?? supplier.status}
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
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
