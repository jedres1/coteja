import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (x) => Number(x ?? 0).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const STATUS_LABELS = { borrador: 'Borrador', aplicado: 'Aplicado' };
const STATUS_COLORS = {
    borrador: { background: '#f3f4f6', color: '#374151' },
    aplicado: { background: '#f0fdf4', color: '#15803d' },
};

function LineEditForm({ line, onCancel }) {
    const { data, setData, processing, errors } = useForm({
        overtime_hours: line.overtime_hours ?? '',
        overtime_amount: line.overtime_amount ?? '',
        bonuses: line.bonuses ?? '',
        notes: line.notes ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        router.put(route('admin.payroll.lines.update', line.id), data, {
            onSuccess: onCancel,
        });
    }

    return (
        <form onSubmit={handleSubmit} style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
            <label style={{ margin: 0, fontSize: 13 }}>
                Horas extra
                <input
                    type="number"
                    value={data.overtime_hours}
                    onChange={(e) => setData('overtime_hours', e.target.value)}
                    min="0"
                    step="0.5"
                    style={{ width: 90 }}
                />
                {errors.overtime_hours && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.overtime_hours}</span>}
            </label>
            <label style={{ margin: 0, fontSize: 13 }}>
                Monto extra
                <input
                    type="number"
                    value={data.overtime_amount}
                    onChange={(e) => setData('overtime_amount', e.target.value)}
                    min="0"
                    step="0.01"
                    style={{ width: 100 }}
                />
                {errors.overtime_amount && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.overtime_amount}</span>}
            </label>
            <label style={{ margin: 0, fontSize: 13 }}>
                Bonificaciones
                <input
                    type="number"
                    value={data.bonuses}
                    onChange={(e) => setData('bonuses', e.target.value)}
                    min="0"
                    step="0.01"
                    style={{ width: 100 }}
                />
                {errors.bonuses && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.bonuses}</span>}
            </label>
            <label style={{ margin: 0, fontSize: 13, flex: 1, minWidth: 160 }}>
                Notas
                <input
                    type="text"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    maxLength={255}
                />
            </label>
            <div style={{ display: 'flex', gap: 6 }}>
                <button type="submit" className="btn" disabled={processing} style={{ fontSize: 12 }}>
                    {processing ? '...' : 'Guardar'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel} style={{ fontSize: 12 }}>
                    Cancelar
                </button>
            </div>
        </form>
    );
}

export default function Period({ period, settings }) {
    const [editingLineId, setEditingLineId] = useState(null);
    const isApplied = period.status === 'aplicado';
    const statusStyle = STATUS_COLORS[period.status] || STATUS_COLORS.borrador;

    function handleApply() {
        if (!confirm('¿Aplicar esta nómina? Se generará la partida contable y no podrá editarse.')) return;
        router.post(route('admin.payroll.periods.apply', period.id));
    }

    function handleDelete() {
        if (!confirm('¿Eliminar este período de nómina?')) return;
        router.delete(route('admin.payroll.periods.destroy', period.id));
    }

    const totalGross = period.lines?.reduce((s, l) => s + Number(l.gross_salary ?? 0), 0) ?? 0;
    const totalNet = period.lines?.reduce((s, l) => s + Number(l.net_salary ?? 0), 0) ?? 0;
    const totalCost = period.lines?.reduce((s, l) => s + Number(l.total_employer_cost ?? 0), 0) ?? 0;

    return (
        <AppLayout>
            <Head title={`Nómina: ${period.name}`} />

            <div className="top">
                <h1>Nómina: {period.name}</h1>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    <a
                        href={route('admin.payroll.periods.export', period.id)}
                        className="btn secondary"
                        style={{ textDecoration: 'none' }}
                    >
                        Exportar
                    </a>
                    {!isApplied && (
                        <>
                            <button type="button" className="btn" onClick={handleApply}>
                                Aplicar nómina
                            </button>
                            <button
                                type="button"
                                style={{ fontSize: 13, padding: '6px 14px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                onClick={handleDelete}
                            >
                                Eliminar
                            </button>
                        </>
                    )}
                </div>
            </div>

            {/* Period summary card */}
            <div className="card" style={{ marginBottom: 16, display: 'flex', gap: 32, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                <div>
                    <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Período</div>
                    <div style={{ fontWeight: 600 }}>{period.period_start} → {period.period_end}</div>
                </div>
                <div>
                    <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Estado</div>
                    <span style={{ fontSize: 12, fontWeight: 600, padding: '2px 10px', borderRadius: 12, ...statusStyle }}>
                        {STATUS_LABELS[period.status] ?? period.status}
                    </span>
                </div>
                <div>
                    <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Empleados</div>
                    <div style={{ fontWeight: 600 }}>{period.lines?.length ?? 0}</div>
                </div>
                <div>
                    <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Salario bruto total</div>
                    <div style={{ fontWeight: 600, fontFamily: 'monospace' }}>{fmt(totalGross)}</div>
                </div>
                <div>
                    <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Salario neto total</div>
                    <div style={{ fontWeight: 600, fontFamily: 'monospace' }}>{fmt(totalNet)}</div>
                </div>
                <div>
                    <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Costo patronal total</div>
                    <div style={{ fontWeight: 600, fontFamily: 'monospace' }}>{fmt(totalCost)}</div>
                </div>
                {period.journalEntry && (
                    <div>
                        <div style={{ fontSize: 12, color: '#6b7280', marginBottom: 2 }}>Partida contable</div>
                        <div style={{ fontWeight: 600, fontFamily: 'monospace' }}>{period.journalEntry.entry_number}</div>
                    </div>
                )}
            </div>

            {/* Lines table */}
            <div className="card table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th style={{ textAlign: 'right' }}>Salario base</th>
                            <th style={{ textAlign: 'right' }}>Extra</th>
                            <th style={{ textAlign: 'right' }}>Bonos</th>
                            <th style={{ textAlign: 'right' }}>Bruto</th>
                            <th style={{ textAlign: 'right' }}>IGSS emp.</th>
                            <th style={{ textAlign: 'right' }}>ISR</th>
                            <th style={{ textAlign: 'right' }}>Deducciones</th>
                            <th style={{ textAlign: 'right' }}>Neto</th>
                            {!isApplied && <th>Acción</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {(!period.lines || period.lines.length === 0) && (
                            <tr>
                                <td colSpan={isApplied ? 9 : 10} className="empty">Sin líneas en este período</td>
                            </tr>
                        )}
                        {period.lines?.map((line) => (
                            <>
                                <tr key={line.id}>
                                    <td style={{ fontWeight: 500 }}>{line.employee?.name ?? '—'}</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{fmt(line.salary)}</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                        {Number(line.overtime_amount) > 0 ? fmt(line.overtime_amount) : '—'}
                                    </td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                        {Number(line.bonuses) > 0 ? fmt(line.bonuses) : '—'}
                                    </td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 600 }}>{fmt(line.gross_salary)}</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', color: '#dc2626' }}>({fmt(line.isss_employee)})</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', color: '#dc2626' }}>({fmt(line.isr)})</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', color: '#dc2626' }}>({fmt(line.total_deductions)})</td>
                                    <td style={{ textAlign: 'right', fontFamily: 'monospace', fontWeight: 700, color: '#15803d' }}>{fmt(line.net_salary)}</td>
                                    {!isApplied && (
                                        <td>
                                            {editingLineId !== line.id ? (
                                                <button
                                                    type="button"
                                                    className="btn secondary"
                                                    style={{ fontSize: 12, padding: '3px 10px' }}
                                                    onClick={() => setEditingLineId(line.id)}
                                                >
                                                    Editar
                                                </button>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="btn secondary"
                                                    style={{ fontSize: 12, padding: '3px 10px' }}
                                                    onClick={() => setEditingLineId(null)}
                                                >
                                                    Cerrar
                                                </button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                                {editingLineId === line.id && (
                                    <tr key={`edit-${line.id}`}>
                                        <td colSpan={10} style={{ background: '#f9fafb', padding: '12px 16px' }}>
                                            <LineEditForm line={line} onCancel={() => setEditingLineId(null)} />
                                        </td>
                                    </tr>
                                )}
                            </>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
