import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import Modal from '@/Components/Modal';
import { route } from 'ziggy-js';

const environments = {
    production: 'Produccion',
    testing: 'Pruebas',
};

function CompanyForm({ company, modules, onSuccess }) {
    const form = useForm({
        business_name:   company?.business_name ?? '',
        trade_name:      company?.trade_name ?? '',
        nit_dui:         company?.nit_dui ?? '',
        nrc:             company?.nrc ?? '',
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

export default function CompaniesIndex({ companies, modules }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

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
                            <th>Ambiente</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {companies.data.length === 0 && (
                            <tr>
                                <td colSpan={7} style={{ textAlign: 'center' }} className="muted">
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
                                    <span className={`badge ${company.environment}`}>
                                        {environments[company.environment] ?? company.environment}
                                    </span>
                                </td>
                                <td>
                                    <button
                                        className="btn btn-sm"
                                        onClick={() => setEditItem(company)}
                                    >
                                        Editar
                                    </button>
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
        </AppLayout>
    );
}
