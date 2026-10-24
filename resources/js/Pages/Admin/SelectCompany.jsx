import { useState, useRef, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { showToast } from '@/utils';

export default function SelectCompany() {
    const { availableCompanies = [], auth, flash } = usePage().props;
    const [darkLogo] = useState(() => {
        const storedTheme = typeof localStorage !== 'undefined' ? localStorage.getItem('coteja-theme') : null;
        if (storedTheme) return storedTheme === 'dark';
        if (typeof document !== 'undefined' && document.body.classList.contains('dark-mode')) return true;
        return typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    });
    const [selected, setSelected] = useState(null);
    const [open, setOpen]         = useState(false);
    const [loading, setLoading]   = useState(false);
    const ref                     = useRef(null);

    useEffect(() => {
        function onOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onOutside);
        return () => document.removeEventListener('mousedown', onOutside);
    }, []);

    useEffect(() => {
        if (flash?.error) showToast(flash.error, false);
    }, [flash?.error]);

    function choose(company) {
        setSelected(company);
        setOpen(false);
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (!selected) return;
        setLoading(true);
        router.post(route('admin.empresa.cambiar'), { company_id: selected.id }, {
            onFinish: () => setLoading(false),
        });
    }

    return (
        <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: '24px' }}>
            <Head title="Seleccionar empresa" />

            <div className="card" style={{ width: 'min(420px, 100%)' }}>
                <img
                    src={`/images/coteja-erp-logo-${darkLogo ? 'oscuro' : 'claro'}.svg`}
                    alt="Coteja ERP"
                    width="330" height="90"
                    style={{ width: 280, maxWidth: '100%', height: 'auto', margin: '0 auto 14px', display: 'block' }}
                />

                <p className="muted" style={{ margin: '0 0 4px' }}>Bienvenido, <strong>{auth?.user?.name}</strong>.</p>
                <p className="muted" style={{ margin: '0 0 18px' }}>Selecciona una empresa para continuar.</p>

                <form onSubmit={handleSubmit}>
                    <div className="form-grid" style={{ gridTemplateColumns: '1fr' }}>
                        <label>
                            Empresa
                            <div className="combobox" ref={ref}>
                                <div className="combobox-inner" style={{ cursor: 'pointer' }} onClick={() => setOpen((o) => !o)}>
                                    <span className="combobox-input" style={{ color: selected ? 'var(--ink)' : 'var(--muted)', userSelect: 'none' }}>
                                        {selected ? selected.name : '— Selecciona una empresa —'}
                                    </span>
                                    <span className="combobox-arrow" style={{ transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }}>
                                        ›
                                    </span>
                                </div>

                                {open && (
                                    <ul className="combobox-list">
                                        {availableCompanies.map((c) => (
                                            <li
                                                key={c.id}
                                                className={`combobox-option${selected?.id === c.id ? ' selected' : ''}`}
                                                onClick={() => choose(c)}
                                            >
                                                <span className="combobox-option-name">{c.name}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </label>

                        <button className="btn" disabled={!selected || loading}>
                            {loading ? 'Cargando…' : 'Continuar'}
                        </button>
                    </div>
                </form>

                <p className="muted" style={{ margin: '18px 0 0', fontSize: 12, textAlign: 'center' }}>
                    <button
                        type="button"
                        onClick={() => router.post(route('logout'))}
                        style={{ background: 'none', border: 'none', color: 'var(--muted)', cursor: 'pointer', fontSize: 12, textDecoration: 'underline', padding: 0 }}
                    >
                        Cerrar sesión
                    </button>
                </p>
            </div>
        </div>
    );
}
