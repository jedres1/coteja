import { Head, useForm, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

function MailboxForm({ host, port, username, password, mailbox, onlyUnseen, limit, disabled, onSaved }) {
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        mailbox_host:        host ?? '',
        mailbox_port:        port ?? '',
        mailbox_username:    username ?? '',
        mailbox_password:    '',
        mailbox_mailbox:     mailbox ?? 'INBOX',
        mailbox_only_unseen: onlyUnseen ? '1' : '0',
        mailbox_limit:       limit ?? '25',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.purchase-invoices.settings.update'), { onSuccess: () => onSaved?.() });
    }

    return (
        <form onSubmit={handleSubmit} noValidate>
            <h2 style={{ marginTop: 0, marginBottom: 20, fontSize: 15, fontWeight: 600 }}>
                Configuración de correo / IMAP
            </h2>
            <fieldset disabled={disabled} style={{ border: 'none', padding: 0, margin: 0 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                <label style={{ margin: 0 }}>
                    Host IMAP
                    <input
                        type="text"
                        value={data.mailbox_host}
                        onChange={(e) => setData('mailbox_host', e.target.value)}
                        placeholder="imap.gmail.com"
                        required
                    />
                    {errors.mailbox_host && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.mailbox_host}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Puerto
                    <input
                        type="number"
                        value={data.mailbox_port}
                        onChange={(e) => setData('mailbox_port', e.target.value)}
                        placeholder="993"
                        required
                        min="1"
                        max="65535"
                    />
                    {errors.mailbox_port && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.mailbox_port}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Usuario
                    <input
                        type="text"
                        value={data.mailbox_username}
                        onChange={(e) => setData('mailbox_username', e.target.value)}
                        required
                    />
                    {errors.mailbox_username && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.mailbox_username}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Contraseña
                    <input
                        type="password"
                        value={data.mailbox_password}
                        onChange={(e) => setData('mailbox_password', e.target.value)}
                        autoComplete="new-password"
                        placeholder={password ? '••••••••' : 'Sin contraseña guardada'}
                    />
                    {errors.mailbox_password && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.mailbox_password}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Carpeta (Mailbox)
                    <input
                        type="text"
                        value={data.mailbox_mailbox}
                        onChange={(e) => setData('mailbox_mailbox', e.target.value)}
                        required
                    />
                    {errors.mailbox_mailbox && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.mailbox_mailbox}</span>}
                </label>
                <label style={{ margin: 0 }}>
                    Límite de correos
                    <input
                        type="number"
                        value={data.mailbox_limit}
                        onChange={(e) => setData('mailbox_limit', e.target.value)}
                        required
                        min="1"
                        max="100"
                    />
                    {errors.mailbox_limit && <span style={{ color: '#ef4444', fontSize: 12 }}>{errors.mailbox_limit}</span>}
                </label>
                <label style={{ margin: 0, gridColumn: '1 / -1', display: 'flex', alignItems: 'center', gap: 10, flexDirection: 'row' }}>
                    <input
                        type="checkbox"
                        checked={data.mailbox_only_unseen === '1'}
                        onChange={(e) => setData('mailbox_only_unseen', e.target.checked ? '1' : '0')}
                        style={{ width: 'auto', margin: 0 }}
                    />
                    Solo correos no leídos
                </label>
            </div>
            </fieldset>
            <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginTop: 20 }}>
                <button type="submit" className="btn" disabled={disabled || processing}>
                    {processing ? 'Guardando...' : 'Guardar configuración de correo'}
                </button>
                {recentlySuccessful && (
                    <span style={{ color: '#16a34a', fontSize: 13 }}>Configuración guardada.</span>
                )}
            </div>
        </form>
    );
}

function AccountingForm({ purchasePackage, payablePackage, accountingAccounts, costCenters, disabled, onSaved }) {
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        purchase_debit_account_id:  purchasePackage?.debit_account_id  ? String(purchasePackage.debit_account_id)  : '',
        purchase_credit_account_id: purchasePackage?.credit_account_id ? String(purchasePackage.credit_account_id) : '',
        purchase_cost_center_id:    purchasePackage?.cost_center_id    ? String(purchasePackage.cost_center_id)    : '',
        payable_debit_account_id:   payablePackage?.debit_account_id   ? String(payablePackage.debit_account_id)   : '',
        payable_credit_account_id:  payablePackage?.credit_account_id  ? String(payablePackage.credit_account_id)  : '',
        payable_cost_center_id:     payablePackage?.cost_center_id     ? String(payablePackage.cost_center_id)     : '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.purchase-invoices.settings.accounting'), { onSuccess: () => onSaved?.() });
    }

    const accountOpts = (accountingAccounts ?? []).map((a) => (
        <option key={a.id} value={String(a.id)}>{a.code} — {a.name}</option>
    ));
    const centerOpts = (costCenters ?? []).map((c) => (
        <option key={c.id} value={String(c.id)}>{c.code} — {c.name}</option>
    ));

    const selectStyle = { margin: 0 };
    const fieldErr = (field) => errors[field] && (
        <span style={{ color: '#ef4444', fontSize: 12 }}>{errors[field]}</span>
    );

    return (
        <form onSubmit={handleSubmit} noValidate>
            <h2 style={{ marginTop: 0, marginBottom: 4, fontSize: 15, fontWeight: 600 }}>
                Cuentas contables
            </h2>
            <p style={{ margin: '0 0 16px', fontSize: 13, color: '#6b7280' }}>
                Partida de compras (CP) y partida de cuentas por pagar (CXP).
            </p>
            <fieldset disabled={disabled} style={{ border: 'none', padding: 0, margin: 0 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                <div style={{ gridColumn: '1 / -1', fontWeight: 600, fontSize: 13, borderBottom: '1px solid #e5e7eb', paddingBottom: 6 }}>
                    Compras (CP)
                </div>
                <label style={selectStyle}>
                    Cuenta débito
                    <select value={data.purchase_debit_account_id} onChange={(e) => setData('purchase_debit_account_id', e.target.value)}>
                        <option value="">— Seleccionar —</option>
                        {accountOpts}
                    </select>
                    {fieldErr('purchase_debit_account_id')}
                </label>
                <label style={selectStyle}>
                    Cuenta crédito
                    <select value={data.purchase_credit_account_id} onChange={(e) => setData('purchase_credit_account_id', e.target.value)}>
                        <option value="">— Seleccionar —</option>
                        {accountOpts}
                    </select>
                    {fieldErr('purchase_credit_account_id')}
                </label>
                {costCenters?.length > 0 && (
                    <label style={selectStyle}>
                        Centro de costo
                        <select value={data.purchase_cost_center_id} onChange={(e) => setData('purchase_cost_center_id', e.target.value)}>
                            <option value="">— Sin centro de costo —</option>
                            {centerOpts}
                        </select>
                        {fieldErr('purchase_cost_center_id')}
                    </label>
                )}

                <div style={{ gridColumn: '1 / -1', fontWeight: 600, fontSize: 13, borderBottom: '1px solid #e5e7eb', paddingBottom: 6, marginTop: 8 }}>
                    Cuentas por pagar (CXP)
                </div>
                <label style={selectStyle}>
                    Cuenta débito
                    <select value={data.payable_debit_account_id} onChange={(e) => setData('payable_debit_account_id', e.target.value)}>
                        <option value="">— Seleccionar —</option>
                        {accountOpts}
                    </select>
                    {fieldErr('payable_debit_account_id')}
                </label>
                <label style={selectStyle}>
                    Cuenta crédito
                    <select value={data.payable_credit_account_id} onChange={(e) => setData('payable_credit_account_id', e.target.value)}>
                        <option value="">— Seleccionar —</option>
                        {accountOpts}
                    </select>
                    {fieldErr('payable_credit_account_id')}
                </label>
                {costCenters?.length > 0 && (
                    <label style={selectStyle}>
                        Centro de costo
                        <select value={data.payable_cost_center_id} onChange={(e) => setData('payable_cost_center_id', e.target.value)}>
                            <option value="">— Sin centro de costo —</option>
                            {centerOpts}
                        </select>
                        {fieldErr('payable_cost_center_id')}
                    </label>
                )}
            </div>
            </fieldset>
            <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginTop: 20 }}>
                <button type="submit" className="btn" disabled={disabled || processing}>
                    {processing ? 'Guardando...' : 'Guardar cuentas contables'}
                </button>
                {recentlySuccessful && (
                    <span style={{ color: '#16a34a', fontSize: 13 }}>Cuentas guardadas.</span>
                )}
            </div>
        </form>
    );
}

export default function Settings({
    host, port, username, password, mailbox, onlyUnseen, limit,
    accountingAccounts, costCenters, purchasePackage, payablePackage,
    missingPurchaseEntries, missingPayableEntries,
}) {
    const { auth, billingTenant } = usePage().props;
    const tenantModules = billingTenant?.modules ?? null;
    const hasAccounting = tenantModules
        ? tenantModules.includes('accounting')
        : (auth?.user?.modules?.accounting ?? false);

    const [unlocked, setUnlocked]       = useState(false);
    const [showWarning, setShowWarning] = useState(false);
    const [generating, setGenerating]   = useState(false);

    function handleSaved() { setUnlocked(false); }

    function handleGenerateMissing() {
        if (!confirm('¿Generar partidas contables faltantes? Esto puede tardar unos segundos.')) return;
        setGenerating(true);
        router.post(
            route('admin.purchase-invoices.settings.generate-missing-entries'),
            {},
            { onFinish: () => setGenerating(false) }
        );
    }

    const totalMissing = (missingPurchaseEntries ?? 0) + (missingPayableEntries ?? 0);

    return (
        <AppLayout>
            <Head title="Configuración de compras" />

            <div className="top">
                <h1>Configuración de compras</h1>
            </div>

            {/* Lock panel */}
            <div className="card" style={{ marginBottom: 16, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16 }}>
                <div>
                    <p style={{ margin: 0, fontWeight: 600 }}>
                        {unlocked ? 'Configuración desbloqueada' : 'Configuración bloqueada'}
                    </p>
                    <p style={{ margin: '2px 0 0', fontSize: 12, color: '#6b7280' }}>
                        {unlocked
                            ? 'Edita solo si vas a corregir datos de correo o cuentas contables.'
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

            {/* Warning modal */}
            {showWarning && (
                <div style={{ position: 'fixed', inset: 0, zIndex: 9998, background: 'rgba(0,0,0,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <div style={{ background: '#fff', borderRadius: 10, padding: '28px 32px', maxWidth: 460, width: '90%', boxShadow: '0 8px 32px rgba(0,0,0,0.18)' }}>
                        <p style={{ margin: '0 0 8px', fontWeight: 700, fontSize: 16, color: '#dc2626' }}>Advertencia — edición de parámetros</p>
                        <p style={{ margin: '0 0 12px', fontSize: 13, color: '#374151' }}>
                            Cambiar estos datos puede afectar la importación de facturas por correo o las partidas contables generadas automáticamente.
                        </p>
                        <ul style={{ margin: '0 0 20px', paddingLeft: 20, fontSize: 12, color: '#7f1d1d' }}>
                            <li>Verifica el host, puerto y credenciales IMAP antes de guardar.</li>
                            <li>Cambiar cuentas contables afecta las partidas generadas desde este momento.</li>
                        </ul>
                        <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
                            <button type="button" className="btn secondary" onClick={() => setShowWarning(false)}>Cancelar</button>
                            <button type="button" className="btn" onClick={() => { setShowWarning(false); setUnlocked(true); }}>Aceptar</button>
                        </div>
                    </div>
                </div>
            )}

            <div className="card" style={{ marginBottom: 16 }}>
                <MailboxForm
                    host={host} port={port} username={username} password={password}
                    mailbox={mailbox} onlyUnseen={onlyUnseen} limit={limit}
                    disabled={!unlocked} onSaved={handleSaved}
                />
            </div>

            {hasAccounting ? (
                <div className="card" style={{ marginBottom: 16 }}>
                    <AccountingForm
                        purchasePackage={purchasePackage} payablePackage={payablePackage}
                        accountingAccounts={accountingAccounts} costCenters={costCenters}
                        disabled={!unlocked} onSaved={handleSaved}
                    />
                </div>
            ) : (
                <div className="card" style={{ marginBottom: 16, padding: '14px 18px' }}>
                    <p style={{ margin: 0, fontSize: 13, color: '#6b7280' }}>
                        Las cuentas contables de compras requieren acceso al módulo de Contabilidad.
                    </p>
                </div>
            )}

            <div className="card">
                <h2 style={{ marginTop: 0, marginBottom: 8, fontSize: 15, fontWeight: 600 }}>
                    Partidas contables faltantes
                </h2>
                <p style={{ margin: '0 0 12px', fontSize: 13, color: '#6b7280' }}>
                    Facturas aprobadas sin partida de compra: <strong>{missingPurchaseEntries ?? 0}</strong>.{' '}
                    Pagos sin partida de cuentas por pagar: <strong>{missingPayableEntries ?? 0}</strong>.
                </p>
                <button
                    type="button"
                    className="btn secondary"
                    onClick={handleGenerateMissing}
                    disabled={generating || totalMissing === 0}
                >
                    {generating ? 'Generando...' : `Generar partidas faltantes (${totalMissing})`}
                </button>
            </div>
        </AppLayout>
    );
}
