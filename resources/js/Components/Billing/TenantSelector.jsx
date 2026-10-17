import { useState } from 'react';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';

export default function TenantSelector({ activeCompany, availableCompanies = [] }) {
    const [selectOpen, setSelectOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    if (availableCompanies.length <= 1) return null;

    function selectCompany(companyId) {
        setLoading(true);
        router.post(
            route('admin.empresa.cambiar'),
            { company_id: companyId },
            { preserveScroll: true, onFinish: () => { setLoading(false); setSelectOpen(false); } }
        );
    }

    function clearCompany() {
        setLoading(true);
        router.post(
            route('admin.empresa.salir'),
            {},
            { preserveScroll: true, onFinish: () => setLoading(false) }
        );
    }

    return (
        <div style={{
            display: 'flex', alignItems: 'center', gap: 10,
            padding: '8px 14px', marginBottom: 12,
            background: activeCompany ? '#eff6ff' : '#f9fafb',
            border: `1px solid ${activeCompany ? '#bfdbfe' : '#e5e7eb'}`,
            borderRadius: 8, fontSize: 13,
        }}>
            <span style={{ color: '#6b7280', fontWeight: 500 }}>
                Empresa activa:
            </span>

            {activeCompany ? (
                <>
                    <span style={{ fontWeight: 700, color: '#1d4ed8' }}>{activeCompany.name}</span>
                    <button
                        type="button"
                        disabled={loading}
                        onClick={clearCompany}
                        style={{ marginLeft: 4, padding: '3px 10px', fontSize: 12, background: '#fee2e2', color: '#b91c1c', border: '1px solid #fca5a5', borderRadius: 5, cursor: 'pointer', fontWeight: 600 }}
                    >
                        Salir
                    </button>
                </>
            ) : (
                <span style={{ color: '#9ca3af', fontStyle: 'italic' }}>ninguna (viendo todas)</span>
            )}

            <div style={{ marginLeft: 'auto', position: 'relative' }}>
                <button
                    type="button"
                    disabled={loading}
                    onClick={() => setSelectOpen((o) => !o)}
                    style={{ padding: '4px 12px', fontSize: 12, background: '#fff', border: '1px solid #d1d5db', borderRadius: 5, cursor: 'pointer', fontWeight: 600 }}
                >
                    {activeCompany ? 'Cambiar empresa' : 'Seleccionar empresa'}
                </button>

                {selectOpen && (
                    <div style={{
                        position: 'absolute', top: '110%', right: 0, zIndex: 100,
                        background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8,
                        boxShadow: '0 4px 16px rgba(0,0,0,0.12)', minWidth: 240, maxHeight: 280, overflowY: 'auto',
                    }}>
                        {availableCompanies.map((c) => (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => selectCompany(c.id)}
                                style={{
                                    display: 'block', width: '100%', textAlign: 'left',
                                    padding: '9px 14px', border: 'none', background: activeCompany?.id === c.id ? '#eff6ff' : 'transparent',
                                    cursor: 'pointer', fontSize: 13, fontWeight: activeCompany?.id === c.id ? 700 : 400,
                                    color: activeCompany?.id === c.id ? '#1d4ed8' : '#111827',
                                    borderBottom: '1px solid #f3f4f6',
                                }}
                            >
                                {c.name}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
