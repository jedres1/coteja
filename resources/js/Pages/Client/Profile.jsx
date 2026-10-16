import { Head, useForm, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const DOC_TYPES = { '13': 'DUI', '36': 'NIT', '37': 'Pasaporte', '03': 'Carnet de residente', '02': 'Carnet de minoridad' };
const DTE_TYPES = { '01': 'Factura (01)', '03': 'Comprobante de crédito fiscal (03)', '14': 'Factura de sujeto excluido (14)' };

function InfoField({ label, value }) {
    return (
        <div>
            <div style={{ fontSize: 11, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '.04em', marginBottom: 2 }}>{label}</div>
            <div style={{ fontSize: 14 }}>{value || <span style={{ color: 'var(--muted)', fontStyle: 'italic' }}>—</span>}</div>
        </div>
    );
}

export default function ClientProfile({ customer }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    const pwForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function handlePassword(e) {
        e.preventDefault();
        pwForm.put(route('client.profile.password'), {
            onSuccess: () => pwForm.reset(),
        });
    }

    return (
        <AppLayout>
            <Head title="Mi perfil" />

            <div className="top">
                <h1>Mi perfil</h1>
            </div>

            {/* Datos de acceso */}
            <div className="card" style={{ marginBottom: 16 }}>
                <h3 style={{ margin: '0 0 16px' }}>Datos de acceso</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '12px 24px' }}>
                    <InfoField label="Nombre de usuario" value={user?.name} />
                    <InfoField label="Correo de acceso" value={user?.email} />
                </div>
            </div>

            {/* Datos del inquilino */}
            {customer && (
                <div className="card" style={{ marginBottom: 16 }}>
                    <h3 style={{ margin: '0 0 16px' }}>Datos del inquilino</h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '12px 24px' }}>
                        <InfoField label="Razón social" value={customer.name} />
                        <InfoField label="Nombre comercial" value={customer.trade_name} />
                        <InfoField label="Correo" value={customer.email} />
                        <InfoField label="Teléfono" value={customer.phone} />
                        <InfoField label="Tipo de documento" value={DOC_TYPES[customer.document_type] ?? customer.document_type} />
                        <InfoField label="Número de documento" value={customer.document_number} />
                        <InfoField label="NRC" value={customer.nrc} />
                        <InfoField label="Actividad económica" value={customer.business_activity} />
                        <InfoField label="Tipo DTE preferido" value={DTE_TYPES[customer.preferred_dte_type] ?? customer.preferred_dte_type} />
                        <InfoField label="Correo de facturación" value={customer.billing_email} />
                        <InfoField label="Teléfono de facturación" value={customer.billing_phone} />
                    </div>
                    {customer.address && (
                        <div style={{ marginTop: 12 }}>
                            <InfoField label="Dirección" value={customer.address} />
                        </div>
                    )}
                    <p style={{ margin: '16px 0 0', fontSize: 12, color: 'var(--muted)' }}>
                        Para actualizar estos datos contacte al administrador del sistema.
                    </p>
                </div>
            )}

            {/* Cambio de contraseña */}
            <div className="card">
                <h3 style={{ margin: '0 0 16px' }}>Cambiar contraseña</h3>
                <form onSubmit={handlePassword}>
                    <div className="form-grid">
                        <label className="full-width">
                            Contraseña actual
                            <input
                                type="password"
                                value={pwForm.data.current_password}
                                onChange={(e) => pwForm.setData('current_password', e.target.value)}
                                autoComplete="current-password"
                                required
                            />
                            {pwForm.errors.current_password && (
                                <p className="field-error">{pwForm.errors.current_password}</p>
                            )}
                        </label>
                        <label>
                            Nueva contraseña
                            <input
                                type="password"
                                value={pwForm.data.password}
                                onChange={(e) => pwForm.setData('password', e.target.value)}
                                autoComplete="new-password"
                                required
                            />
                            {pwForm.errors.password && (
                                <p className="field-error">{pwForm.errors.password}</p>
                            )}
                        </label>
                        <label>
                            Confirmar contraseña
                            <input
                                type="password"
                                value={pwForm.data.password_confirmation}
                                onChange={(e) => pwForm.setData('password_confirmation', e.target.value)}
                                autoComplete="new-password"
                                required
                            />
                            {pwForm.errors.password_confirmation && (
                                <p className="field-error">{pwForm.errors.password_confirmation}</p>
                            )}
                        </label>
                    </div>
                    <div className="form-actions" style={{ marginTop: 16 }}>
                        <button type="submit" className="btn" disabled={pwForm.processing}>
                            {pwForm.processing ? 'Guardando…' : 'Actualizar contraseña'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
