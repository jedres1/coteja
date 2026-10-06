import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { route } from 'ziggy-js';

const environments = {
    production: 'Produccion',
    testing: 'Pruebas',
};

function CompanyForm({ company, customers, onSuccess }) {
    const form = useForm({
        customer_id: company?.customer_id ?? '',
        business_name: company?.business_name ?? '',
        trade_name: company?.trade_name ?? '',
        nit_dui: company?.nit_dui ?? '',
        nrc: company?.nrc ?? '',
        email: company?.email ?? '',
        phone: company?.phone ?? '',
        environment: company?.environment ?? 'production',
    });

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
            <div className="field">
                <label>Cliente</label>
                <select
                    value={form.data.customer_id}
                    onChange={(e) => form.setData('customer_id', e.target.value)}
                >
                    <option value="">— Seleccionar —</option>
                    {customers.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                </select>
                {form.errors.customer_id && <p className="field-error">{form.errors.customer_id}</p>}
            </div>
            <div className="field">
                <label>Razón social</label>
                <input
                    type="text"
                    value={form.data.business_name}
                    onChange={(e) => form.setData('business_name', e.target.value)}
                />
                {form.errors.business_name && <p className="field-error">{form.errors.business_name}</p>}
            </div>
            <div className="field">
                <label>Nombre comercial</label>
                <input
                    type="text"
                    value={form.data.trade_name}
                    onChange={(e) => form.setData('trade_name', e.target.value)}
                />
                {form.errors.trade_name && <p className="field-error">{form.errors.trade_name}</p>}
            </div>
            <div className="field">
                <label>NIT / DUI</label>
                <input
                    type="text"
                    value={form.data.nit_dui}
                    onChange={(e) => form.setData('nit_dui', e.target.value)}
                    placeholder="0000-000000-000-0"
                />
                {form.errors.nit_dui && <p className="field-error">{form.errors.nit_dui}</p>}
            </div>
            <div className="field">
                <label>NRC</label>
                <input
                    type="text"
                    value={form.data.nrc}
                    onChange={(e) => form.setData('nrc', e.target.value)}
                />
                {form.errors.nrc && <p className="field-error">{form.errors.nrc}</p>}
            </div>
            <div className="field">
                <label>Correo electrónico</label>
                <input
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                {form.errors.email && <p className="field-error">{form.errors.email}</p>}
            </div>
            <div className="field">
                <label>Teléfono</label>
                <input
                    type="text"
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                />
                {form.errors.phone && <p className="field-error">{form.errors.phone}</p>}
            </div>
            <div className="field">
                <label>Ambiente</label>
                <select
                    value={form.data.environment}
                    onChange={(e) => form.setData('environment', e.target.value)}
                >
                    {Object.entries(environments).map(([k, v]) => (
                        <option key={k} value={k}>{v}</option>
                    ))}
                </select>
                {form.errors.environment && <p className="field-error">{form.errors.environment}</p>}
            </div>
            <div className="modal-footer">
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </button>
            </div>
        </form>
    );
}

export default function CompaniesIndex({ companies, customers }) {
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
                            <th>Cliente</th>
                            <th>NIT/DUI</th>
                            <th>NRC</th>
                            <th>Contacto</th>
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
                                    <div>{company.business_name}</div>
                                    {company.trade_name && (
                                        <div className="muted" style={{ fontSize: '0.85em' }}>{company.trade_name}</div>
                                    )}
                                </td>
                                <td>{company.customer?.name ?? '—'}</td>
                                <td>{company.nit_dui ?? '—'}</td>
                                <td>{company.nrc ?? '—'}</td>
                                <td>
                                    {company.email && <div>{company.email}</div>}
                                    {company.phone && <div className="muted" style={{ fontSize: '0.85em' }}>{company.phone}</div>}
                                    {!company.email && !company.phone && '—'}
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
                <div className="overlay-layer">
                    <div className="modal">
                        <div className="modal-header">
                            <h2>Nueva empresa</h2>
                            <button className="modal-close" onClick={() => setShowCreate(false)}>✕</button>
                        </div>
                        <CompanyForm
                            customers={customers}
                            onSuccess={() => setShowCreate(false)}
                        />
                    </div>
                </div>
            )}

            {editItem && (
                <div className="overlay-layer">
                    <div className="modal">
                        <div className="modal-header">
                            <h2>Editar empresa</h2>
                            <button className="modal-close" onClick={() => setEditItem(null)}>✕</button>
                        </div>
                        <CompanyForm
                            company={editItem}
                            customers={customers}
                            onSuccess={() => setEditItem(null)}
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
