import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

const fmt = (x) => Number(x).toLocaleString('es-SV', { minimumFractionDigits: 2 });

const emptyEmployee = {
    name: '',
    email: '',
    position: '',
    salary: '',
    hire_date: '',
    department: '',
    is_active: '1',
};

function EmployeeForm({ employee, onCancel, accounts }) {
    const isEditing = !!employee;
    const { data, setData, processing, errors, reset } = useForm(
        isEditing
            ? {
                name: employee.name || '',
                email: employee.email || '',
                position: employee.position || '',
                salary: employee.salary || '',
                hire_date: employee.hire_date || '',
                department: employee.department || '',
                is_active: employee.is_active ? '1' : '0',
            }
            : { ...emptyEmployee }
    );

    function handleSubmit(e) {
        e.preventDefault();
        if (isEditing) {
            router.put(route('admin.payroll.employees.update', employee.id), data, {
                onSuccess: onCancel,
            });
        } else {
            router.post(route('admin.payroll.employees.store'), data, {
                onSuccess: () => { reset(); onCancel(); },
            });
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
                <label style={{ margin: 0 }}>
                    Nombre <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        maxLength={150}
                    />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Email
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        maxLength={150}
                    />
                    {errors.email && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.email}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Puesto
                    <input
                        type="text"
                        value={data.position}
                        onChange={(e) => setData('position', e.target.value)}
                        maxLength={100}
                    />
                    {errors.position && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.position}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Salario
                    <input
                        type="number"
                        value={data.salary}
                        onChange={(e) => setData('salary', e.target.value)}
                        min="0"
                        step="0.01"
                    />
                    {errors.salary && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.salary}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Departamento
                    <input
                        type="text"
                        value={data.department}
                        onChange={(e) => setData('department', e.target.value)}
                        maxLength={100}
                    />
                    {errors.department && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.department}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Fecha de ingreso
                    <input
                        type="date"
                        value={data.hire_date}
                        onChange={(e) => setData('hire_date', e.target.value)}
                    />
                    {errors.hire_date && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.hire_date}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Estado
                    <select value={data.is_active} onChange={(e) => setData('is_active', e.target.value)}>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    {errors.is_active && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.is_active}</span>}
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando...' : isEditing ? 'Actualizar' : 'Guardar empleado'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

function PeriodForm({ onCancel }) {
    const { data, setData, processing, errors, reset } = useForm({
        name: '',
        period_start: '',
        period_end: '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        router.post(route('admin.payroll.periods.store'), data, {
            onSuccess: () => { reset(); onCancel(); },
        });
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
                <label style={{ margin: 0 }}>
                    Nombre del período <span style={{ color: '#ef4444' }}>*</span>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        maxLength={100}
                        placeholder="Ej: Enero 2026"
                    />
                    {errors.name && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.name}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Fecha inicio
                    <input
                        type="date"
                        value={data.period_start}
                        onChange={(e) => setData('period_start', e.target.value)}
                    />
                    {errors.period_start && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.period_start}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Fecha fin
                    <input
                        type="date"
                        value={data.period_end}
                        onChange={(e) => setData('period_end', e.target.value)}
                    />
                    {errors.period_end && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.period_end}</span>}
                </label>
            </div>
            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando...' : 'Crear período'}
                </button>
                <button type="button" className="btn secondary" onClick={onCancel}>Cancelar</button>
            </div>
        </form>
    );
}

const STATUS_LABELS = {
    borrador: 'Borrador',
    aplicado: 'Aplicado',
};

const STATUS_COLORS = {
    borrador: { background: '#f3f4f6', color: '#374151' },
    aplicado: { background: '#f0fdf4', color: '#15803d' },
};

export default function PayrollIndex({ employees, periods, activeTab }) {
    const payrollPeriods = periods ?? [];
    const tab = activeTab || 'empleados';
    const [showEmployeeForm, setShowEmployeeForm] = useState(false);
    const [editingEmployee, setEditingEmployee] = useState(null);
    const [showPeriodForm, setShowPeriodForm] = useState(false);

    function switchTab(t) {
        router.get(route('admin.payroll.index', { tab: t }));
    }

    function handleDeleteEmployee(id) {
        if (!confirm('¿Eliminar este empleado?')) return;
        router.delete(route('admin.payroll.employees.destroy', id));
    }

    function handleEditEmployee(emp) {
        setEditingEmployee(emp);
        setShowEmployeeForm(true);
    }

    function closeEmployeeForm() {
        setShowEmployeeForm(false);
        setEditingEmployee(null);
    }

    return (
        <AppLayout>
            <Head title="Nómina" />

            <div className="top">
                <h1>Nómina</h1>
            </div>

            {/* Tabs */}
            <div style={{ display: 'flex', gap: 0, borderBottom: '2px solid #e5e7eb', marginBottom: 16 }}>
                {[
                    { key: 'empleados', label: 'Empleados' },
                    { key: 'nominas', label: 'Períodos de Nómina' },
                ].map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => switchTab(t.key)}
                        style={{
                            padding: '10px 20px',
                            border: 'none',
                            borderBottom: tab === t.key ? '3px solid #2563eb' : '3px solid transparent',
                            background: 'none',
                            cursor: 'pointer',
                            fontWeight: tab === t.key ? 700 : 400,
                            color: tab === t.key ? '#2563eb' : '#6b7280',
                            fontSize: 14,
                            marginBottom: -2,
                        }}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Tab: Empleados */}
            {tab === 'empleados' && (
                <>
                    {showEmployeeForm && (
                        <div className="card" style={{ marginBottom: 16 }}>
                            <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>
                                {editingEmployee ? 'Editar empleado' : 'Nuevo empleado'}
                            </h2>
                            <EmployeeForm
                                employee={editingEmployee}
                                onCancel={closeEmployeeForm}
                            />
                        </div>
                    )}

                    {!showEmployeeForm && (
                        <div style={{ marginBottom: 12 }}>
                            <button
                                type="button"
                                className="btn"
                                onClick={() => { setEditingEmployee(null); setShowEmployeeForm(true); }}
                            >
                                + Nuevo empleado
                            </button>
                        </div>
                    )}

                    <div className="card table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Puesto</th>
                                    <th style={{ textAlign: 'right' }}>Salario</th>
                                    <th>Departamento</th>
                                    <th>Fecha ingreso</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(!employees || employees.length === 0) && (
                                    <tr>
                                        <td colSpan={8} className="empty">Sin empleados registrados</td>
                                    </tr>
                                )}
                                {employees && employees.map((emp) => (
                                    <tr key={emp.id}>
                                        <td style={{ fontWeight: 500 }}>{emp.name}</td>
                                        <td style={{ color: '#6b7280', fontSize: 13 }}>{emp.email || '—'}</td>
                                        <td>{emp.position || '—'}</td>
                                        <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                            {emp.salary ? fmt(emp.salary) : '—'}
                                        </td>
                                        <td>{emp.department || '—'}</td>
                                        <td>{emp.hire_date || '—'}</td>
                                        <td>
                                            <span style={{
                                                fontSize: 12,
                                                fontWeight: 600,
                                                padding: '2px 8px',
                                                borderRadius: 12,
                                                background: emp.is_active ? '#f0fdf4' : '#f3f4f6',
                                                color: emp.is_active ? '#15803d' : '#6b7280',
                                            }}>
                                                {emp.is_active ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', gap: 6 }}>
                                                <button
                                                    type="button"
                                                    className="btn secondary"
                                                    style={{ fontSize: 12, padding: '3px 10px' }}
                                                    onClick={() => handleEditEmployee(emp)}
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    style={{ fontSize: 12, padding: '3px 10px', background: '#fef2f2', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: 6, cursor: 'pointer' }}
                                                    onClick={() => handleDeleteEmployee(emp.id)}
                                                >
                                                    Eliminar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            {/* Tab: Nóminas */}
            {tab === 'nominas' && (
                <>
                    {showPeriodForm && (
                        <div className="card" style={{ marginBottom: 16 }}>
                            <h2 style={{ marginTop: 0, marginBottom: 14, fontSize: 15, fontWeight: 600 }}>Nuevo período de nómina</h2>
                            <PeriodForm onCancel={() => setShowPeriodForm(false)} />
                        </div>
                    )}

                    {!showPeriodForm && (
                        <div style={{ marginBottom: 12 }}>
                            <button
                                type="button"
                                className="btn"
                                onClick={() => setShowPeriodForm(true)}
                            >
                                + Nuevo período
                            </button>
                        </div>
                    )}

                    <div className="card table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th>Fecha inicio</th>
                                    <th>Fecha fin</th>
                                    <th>Estado</th>
                                    <th style={{ textAlign: 'right' }}>Bruto total</th>
                                    <th style={{ textAlign: 'right' }}>Neto total</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payrollPeriods.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="empty">Sin períodos de nómina</td>
                                    </tr>
                                )}
                                {payrollPeriods.map((period) => {
                                    const statusStyle = STATUS_COLORS[period.status] || STATUS_COLORS.draft;
                                    return (
                                        <tr key={period.id}>
                                            <td style={{ fontWeight: 500 }}>{period.name}</td>
                                            <td>{period.period_start || '—'}</td>
                                            <td>{period.period_end || '—'}</td>
                                            <td>
                                                <span style={{
                                                    fontSize: 12,
                                                    fontWeight: 600,
                                                    padding: '2px 8px',
                                                    borderRadius: 12,
                                                    ...statusStyle,
                                                }}>
                                                    {STATUS_LABELS[period.status] || period.status}
                                                </span>
                                            </td>
                                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                                {period.total_gross != null ? fmt(period.total_gross) : '—'}
                                            </td>
                                            <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>
                                                {period.total_net != null ? fmt(period.total_net) : '—'}
                                            </td>
                                            <td>
                                                <a
                                                    href={route('admin.payroll.periods.show', period.id)}
                                                    className="btn secondary"
                                                    style={{ fontSize: 12, padding: '3px 10px', textDecoration: 'none', display: 'inline-block' }}
                                                >
                                                    Ver
                                                </a>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                </>
            )}
        </AppLayout>
    );
}
