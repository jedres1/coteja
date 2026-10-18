import { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { route } from 'ziggy-js';

const environments = {
    production: 'Produccion',
    testing: 'Pruebas',
};

const licenseStatuses = {
    active:    { label: 'Activa',     bg: '#dcfce7', color: '#166534' },
    expired:   { label: 'Vencida',    bg: '#fee2e2', color: '#991b1b' },
    suspended: { label: 'Suspendida', bg: '#fef3c7', color: '#92400e' },
};

const licenseTypes = {
    propietario: 'Propietario',
    mensual:     'Mensual',
    anual:       'Anual',
};

function CompanyForm({ company, modules, onSuccess }) {
    const [activities, setActivities] = useState([]);

    useEffect(() => {
        fetch('/catalogs/actividades-economicas.json')
            .then((r) => r.json())
            .then((d) => setActivities(Array.isArray(d) ? d : []))
            .catch(() => {});
    }, []);

    const form = useForm({
        business_name:     company?.business_name     ?? '',
        trade_name:        company?.trade_name        ?? '',
        nit_dui:           company?.nit_dui           ?? '',
        nrc:               company?.nrc               ?? '',
        business_activity: company?.business_activity ?? '',
        email:           company?.email ?? '',
        phone:           company?.phone ?? '',
        environment:     company?.environment ?? 'production',
        module_accesses: company?.module_accesses ?? [],
    });

    function toggleModule(key) {
        const current = form.data.module_accesses;
        form.setData('module_accesses', current.includes(key)
            ? current.filter((m) => m !== key)
            : [...current, key]);
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (company) {
            form.put(route('admin.companies.update', company.id), { onSuccess });
        } else {
            form.post(route('admin.companies.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="form-grid">
                <label className="full-width">
                    Razón social <span style={{ color: 'red' }}>*</span>
                    <input
                        type="text"
                        value={form.data.business_name}
                        onChange={(e) => form.setData('business_name', e.target.value)}
                        required
                    />
                    {form.errors.business_name && <p className="field-error">{form.errors.business_name}</p>}
                </label>

                <label>
                    Nombre comercial
                    <input
                        type="text"
                        value={form.data.trade_name}
                        onChange={(e) => form.setData('trade_name', e.target.value)}
                    />
                    {form.errors.trade_name && <p className="field-error">{form.errors.trade_name}</p>}
                </label>

                <label>
                    NIT / DUI <span style={{ color: 'red' }}>*</span>
                    <input
                        type="text"
                        value={form.data.nit_dui}
                        onChange={(e) => form.setData('nit_dui', e.target.value)}
                        placeholder="0000-000000-000-0"
                        required
                    />
                    {form.errors.nit_dui && <p className="field-error">{form.errors.nit_dui}</p>}
                </label>

                <label>
                    NRC
                    <input
                        type="text"
                        value={form.data.nrc}
                        onChange={(e) => form.setData('nrc', e.target.value)}
                    />
                    {form.errors.nrc && <p className="field-error">{form.errors.nrc}</p>}
                </label>

                <label className="full-width">
                    Actividad económica
                    <input
                        type="text"
                        value={form.data.business_activity}
                        onChange={(e) => form.setData('business_activity', e.target.value)}
                        list="company-activities-list"
                        placeholder="Buscar por código o descripción…"
                    />
                    <datalist id="company-activities-list">
                        {activities.map((a) => (
                            <option key={a.codigo} value={`${a.codigo} - ${a.descripcion}`} />
                        ))}
                    </datalist>
                    {form.errors.business_activity && <p className="field-error">{form.errors.business_activity}</p>}
                </label>

                <label>
                    Correo electrónico
                    <input
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                    {form.errors.email && <p className="field-error">{form.errors.email}</p>}
                </label>

                <label>
                    Teléfono
                    <input
                        type="text"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                    />
                    {form.errors.phone && <p className="field-error">{form.errors.phone}</p>}
                </label>

                <label>
                    Ambiente
                    <select
                        value={form.data.environment}
                        onChange={(e) => form.setData('environment', e.target.value)}
                    >
                        {Object.entries(environments).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {form.errors.environment && <p className="field-error">{form.errors.environment}</p>}
                </label>

                <div className="full-width">
                    <p style={{ margin: '0 0 8px', fontSize: 13, fontWeight: 600, color: '#374151' }}>
                        Módulos habilitados
                    </p>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                        {Object.entries(modules).map(([key, label]) => (
                            <label key={key} style={{ flexDirection: 'row', alignItems: 'center', gap: 6, fontWeight: 400 }}>
                                <input
                                    type="checkbox"
                                    style={{ width: 'auto' }}
                                    checked={form.data.module_accesses.includes(key)}
                                    onChange={() => toggleModule(key)}
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                    {form.errors.module_accesses && <p className="field-error">{form.errors.module_accesses}</p>}
                </div>
            </div>

            <div className="form-actions" style={{ marginTop: 16 }}>
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </button>
            </div>
        </form>
    );
}

function LicenseForm({ company, plans, onSuccess, onCancel }) {
    const today = new Date().toISOString().substring(0, 10);

    const form = useForm({
        company_id:   company.id,
        plan_id:      '',
        license_type: 'mensual',
        status:       'active',
        starts_at:    today,
        expires_at:   '',
        max_users:    1,
        max_devices:  1,
        grace_days:   0,
    });

    const isPropietario = form.data.license_type === 'propietario';

    function handleSubmit(e) {
        e.preventDefault();
        form.post(route('admin.licenses.store'), { onSuccess });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ marginBottom: 12, padding: '8px 12px', background: '#f0f9ff', borderRadius: 6, fontSize: 13, color: '#0369a1' }}>
                Empresa: <strong>{company.business_name}</strong>
            </div>

            <div className="form-grid">
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
                    {form.errors.license_type && <p className="field-error">{form.errors.license_type}</p>}
                </label>

                <label>
                    Estado
                    <select
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        {Object.entries(licenseStatuses).map(([k, v]) => (
                            <option key={k} value={k}>{v.label}</option>
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
                    {form.processing ? 'Guardando…' : 'Asignar licencia'}
                </button>
            </div>
        </form>
    );
}

function LicenseBadge({ license }) {
    if (!license) {
        return <span style={{ fontSize: 12, color: '#9ca3af', fontStyle: 'italic' }}>Sin licencia</span>;
    }

    const st = licenseStatuses[license.status] ?? { label: license.status, bg: '#f3f4f6', color: '#374151' };
    const today = new Date();
    const expires = license.expires_at ? new Date(license.expires_at) : null;
    const isReallyActive = license.status === 'active' && (license.license_type === 'propietario' || !expires || expires >= today);

    const displaySt = isReallyActive ? st : (license.status === 'active' ? licenseStatuses.expired : st);

    return (
        <div>
            <span style={{
                display: 'inline-block', fontSize: 12, fontWeight: 600,
                padding: '2px 8px', borderRadius: 10,
                background: displaySt.bg, color: displaySt.color,
            }}>
                {displaySt.label}
            </span>
            {license.plan && (
                <div style={{ fontSize: 11, color: '#6b7280', marginTop: 2 }}>{license.plan.name}</div>
            )}
            {license.license_type === 'propietario' && (
                <div style={{ fontSize: 11, color: '#6b7280' }}>Propietario</div>
            )}
            {expires && license.license_type !== 'propietario' && (
                <div style={{ fontSize: 11, color: '#6b7280' }}>Vence: {license.expires_at.substring(0, 10)}</div>
            )}
        </div>
    );
}

export default function CompaniesIndex({ companies, modules, plans }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem]     = useState(null);
    const [licenseCompany, setLicenseCompany] = useState(null);

    return (
        <AppLayout>
            <Head title="Empresas" />
            <div className="top">
                <h1>Empresas</h1>
                <button className="btn" onClick={() => setShowCreate(true)}>
                    Nueva empresa
                </button>
            </div>

            <div className="card">
                <table>
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>NIT/DUI</th>
                            <th>NRC</th>
                            <th>Contacto</th>
                            <th>Módulos</th>
                            <th>Licencia</th>
                            <th>Ambiente</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {companies.data.length === 0 && (
                            <tr>
                                <td colSpan={8} style={{ textAlign: 'center' }} className="muted">
                                    Sin registros
                                </td>
                            </tr>
                        )}
                        {companies.data.map((company) => (
                            <tr key={company.id}>
                                <td>
                                    <div style={{ fontWeight: 600 }}>{company.business_name}</div>
                                    {company.trade_name && (
                                        <div className="muted" style={{ fontSize: '0.85em' }}>{company.trade_name}</div>
                                    )}
                                </td>
                                <td>{company.nit_dui ?? '—'}</td>
                                <td>{company.nrc ?? '—'}</td>
                                <td>
                                    {company.email && <div>{company.email}</div>}
                                    {company.phone && <div className="muted" style={{ fontSize: '0.85em' }}>{company.phone}</div>}
                                    {!company.email && !company.phone && '—'}
                                </td>
                                <td>
                                    {company.module_accesses?.length > 0
                                        ? company.module_accesses.map((m) => (
                                            <span key={m} style={{ display: 'block', fontSize: 12 }}>
                                                {modules[m] ?? m}
                                            </span>
                                        ))
                                        : <span className="muted">—</span>
                                    }
                                </td>
                                <td>
                                    <LicenseBadge license={company.latest_license} />
                                </td>
                                <td>
                                    <span className={`badge ${company.environment}`}>
                                        {environments[company.environment] ?? company.environment}
                                    </span>
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6, flexDirection: 'column', alignItems: 'flex-start' }}>
                                        <button className="btn btn-sm" onClick={() => setEditItem(company)}>
                                            Editar
                                        </button>
                                        <button
                                            className="btn btn-sm secondary"
                                            onClick={() => setLicenseCompany(company)}
                                        >
                                            + Licencia
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <Pagination links={companies.links} />
            </div>

            {showCreate && (
                <Modal title="Nueva empresa" onClose={() => setShowCreate(false)}>
                    <CompanyForm
                        modules={modules}
                        onSuccess={() => setShowCreate(false)}
                    />
                </Modal>
            )}

            {editItem && (
                <Modal title="Editar empresa" onClose={() => setEditItem(null)}>
                    <CompanyForm
                        company={editItem}
                        modules={modules}
                        onSuccess={() => setEditItem(null)}
                    />
                </Modal>
            )}

            {licenseCompany && (
                <Modal
                    title={`Asignar licencia — ${licenseCompany.business_name}`}
                    onClose={() => setLicenseCompany(null)}
                >
                    <LicenseForm
                        company={licenseCompany}
                        plans={plans}
                        onSuccess={() => setLicenseCompany(null)}
                        onCancel={() => setLicenseCompany(null)}
                    />
                </Modal>
            )}
        </AppLayout>
    );
}
