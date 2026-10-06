import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const emptyLine = () => ({
    id: null,
    account_id: '',
    description: '',
    debit: '',
    credit: '',
    cost_center_id: '',
});

export default function DiarioNuevo({ entry, accounts, costCenters, today, cgPackage }) {
    const isEdit = !!entry;

    const [headerData, setHeaderData] = useState({
        entry_date:  entry?.entry_date ? String(entry.entry_date).substring(0, 10) : (today ?? new Date().toISOString().slice(0, 10)),
        reference:   entry?.reference ?? '',
        description: entry?.description ?? '',
        notes:       entry?.notes ?? '',
    });

    const [lines, setLines] = useState(
        entry?.lines?.length > 0
            ? entry.lines.map((l) => ({
                id:             l.id || null,
                account_id:     String(l.account_id ?? ''),
                description:    l.description ?? '',
                debit:          l.debit ? String(l.debit) : '',
                credit:         l.credit ? String(l.credit) : '',
                cost_center_id: l.cost_center_id ? String(l.cost_center_id) : '',
            }))
            : [emptyLine(), emptyLine()]
    );

    const [action, setAction]       = useState('borrador');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors]         = useState({});

    const totalDebit  = lines.reduce((s, l) => s + (parseFloat(l.debit)  || 0), 0);
    const totalCredit = lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0);
    const balanced    = Math.abs(totalDebit - totalCredit) < 0.005;

    function updateHeader(field, value) {
        setHeaderData((prev) => ({ ...prev, [field]: value }));
    }

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
                if (field === 'debit'  && value !== '') updated.credit = '';
                if (field === 'credit' && value !== '') updated.debit  = '';
                return updated;
            })
        );
    }

    function applyPackage() {
        if (!cgPackage) return;
        const debitLine  = emptyLine();
        const creditLine = emptyLine();
        debitLine.account_id  = cgPackage.debit_account_id  ? String(cgPackage.debit_account_id)  : '';
        creditLine.account_id = cgPackage.credit_account_id ? String(cgPackage.credit_account_id) : '';
        if (cgPackage.cost_center_id) {
            debitLine.cost_center_id  = String(cgPackage.cost_center_id);
            creditLine.cost_center_id = String(cgPackage.cost_center_id);
        }
        setLines((prev) => [...prev, debitLine, creditLine]);
    }

    function handleSubmit(e) {
        e.preventDefault();

        const errs = {};
        if (!headerData.entry_date) errs.entry_date = 'La fecha es obligatoria.';
        if (!balanced) errs.lines = 'El total de débitos y créditos debe ser igual.';
        if (lines.some((l) => !l.account_id)) {
            errs.lines = (errs.lines ? errs.lines + ' ' : '') + 'Todas las líneas deben tener una cuenta.';
        }

        setErrors(errs);
        if (Object.keys(errs).length > 0) return;

        setSubmitting(true);

        const payload = {
            entry_date:  headerData.entry_date,
            reference:   headerData.reference,
            description: headerData.description,
            notes:       headerData.notes,
            action,
            lines: lines.map((l) => ({
                id:             l.id,
                account_id:     l.account_id,
                description:    l.description,
                debit:          l.debit  === '' ? 0 : parseFloat(l.debit),
                credit:         l.credit === '' ? 0 : parseFloat(l.credit),
                cost_center_id: l.cost_center_id || null,
            })),
        };

        if (isEdit) {
            router.put(route('admin.accounting.diario.update', entry.id), payload, {
                onError: (serverErrors) => { setErrors(serverErrors); setSubmitting(false); },
                onFinish: () => setSubmitting(false),
            });
        } else {
            router.post(route('admin.accounting.diario.store'), payload, {
                onError: (serverErrors) => { setErrors(serverErrors); setSubmitting(false); },
                onFinish: () => setSubmitting(false),
            });
        }
    }

    return (
        <AppLayout>
            <Head title={isEdit ? 'Editar asiento' : 'Nuevo asiento contable'} />

            <div className="top">
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <Link href={route('admin.accounting.diario.index')} className="btn secondary">← Volver</Link>
                    <h1>{isEdit ? 'Editar asiento' : 'Nuevo asiento contable'}</h1>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                {/* Encabezado */}
                <div className="card" style={{ marginBottom: 16 }}>
                    <h2 style={{ marginTop: 0, marginBottom: 16, fontSize: 15, fontWeight: 600 }}>Encabezado</h2>
                    <div style={{ display: 'grid', gridTemplateColumns: '160px 1fr 2fr', gap: 12, alignItems: 'start' }}>
                        <label style={{ margin: 0 }}>
                            Fecha <span style={{ color: '#ef4444' }}>*</span>
                            <input
                                type="date"
                                value={headerData.entry_date}
                                onChange={(e) => updateHeader('entry_date', e.target.value)}
                                required
                            />
                            {errors.entry_date && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.entry_date}</span>}
                        </label>
                        <label style={{ margin: 0 }}>
                            Referencia
                            <input
                                type="text"
                                value={headerData.reference}
                                onChange={(e) => updateHeader('reference', e.target.value)}
                                placeholder="Nro. de referencia"
                                maxLength={100}
                            />
                        </label>
                        <label style={{ margin: 0 }}>
                            Descripción
                            <input
                                type="text"
                                value={headerData.description}
                                onChange={(e) => updateHeader('description', e.target.value)}
                                placeholder="Descripción del asiento"
                                maxLength={255}
                            />
                        </label>
                    </div>
                    <label style={{ margin: '12px 0 0' }}>
                        Notas
                        <input
                            type="text"
                            value={headerData.notes}
                            onChange={(e) => updateHeader('notes', e.target.value)}
                            maxLength={500}
                        />
                    </label>
                </div>

                {/* Paquete CG */}
                {cgPackage && (
                    <div className="card" style={{ marginBottom: 16 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div>
                                <span style={{ fontWeight: 600, fontSize: 13 }}>Paquete: {cgPackage.code} — {cgPackage.name}</span>
                                {cgPackage.description && (
                                    <span style={{ fontSize: 12, color: '#6b7280', marginLeft: 8 }}>{cgPackage.description}</span>
                                )}
                            </div>
                            <button type="button" className="btn secondary" style={{ fontSize: 13 }} onClick={applyPackage}>
                                Agregar líneas del paquete
                            </button>
                        </div>
                    </div>
                )}

                {/* Líneas */}
                <div className="card" style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                        <h2 style={{ margin: 0, fontSize: 15, fontWeight: 600 }}>Líneas</h2>
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
                        <table style={{ minWidth: 860 }}>
                            <thead>
                                <tr>
                                    <th style={{ minWidth: 240 }}>Cuenta <span style={{ color: '#ef4444' }}>*</span></th>
                                    <th style={{ minWidth: 180 }}>Descripción</th>
                                    <th style={{ minWidth: 110, textAlign: 'right' }}>Débito</th>
                                    <th style={{ minWidth: 110, textAlign: 'right' }}>Crédito</th>
                                    <th style={{ minWidth: 150 }}>Centro de costo</th>
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
                                                {(accounts ?? []).map((a) => (
                                                    <option key={a.id} value={a.id}>
                                                        {a.code} — {a.name}
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
                                                {(costCenters ?? []).map((cc) => (
                                                    <option key={cc.id} value={cc.id}>{cc.code} — {cc.name}</option>
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
                                                    background: 'none', border: 'none', padding: '0 4px', lineHeight: 1, fontSize: 18,
                                                    color: lines.length <= 2 ? '#d1d5db' : '#ef4444',
                                                    cursor: lines.length <= 2 ? 'default' : 'pointer',
                                                }}
                                            >×</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Totales */}
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 24, marginTop: 16, paddingTop: 12, borderTop: '2px solid #e5e7eb' }}>
                        {[
                            { label: 'Total débitos', value: totalDebit },
                            { label: 'Total créditos', value: totalCredit },
                            { label: 'Diferencia', value: Math.abs(totalDebit - totalCredit), color: balanced ? '#16a34a' : '#dc2626' },
                        ].map(({ label, value, color }) => (
                            <div key={label} style={{ textAlign: 'right' }}>
                                <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>{label}</div>
                                <div style={{ fontSize: 17, fontWeight: 700, fontFamily: 'monospace', color: color ?? '#374151' }}>
                                    {fmt(value)}
                                </div>
                            </div>
                        ))}
                    </div>
                    {!balanced && totalDebit > 0 && totalCredit > 0 && (
                        <div style={{ marginTop: 10, background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: 6, padding: '8px 12px', color: '#dc2626', fontSize: 13, textAlign: 'right' }}>
                            La partida no cuadra. Los débitos y créditos deben ser iguales.
                        </div>
                    )}
                </div>

                {/* Acciones */}
                <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', alignItems: 'center' }}>
                    <Link href={route('admin.accounting.diario.index')} className="btn secondary">Cancelar</Link>
                    <button
                        type="submit"
                        className="btn secondary"
                        disabled={submitting}
                        onClick={() => setAction('borrador')}
                    >
                        {submitting ? 'Guardando…' : 'Guardar borrador'}
                    </button>
                    <button
                        type="submit"
                        className="btn"
                        disabled={submitting}
                        onClick={() => setAction('aprobado')}
                    >
                        {submitting ? 'Guardando…' : isEdit ? 'Actualizar y aprobar' : 'Registrar y aprobar'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
