import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { route } from 'ziggy-js';

const licenseStatuses = {
    active: 'Activa',
    expired: 'Vencida',
    suspended: 'Suspendida',
};

function CreateLicenseForm({ companies, plans, onSuccess }) {
    const form = useForm({
        company_id: '',
        plan_id: '',
        status: 'active',
        starts_at: '',
        expires_at: '',
        max_users: 1,
        max_devices: 1,
        grace_days: 0,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.licenses.store'), { onSuccess });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="field">
                <label>Empresa</label>
                <select
                    value={form.data.company_id}
                    onChange={(e) => form.setData('company_id', e.target.value)}
                >
                    <option value="">— Seleccionar —</option>
                    {companies.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.customer?.name} — {c.business_name}
                        </option>
                    ))}
                </select>
                {form.errors.company_id && <p className="field-error">{form.errors.company_id}</p>}
            </div>
            <div className="field">
                <label>Plan</label>
                <select
                    value={form.data.plan_id}
                    onChange={(e) => form.setData('plan_id', e.target.value)}
                >
                    <option value="">— Seleccionar —</option>
                    {plans.map((p) => (
                        <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                </select>
                {form.errors.plan_id && <p className="field-error">{form.errors.plan_id}</p>}
            </div>
            <div className="field">
                <label>Estado</label>
                <select
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    {Object.entries(licenseStatuses).map(([k, v]) => (
                        <option key={k} value={k}>{v}</option>
                    ))}
                </select>
                {form.errors.status && <p className="field-error">{form.errors.status}</p>}
            </div>
            <div className="field">
                <label>Fecha inicio</label>
                <input
                    type="date"
                    value={form.data.starts_at}
                    onChange={(e) => form.setData('starts_at', e.target.value)}
                />
                {form.errors.starts_at && <p className="field-error">{form.errors.starts_at}</p>}
            </div>
            <div className="field">
                <label>Fecha vencimiento</label>
                <input
                    type="date"
                    value={form.data.expires_at}
                    onChange={(e) => form.setData('expires_at', e.target.value)}
                />
                {form.errors.expires_at && <p className="field-error">{form.errors.expires_at}</p>}
            </div>
            <div className="field">
                <label>Máx. usuarios</label>
                <input
                    type="number"
                    min="1"
                    value={form.data.max_users}
                    onChange={(e) => form.setData('max_users', e.target.value)}
                />
                {form.errors.max_users && <p className="field-error">{form.errors.max_users}</p>}
            </div>
            <div className="field">
                <label>Máx. dispositivos</label>
                <input
                    type="number"
                    min="1"
                    value={form.data.max_devices}
                    onChange={(e) => form.setData('max_devices', e.target.value)}
                />
                {form.errors.max_devices && <p className="field-error">{form.errors.max_devices}</p>}
            </div>
            <div className="field">
                <label>Días de gracia</label>
                <input
                    type="number"
                    min="0"
                    value={form.data.grace_days}
                    onChange={(e) => form.setData('grace_days', e.target.value)}
                />
                {form.errors.grace_days && <p className="field-error">{form.errors.grace_days}</p>}
            </div>
            <div className="modal-footer">
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </button>
            </div>
        </form>
    );
}

function EditLicenseForm({ license, onSuccess }) {
    const form = useForm({
        status: license?.status ?? 'active',
        expires_at: license?.expires_at ? license.expires_at.substring(0, 10) : '',
        max_users: license?.max_users ?? 1,
        max_devices: license?.max_devices ?? 1,
        grace_days: license?.grace_days ?? 0,
    });

    function handleSubmit(e) {
        e.preventDefault();
        form.put(route('admin.licenses.update', license.id), { onSuccess });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="field">
                <label>Estado</label>
                <select
                    value={form.data.status}
                    onChange={(e) => form.setData('status', e.target.value)}
                >
                    {Object.entries(licenseStatuses).map(([k, v]) => (
                        <option key={k} value={k}>{v}</option>
                    ))}
                </select>
                {form.errors.status && <p className="field-error">{form.errors.status}</p>}
            </div>
            <div className="field">
                <label>Fecha vencimiento</label>
                <input
                    type="date"
                    value={form.data.expires_at}
                    onChange={(e) => form.setData('expires_at', e.target.value)}
                />
                {form.errors.expires_at && <p className="field-error">{form.errors.expires_at}</p>}
            </div>
            <div className="field">
                <label>Máx. usuarios</label>
                <input
                    type="number"
                    min="1"
                    value={form.data.max_users}
                    onChange={(e) => form.setData('max_users', e.target.value)}
                />
                {form.errors.max_users && <p className="field-error">{form.errors.max_users}</p>}
            </div>
            <div className="field">
                <label>Máx. dispositivos</label>
                <input
                    type="number"
                    min="1"
                    value={form.data.max_devices}
                    onChange={(e) => form.setData('max_devices', e.target.value)}
                />
                {form.errors.max_devices && <p className="field-error">{form.errors.max_devices}</p>}
            </div>
            <div className="field">
                <label>Días de gracia</label>
                <input
                    type="number"
                    min="0"
                    value={form.data.grace_days}
                    onChange={(e) => form.setData('grace_days', e.target.value)}
                />
                {form.errors.grace_days && <p className="field-error">{form.errors.grace_days}</p>}
            </div>
            <div className="modal-footer">
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
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
                    Nueva licencia
                </button>
            </div>

            <div className="card">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Empresa</th>
                            <th>Plan</th>
                            <th>Estado</th>
                            <th>Vence</th>
                            <th>Límites</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {licenses.data.length === 0 && (
                            <tr>
                                <td colSpan={8} style={{ textAlign: 'center' }} className="muted">
                                    Sin registros
                                </td>
                            </tr>
                        )}
                        {licenses.data.map((license) => (
                            <tr key={license.id}>
                                <td style={{ fontFamily: 'monospace', fontSize: '0.85em' }}>
                                    {license.license_key}
                                </td>
                                <td>{license.customer?.name ?? '—'}</td>
                                <td>{license.company?.business_name ?? '—'}</td>
                                <td>{license.plan?.name ?? '—'}</td>
                                <td>
                                    <span className={`badge ${license.status}`}>
                                        {licenseStatuses[license.status] ?? license.status}
                                    </span>
                                </td>
                                <td>
                                    {license.expires_at ? license.expires_at.substring(0, 10) : '—'}
                                </td>
                                <td className="muted" style={{ fontSize: '0.85em' }}>
                                    {license.max_users} usuarios / {license.max_devices} dispositivos / {license.grace_days} días
                                </td>
                                <td>
                                    <button
                                        className="btn btn-sm"
                                        onClick={() => setEditItem(license)}
                                    >
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={licenses.links} />
            </div>

            {showCreate && (
                <div className="overlay-layer">
                    <div className="modal">
                        <div className="modal-header">
                            <h2>Nueva licencia</h2>
                            <button className="modal-close" onClick={() => setShowCreate(false)}>✕</button>
                        </div>
                        <CreateLicenseForm
                            companies={companies}
                            plans={plans}
                            onSuccess={() => setShowCreate(false)}
                        />
                    </div>
                </div>
            )}

            {editItem && (
                <div className="overlay-layer">
                    <div className="modal">
                        <div className="modal-header">
                            <h2>Editar licencia</h2>
                            <button className="modal-close" onClick={() => setEditItem(null)}>✕</button>
                        </div>
                        <EditLicenseForm
                            license={editItem}
                            onSuccess={() => setEditItem(null)}
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
