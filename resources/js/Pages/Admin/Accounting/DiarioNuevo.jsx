import { useState } from 'react';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const emptyLine = () => ({
    id: null,
    account_id: '',
    description: '',
    debit: '',
    credit: '',
    cost_center_id: '',
});

export default function DiarioNuevo({ entry, accounts, costCenters, packages, actionUrl, isEdit }) {
    const [lines, setLines] = useState(
        entry?.lines?.map((l) => ({
            id: l.id || null,
            account_id: l.account_id,
            description: l.description || '',
            debit: l.debit || '',
            credit: l.credit || '',
            cost_center_id: l.cost_center_id || '',
        })) || [emptyLine(), emptyLine()]
    );

    const [selectedPackage, setSelectedPackage] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    const { data, setData } = useForm({
        date: entry?.date || new Date().toISOString().slice(0, 10),
        reference: entry?.reference || '',
        description: entry?.description || '',
    });

    const totalDebit = lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0);
    const totalCredit = lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0);
    const balanced = Math.abs(totalDebit - totalCredit) < 0.005;

    // --- Line operations ---
    function addLine() {
        setLines((prev) => [...prev, emptyLine()]);
    }

    function removeLine(idx) {
        if (lines.length <= 2) return;
        setLines((prev) => prev.filter((_, i) => i !== idx));
    }

    function updateLine(idx, field, value) {
        setLines((prev) =>
            prev.map((l, i) => {
                if (i !== idx) return l;
                const updated = { ...l, [field]: value };
                // Mutual exclusion: filling debit clears credit and vice versa
                if (field === 'debit' && value !== '') updated.credit = '';
                if (field === 'credit' && value !== '') updated.debit = '';
                return updated;
            })
        );
    }

    // --- Package loader ---
    function applyPackage() {
        if (!selectedPackage) return;
        const pkg = packages.find((p) => String(p.id) === String(selectedPackage));
        if (!pkg || !pkg.accounts || pkg.accounts.length === 0) return;

        const newLines = pkg.accounts.flatMap((pa) => [
            {
                id: null,
                account_id: pa.debit_account_id || '',
                description: pkg.name,
                debit: '',
                credit: '',
                cost_center_id: '',
            },
            {
                id: null,
                account_id: pa.credit_account_id || '',
                description: pkg.name,
                debit: '',
                credit: '',
                cost_center_id: '',
            },
        ]);

        setLines((prev) => [...prev, ...newLines]);
        setSelectedPackage('');
    }

    // --- Submit ---
    function handleSubmit(e) {
        e.preventDefault();

        const errs = {};
        if (!data.date) errs.date = 'La fecha es obligatoria.';
        if (!balanced) errs.lines = 'El total de débitos y créditos debe ser igual.';

        const hasAccountErrors = lines.some((l) => !l.account_id);
        if (hasAccountErrors) errs.lines = (errs.lines ? errs.lines + ' ' : '') + 'Todas las líneas deben tener una cuenta.';

        setErrors(errs);
        if (Object.keys(errs).length > 0) return;

        setSubmitting(true);

        const payload = {
            date: data.date,
            reference: data.reference,
            description: data.description,
            lines: lines.map((l) => ({
                id: l.id,
                account_id: l.account_id,
                description: l.description,
                debit: l.debit === '' ? null : parseFloat(l.debit),
                credit: l.credit === '' ? null : parseFloat(l.credit),
                cost_center_id: l.cost_center_id || null,
            })),
        };

        const method = isEdit ? router.put : router.post;
        method(actionUrl, payload, {
            onSuccess: () => router.visit(route('admin.accounting.diario.index')),
            onError: (serverErrors) => {
                setErrors(serverErrors);
                setSubmitting(false);
            },
            onFinish: () => setSubmitting(false),
        });
    }

    return (
        <AppLayout>
            <Head title={isEdit ? 'Editar partida' : 'Nueva partida'} />

            <div className="top">
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <Link href={route('admin.accounting.diario.index')} className="btn secondary">
                        ← Volver
                    </Link>
                    <h1>{isEdit ? 'Editar partida' : 'Nueva partida'}</h1>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                {/* Header section */}
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 16, fontSize: 15, fontWeight: 600 }}>Encabezado</h2>
                    <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr 2fr', gap: 12, alignItems: 'start' }}>
                        <label style={{ margin: 0 }}>
                            Fecha <span style={{ color: '#ef4444' }}>*</span>
                            <input
                                type="date"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                required
                            />
                            {errors.date && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.date}</span>}
                        </label>
                        <label style={{ margin: 0 }}>
                            Referencia
                            <input
                                type="text"
                                value={data.reference}
                                onChange={(e) => setData('reference', e.target.value)}
                                placeholder="Nro. de referencia"
                                maxLength={100}
                            />
                        </label>
                        <label style={{ margin: 0 }}>
                            Descripción
                            <input
                                type="text"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Descripción de la partida"
                                maxLength={255}
                            />
                        </label>
                    </div>
                </div>

                {/* Package loader */}
                {packages && packages.length > 0 && (
                    <div className="card" style={{ marginBottom: 16 }}>
                        <h2 style={{ marginTop: 0, marginBottom: 12, fontSize: 15, fontWeight: 600 }}>Aplicar paquete contable</h2>
                        <div style={{ display: 'flex', gap: 10, alignItems: 'flex-end' }}>
                            <label style={{ margin: 0, flex: '0 1 320px' }}>
                                Paquete
                                <select value={selectedPackage} onChange={(e) => setSelectedPackage(e.target.value)}>
                                    <option value="">— Seleccionar paquete —</option>
                                    {packages.map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                            </label>
                            <button
                                type="button"
                                className="btn secondary"
                                onClick={applyPackage}
                                disabled={!selectedPackage}
                            >
                                Aplicar paquete
                            </button>
                        </div>
                    </div>
                )}

                {/* Lines table */}
                <div className="card" style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                        <h2 style={{ margin: 0, fontSize: 15, fontWeight: 600 }}>Líneas de la partida</h2>
                        <button type="button" className="btn secondary" onClick={addLine} style={{ fontSize: 13 }}>
                            + Agregar línea
                        </button>
                    </div>

                    {errors.lines && (
                        <div style={{ background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: 6, padding: '8px 12px', marginBottom: 12, color: '#dc2626', fontSize: 13 }}>
                            {errors.lines}
                        </div>
                    )}

                    <div className="table-scroll">
                        <table style={{ minWidth: 900 }}>
                            <thead>
                                <tr>
                                    <th style={{ minWidth: 240 }}>Cuenta <span style={{ color: '#ef4444' }}>*</span></th>
                                    <th style={{ minWidth: 200 }}>Descripción</th>
                                    <th style={{ minWidth: 120, textAlign: 'right' }}>Débito</th>
                                    <th style={{ minWidth: 120, textAlign: 'right' }}>Crédito</th>
                                    <th style={{ minWidth: 160 }}>Centro de costo</th>
                                    <th style={{ width: 40 }}></th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line, idx) => (
                                    <tr key={idx}>
                                        <td>
                                            <select
                                                value={line.account_id}
                                                onChange={(e) => updateLine(idx, 'account_id', e.target.value)}
                                                style={{ width: '100%', margin: 0 }}
                                            >
                                                <option value="">— Cuenta —</option>
                                                {accounts.map((a) => (
                                                    <option key={a.id} value={a.id}>
                                                        {a.code} - {a.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                value={line.description}
                                                onChange={(e) => updateLine(idx, 'description', e.target.value)}
                                                placeholder="Detalle"
                                                style={{ width: '100%', margin: 0 }}
                                                maxLength={255}
                                            />
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                value={line.debit}
                                                onChange={(e) => updateLine(idx, 'debit', e.target.value)}
                                                placeholder="0.00"
                                                min="0"
                                                step="0.01"
                                                style={{ width: '100%', margin: 0, textAlign: 'right' }}
                                            />
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                value={line.credit}
                                                onChange={(e) => updateLine(idx, 'credit', e.target.value)}
                                                placeholder="0.00"
                                                min="0"
                                                step="0.01"
                                                style={{ width: '100%', margin: 0, textAlign: 'right' }}
                                            />
                                        </td>
                                        <td>
                                            <select
                                                value={line.cost_center_id}
                                                onChange={(e) => updateLine(idx, 'cost_center_id', e.target.value)}
                                                style={{ width: '100%', margin: 0 }}
                                            >
                                                <option value="">— Sin centro —</option>
                                                {costCenters.map((cc) => (
                                                    <option key={cc.id} value={cc.id}>{cc.name}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td style={{ textAlign: 'center' }}>
                                            <button
                                                type="button"
                                                onClick={() => removeLine(idx)}
                                                disabled={lines.length <= 2}
                                                title="Eliminar línea"
                                                style={{
                                                    background: 'none',
                                                    border: 'none',
                                                    color: lines.length <= 2 ? '#d1d5db' : '#ef4444',
                                                    cursor: lines.length <= 2 ? 'default' : 'pointer',
                                                    fontSize: 18,
                                                    padding: '0 4px',
                                                    lineHeight: 1,
                                                }}
                                            >
                                                ×
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Totals footer */}
                    <div style={{
                        display: 'flex',
                        justifyContent: 'flex-end',
                        gap: 24,
                        marginTop: 16,
                        paddingTop: 12,
                        borderTop: '2px solid #e5e7eb',
                    }}>
                        <div style={{ textAlign: 'right' }}>
                            <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Total débitos</div>
                            <div style={{ fontSize: 18, fontWeight: 700, fontFamily: 'monospace' }}>
                                {fmt(totalDebit)}
                            </div>
                        </div>
                        <div style={{ textAlign: 'right' }}>
                            <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Total créditos</div>
                            <div style={{ fontSize: 18, fontWeight: 700, fontFamily: 'monospace' }}>
                                {fmt(totalCredit)}
                            </div>
                        </div>
                        <div style={{ textAlign: 'right' }}>
                            <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Diferencia</div>
                            <div style={{
                                fontSize: 18,
                                fontWeight: 700,
                                fontFamily: 'monospace',
                                color: balanced ? '#16a34a' : '#dc2626',
                            }}>
                                {fmt(Math.abs(totalDebit - totalCredit))}
                            </div>
                        </div>
                    </div>

                    {!balanced && totalDebit > 0 && totalCredit > 0 && (
                        <div style={{
                            marginTop: 10,
                            background: '#fef2f2',
                            border: '1px solid #fca5a5',
                            borderRadius: 6,
                            padding: '8px 12px',
                            color: '#dc2626',
                            fontSize: 13,
                            textAlign: 'right',
                        }}>
                            La partida no cuadra. Los débitos y créditos deben ser iguales.
                        </div>
                    )}
                </div>

                {/* Actions */}
                <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                    <Link href={route('admin.accounting.diario.index')} className="btn secondary">
                        Cancelar
                    </Link>
                    <button type="submit" className="btn" disabled={submitting}>
                        {submitting ? 'Guardando...' : isEdit ? 'Actualizar partida' : 'Registrar partida'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
