import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const TYPE_LABELS = { income: 'Ingreso', deduction: 'Deducción' };
const TYPE_COLORS = {
    income: { background: '#f0fdf4', color: '#15803d' },
    deduction: { background: '#fef2f2', color: '#dc2626' },
};

const emptyConcept = {
    name: '',
    type: 'income',
    is_taxable: '1',
    is_mandatory: '0',
    account_id: '',
};

function ConceptForm({ concept, accounts, onCancel }) {
    const isEditing = !!concept;
    const { data, setData, processing, errors, reset } = useForm(
        isEditing
            ? {
                name: concept.name || '',
                type: concept.type || 'income',
                is_taxable: concept.is_taxable ? '1' : '0',
                is_mandatory: concept.is_mandatory ? '1' : '0',
                account_id: concept.account_id || '',
            }
            : { ...emptyConcept }
    );

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            router.put(route('admin.payroll.concepts.update', concept.id), data, {
                onSuccess: onCancel,
            });
        } else {
            router.post(route('admin.payroll.concepts.store'), data, {
                onSuccess: () => { reset(); onCancel(); },
            });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr 2fr', gap: 12, alignItems: 'start' }}>
                <label style={{ margin: 0 }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        maxLength={100}
                        placeholder="Nombre del concepto"
                    />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Tipo <span style={{ color: '#ef4444' }}>*</span>
                    <select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        <option value="income">Ingreso</option>
                        <option value="deduction">Deducción</option>
                    </select>
                    {errors.type && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.type}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Gravable
                    <select value={data.is_taxable} onChange={(e) => setData('is_taxable', e.target.value)}>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                    {errors.is_taxable && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.is_taxable}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Obligatorio
                    <select value={data.is_mandatory} onChange={(e) => setData('is_mandatory', e.target.value)}>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                    {errors.is_mandatory && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.is_mandatory}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Cuenta contable
                    <select value={data.account_id} onChange={(e) => setData('account_id', e.target.value)}>
                        <option value="">— Sin cuenta —</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>{a.code} - {a.name}</option>
                        ))}
                    </select>
                    {errors.account_id && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.account_id}</span>}
                </label>
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

export default function Concepts({ concepts, accounts }) {
    const [showForm, setShowForm] = useState(false);
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

            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Gravable</th>
                            <th>Obligatorio</th>
                            <th>Cuenta contable</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!concepts || concepts.length === 0) && (
                            <tr>
                                <td colSpan={6} className="empty">Sin conceptos registrados</td>
                            </tr>
                        )}
                        {concepts && concepts.map((concept) => {
                            const typeStyle = TYPE_COLORS[concept.type] || {};
                            return (
                                <tr key={concept.id}>
                                    <td style={{ fontWeight: 500 }}>{concept.name}</td>
                                    <td>
                                        <span style={{
                                            fontSize: 12,
                                            fontWeight: 600,
                                            padding: '2px 8px',
                                            borderRadius: 12,
                                            ...typeStyle,
                                        }}>
                                            {TYPE_LABELS[concept.type] || concept.type}
                                        </span>
                                    </td>
                                    <td>
                                        <span style={{
                                            fontSize: 12,
                                            fontWeight: 600,
                                            padding: '2px 8px',
                                            borderRadius: 12,
                                            background: concept.is_taxable ? '#eff6ff' : '#f3f4f6',
                                            color: concept.is_taxable ? '#1d4ed8' : '#6b7280',
                                        }}>
                                            {concept.is_taxable ? 'Sí' : 'No'}
                                        </span>
                                    </td>
                                    <td>
                                        <span style={{
                                            fontSize: 12,
                                            fontWeight: 600,
                                            padding: '2px 8px',
                                            borderRadius: 12,
                                            background: concept.is_mandatory ? '#fefce8' : '#f3f4f6',
                                            color: concept.is_mandatory ? '#a16207' : '#6b7280',
                                        }}>
                                            {concept.is_mandatory ? 'Sí' : 'No'}
                                        </span>
                                    </td>
                                    <td style={{ fontFamily: 'monospace', fontSize: 13 }}>
                                        {concept.account
                                            ? `${concept.account.code} - ${concept.account.name}`
                                            : '—'}
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <button
                                                type="button"
                                                className="btn secondary"
                                                style={{ fontSize: 12, padding: '3px 10px' }}
                                                onClick={() => handleEdit(concept)}
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                                onClick={() => handleDelete(concept.id)}
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
