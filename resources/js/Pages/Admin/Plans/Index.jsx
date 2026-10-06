import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { route } from 'ziggy-js';

function PlanForm({ plan, onSuccess }) {
    const form = useForm({
        code: plan?.code ?? '',
        name: plan?.name ?? '',
        monthly_price: plan?.monthly_price ?? '',
        annual_price: plan?.annual_price ?? '',
        implementation_fee: plan?.implementation_fee ?? '',
        additional_user_price: plan?.additional_user_price ?? '',
        included_users: plan?.included_users ?? 1,
        max_devices: plan?.max_devices ?? 1,
        support_included: plan?.support_included !== undefined ? Number(plan.support_included) : 0,
        cloud_backup: plan?.cloud_backup !== undefined ? Number(plan.cloud_backup) : 0,
        unlimited_documents: plan?.unlimited_documents !== undefined ? Number(plan.unlimited_documents) : 0,
        is_active: plan?.is_active !== undefined ? Number(plan.is_active) : 1,
    });

    function handleSubmit(e) {
        e.preventDefault();
        if (plan) {
            form.put(route('admin.plans.update', plan.id), { onSuccess });
        } else {
            form.post(route('admin.plans.store'), { onSuccess });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="field">
                <label>Código</label>
                <input
                    type="text"
                    value={form.data.code}
                    onChange={(e) => form.setData('code', e.target.value)}
                    placeholder="BASICO"
                />
                {form.errors.code && <p className="field-error">{form.errors.code}</p>}
            </div>
            <div className="field">
                <label>Nombre</label>
                <input
                    type="text"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Plan Básico"
                />
                {form.errors.name && <p className="field-error">{form.errors.name}</p>}
            </div>
            <div className="field">
                <label>Precio mensual</label>
                <input
                    type="number"
                    step="0.01"
                    value={form.data.monthly_price}
                    onChange={(e) => form.setData('monthly_price', e.target.value)}
                />
                {form.errors.monthly_price && <p className="field-error">{form.errors.monthly_price}</p>}
            </div>
            <div className="field">
                <label>Precio anual</label>
                <input
                    type="number"
                    step="0.01"
                    value={form.data.annual_price}
                    onChange={(e) => form.setData('annual_price', e.target.value)}
                />
                {form.errors.annual_price && <p className="field-error">{form.errors.annual_price}</p>}
            </div>
            <div className="field">
                <label>Fee de implementación</label>
                <input
                    type="number"
                    step="0.01"
                    value={form.data.implementation_fee}
                    onChange={(e) => form.setData('implementation_fee', e.target.value)}
                />
                {form.errors.implementation_fee && <p className="field-error">{form.errors.implementation_fee}</p>}
            </div>
            <div className="field">
                <label>Precio usuario adicional</label>
                <input
                    type="number"
                    step="0.01"
                    value={form.data.additional_user_price}
                    onChange={(e) => form.setData('additional_user_price', e.target.value)}
                />
                {form.errors.additional_user_price && <p className="field-error">{form.errors.additional_user_price}</p>}
            </div>
            <div className="field">
                <label>Usuarios incluidos</label>
                <input
                    type="number"
                    min="1"
                    value={form.data.included_users}
                    onChange={(e) => form.setData('included_users', e.target.value)}
                />
                {form.errors.included_users && <p className="field-error">{form.errors.included_users}</p>}
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
                <label>Soporte incluido</label>
                <select
                    value={form.data.support_included}
                    onChange={(e) => form.setData('support_included', Number(e.target.value))}
                >
                    <option value={1}>Si</option>
                    <option value={0}>No</option>
                </select>
                {form.errors.support_included && <p className="field-error">{form.errors.support_included}</p>}
            </div>
            <div className="field">
                <label>Backup en la nube</label>
                <select
                    value={form.data.cloud_backup}
                    onChange={(e) => form.setData('cloud_backup', Number(e.target.value))}
                >
                    <option value={1}>Si</option>
                    <option value={0}>No</option>
                </select>
                {form.errors.cloud_backup && <p className="field-error">{form.errors.cloud_backup}</p>}
            </div>
            <div className="field">
                <label>Documentos ilimitados</label>
                <select
                    value={form.data.unlimited_documents}
                    onChange={(e) => form.setData('unlimited_documents', Number(e.target.value))}
                >
                    <option value={1}>Si</option>
                    <option value={0}>No</option>
                </select>
                {form.errors.unlimited_documents && <p className="field-error">{form.errors.unlimited_documents}</p>}
            </div>
            <div className="field">
                <label>Activo</label>
                <select
                    value={form.data.is_active}
                    onChange={(e) => form.setData('is_active', Number(e.target.value))}
                >
                    <option value={1}>Si</option>
                    <option value={0}>No</option>
                </select>
                {form.errors.is_active && <p className="field-error">{form.errors.is_active}</p>}
            </div>
            <div className="modal-footer">
                <button type="submit" className="btn" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </button>
            </div>
        </form>
    );
}

function yesNo(val) {
    return val ? 'Si' : 'No';
}

export default function PlansIndex({ plans }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editItem, setEditItem] = useState(null);

    function handleDelete(id) {
        if (!confirm('¿Eliminar este plan?')) return;
        router.delete(route('admin.plans.destroy', id));
    }

    return (
        <AppLayout>
            <Head title="Planes" />
            <div className="top">
                <h1>Planes</h1>
                <button className="btn" onClick={() => setShowCreate(true)}>
                    Nuevo plan
                </button>
            </div>

            <div className="card">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Precio mensual</th>
                            <th>Precio anual</th>
                            <th>Implementación</th>
                            <th>Usuarios</th>
                            <th>Dispositivos</th>
                            <th>Soporte</th>
                            <th>Backup</th>
                            <th>Docs ilimitados</th>
                            <th>Activo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {plans.length === 0 && (
                            <tr>
                                <td colSpan={12} style={{ textAlign: 'center' }} className="muted">
                                    Sin registros
                                </td>
                            </tr>
                        )}
                        {plans.map((plan) => (
                            <tr key={plan.id}>
                                <td>{plan.name}</td>
                                <td>{plan.code}</td>
                                <td>${Number(plan.monthly_price).toFixed(2)}</td>
                                <td>${Number(plan.annual_price).toFixed(2)}</td>
                                <td>${Number(plan.implementation_fee).toFixed(2)}</td>
                                <td>{plan.included_users}</td>
                                <td>{plan.max_devices}</td>
                                <td>{yesNo(plan.support_included)}</td>
                                <td>{yesNo(plan.cloud_backup)}</td>
                                <td>{yesNo(plan.unlimited_documents)}</td>
                                <td>
                                    <span className={`badge ${plan.is_active ? 'active' : 'inactive'}`}>
                                        {yesNo(plan.is_active)}
                                    </span>
                                </td>
                                <td>
                                    <button
                                        className="btn btn-sm"
                                        onClick={() => setEditItem(plan)}
                                    >
                                        Editar
                                    </button>
                                    {' '}
                                    <button
                                        className="btn btn-sm btn-danger"
                                        onClick={() => handleDelete(plan.id)}
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {showCreate && (
                <div className="overlay-layer">
                    <div className="modal">
                        <div className="modal-header">
                            <h2>Nuevo plan</h2>
                            <button className="modal-close" onClick={() => setShowCreate(false)}>✕</button>
                        </div>
                        <PlanForm onSuccess={() => setShowCreate(false)} />
                    </div>
                </div>
            )}

            {editItem && (
                <div className="overlay-layer">
                    <div className="modal">
                        <div className="modal-header">
                            <h2>Editar plan</h2>
                            <button className="modal-close" onClick={() => setEditItem(null)}>✕</button>
                        </div>
                        <PlanForm plan={editItem} onSuccess={() => setEditItem(null)} />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
