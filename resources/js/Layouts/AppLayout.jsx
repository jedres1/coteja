import { useState, useEffect, useRef } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import TenantSelector from '@/Components/Billing/TenantSelector';

function NavIco({ d, d2, d3 }) {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" style={{ flexShrink: 0, opacity: .7 }}>
            <path d={d} />
            {d2 && <path d={d2} />}
            {d3 && <path d={d3} />}
        </svg>
    );
}

function NavGroup({ label, icon, children, routePatterns = [] }) {
    const { url } = usePage();
    const isActive = routePatterns.some((p) => {
        try { return route().current(p); } catch { return false; }
    });
    const [open, setOpen] = useState(isActive);

    useEffect(() => {
        if (isActive) setOpen(true);
    }, [url]);

    return (
        <div className={`nav-group${open ? ' open' : ''}`}>
            <button
                className="nav-toggle"
                type="button"
                aria-expanded={open}
                onClick={() => setOpen((v) => !v)}
            >
                {icon}
                <span className="nav-label">{label}</span>
                <span className="nav-caret">›</span>
            </button>
            {open && <div className="nav-sub">{children}</div>}
        </div>
    );
}

function NavLink({ href, children, routePattern, isActive }) {
    let active = isActive;
    if (active === undefined) {
        try { active = route().current(routePattern); } catch { active = false; }
    }

    return (
        <Link href={href} className={active ? 'active-link' : ''}>
            {children}
        </Link>
    );
}

export default function AppLayout({ title, children }) {
    const { auth, flash, activeCompany, availableCompanies = [] } = usePage().props;
    const user = auth?.user;
    const [darkMode, setDarkMode] = useState(() => {
        const stored = typeof localStorage !== 'undefined' ? localStorage.getItem('coteja-theme') : null;
        if (stored) return stored === 'dark';
        return typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    });
    const logoutFormRef = useRef(null);

    useEffect(() => {
        document.body.classList.toggle('dark-mode', darkMode);
        localStorage.setItem('coteja-theme', darkMode ? 'dark' : 'light');
    }, [darkMode]);

    const handleLogout = (e) => {
        e.preventDefault();
        if (!confirm('Se va a cerrar la sesión. ¿Desea continuar?')) return;
        router.post(route('logout'));
    };

    const isAdmin = user?.is_admin;
    const isInquilino = user?.is_inquilino;
    const canAccessAdmin = user?.can_access_admin;
    // Cuando hay empresa activa, los módulos visibles son los de esa empresa; si no, los del usuario
    const tenantModules = activeCompany?.modules ?? null;
    const mod = tenantModules
        ? Object.fromEntries(['purchases','banking','accounting','payroll','billing','inventory'].map(m => [m, tenantModules.includes(m)]))
        : (user?.modules ?? {});
    const { url } = usePage();
    const onPayrollIndex = (() => { try { return route().current('admin.payroll.index'); } catch { return false; } })();
    const isPayrollEmployees = onPayrollIndex && !url.includes('tab=nominas');
    const isPayrollPeriods   = (onPayrollIndex && url.includes('tab=nominas')) || (() => { try { return route().current('admin.payroll.periods.*'); } catch { return false; } })();

    return (
        <div className="shell">
            <aside className="side">
                <div className="brand">
                    <img
                        src="/images/facturacion-electron-logo.png"
                        alt="CONSULTING AND TECH JANDRES"
                        width="136"
                        height="136"
                    />
                </div>

                <nav className="nav">
                    {canAccessAdmin && (
                        <>
                            <NavLink href={route('admin.dashboard')} routePattern="admin.dashboard">
                                <NavIco d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                Dashboard
                            </NavLink>

                            {isAdmin && !activeCompany && (
                                <NavGroup
                                    label="Administración"
                                    icon={<NavIco d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" d2="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />}
                                    routePatterns={['admin.users.*','admin.companies.*','admin.licenses.*','admin.plans.*','admin.releases.*','admin.backups.*']}
                                >
                                    <NavLink href={route('admin.users.index')} routePattern="admin.users.*">Usuarios y acceso</NavLink>
                                    <NavLink href={route('admin.companies.index')} routePattern="admin.companies.*">Empresas</NavLink>
                                    <NavLink href={route('admin.licenses.index')} routePattern="admin.licenses.*">Licencias</NavLink>
                                    <NavLink href={route('admin.plans.index')} routePattern="admin.plans.*">Planes</NavLink>
                                    <NavLink href={route('admin.releases.index')} routePattern="admin.releases.*">Descargas</NavLink>
                                    <NavLink href={route('admin.backups.index')} routePattern="admin.backups.*">Backups</NavLink>
                                </NavGroup>
                            )}

                            {mod.purchases && (
                                <NavGroup
                                    label="Compras"
                                    icon={<NavIco d="M3 3h2l.4 2M7 13h10l4-8H5.4m1.6 8l-1.5 6M17 13l1.5 6M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />}
                                    routePatterns={['admin.purchase-invoices.*','admin.suppliers.*']}
                                >
                                    <NavLink href={route('admin.purchase-invoices.pending-approval')} routePattern="admin.purchase-invoices.pending-approval">Extraer facturas</NavLink>
                                    <NavLink href={route('admin.purchase-invoices.index')} routePattern="admin.purchase-invoices.index">Facturas de compra</NavLink>
                                    <NavLink href={route('admin.purchase-invoices.accounts-payable')} routePattern="admin.purchase-invoices.accounts-payable">Cuentas por pagar</NavLink>
                                    <NavLink href={route('admin.suppliers.index')} routePattern="admin.suppliers.*">Proveedores</NavLink>
                                    {!isInquilino && (
                                        <NavLink href={route('admin.purchase-invoices.settings')} routePattern="admin.purchase-invoices.settings">Configuración</NavLink>
                                    )}
                                </NavGroup>
                            )}

                            {mod.banking && (
                                <NavGroup
                                    label="Control de bancos"
                                    icon={<NavIco d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />}
                                    routePatterns={['admin.bank-transactions.*']}
                                >
                                    <NavLink href={route('admin.bank-transactions.accounts')} routePattern="admin.bank-transactions.accounts">Cuentas bancarias</NavLink>
                                    <NavLink href={route('admin.bank-transactions.transactions')} routePattern="admin.bank-transactions.transactions">Transacciones</NavLink>
                                    <NavLink href={route('admin.bank-transactions.reconciliations')} routePattern="admin.bank-transactions.reconciliations*">Conciliación</NavLink>
                                </NavGroup>
                            )}

                            {mod.accounting && (
                                <NavGroup
                                    label="Contabilidad"
                                    icon={<NavIco d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />}
                                    routePatterns={['admin.accounting.*']}
                                >
                                    <NavLink href={route('admin.accounting.diario.index')} routePattern="admin.accounting.diario.*">Diario contable</NavLink>
                                    <NavLink href={route('admin.accounting.catalogo')} routePattern="admin.accounting.catalogo">Catálogo de cuentas</NavLink>
                                    <NavLink href={route('admin.accounting.saldos')} routePattern="admin.accounting.saldos">Saldos por período</NavLink>
                                    <NavLink href={route('admin.accounting.balanza-comprobacion')} routePattern="admin.accounting.balanza-comprobacion">Balanza de Comprobación</NavLink>
                                    <NavLink href={route('admin.accounting.libro-mayor')} routePattern="admin.accounting.libro-mayor">Libro Mayor</NavLink>
                                    <NavLink href={route('admin.accounting.balance-general')} routePattern="admin.accounting.balance-general">Balance General</NavLink>
                                    <NavLink href={route('admin.accounting.estado-resultados')} routePattern="admin.accounting.estado-resultados">Estado de Resultados</NavLink>
                                    <NavLink href={route('admin.accounting.reportes-fiscales')} routePattern="admin.accounting.reportes-fiscales*">Reportes fiscales</NavLink>
                                    <NavLink href={route('admin.accounting.periodos')} routePattern="admin.accounting.periodos">Períodos contables</NavLink>
                                    {!isInquilino && (
                                        <NavLink href={route('admin.accounting.configuracion')} routePattern="admin.accounting.configuracion">Configuración</NavLink>
                                    )}
                                </NavGroup>
                            )}

                            {mod.payroll && (
                                <NavGroup
                                    label="Nómina"
                                    icon={<NavIco d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />}
                                    routePatterns={['admin.payroll.*']}
                                >
                                    <NavLink href={route('admin.payroll.index')} isActive={isPayrollEmployees}>Empleados</NavLink>
                                    <NavLink href={route('admin.payroll.index', { tab: 'nominas' })} isActive={isPayrollPeriods}>Períodos de Nómina</NavLink>
                                    <NavLink href={route('admin.payroll.concepts.index')} routePattern="admin.payroll.concepts.*">Gestión de conceptos</NavLink>
                                    {!isInquilino && (
                                        <NavLink href={route('admin.payroll.settings.index')} routePattern="admin.payroll.settings.*">Configuración</NavLink>
                                    )}
                                </NavGroup>
                            )}

                            {mod.inventory && (
                                <NavGroup
                                    label="Inventarios"
                                    icon={<NavIco d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />}
                                    routePatterns={['admin.inventory.*','admin.factura-sv.billing.productos']}
                                >
                                    <NavLink href={route('admin.inventory.movements')} routePattern="admin.inventory.movements">Movimientos</NavLink>
                                    <NavLink href={route('admin.factura-sv.billing.productos')} routePattern="admin.factura-sv.billing.productos">Productos</NavLink>
                                    <NavLink href={route('admin.inventory.product-types')} routePattern="admin.inventory.product-types">Tipos de producto</NavLink>
                                    <NavLink href={route('admin.inventory.warehouses')} routePattern="admin.inventory.warehouses">Bodegas</NavLink>
                                    {!isInquilino && (
                                        <NavLink href={route('admin.inventory.parameters')} routePattern="admin.inventory.parameters">Parámetros</NavLink>
                                    )}
                                </NavGroup>
                            )}

                            {mod.billing && (
                                <NavGroup
                                    label="Facturación"
                                    icon={<NavIco d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />}
                                    routePatterns={['admin.factura-sv.billing.*']}
                                >
                                    <NavLink href={route('admin.factura-sv.billing.dashboard')} routePattern="admin.factura-sv.billing.dashboard">Dashboard</NavLink>
                                    <NavLink href={route('admin.factura-sv.billing.facturas')} routePattern="admin.factura-sv.billing.facturas">Facturas</NavLink>
                                    <NavLink href={route('admin.factura-sv.billing.nueva-factura')} routePattern="admin.factura-sv.billing.nueva-factura">Nueva Factura</NavLink>
                                    <NavLink href={route('admin.factura-sv.billing.cuentas-por-cobrar')} routePattern="admin.factura-sv.billing.cuentas-por-cobrar">Cuentas por cobrar</NavLink>
                                    <NavLink href={route('admin.factura-sv.billing.clientes')} routePattern="admin.factura-sv.billing.clientes">Clientes</NavLink>
                                    <NavLink href={route('admin.factura-sv.billing.productos')} routePattern="admin.factura-sv.billing.productos">Bienes y servicios</NavLink>
                                    {!isInquilino && (
                                        <NavLink href={route('admin.factura-sv.billing.configuracion')} routePattern="admin.factura-sv.billing.configuracion">Configuración</NavLink>
                                    )}
                                </NavGroup>
                            )}
                        </>
                    )}

                    <form onSubmit={handleLogout}>
                        <button className="logout-btn" type="submit">Salir</button>
                    </form>

                    <div className="theme-toggle">
                        <button
                            type="button"
                            onClick={() => setDarkMode((v) => !v)}
                            aria-label="Cambiar modo oscuro"
                        >
                            <span>{darkMode ? '☀' : '☾'}</span>
                        </button>
                    </div>
                </nav>
            </aside>

            <main className="main">
                <div className="topbar">
                    <div className="topbar-user">
                        <span className="topbar-name">{user?.name}</span>
                        <span className="topbar-email">{user?.email}</span>
                    </div>
                    {availableCompanies.length > 0 && (
                        <TenantSelector
                            activeCompany={activeCompany}
                            availableCompanies={availableCompanies}
                        />
                    )}
                </div>
                {flash?.status && <div className="notice">{flash.status}</div>}
                {flash?.error && <div className="errors">{flash.error}</div>}
                {children}
            </main>
        </div>
    );
}
