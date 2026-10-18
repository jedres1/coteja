import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { route } from 'ziggy-js';
import { IconEdit } from '@/Components/Icons';

const licenseStatuses = {
    active:    'Activa',
    expired:   'Vencida',
    suspended: 'Suspendida',
};

const licenseTypes = {
    propietario: 'Propietario',
    mensual:     'Mensual',
    anual:       'Anual',
};

const statusColors = {
    active:    { bg: '#dcfce7', color: '#166534' },
    expired:   { bg: '#fee2e2', color: '#991b1b' },
    suspended: { bg: '#fef3c7', color: '#92400e' },
};

function LicenseForm({ license, companies, plans, onSuccess, onCancel }) {
    const isEdit = !!license;

    const form = useForm({
        company_id:   license?.company_id   ?? '',
        plan_id:      license?.plan_id       ?? '',
        license_type: license?.license_type  ?? 'mensual',
        status:       license?.status        ?? 'active',
        starts_at:    license?.starts_at     ? license.starts_at.substring(0, 10) : new Date().toISOString().substring(0, 10),
        expires_at:   license?.expires_at    ? license.expires_at.substring(0, 10) : '',
        max_users:    license?.max_users     ?? 1,
        max_devices:  license?.max_devices   ?? 1,
        grace_days:   license?.grace_days    ?? 0,
    });

    const isPropietario = form.data.license_type === 'propietario';

    function handleSubmit(e) {
        e.preventDefault();
        if (isEdit) {
            form.put(route('admin.licenses.update', license.id), { onSuccess });
        } else {
            form.post(route('admin.licenses.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                {!isEdit && (
                    <label className="full-width">
                        Empresa <span style={{ color: 'red' }}>*</span>
                        <select
                            value={form.data.company_id}
                            onChange={(e) => form.setData('company_id', e.target.value)}
                            required
                        >
                            <option value="">— Seleccionar empresa —</option>
                            {companies.map((c) => (
                                <option key={c.id} value={c.id}>{c.business_name}</option>
                            ))}
                        </select>
                        {form.errors.company_id && <p className="field-error">{form.errors.company_id}</p>}
                    </label>
                )}

                {!isEdit && (
                    <label className="full-width">
                        Plan <span style={{ color: 'red' }}>*</span>
                        <select
                            value={form.data.plan_id}
                            onChange={(e) => form.setData('plan_id', e.target.value)}
                            required
                        >
                            <option value="">— Seleccionar plan —</option>
                            {plans.map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>
                        {form.errors.plan_id && <p className="field-error">{form.errors.plan_id}</p>}
                    </label>
                )}

                <label>
                    Tipo de licencia
                    <select
                        value={form.data.license_type}
                        onChange={(e) => form.setData('license_type', e.target.value)}
                    >
                        {Object.entries(licenseTypes).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {isPropietario && (
                        <span style={{ fontSize: 11, color: '#6b7280' }}>No requiere fecha de vencimiento</span>
                    )}
                    {form.errors.license_type && <p className="field-error">{form.errors.license_type}</p>}
                </label>

                <label>
                    Estado
                    <select
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        {Object.entries(licenseStatuses).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {form.errors.status && <p className="field-error">{form.errors.status}</p>}
                </label>

                <label>
                    Fecha inicio
                    <input
                        type="date"
                        value={form.data.starts_at}
                        onChange={(e) => form.setData('starts_at', e.target.value)}
                        required
                    />
                    {form.errors.starts_at && <p className="field-error">{form.errors.starts_at}</p>}
                </label>

                {!isPropietario && (
                    <label>
                        Fecha vencimiento
                        <input
                            type="date"
                            value={form.data.expires_at}
                            onChange={(e) => form.setData('expires_at', e.target.value)}
                        />
                        {form.errors.expires_at && <p className="field-error">{form.errors.expires_at}</p>}
                    </label>
                )}

                <label>
                    Máx. usuarios
                    <input
                        type="number"
                        min="1"
                        value={form.data.max_users}
                        onChange={(e) => form.setData('max_users', e.target.value)}
                    />
                    {form.errors.max_users && <p className="field-error">{form.errors.max_users}</p>}
                </label>

                <label>
                    Máx. dispositivos
                    <input
                        type="number"
                        min="1"
                        value={form.data.max_devices}
                        onChange={(e) => form.setData('max_devices', e.target.value)}
                    />
                    {form.errors.max_devices && <p className="field-error">{form.errors.max_devices}</p>}
                </label>

                <label>
                    Días de gracia
                    <input
                        type="number"
                        min="0"
                        value={form.data.grace_days}
                        onChange={(e) => form.setData('grace_days', e.target.value)}
                    />
                    {form.errors.grace_days && <p className="field-error">{form.errors.grace_days}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear licencia'}
                </button>
            </div>
        </form>
    );
}

export default function LicensesIndex({ licenses, companies, plans }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    return (
        <AppLayout>
            <Head title="Licencias" />
            <div className="top">
                <h1>Licencias</h1>
                <button className="btn" onClick={() => setShowCreate(true)}>
                    + Nueva licencia
                </button>
            </div>

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Clave</th>
                            <th>Empresa</th>
                            <th>Plan</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Inicio</th>
                            <th>Vence</th>
                            <th>Límites</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {licenses.data.length === 0 && (
                            <tr>
                                <td colSpan={9} className="empty">Sin registros</td>
                            </tr>
                        )}
                        {licenses.data.map((lic) => {
                            const sc = statusColors[lic.status] ?? { bg: '#f3f4f6', color: '#374151' };
                            return (
                                <tr key={lic.id}>
                                    <td style={{ fontFamily: 'monospace', fontSize: '0.82em', whiteSpace: 'nowrap' }}>
                                        {lic.license_key}
                                    </td>
                                    <td style={{ fontWeight: 600 }}>{lic.company?.business_name ?? '—'}</td>
                                    <td>{lic.plan?.name ?? '—'}</td>
                                    <td>
                                        <span style={{
                                            fontSize: 12, fontWeight: 600, padding: '2px 8px', borderRadius: 10,
                                            background: lic.license_type === 'propietario' ? '#dbeafe' : '#f3f4f6',
                                            color: lic.license_type === 'propietario' ? '#1e40af' : '#374151',
                                        }}>
                                            {licenseTypes[lic.license_type] ?? lic.license_type}
                                        </span>
                                    </td>
                                    <td>
                                        <span style={{ fontSize: 12, fontWeight: 600, padding: '2px 8px', borderRadius: 10, background: sc.bg, color: sc.color }}>
                                            {licenseStatuses[lic.status] ?? lic.status}
                                        </span>
                                    </td>
                                    <td className="muted">{lic.starts_at ? lic.starts_at.substring(0, 10) : '—'}</td>
                                    <td className="muted">
                                        {lic.license_type === 'propietario'
                                            ? <span style={{ fontStyle: 'italic' }}>Sin vencimiento</span>
                                            : lic.expires_at ? lic.expires_at.substring(0, 10) : '—'}
                                    </td>
                                    <td className="muted" style={{ fontSize: '0.82em', whiteSpace: 'nowrap' }}>
                                        {lic.max_users}u / {lic.max_devices}d / {lic.grace_days}días
                                    </td>
                                    <td>
                                        <button
                                            className="btn secondary btn-icon"
                                            title="Editar"
                                            onClick={() => setEditItem(lic)}
                                        >
                                            <IconEdit />
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                <Pagination links={licenses.links} />
            </div>

            {showCreate && (
                <Modal title="Nueva licencia" onClose={() => setShowCreate(false)}>
                    <LicenseForm
                        companies={companies}
                        plans={plans}
                        onSuccess={() => setShowCreate(false)}
                        onCancel={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {editItem && (
                <Modal title={`Editar licencia — ${editItem.company?.business_name ?? ''}`} onClose={() => setEditItem(null)}>
                    <LicenseForm
                        license={editItem}
                        companies={companies}
                        plans={plans}
                        onSuccess={() => setEditItem(null)}
                        onCancel={() => setEditItem(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
