import { useState, useEffect, useRef } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';

function NavGroup({ label, children, routePatterns = [] }) {
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
                <span className="nav-label">{label}</span>
                <span className="nav-caret">›</span>
            </button>
            {open && <div className="nav-sub">{children}</div>}
        </div>
    );
}

function NavLink({ href, children, routePattern }) {
    const { url } = usePage();
    let active = false;
    try { active = route().current(routePattern); } catch { active = false; }

    return (
        <Link href={href} className={active ? 'active-link' : ''}>
            {children}
        </Link>
    );
}

export default function AppLayout({ title, children }) {
    const { auth, flash } = usePage().props;
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
    const canAccessAdmin = user?.can_access_admin;
    const mod = user?.modules ?? {};

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
                    {canAccessAdmin ? (
                        <>
                            <NavLink href={route('admin.dashboard')} routePattern="admin.dashboard">
                                Dashboard
                            </NavLink>

                            {isAdmin && (
                                <>
                                    <NavGroup
                                        label="Administración de negocio"
                                        routePatterns={['admin.users.*','admin.companies.*','admin.plans.*','admin.licenses.*','admin.payments.*','admin.releases.*','admin.backups.*']}
                                    >
                                        <NavLink href={route('admin.users.index')} routePattern="admin.users.*">Usuarios y acceso</NavLink>
                                        <NavLink href={route('admin.companies.index')} routePattern="admin.companies.*">Empresas</NavLink>
                                        <NavLink href={route('admin.plans.index')} routePattern="admin.plans.*">Planes</NavLink>
                                        <NavLink href={route('admin.licenses.index')} routePattern="admin.licenses.*">Licencias</NavLink>
                                        <NavLink href={route('admin.payments.index')} routePattern="admin.payments.*">Cobros</NavLink>
                                        <NavLink href={route('admin.releases.index')} routePattern="admin.releases.*">Descargas</NavLink>
                                        <NavLink href={route('admin.backups.index')} routePattern="admin.backups.*">Backups</NavLink>
                                    </NavGroup>

                                    <NavGroup label="Compras" routePatterns={['admin.purchase-invoices.*','admin.suppliers.*']}>
                                        <NavLink href={route('admin.purchase-invoices.pending-approval')} routePattern="admin.purchase-invoices.pending-approval">Extraer facturas</NavLink>
                                        <NavLink href={route('admin.purchase-invoices.index')} routePattern="admin.purchase-invoices.index">Facturas de compra</NavLink>
                                        <NavLink href={route('admin.purchase-invoices.accounts-payable')} routePattern="admin.purchase-invoices.accounts-payable">Cuentas por pagar</NavLink>
                                        <NavLink href={route('admin.suppliers.index')} routePattern="admin.suppliers.*">Proveedores</NavLink>
                                        <NavLink href={route('admin.purchase-invoices.settings')} routePattern="admin.purchase-invoices.settings">Configuración</NavLink>
                                    </NavGroup>

                                    <NavGroup label="Control de bancos" routePatterns={['admin.bank-transactions.*']}>
                                        <NavLink href={route('admin.bank-transactions.accounts')} routePattern="admin.bank-transactions.accounts">Cuentas bancarias</NavLink>
                                        <NavLink href={route('admin.bank-transactions.transactions')} routePattern="admin.bank-transactions.transactions">Transacciones</NavLink>
                                        <NavLink href={route('admin.bank-transactions.reconciliations')} routePattern="admin.bank-transactions.reconciliations*">Conciliación</NavLink>
                                    </NavGroup>

                                    <NavGroup label="Contabilidad" routePatterns={['admin.accounting.*']}>
                                        <NavLink href={route('admin.accounting.diario.index')} routePattern="admin.accounting.diario.*">Diario contable</NavLink>
                                        <NavLink href={route('admin.accounting.catalogo')} routePattern="admin.accounting.catalogo">Catálogo de cuentas</NavLink>
                                        <NavLink href={route('admin.accounting.saldos')} routePattern="admin.accounting.saldos">Saldos por período</NavLink>
                                        <NavLink href={route('admin.accounting.balanza-comprobacion')} routePattern="admin.accounting.balanza-comprobacion">Balanza de Comprobación</NavLink>
                                        <NavLink href={route('admin.accounting.libro-mayor')} routePattern="admin.accounting.libro-mayor">Libro Mayor</NavLink>
                                        <NavLink href={route('admin.accounting.balance-general')} routePattern="admin.accounting.balance-general">Balance General</NavLink>
                                        <NavLink href={route('admin.accounting.estado-resultados')} routePattern="admin.accounting.estado-resultados">Estado de Resultados</NavLink>
                                        <NavLink href={route('admin.accounting.periodos')} routePattern="admin.accounting.periodos">Períodos contables</NavLink>
                                        <NavLink href={route('admin.accounting.configuracion')} routePattern="admin.accounting.configuracion">Configuración</NavLink>
                                    </NavGroup>

                                    <NavGroup label="Nómina" routePatterns={['admin.payroll.*']}>
                                        <NavLink href={route('admin.payroll.index')} routePattern="admin.payroll.index">Empleados</NavLink>
                                        <NavLink href={route('admin.payroll.index', { tab: 'nominas' })} routePattern="admin.payroll.periods.*">Períodos de Nómina</NavLink>
                                        <NavLink href={route('admin.payroll.concepts.index')} routePattern="admin.payroll.concepts.*">Gestión de conceptos</NavLink>
                                        <NavLink href={route('admin.payroll.settings.index')} routePattern="admin.payroll.settings.*">Configuración</NavLink>
                                    </NavGroup>

                                    <NavGroup label="Inventarios" routePatterns={['admin.inventory.*','admin.factura-sv.billing.productos']}>
                                        <NavLink href={route('admin.inventory.movements')} routePattern="admin.inventory.movements">Movimientos</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.productos')} routePattern="admin.factura-sv.billing.productos">Productos</NavLink>
                                        <NavLink href={route('admin.inventory.product-types')} routePattern="admin.inventory.product-types">Tipos de producto</NavLink>
                                        <NavLink href={route('admin.inventory.warehouses')} routePattern="admin.inventory.warehouses">Bodegas</NavLink>
                                        <NavLink href={route('admin.inventory.parameters')} routePattern="admin.inventory.parameters">Parámetros</NavLink>
                                    </NavGroup>

                                    <NavGroup label="Facturación" routePatterns={['admin.customers.*','admin.factura-sv.billing.*']}>
                                        <NavLink href={route('admin.factura-sv.billing.dashboard')} routePattern="admin.factura-sv.billing.dashboard">Dashboard</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.facturas')} routePattern="admin.factura-sv.billing.facturas">Facturas</NavLink>
                                        <NavLink href={route('admin.customers.index')} routePattern="admin.customers.*">Clientes</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.nueva-factura')} routePattern="admin.factura-sv.billing.nueva-factura">Nueva Factura</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.cuentas-por-cobrar')} routePattern="admin.factura-sv.billing.cuentas-por-cobrar">Cuentas por cobrar</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.productos')} routePattern="admin.factura-sv.billing.productos">Servicios</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.configuracion')} routePattern="admin.factura-sv.billing.configuracion">Configuración</NavLink>
                                    </NavGroup>
                                </>
                            )}
                        </>
                    ) : (
                        <>
                            <NavLink href={route('client.dashboard')} routePattern="client.dashboard">Mi cuenta</NavLink>

                            {mod.purchases && (
                                <NavGroup label="Compras" routePatterns={['admin.purchase-invoices.*','admin.suppliers.*']}>
                                    <NavLink href={route('admin.purchase-invoices.pending-approval')} routePattern="admin.purchase-invoices.pending-approval">Extraer facturas</NavLink>
                                    <NavLink href={route('admin.purchase-invoices.index')} routePattern="admin.purchase-invoices.index">Facturas de compra</NavLink>
                                    <NavLink href={route('admin.purchase-invoices.accounts-payable')} routePattern="admin.purchase-invoices.accounts-payable">Cuentas por pagar</NavLink>
                                    <NavLink href={route('admin.suppliers.index')} routePattern="admin.suppliers.*">Proveedores</NavLink>
                                    <NavLink href={route('admin.purchase-invoices.settings')} routePattern="admin.purchase-invoices.settings">Configuración</NavLink>
                                </NavGroup>
                            )}

                            {mod.purchases && (
                                <NavGroup label="Control de bancos" routePatterns={['admin.bank-transactions.*']}>
                                    <NavLink href={route('admin.bank-transactions.accounts')} routePattern="admin.bank-transactions.accounts">Cuentas bancarias</NavLink>
                                    <NavLink href={route('admin.bank-transactions.transactions')} routePattern="admin.bank-transactions.transactions">Transacciones</NavLink>
                                    <NavLink href={route('admin.bank-transactions.reconciliations')} routePattern="admin.bank-transactions.reconciliations*">Conciliación</NavLink>
                                </NavGroup>
                            )}

                            {mod.payroll && (
                                <NavGroup label="Nómina" routePatterns={['admin.payroll.*']}>
                                    <NavLink href={route('admin.payroll.index')} routePattern="admin.payroll.index">Empleados</NavLink>
                                    <NavLink href={route('admin.payroll.index', { tab: 'nominas' })} routePattern="admin.payroll.periods.*">Períodos de Nómina</NavLink>
                                    <NavLink href={route('admin.payroll.concepts.index')} routePattern="admin.payroll.concepts.*">Gestión de conceptos</NavLink>
                                    <NavLink href={route('admin.payroll.settings.index')} routePattern="admin.payroll.settings.*">Configuración</NavLink>
                                </NavGroup>
                            )}

                            {mod.billing && (
                                <>
                                    {mod.inventory && (
                                        <NavGroup label="Inventarios" routePatterns={['admin.inventory.*','admin.factura-sv.billing.productos']}>
                                            <NavLink href={route('admin.inventory.movements')} routePattern="admin.inventory.movements">Movimientos</NavLink>
                                            <NavLink href={route('admin.factura-sv.billing.productos')} routePattern="admin.factura-sv.billing.productos">Productos</NavLink>
                                            <NavLink href={route('admin.inventory.product-types')} routePattern="admin.inventory.product-types">Tipos de producto</NavLink>
                                            <NavLink href={route('admin.inventory.warehouses')} routePattern="admin.inventory.warehouses">Bodegas</NavLink>
                                            <NavLink href={route('admin.inventory.parameters')} routePattern="admin.inventory.parameters">Parámetros</NavLink>
                                        </NavGroup>
                                    )}
                                    <NavGroup label="Facturación" routePatterns={['admin.factura-sv.billing.*']}>
                                        <NavLink href={route('admin.factura-sv.billing.dashboard')} routePattern="admin.factura-sv.billing.dashboard">Dashboard</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.facturas')} routePattern="admin.factura-sv.billing.facturas">Facturas</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.nueva-factura')} routePattern="admin.factura-sv.billing.nueva-factura">Nueva Factura</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.cuentas-por-cobrar')} routePattern="admin.factura-sv.billing.cuentas-por-cobrar">Cuentas por cobrar</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.productos')} routePattern="admin.factura-sv.billing.productos">Servicios</NavLink>
                                        <NavLink href={route('admin.factura-sv.billing.configuracion')} routePattern="admin.factura-sv.billing.configuracion">Configuración</NavLink>
                                    </NavGroup>
                                </>
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
                {flash?.status && <div className="notice">{flash.status}</div>}
                {flash?.error && <div className="errors">{flash.error}</div>}
                {children}
            </main>
        </div>
    );
}
