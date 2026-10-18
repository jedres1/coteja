import { useState } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import { IconEdit, IconTrash } from '@/Components/Icons';

const TYPE_LABELS = { beneficio: 'Beneficio', deduccion: 'Deducción' };
const TYPE_COLORS = {
    beneficio: { background: '#f0fdf4', color: '#15803d' },
    deduccion: { background: '#fef2f2', color: '#dc2626' },
};

const CALC_LABELS = {
    fijo:       'Monto fijo',
    porcentaje: 'Porcentaje',
    formula:    'Fórmula',
    editable:   'Editable por período',
};

const emptyConcept = {
    name:               '',
    type:               'beneficio',
    calculation_method: 'fijo',
    amount:             '',
    rate:               '',
    formula:            '',
    applies_to_all:     '1',
    account_id:         '',
};

function ConceptForm({ concept, accounts, hasAccounting, onCancel }) {
    const isEditing = !!concept;
    const { data, setData, processing, errors, reset } = useForm(
        isEditing
            ? {
                name:               concept.name ?? '',
                type:               concept.type ?? 'beneficio',
                calculation_method: concept.calculation_method ?? 'fijo',
                amount:             concept.amount ?? '',
                rate:               concept.rate ?? '',
                formula:            concept.formula ?? '',
                applies_to_all:     concept.applies_to_all ? '1' : '0',
                account_id:         concept.account_id ?? '',
            }
            : { ...emptyConcept }
    );

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            router.put(route('admin.payroll.concepts.update', concept.id), data, { onSuccess: onCancel });
        } else {
            router.post(route('admin.payroll.concepts.store'), data, {
                onSuccess: () => { reset(); onCancel(); },
            });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr', gap: 12, alignItems: 'start' }}>
                <label style={{ margin: 0 }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        maxLength={255}
                        placeholder="Nombre del concepto"
                    />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>

                <label style={{ margin: 0 }}>
                    Tipo <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        <option value="beneficio">Beneficio</option>
                        <option value="deduccion">Deducción</option>
                    </select>
                    {errors.type && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.type}</span>}
                </label>

                <label style={{ margin: 0 }}>
                    Aplica a <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.applies_to_all} onChange={(e) => setData('applies_to_all', e.target.value)}>
                        <option value="1">Todos los empleados</option>
                        <option value="0">Empleados específicos</option>
                    </select>
                    {errors.applies_to_all && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.applies_to_all}</span>}
                </label>

                <label style={{ margin: 0 }}>
                    Método de cálculo <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.calculation_method} onChange={(e) => setData('calculation_method', e.target.value)}>
                        {Object.entries(CALC_LABELS).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                    {errors.calculation_method && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.calculation_method}</span>}
                </label>

                {data.calculation_method === 'fijo' && (
                    <label style={{ margin: 0 }}>
                        Monto fijo <span style={{ color: '#ef4444' }}>*</span>
                        <input
                            type="number"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        />
                        {errors.amount && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.amount}</span>}
                    </label>
                )}

                {data.calculation_method === 'porcentaje' && (
                    <label style={{ margin: 0 }}>
                        Porcentaje (0–100) <span style={{ color: '#ef4444' }}>*</span>
                        <input
                            type="number"
                            value={data.rate}
                            onChange={(e) => setData('rate', e.target.value)}
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="0.00"
                        />
                        {errors.rate && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.rate}</span>}
                    </label>
                )}

                {data.calculation_method === 'formula' && (
                    <label style={{ margin: 0, gridColumn: 'span 2' }}>
                        Fórmula <span style={{ color: '#ef4444' }}>*</span>
                        <input
                            type="text"
                            value={data.formula}
                            onChange={(e) => setData('formula', e.target.value)}
                            placeholder="Ej: salary * 0.05"
                            maxLength={255}
                        />
                        {errors.formula && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.formula}</span>}
                    </label>
                )}

                {hasAccounting && (
                    <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                        Cuenta contable <span style={{ color: '#ef4444' }}>*</span>
                        <select value={data.account_id} onChange={(e) => setData('account_id', e.target.value)}>
                            <option value="">— Sin cuenta —</option>
                            {(accounts ?? []).map((a) => (
                                <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                            ))}
                        </select>
                        {errors.account_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.account_id}</span>}
                    </label>
                )}
            </div>

            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando...' : isEditing ? 'Actualizar concepto' : 'Guardar concepto'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

export default function Concepts({ concepts, accounts, legalConcepts }) {
    const { auth, activeCompany } = usePage().props;
    const tenantModules = activeCompany?.modules ?? null;
    const hasAccounting = tenantModules
        ? tenantModules.includes('accounting')
        : (auth?.user?.modules?.accounting ?? false);

    const [showForm, setShowForm]           = useState(false);
    const [editingConcept, setEditingConcept] = useState(null);

    function handleEdit(concept) {
        setEditingConcept(concept);
        setShowForm(true);
    }

    function closeForm() {
        setShowForm(false);
        setEditingConcept(null);
    }

    function handleDelete(id) {
        if (!confirm('¿Eliminar este concepto de nómina?')) return;
        router.delete(route('admin.payroll.concepts.destroy', id));
    }

    return (
        <AppLayout>
            <Head title="Gestión de conceptos" />

            <div className="top">
                <h1>Gestión de conceptos</h1>
            </div>

            {showForm && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>
                        {editingConcept ? 'Editar concepto' : 'Nuevo concepto'}
                    </h2>
                    <ConceptForm
                        concept={editingConcept}
                        accounts={accounts}
                        hasAccounting={hasAccounting}
                        onCancel={closeForm}
                    />
                </div>
            )}

            {!showForm && (
                <div style={{ marginBottom: 12 }}>
                    <button
                        type="button"
                        className="btn"
                        onClick={() => { setEditingConcept(null); setShowForm(true); }}
                    >
                        + Nuevo concepto
                    </button>
                </div>
            )}

            {/* Custom concepts */}
            <div className="card table-scroll" style={{ marginBottom: 16 }}>
                <h2 style={{ margin: '0 0 12px', fontSize: 14, fontWeight: 600 }}>Conceptos personalizados</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Método</th>
                            {hasAccounting && <th>Cuenta contable</th>}
                            <th>Aplica a</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!concepts || concepts.length === 0) && (
                            <tr>
                                <td colSpan={hasAccounting ? 6 : 5} className="empty">Sin conceptos personalizados</td>
                            </tr>
                        )}
                        {(concepts ?? []).map((concept) => (
                            <tr key={concept.id}>
                                <td style={{ fontWeight: 500 }}>{concept.name}</td>
                                <td>
                                    <span style={{
                                        fontSize: 12, fontWeight: 600, padding: '2px 8px', borderRadius: 12,
                                        ...(TYPE_COLORS[concept.type] ?? {}),
                                    }}>
                                        {TYPE_LABELS[concept.type] ?? concept.type}
                                    </span>
                                </td>
                                <td style={{ fontSize: 12, color: '#6b7280' }}>
                                    {CALC_LABELS[concept.calculation_method] ?? concept.calculation_method}
                                </td>
                                {hasAccounting && (
                                    <td style={{ fontFamily: 'monospace', fontSize: 12 }}>
                                        {concept.account ? `${concept.account.code} — ${concept.account.name}` : '—'}
                                    </td>
                                )}
                                <td style={{ fontSize: 12 }}>
                                    {concept.applies_to_all ? 'Todos' : `${concept.employees?.length ?? 0} empleado(s)`}
                                </td>
                                <td>
                                    <div style={{ display: 'flex', gap: 6 }}>
                                        <button
                                            type="button"
                                            className="btn secondary btn-icon"
                                            title="Editar"
                                            onClick={() => handleEdit(concept)}
                                        >
                                            <IconEdit />
                                        </button>
                                        <button
                                            type="button"
                                            className="btn danger btn-icon"
                                            title="Eliminar"
                                            onClick={() => handleDelete(concept.id)}
                                        >
                                            <IconTrash />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Legal concepts (read-only) */}
            {(legalConcepts ?? []).length > 0 && (
                <div className="card table-scroll">
                    <h2 style={{ margin: '0 0 12px', fontSize: 14, fontWeight: 600 }}>Conceptos legales</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th>Tipo</th>
                                {hasAccounting && <th>Cuenta gasto/ingreso</th>}
                                {hasAccounting && <th>Cuenta por pagar</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {legalConcepts.map((concept) => (
                                <tr key={concept.id}>
                                    <td style={{ fontWeight: 500 }}>{concept.name}</td>
                                    <td>
                                        <span style={{
                                            fontSize: 12, fontWeight: 600, padding: '2px 8px', borderRadius: 12,
                                            ...(TYPE_COLORS[concept.type] ?? {}),
                                        }}>
                                            {TYPE_LABELS[concept.type] ?? concept.type}
                                        </span>
                                    </td>
                                    {hasAccounting && (
                                        <td style={{ fontFamily: 'monospace', fontSize: 12 }}>
                                            {concept.account ? `${concept.account.code} — ${concept.account.name}` : <span className="muted">—</span>}
                                        </td>
                                    )}
                                    {hasAccounting && (
                                        <td style={{ fontFamily: 'monospace', fontSize: 12 }}>
                                            {concept.payable_account ? `${concept.payable_account.code} — ${concept.payable_account.name}` : <span className="muted">—</span>}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppLayout>
    );
}
