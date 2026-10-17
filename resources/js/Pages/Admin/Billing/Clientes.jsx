import { useState } from 'react';
import { useForm, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';

const DTE_LABEL = {
    '01': 'Factura', '03': 'CCF', '04': 'Nota de Remisión',
    '05': 'Nota de Crédito', '06': 'Nota de Débito',
    '07': 'Retención', '11': 'Exportación', '14': 'Sujeto Excluido',
};

const EMPTY_CLIENT = {
    id: null, name: '', trade_name: '', document_type: '',
    document_number: '', nrc: '', business_activity: '', activity_description: '',
    email: '', phone: '', billing_email: '', billing_phone: '',
    address_department: '', address_municipality: '', address: '',
    preferred_dte_type: '01',
};

function ClientForm({ client, onSuccess, onCancel }) {
    const isEdit = !!client?.id;

    const { data, setData, post, processing, errors } = useForm({
        id:                    client?.id ?? null,
        name:                  client?.name ?? '',
        trade_name:            client?.tradeName ?? '',
        document_type:         client?.documentType ?? '',
        document_number:       client?.documentNumber ?? '',
        nrc:                   client?.nrc ?? '',
        business_activity:     client?.businessActivity ?? '',
        activity_description:  client?.activityDescription ?? '',
        email:                 client?.email ?? '',
        phone:                 client?.phone ?? '',
        billing_email:         client?.billingEmail ?? '',
        billing_phone:         client?.billingPhone ?? '',
        address_department:    client?.addressDepartment ?? '',
        address_municipality:  client?.addressMunicipality ?? '',
        address:               client?.address ?? '',
        preferred_dte_type:    client?.preferredDteType ?? '01',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.factura-sv.billing.clientes.store'), { onSuccess });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label className="full-width">
                    Nombre / Razón Social <span style={{ color: 'red' }}>*</span>
                    <input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    {errors.name && <p className="field-error">{errors.name}</p>}
                </label>

                <label>
                    Nombre Comercial
                    <input value={data.trade_name} onChange={(e) => setData('trade_name', e.target.value)} />
                    {errors.trade_name && <p className="field-error">{errors.trade_name}</p>}
                </label>

                <label>
                    Tipo Documento
                    <select value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}>
                        <option value="">— Sin documento —</option>
                        <option value="36">DUI (36)</option>
                        <option value="13">NIT (13)</option>
                        <option value="37">Pasaporte (37)</option>
                        <option value="03">Carné de Residente (03)</option>
                    </select>
                    {errors.document_type && <p className="field-error">{errors.document_type}</p>}
                </label>

                <label>
                    Número de Documento
                    <input value={data.document_number} onChange={(e) => setData('document_number', e.target.value)} />
                    {errors.document_number && <p className="field-error">{errors.document_number}</p>}
                </label>

                <label>
                    NRC
                    <input value={data.nrc} onChange={(e) => setData('nrc', e.target.value)} />
                    {errors.nrc && <p className="field-error">{errors.nrc}</p>}
                </label>

                <label>
                    Código Actividad Económica
                    <input value={data.business_activity} onChange={(e) => setData('business_activity', e.target.value)} placeholder="Ej: 47191" />
                    {errors.business_activity && <p className="field-error">{errors.business_activity}</p>}
                </label>

                <label className="full-width">
                    Descripción Actividad
                    <input value={data.activity_description} onChange={(e) => setData('activity_description', e.target.value)} />
                    {errors.activity_description && <p className="field-error">{errors.activity_description}</p>}
                </label>

                <label>
                    Correo electrónico
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    {errors.email && <p className="field-error">{errors.email}</p>}
                </label>

                <label>
                    Teléfono
                    <input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    {errors.phone && <p className="field-error">{errors.phone}</p>}
                </label>

                <label>
                    Correo Facturación
                    <input type="email" value={data.billing_email} onChange={(e) => setData('billing_email', e.target.value)} />
                    {errors.billing_email && <p className="field-error">{errors.billing_email}</p>}
                </label>

                <label>
                    Teléfono Facturación
                    <input value={data.billing_phone} onChange={(e) => setData('billing_phone', e.target.value)} />
                    {errors.billing_phone && <p className="field-error">{errors.billing_phone}</p>}
                </label>

                <label>
                    Departamento
                    <input value={data.address_department} onChange={(e) => setData('address_department', e.target.value)} />
                    {errors.address_department && <p className="field-error">{errors.address_department}</p>}
                </label>

                <label>
                    Municipio
                    <input value={data.address_municipality} onChange={(e) => setData('address_municipality', e.target.value)} />
                    {errors.address_municipality && <p className="field-error">{errors.address_municipality}</p>}
                </label>

                <label className="full-width">
                    Dirección
                    <input value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    {errors.address && <p className="field-error">{errors.address}</p>}
                </label>

                <label>
                    Tipo DTE Preferido
                    <select value={data.preferred_dte_type} onChange={(e) => setData('preferred_dte_type', e.target.value)}>
                        {Object.entries(DTE_LABEL).map(([code, label]) => (
                            <option key={code} value={code}>{code} - {label}</option>
                        ))}
                    </select>
                    {errors.preferred_dte_type && <p className="field-error">{errors.preferred_dte_type}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear cliente'}
                </button>
            </div>
        </form>
    );
}

export default function Clientes({ clients }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);
    const [search, setSearch] = useState('');

    const filtered = clients.filter((c) => {
        if (!search.trim()) return true;
        const q = search.toLowerCase();
        return (
            c.name?.toLowerCase().includes(q) ||
            c.tradeName?.toLowerCase().includes(q) ||
            c.documentNumber?.toLowerCase().includes(q) ||
            c.nrc?.toLowerCase().includes(q) ||
            c.email?.toLowerCase().includes(q)
        );
    });

    function handleDelete(client) {
        if (!confirm(`¿Eliminar al cliente "${client.name}"?`)) return;
        router.delete(route('admin.factura-sv.billing.clientes.destroy', client.id));
    }

    return (
        <AppLayout title="Clientes de Facturación">

            <div className="section-top">
                <div>
                    <h1>Clientes de Facturación</h1>
                    <p className="muted">Directorio de receptores de documentos tributarios</p>
                </div>
                <button className="btn" type="button" onClick={() => setShowCreate(true)}>
                    + Nuevo cliente
                </button>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar por nombre, documento, NRC o correo…"
                    style={{ width: '100%', maxWidth: 400 }}
                />
            </div>

            <div className="card table-scroll">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Documento</th>
                            <th>NRC</th>
                            <th>Actividad</th>
                            <th>Contacto</th>
                            <th>DTE Preferido</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 && (
                            <tr><td colSpan={7} className="empty">Sin clientes registrados</td></tr>
                        )}
                        {filtered.map((client) => (
                            <tr key={client.id}>
                                <td>
                                    <span style={{ fontWeight: 600 }}>{client.name}</span>
                                    {client.tradeName && <><br /><span className="muted">{client.tradeName}</span></>}
                                    {client.isClientesVarios && <><br /><span className="badge badge-pending" style={{ fontSize: 11 }}>Sistema</span></>}
                                </td>
                                <td>
                                    {client.documentNumber
                                        ? <>{client.documentType} {client.documentNumber}</>
                                        : <span className="muted">—</span>}
                                </td>
                                <td>{client.nrc || <span className="muted">—</span>}</td>
                                <td>
                                    {client.businessActivity
                                        ? <>{client.businessActivity}<br /><span className="muted" style={{ fontSize: 12 }}>{client.activityDescription}</span></>
                                        : <span className="muted">—</span>}
                                </td>
                                <td>
                                    {(client.billingEmail || client.email) && (
                                        <span style={{ display: 'block', fontSize: 13 }}>{client.billingEmail || client.email}</span>
                                    )}
                                    {(client.billingPhone || client.phone) && (
                                        <span className="muted" style={{ fontSize: 12 }}>{client.billingPhone || client.phone}</span>
                                    )}
                                    {!client.email && !client.billingEmail && !client.phone && !client.billingPhone && (
                                        <span className="muted">—</span>
                                    )}
                                </td>
                                <td>
                                    <span className="badge badge-pending" style={{ fontSize: 12 }}>
                                        {DTE_LABEL[client.preferredDteType] ?? client.preferredDteType ?? '—'}
                                    </span>
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn btn-sm secondary"
                                            style={{ fontSize: 13, padding: '6px 10px' }}
                                            onClick={() => setEditItem(client)}
                                        >
                                            Editar
                                        </button>
                                        {!client.isClientesVarios && (
                                            <button
                                                type="button"
                                                className="btn btn-sm danger"
                                                style={{ fontSize: 13, padding: '6px 10px' }}
                                                onClick={() => handleDelete(client)}
                                            >
                                                Eliminar
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {showCreate && (
                <Modal title="Nuevo cliente de facturación" onClose={() => setShowCreate(false)}>
                    <ClientForm
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {editItem && (
                <Modal title={`Editar cliente — ${editItem.name}`} onClose={() => setEditItem(null)}>
                    <ClientForm
                        client={editItem}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
