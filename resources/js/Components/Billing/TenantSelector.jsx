import { useState } from 'react';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';

export default function TenantSelector({ billingTenant, availableCustomers = [], setRoute, clearRoute }) {
    const [selectOpen, setSelectOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    if (!availableCustomers.length) return null;

    function selectCompany(customerId) {
        setLoading(true);
        router.post(
            route(setRoute),
            { customer_id: customerId },
            { preserveScroll: true, onFinish: () => { setLoading(false); setSelectOpen(false); } }
        );
    }

    function clearCompany() {
        setLoading(true);
        router.post(
            route(clearRoute),
            {},
            { preserveScroll: true, onFinish: () => setLoading(false) }
        );
    }

    return (
        <div style={{
            display: 'flex', alignItems: 'center', gap: 10,
            padding: '8px 14px', marginBottom: 12,
            background: billingTenant ? '#eff6ff' : '#f9fafb',
            border: `1px solid ${billingTenant ? '#bfdbfe' : '#e5e7eb'}`,
            borderRadius: 8, fontSize: 13,
        }}>
            <span style={{ color: '#6b7280', fontWeight: 500 }}>
                Empresa activa:
            </span>

            {billingTenant ? (
                <>
                    <span style={{ fontWeight: 700, color: '#1d4ed8' }}>{billingTenant.name}</span>
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
                    {billingTenant ? 'Cambiar empresa' : 'Seleccionar empresa'}
                </button>

                {selectOpen && (
                    <div style={{
                        position: 'absolute', top: '110%', right: 0, zIndex: 100,
                        background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8,
                        boxShadow: '0 4px 16px rgba(0,0,0,0.12)', minWidth: 240, maxHeight: 280, overflowY: 'auto',
                    }}>
                        {availableCustomers.map((c) => (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => selectCompany(c.id)}
                                style={{
                                    display: 'block', width: '100%', textAlign: 'left',
                                    padding: '9px 14px', border: 'none', background: billingTenant?.id === c.id ? '#eff6ff' : 'transparent',
                                    cursor: 'pointer', fontSize: 13, fontWeight: billingTenant?.id === c.id ? 700 : 400,
                                    color: billingTenant?.id === c.id ? '#1d4ed8' : '#111827',
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
