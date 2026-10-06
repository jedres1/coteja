import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

// ─── EmailSettingsForm ────────────────────────────────────────────────────────

function EmailSettingsForm({ settings }) {
    const [locked, setLocked] = useState(!!settings?.is_locked);

    const { data, setData, post, processing, errors } = useForm({
        email:         settings?.email ?? '',
        imap_host:     settings?.imap_host ?? '',
        imap_port:     settings?.imap_port ?? '',
        imap_user:     settings?.imap_user ?? '',
        imap_password: settings?.imap_password ?? '',
        folder:        settings?.folder ?? 'INBOX',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.purchase-invoices.settings.update'));
    }

    return (
        <form onSubmit={handleSubmit}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                <h3 style={{ margin: 0 }}>Correo / IMAP</h3>
                <button
                    type="button"
                    className="btn secondary"
                    onClick={() => setLocked((v) => !v)}
                >
                    {locked ? 'Desbloquear' : 'Bloquear'}
                </button>
            </div>

            <div className="form-grid">
                <label>
                    Correo electrónico
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        disabled={locked}
                        required
                    />
                    {errors.email && <p className="field-error">{errors.email}</p>}
                </label>

                <label>
                    Host IMAP
                    <input
                        type="text"
                        value={data.imap_host}
                        onChange={(e) => setData('imap_host', e.target.value)}
                        disabled={locked}
                        placeholder="imap.gmail.com"
                    />
                    {errors.imap_host && <p className="field-error">{errors.imap_host}</p>}
                </label>

                <label>
                    Puerto IMAP
                    <input
                        type="number"
                        value={data.imap_port}
                        onChange={(e) => setData('imap_port', e.target.value)}
                        disabled={locked}
                        placeholder="993"
                    />
                    {errors.imap_port && <p className="field-error">{errors.imap_port}</p>}
                </label>

                <label>
                    Usuario IMAP
                    <input
                        type="text"
                        value={data.imap_user}
                        onChange={(e) => setData('imap_user', e.target.value)}
                        disabled={locked}
                    />
                    {errors.imap_user && <p className="field-error">{errors.imap_user}</p>}
                </label>

                <label>
                    Contraseña IMAP
                    <input
                        type="password"
                        value={data.imap_password}
                        onChange={(e) => setData('imap_password', e.target.value)}
                        disabled={locked}
                        autoComplete="new-password"
                        placeholder={locked ? '••••••••' : 'Contraseña de aplicación'}
                    />
                    {errors.imap_password && <p className="field-error">{errors.imap_password}</p>}
                </label>

                <label>
                    Carpeta
                    <input
                        type="text"
                        value={data.folder}
                        onChange={(e) => setData('folder', e.target.value)}
                        disabled={locked}
                        placeholder="INBOX"
                    />
                    {errors.folder && <p className="field-error">{errors.folder}</p>}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="submit" className="btn" disabled={processing || locked}>
                    {processing ? 'Guardando…' : 'Guardar configuración de correo'}
                </button>
            </div>
        </form>
    );
}

// ─── AccountingSettingsForm ───────────────────────────────────────────────────

function AccountingSettingsForm({ accountingSettings, accounts }) {
    const { data, setData, post, processing, errors } = useForm({
        accounts_payable_account_id: accountingSettings?.accounts_payable_account_id
            ? String(accountingSettings.accounts_payable_account_id)
            : '',
        purchase_account_id: accountingSettings?.purchase_account_id
            ? String(accountingSettings.purchase_account_id)
            : '',
        iva_account_id: accountingSettings?.iva_account_id
            ? String(accountingSettings.iva_account_id)
            : '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('admin.purchase-invoices.settings.accounting'));
    }

    const accountOptions = accounts.map((a) => (
        <option key={a.id} value={String(a.id)}>
            {a.code} — {a.name}
        </option>
    ));

    return (
        <form onSubmit={handleSubmit}>
            <h3 style={{ marginTop: 0 }}>Cuentas contables</h3>

            <div className="form-grid">
                <label>
                    Cuentas por pagar
                    <select
                        value={data.accounts_payable_account_id}
                        onChange={(e) => setData('accounts_payable_account_id', e.target.value)}
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {accountOptions}
                    </select>
                    {errors.accounts_payable_account_id && (
                        <p className="field-error">{errors.accounts_payable_account_id}</p>
                    )}
                </label>

                <label>
                    Cuenta de compras
                    <select
                        value={data.purchase_account_id}
                        onChange={(e) => setData('purchase_account_id', e.target.value)}
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {accountOptions}
                    </select>
                    {errors.purchase_account_id && (
                        <p className="field-error">{errors.purchase_account_id}</p>
                    )}
                </label>

                <label>
                    Cuenta IVA crédito fiscal
                    <select
                        value={data.iva_account_id}
                        onChange={(e) => setData('iva_account_id', e.target.value)}
                    >
                        <option value="">— Seleccionar cuenta —</option>
                        {accountOptions}
                    </select>
                    {errors.iva_account_id && (
                        <p className="field-error">{errors.iva_account_id}</p>
                    )}
                </label>
            </div>

            <div className="form-actions" style={{ marginTop: 20 }}>
                <button type="submit" className="btn" disabled={processing}>
                    {processing ? 'Guardando…' : 'Guardar cuentas contables'}
                </button>
            </div>
        </form>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function Settings({ settings, accountingSettings, accounts }) {
    const [generating, setGenerating] = useState(false);

    function handleGenerateMissing() {
        if (!confirm('¿Generar partidas contables para todas las facturas sin partidas? Esto puede tardar unos segundos.')) return;
        setGenerating(true);
        router.post(
            route('admin.purchase-invoices.settings.generate-missing-entries'),
            {},
            { onFinish: () => setGenerating(false) }
        );
    }

    return (
        <AppLayout>
            <Head title="Configuración de compras" />

            <div className="top">
                <h1>Configuración de compras</h1>
            </div>

            {/* Email / IMAP card */}
            <div className="card" style={{ marginBottom: 16 }}>
                <EmailSettingsForm settings={settings} />
            </div>

            {/* Accounting settings card */}
            <div className="card" style={{ marginBottom: 16 }}>
                <AccountingSettingsForm accountingSettings={accountingSettings} accounts={accounts} />
            </div>

            {/* Generate missing entries card */}
            <div className="card">
                <h3 style={{ marginTop: 0 }}>Partidas contables</h3>
                <p className="muted" style={{ marginTop: 0 }}>
                    Genera partidas contables automáticas para facturas aprobadas que aún no tienen registro contable.
                </p>
                <button
                    type="button"
                    className="btn secondary"
                    onClick={handleGenerateMissing}
                    disabled={generating}
                >
                    {generating ? 'Generando…' : 'Generar partidas faltantes'}
                </button>
            </div>
        </AppLayout>
    );
}
