import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

export default function PayrollSettings({ settings, accounts }) {
    const [unlocked, setUnlocked]       = useState(false);
    const [showWarning, setShowWarning] = useState(false);

    const bankAccounts = accounts ?? [];
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        igss_employee_rate: settings?.igss_employee_rate ?? '',
        igss_employer_rate: settings?.igss_employer_rate ?? '',
        isr_rate: settings?.isr_rate ?? '',
        minimum_wage: settings?.minimum_wage ?? '',
        bank_account_id: settings?.bank_account_id ?? '',
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
                        {unlocked ? 'Configuración desbloqueada' : 'Configuración bloqueada'}
                    </p>
                    <p style={{ margin: '2px 0 0', fontSize: 12, color: '#6b7280' }}>
                        {unlocked
                            ? 'Edita solo si es necesario modificar estos parámetros.'
                            : 'Los parámetros permanecen protegidos para evitar cambios accidentales.'}
                    </p>
                </div>
                <button
                    type="button"
                    className={`btn${unlocked ? '' : ' secondary'}`}
                    style={{ whiteSpace: 'nowrap' }}
                    onClick={() => unlocked ? setUnlocked(false) : setShowWarning(true)}
                >
                    {unlocked ? '🔓 Bloquear' : '🔒 Desbloquear'}
                </button>
            </div>

            {showWarning && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9998, background: 'rgba(0,0,0,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <div style={{ background: '#fff', borderRadius: 10, padding: '28px 32px', maxWidth: 460, width: '90%', boxShadow: '0 8px 32px rgba(0,0,0,0.18)' }}>
                        <p style={{ margin: '0 0 8px', fontWeight: 700, fontSize: 16, color: '#dc2626' }}>Advertencia — edición de parámetros</p>
                        <p style={{ margin: '0 0 12px', fontSize: 13, color: '#374151' }}>
                            Cambiar estos parámetros puede afectar el funcionamiento del sistema.
                        </p>
                        <ul style={{ margin: '0 0 20px', paddingLeft: 20, fontSize: 12, color: '#7f1d1d' }}>
                            <li>Verifica los valores antes de guardar.</li>
                            <li>Los cambios tienen efecto inmediato.</li>
                        </ul>
                        <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                            <button type="button" className="btn secondary" onClick={() => setShowWarning(false)}>Cancelar</button>
                            <button type="button" className="btn" onClick={() => { setShowWarning(false); setUnlocked(true); }}>Aceptar</button>
                        </div>
                    </div>
                </div>
            )}

            <div className="card" style={{ maxWidth: 640 }}>
                <form onSubmit={handleSubmit} noValidate>
                    <h2 style={{ marginTop: 0, marginBottom: 20, fontSize: 15, fontWeight: 600 }}>
                        Tasas y parámetros
                    </h2>

                    <fieldset disabled={!unlocked} style={{ border: 'none', padding: 0, margin: 0 }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                            <label style={{ margin: 0 }}>
                                Tasa IGSS empleado (%)
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <input
                                        type="number"
                                        value={data.igss_employee_rate}
                                        onChange={(e) => setData('igss_employee_rate', e.target.value)}
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        placeholder="0.00"
                                        style={{ flex: 1 }}
                                    />
                                    <span style={{ color: '#6b7280', fontWeight: 600 }}>%</span>
                                </div>
                                {errors.igss_employee_rate && (
                                    <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.igss_employee_rate}</span>
                                )}
                            </label>

                            <label style={{ margin: 0 }}>
                                Tasa IGSS patronal (%)
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <input
                                        type="number"
                                        value={data.igss_employer_rate}
                                        onChange={(e) => setData('igss_employer_rate', e.target.value)}
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        placeholder="0.00"
                                        style={{ flex: 1 }}
                                    />
                                    <span style={{ color: '#6b7280', fontWeight: 600 }}>%</span>
                                </div>
                                {errors.igss_employer_rate && (
                                    <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.igss_employer_rate}</span>
                                )}
                            </label>

                            <label style={{ margin: 0 }}>
                                Tasa ISR (%)
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <input
                                        type="number"
                                        value={data.isr_rate}
                                        onChange={(e) => setData('isr_rate', e.target.value)}
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        placeholder="0.00"
                                        style={{ flex: 1 }}
                                    />
                                    <span style={{ color: '#6b7280', fontWeight: 600 }}>%</span>
                                </div>
                                {errors.isr_rate && (
                                    <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.isr_rate}</span>
                                )}
                            </label>

                            <label style={{ margin: 0 }}>
                                Salario mínimo
                                <input
                                    type="number"
                                    value={data.minimum_wage}
                                    onChange={(e) => setData('minimum_wage', e.target.value)}
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                />
                                {errors.minimum_wage && (
                                    <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.minimum_wage}</span>
                                )}
                            </label>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <label style={{ margin: 0 }}>
                                Cuenta bancaria de pago
                                <select
                                    value={data.bank_account_id}
                                    onChange={(e) => setData('bank_account_id', e.target.value)}
                                >
                                    <option value="">— Sin cuenta vinculada —</option>
                                    {bankAccounts.map((ba) => (
                                        <option key={ba.id} value={ba.id}>
                                            {ba.code} — {ba.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.bank_account_id && (
                                    <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.bank_account_id}</span>
                                )}
                            </label>
                        </div>
                    </fieldset>

                    <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginTop: 24 }}>
                        <button type="submit" className="btn" disabled={!unlocked || processing}>
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
