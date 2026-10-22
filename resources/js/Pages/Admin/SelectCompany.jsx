import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';

export default function SelectCompany() {
    const { availableCompanies = [], auth } = usePage().props;
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState(null);

    function selectCompany(companyId) {
        setSelected(companyId);
        setLoading(true);
        router.post(
            route('admin.empresa.cambiar'),
            { company_id: companyId },
            { onFinish: () => setLoading(false) }
        );
    }

    function handleLogout(e) {
        e.preventDefault();
        router.post(route('logout'));
    }

    return (
        <div style={{
            minHeight: '100vh',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: '#f3f4f6',
            padding: '24px 16px',
        }}>
            <Head title="Seleccionar empresa" />

            <div style={{
                background: '#fff',
                borderRadius: 14,
                boxShadow: '0 4px 32px rgba(0,0,0,0.10)',
                padding: '40px 48px',
                width: '100%',
                maxWidth: 460,
            }}>
                <div style={{ marginBottom: 28, textAlign: 'center' }}>
                    <div style={{
                        width: 52, height: 52,
                        background: '#eff6ff',
                        borderRadius: '50%',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        margin: '0 auto 16px',
                    }}>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                            <polyline points="9 22 9 12 15 12 15 22" />
                        </svg>
                    </div>
                    <h1 style={{ fontSize: 22, fontWeight: 700, color: '#111827', margin: '0 0 6px' }}>
                        Seleccionar empresa
                    </h1>
                    <p style={{ fontSize: 14, color: '#6b7280', margin: 0 }}>
                        Tu cuenta tiene acceso a varias empresas.<br />
                        Elige una para continuar.
                    </p>
                </div>

                {availableCompanies.length === 0 ? (
                    <p style={{ textAlign: 'center', color: '#ef4444', fontSize: 14 }}>
                        No tienes empresas asignadas. Contacta al administrador.
                    </p>
                ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        {availableCompanies.map((c) => (
                            <button
                                key={c.id}
                                type="button"
                                disabled={loading}
                                onClick={() => selectCompany(c.id)}
                                style={{
                                    padding: '14px 20px',
                                    border: selected === c.id ? '2px solid #2563eb' : '1.5px solid #e5e7eb',
                                    borderRadius: 9,
                                    background: selected === c.id ? '#eff6ff' : '#fafafa',
                                    cursor: loading ? 'wait' : 'pointer',
                                    textAlign: 'left',
                                    fontSize: 15,
                                    fontWeight: 600,
                                    color: '#111827',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    transition: 'border-color 0.15s, background 0.15s',
                                    opacity: loading && selected !== c.id ? 0.5 : 1,
                                }}
                            >
                                <span style={{
                                    width: 32, height: 32, borderRadius: '50%',
                                    background: '#dbeafe',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    fontSize: 13, fontWeight: 700, color: '#1d4ed8', flexShrink: 0,
                                }}>
                                    {c.name.charAt(0).toUpperCase()}
                                </span>
                                <span style={{ flex: 1 }}>{c.name}</span>
                                {selected === c.id && loading && (
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" strokeWidth="2.5" style={{ animation: 'spin 0.8s linear infinite' }}>
                                        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
                                    </svg>
                                )}
                            </button>
                        ))}
                    </div>
                )}

                <div style={{ marginTop: 28, textAlign: 'center', borderTop: '1px solid #f3f4f6', paddingTop: 20 }}>
                    <span style={{ fontSize: 13, color: '#9ca3af' }}>
                        Sesión iniciada como <strong style={{ color: '#374151' }}>{auth?.user?.name}</strong>
                    </span>
                    <br />
                    <button
                        type="button"
                        onClick={handleLogout}
                        style={{
                            marginTop: 10, fontSize: 13, color: '#6b7280',
                            background: 'none', border: 'none', cursor: 'pointer',
                            textDecoration: 'underline',
                        }}
                    >
                        Cerrar sesión
                    </button>
                </div>
            </div>

            <style>{`
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            `}</style>
        </div>
    );
}
