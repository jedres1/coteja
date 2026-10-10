import { useState, useEffect } from 'react';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
// ─── CustomerForm ─────────────────────────────────────────────────────────────

function CustomerForm({ customer, geography, documentTypes, dteTypes, statuses, onSuccess, onCancel }) {
    const isEdit = !!customer;

    const { data, setData, post, put, processing, errors } = useForm({
        name:                 customer?.name ?? '',
        email:                customer?.email ?? '',
        phone:                customer?.phone ?? '',
        status:               customer?.status ?? 'active',
        document_type:        customer?.document_type ?? '',
        document_number:      customer?.document_number ?? '',
        nrc:                  customer?.nrc ?? '',
        trade_name:           customer?.trade_name ?? '',
        dte_type:             customer?.dte_type ?? '',
        business_activity:    customer?.business_activity ?? '',
        address_department:   customer?.address_department ?? '',
        address_municipality: customer?.address_municipality ?? '',
        address_district:     customer?.address_district ?? '',
    });

    const [activities, setActivities] = useState([]);
    useEffect(() => {
        fetch('/catalogs/actividades-economicas.json')
            .then((r) => r.json())
            .then((d) => setActivities(Array.isArray(d) ? d : []))
            .catch(() => {});
    }, []);

    const depts     = geography?.departamentos ?? [];
    const munis     = data.address_department    ? (geography?.municipios?.[data.address_department]      ?? []) : [];
    const districts = data.address_municipality  ? (geography?.distritos?.[data.address_municipality]     ?? []) : [];

    function handleDeptChange(e) {
        setData({ ...data, address_department: e.target.value, address_municipality: '', address_district: '' });
    }

    function handleMuniChange(e) {
        setData({ ...data, address_municipality: e.target.value, address_district: '' });
    }

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
                        list="customer-activities-list"
                        placeholder="Buscar por código o descripción…"
                    />
                    <datalist id="customer-activities-list">
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
                        onChange={handleMuniChange}
                        disabled={!data.address_department}
                    >
                        <option value="">— Seleccionar municipio —</option>
                        {munis.map((m) => (
                            <option key={m.codigo} value={m.codigo}>{m.codigo} - {m.nombre}</option>
                        ))}
                    </select>
                    {errors.address_municipality && <p className="field-error">{errors.address_municipality}</p>}
                </label>

                {/* District */}
                <label>
                    Distrito
                    <select
                        value={data.address_district}
                        onChange={(e) => setData('address_district', e.target.value)}
                        disabled={!data.address_municipality}
                    >
                        <option value="">— Seleccionar distrito —</option>
                        {districts.map((d) => (
                            <option key={d.codigo} value={d.codigo}>{d.codigo} - {d.nombre}</option>
                        ))}
                    </select>
                    {errors.address_district && <p className="field-error">{errors.address_district}</p>}
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
