import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

export default function PayrollSettings({ settings, accounts }) {
    const { auth, billingTenant } = usePage().props;
    const tenantModules = billingTenant?.modules ?? null;
    const hasPayroll = tenantModules
        ? tenantModules.includes('payroll')
        : (auth?.user?.modules?.payroll ?? false);

    const [unlocked, setUnlocked]       = useState(false);
    const [showWarning, setShowWarning] = useState(false);

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        account_salaries_payable_id: settings?.account_salaries_payable_id ?? '',
        isss_salary_cap:             settings?.isss_salary_cap ?? '',
        isss_employee_rate:          settings?.isss_employee_rate ?? '',
        isss_employer_rate:          settings?.isss_employer_rate ?? '',
        afp_employee_rate:           settings?.afp_employee_rate ?? '',
        afp_employer_rate:           settings?.afp_employer_rate ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.payroll.settings.save'), { onSuccess: () => setUnlocked(false) });
    }

    return (
        <AppLayout>
            <Head title="Configuración de nómina" />

            <div className="top">
                <h1>Configuración de nómina</h1>
            </div>

            <div className="card" style={{ marginBottom: 16, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16 }}>
                <div>
                    <p style={{ margin: 0, fontWeight: 600 }}>
                        {!hasPayroll
                            ? 'Sin acceso al módulo de Nómina'
                            : unlocked ? 'Configuración desbloqueada' : 'Configuración bloqueada'}
                    </p>
                    <p style={{ margin: '2px 0 0', fontSize: 12, color: '#6b7280' }}>
                        {!hasPayroll
                            ? 'Estos parámetros requieren acceso al módulo de Nómina.'
                            : unlocked
                                ? 'Edita solo si es necesario modificar estos parámetros.'
                                : 'Los parámetros permanecen protegidos para evitar cambios accidentales.'}
                    </p>
                </div>
                {hasPayroll ? (
                    <button
                        type="button"
                        className={`btn${unlocked ? '' : ' secondary'}`}
                        style={{ whiteSpace: 'nowrap' }}
                        onClick={() => unlocked ? setUnlocked(false) : setShowWarning(true)}
                    >
                        {unlocked ? 'Bloquear' : 'Desbloquear'}
                    </button>
                ) : (
                    <span style={{ whiteSpace: 'nowrap', fontSize: 13, color: '#9ca3af' }}>Bloqueado</span>
                )}
            </div>

            {hasPayroll && showWarning && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9998, background: 'rgba(0,0,0,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <div style={{ background: '#fff', borderRadius: 10, padding: '28px 32px', maxWidth: 460, width: '90%', boxShadow: '0 8px 32px rgba(0,0,0,0.18)' }}>
                        <p style={{ margin: '0 0 8px', fontWeight: 700, fontSize: 16, color: '#dc2626' }}>Advertencia — edición de parámetros</p>
                        <p style={{ margin: '0 0 12px', fontSize: 13, color: '#374151' }}>
                            Cambiar estos parámetros puede afectar el cálculo de nóminas futuras.
                        </p>
                        <ul style={{ margin: '0 0 20px', paddingLeft: 20, fontSize: 12, color: '#7f1d1d' }}>
                            <li>Verifica los valores antes de guardar.</li>
                            <li>Los cambios tienen efecto inmediato en nuevos períodos.</li>
                        </ul>
                        <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                            <button type="button" className="btn secondary" onClick={() => setShowWarning(false)}>Cancelar</button>
                            <button type="button" className="btn" onClick={() => { setShowWarning(false); setUnlocked(true); }}>Aceptar</button>
                        </div>
                    </div>
                </div>
            )}

            <div className="card" style={{ maxWidth: 680 }}>
                <form onSubmit={handleSubmit} noValidate>
                    <h2 style={{ marginTop: 0, marginBottom: 20, fontSize: 15, fontWeight: 600 }}>
                        Tasas y parámetros legales
                    </h2>

                    <div style={{ position: 'relative' }}>
                        <fieldset style={{ border: 'none', padding: 0, margin: 0 }}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>

                                {/* ISSS */}
                                <label style={{ margin: 0 }}>
                                    Tope salarial ISSS
                                    <input
                                        type="number"
                                        value={data.isss_salary_cap}
                                        onChange={(e) => setData('isss_salary_cap', e.target.value)}
                                        min="0"
                                        step="0.01"
                                        placeholder="1000.00"
                                    />
                                    {errors.isss_salary_cap && (
                                        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.isss_salary_cap}</span>
                                    )}
                                </label>

                                <div />

                                <label style={{ margin: 0 }}>
                                    Tasa ISSS empleado (0–1)
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        <input
                                            type="number"
                                            value={data.isss_employee_rate}
                                            onChange={(e) => setData('isss_employee_rate', e.target.value)}
                                            min="0"
                                            max="1"
                                            step="0.0001"
                                            placeholder="0.0300"
                                            style={{ flex: 1 }}
                                        />
                                    </div>
                                    {errors.isss_employee_rate && (
                                        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.isss_employee_rate}</span>
                                    )}
                                </label>

                                <label style={{ margin: 0 }}>
                                    Tasa ISSS patronal (0–1)
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        <input
                                            type="number"
                                            value={data.isss_employer_rate}
                                            onChange={(e) => setData('isss_employer_rate', e.target.value)}
                                            min="0"
                                            max="1"
                                            step="0.0001"
                                            placeholder="0.0750"
                                            style={{ flex: 1 }}
                                        />
                                    </div>
                                    {errors.isss_employer_rate && (
                                        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.isss_employer_rate}</span>
                                    )}
                                </label>

                                {/* AFP */}
                                <label style={{ margin: 0 }}>
                                    Tasa AFP empleado (0–1)
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        <input
                                            type="number"
                                            value={data.afp_employee_rate}
                                            onChange={(e) => setData('afp_employee_rate', e.target.value)}
                                            min="0"
                                            max="1"
                                            step="0.0001"
                                            placeholder="0.0725"
                                            style={{ flex: 1 }}
                                        />
                                    </div>
                                    {errors.afp_employee_rate && (
                                        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.afp_employee_rate}</span>
                                    )}
                                </label>

                                <label style={{ margin: 0 }}>
                                    Tasa AFP patronal (0–1)
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        <input
                                            type="number"
                                            value={data.afp_employer_rate}
                                            onChange={(e) => setData('afp_employer_rate', e.target.value)}
                                            min="0"
                                            max="1"
                                            step="0.0001"
                                            placeholder="0.0875"
                                            style={{ flex: 1 }}
                                        />
                                    </div>
                                    {errors.afp_employer_rate && (
                                        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.afp_employer_rate}</span>
                                    )}
                                </label>

                                {/* Cuenta contable */}
                                <label style={{ margin: 0, gridColumn: '1 / -1' }}>
                                    Cuenta sueldos por pagar <span style={{ color: '#ef4444' }}>*</span>
                                    <select
                                        value={data.account_salaries_payable_id}
                                        onChange={(e) => setData('account_salaries_payable_id', e.target.value)}
                                    >
                                        <option value="">— Seleccionar cuenta —</option>
                                        {(accounts ?? []).map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.code} — {a.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.account_salaries_payable_id && (
                                        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.account_salaries_payable_id}</span>
                                    )}
                                </label>
                            </div>
                        </fieldset>

                        {(!hasPayroll || !unlocked) && (
                            <div style={{ position: 'absolute', inset: 0, background: 'rgba(248,250,252,0.55)', zIndex: 1, pointerEvents: 'all', cursor: 'not-allowed', borderRadius: 4 }} />
                        )}
                    </div>

                    <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginTop: 24 }}>
                        <button type="submit" className="btn" disabled={!hasPayroll || !unlocked || processing}>
                            {processing ? 'Guardando...' : 'Guardar configuración'}
                        </button>
                        {recentlySuccessful && (
                            <span style={{ color: '#16a34a', fontSize: 13 }}>Configuración guardada.</span>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
