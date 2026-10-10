import { useState, useEffect, useRef } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

const DTE_TYPES = [
    { code: '01', label: 'Factura' },
    { code: '03', label: 'Comprobante de Crédito Fiscal' },
    { code: '04', label: 'Nota de Remisión' },
    { code: '05', label: 'Nota de Crédito' },
    { code: '06', label: 'Nota de Débito' },
    { code: '07', label: 'Comprobante de Retención' },
    { code: '08', label: 'Comprobante de Liquidación' },
    { code: '11', label: 'Factura de Exportación' },
    { code: '14', label: 'Factura de Sujeto Excluido' },
    { code: '15', label: 'Comprobante de Donación' },
];

function SectionTitle({ children }) {
    return (
        <h4 style={{ margin: '24px 0 12px', fontSize: 14, fontWeight: 700, color: '#374151', borderBottom: '1px solid #e5e7eb', paddingBottom: 6 }}>
            {children}
        </h4>
    );
}

function Field({ label, children, full = false }) {
    return (
        <div className="field" style={{ margin: 0, gridColumn: full ? '1 / -1' : undefined }}>
            <label style={{ margin: 0 }}>{label}{children}</label>
        </div>
    );
}

function InfoBox({ children }) {
    return (
        <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 6, padding: '8px 12px', marginBottom: 12, fontSize: 12, color: '#1e40af' }}>
            {children}
        </div>
    );
}

function buildInitialForm(settings, correlativos) {
    const e = settings.emisor   ?? {};
    const h = settings.hacienda ?? {};
    const f = settings.firma    ?? {};
    const c = settings.correo   ?? {};
    const b = settings.backup   ?? {};

    return {
        emisor: {
            nit:                    e.nit                    ?? '',
            nrc:                    e.nrc                    ?? '',
            nombre_empresa:         e.nombre_empresa         ?? '',
            nombre_comercial:       e.nombre_comercial       ?? '',
            logo_path:              e.logo_path              ?? '',
            tipo_persona:           e.tipo_persona           ?? '',
            actividad_economica:    e.actividad_economica    ?? '',
            desc_actividad:         e.desc_actividad         ?? '',
            telefono:               e.telefono               ?? '',
            email:                  e.email                  ?? '',
            departamento:           e.departamento           ?? '',
            municipio:              e.municipio              ?? '',
            distrito:               e.distrito               ?? '',
            direccion:              e.direccion              ?? '',
            codigo_establecimiento: e.codigo_establecimiento ?? 'M001',
            punto_venta:            e.punto_venta            ?? 'P001',
        },
        hacienda: {
            ambiente: h.ambiente ?? '00',
            usuario:  h.usuario  ?? '',
            password: h.password ?? '',
        },
        firma: {
            tipo:               f.tipo                                                              ?? 'svfe',
            firmadorUsuario:    f.firmador_usuario  ?? f.firmadorUsuario                            ?? '',
            firmadorUrl:        f.firmador_url      ?? f.firmadorUrl                                ?? 'http://localhost:8113',
            certificado_path:   f.certificado_path  ?? f.certificadoPath                           ?? '',
            password:           f.certificado_password ?? f.certificadoPassword ?? f.firmador_pin  ?? '',
        },
        correo: {
            smtpHost:  c.smtpHost  ?? '',
            smtpPort:  c.smtpPort  ?? '465',
            smtpSecure: c.smtpSecure ?? true,
            username:  c.username  ?? '',
            from:      c.from      ?? '',
            fromName:  c.fromName  ?? '',
            password:  c.password  ?? '',
        },
        backup: {
            url:           b.url           ?? '',
            token:         b.token         ?? '',
            encryptionKey: b.encryptionKey ?? b.encryption_key ?? '',
            automatico:    b.automatico    ?? false,
            ultimo:        b.ultimo        ?? 'Sin backup',
        },
        documentos: Array.isArray(settings.documentos) && settings.documentos.length
            ? settings.documentos
            : ['01', '03', '05', '06', '07', '11', '14'],
        correlativos: Object.fromEntries(
            DTE_TYPES.map((t) => [t.code, correlativos[t.code] ?? 1])
        ),
    };
}

export default function Configuracion({ settings, correlativos, currentYear, availableCustomers = [] }) {
    const { auth } = usePage().props;
    const [unlocked, setUnlocked]     = useState(false);
    const [activities, setActivities] = useState([]);
    const [geo, setGeo]               = useState({ departamentos: [] });
    const [testMsg, setTestMsg]       = useState(null);
    const [testOk, setTestOk]         = useState(null);
    const logoInputRef                = useRef(null);
    const certInputRef                = useRef(null);

    const form = useForm(buildInitialForm(settings, correlativos));

    useEffect(() => {
        fetch('/catalogs/actividades-economicas.json')
            .then((r) => r.json())
            .then((data) => setActivities(Array.isArray(data) ? data : []))
            .catch(() => {});
        fetch('/catalogs/division-geografica.json')
            .then((r) => r.json())
            .then((data) => setGeo(data ?? { departamentos: [] }))
            .catch(() => {});
    }, []);

    function setE(field, value) { form.setData('emisor',   { ...form.data.emisor,   [field]: value }); }
    function setH(field, value) { form.setData('hacienda', { ...form.data.hacienda, [field]: value }); }
    function setF(field, value) { form.setData('firma',    { ...form.data.firma,    [field]: value }); }
    function setC(field, value) { form.setData('correo',   { ...form.data.correo,   [field]: value }); }
    function setB(field, value) { form.setData('backup',   { ...form.data.backup,   [field]: value }); }
    function setCorrelativo(code, value) {
        form.setData('correlativos', { ...form.data.correlativos, [code]: Number(value) || 1 });
    }
    function toggleDoc(code) {
        const current = form.data.documentos;
        form.setData('documentos', current.includes(code) ? current.filter((c) => c !== code) : [...current, code]);
    }

    // Geo cascade
    const depts = geo.departamentos ?? [];
    const selectedDept = depts.find((d) => d.codigo === form.data.emisor.departamento);
    const munis = selectedDept?.municipios ?? [];
    const selectedMuni = munis.find((m) => m.codigo === form.data.emisor.municipio);
    const districts = selectedMuni?.distritos ?? [];

    function handleDeptChange(e) {
        setE('departamento', e.target.value);
        setE('municipio', '');
        setE('distrito', '');
    }
    function handleMuniChange(e) {
        setE('municipio', e.target.value);
        setE('distrito', '');
    }

    // Activity datalist
    const activityListId = 'billing-activities-list';

    // File uploads
    async function uploadLogo(e) {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('logo', file);
        fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
        try {
            const res = await fetch(route('admin.factura-sv.logo.subir'), { method: 'POST', body: fd });
            const data = await res.json();
            if (data.path) setE('logo_path', data.path);
        } catch { /* ignore */ }
    }

    async function uploadCert(e) {
        const file = e.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('certificado', file);
        fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
        try {
            const res = await fetch(route('admin.factura-sv.certificado.subir'), { method: 'POST', body: fd });
            const data = await res.json();
            if (data.path) setF('certificado_path', data.path);
        } catch { /* ignore */ }
    }

    // Test signer
    async function testSigner() {
        setTestMsg(null);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const res = await fetch(route('admin.factura-sv.firmador.estado'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    certificadoPath: form.data.firma.certificado_path,
                    nit: form.data.firma.firmadorUsuario,
                    passwordPri: form.data.firma.password,
                }),
            });
            const data = await res.json();
            setTestOk(data.success ?? false);
            setTestMsg(data.message ?? JSON.stringify(data));
        } catch (err) {
            setTestOk(false);
            setTestMsg('Error: ' + err.message);
        }
    }

    // Test Hacienda
    async function testHacienda() {
        setTestMsg(null);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const res = await fetch(route('admin.factura-sv.autenticar'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    config: {
                        ambiente: form.data.hacienda.ambiente,
                        usuario: form.data.hacienda.usuario,
                        password: form.data.hacienda.password,
                    },
                }),
            });
            const data = await res.json();
            setTestOk(data.success ?? false);
            setTestMsg(data.message ?? JSON.stringify(data));
        } catch (err) {
            setTestOk(false);
            setTestMsg('Error: ' + err.message);
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        const payload = {
            emisor: {
                ...form.data.emisor,
                codigo_establecimiento: form.data.emisor.codigo_establecimiento.toUpperCase(),
                punto_venta: form.data.emisor.punto_venta.toUpperCase(),
            },
            hacienda: { ...form.data.hacienda },
            firma: {
                tipo: form.data.firma.tipo,
                firmador_usuario: form.data.firma.firmadorUsuario,
                firmador_url: form.data.firma.firmadorUrl,
                certificado_path: form.data.firma.certificado_path,
                certificado_password: form.data.firma.password,
                firmador_pin: form.data.firma.password,
            },
            correo: { ...form.data.correo },
            backup: { ...form.data.backup },
            documentos: form.data.documentos,
            correlativos: form.data.correlativos,
        };
        form.transform(() => payload).post(route('admin.factura-sv.configuracion.guardar'));
    }

    const disabled = !unlocked;
    const gridStyle = { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 12 };
    const firmaTipo = form.data.firma.tipo;

    return (
        <AppLayout>
            <Head title="Configuración facturación" />

            <div className="top">
                <h1>Configuración facturación</h1>
            </div>

            {/* Lock panel */}
            <div className="card" style={{ marginBottom: 16, display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16 }}>
                <div>
                    <p style={{ margin: 0, fontWeight: 600 }}>
                        {unlocked ? 'Configuración desbloqueada' : 'Configuración bloqueada'}
                    </p>
                    <p style={{ margin: '2px 0 0', fontSize: 12, color: '#6b7280' }}>
                        {unlocked
                            ? 'Edita solo si vas a corregir datos fiscales, conexión, firma o correlativos.'
                            : 'Los campos permanecen protegidos para evitar cambios accidentales.'}
                    </p>
                </div>
                <button
                    type="button"
                    className={`btn${unlocked ? '' : ' secondary'}`}
                    style={{ whiteSpace: 'nowrap' }}
                    onClick={() => setUnlocked((v) => !v)}
                >
                    {unlocked ? '🔓 Bloquear' : '🔒 Desbloquear'}
                </button>
            </div>

            {unlocked && (
                <div style={{ background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: 6, padding: '12px 16px', marginBottom: 16 }}>
                    <p style={{ margin: '0 0 6px', fontWeight: 700, color: '#dc2626' }}>Advertencia de configuración activa</p>
                    <p style={{ margin: '0 0 6px', fontSize: 13, color: '#7f1d1d' }}>Cambiar estos datos puede provocar rechazos de Hacienda, errores de firma o números de control incorrectos.</p>
                    <ul style={{ margin: 0, paddingLeft: 20, fontSize: 12, color: '#7f1d1d' }}>
                        <li>Verifica NIT, NRC, actividad económica y dirección antes de emitir documentos.</li>
                        <li>No alteres correlativos salvo que Hacienda indique que el número ya existe.</li>
                        <li>Si cambias credenciales o certificado, prueba Hacienda y el firmador antes de generar facturas.</li>
                    </ul>
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <div className="card" style={{ marginBottom: 16 }}>
                    <fieldset disabled={disabled} style={{ border: 'none', padding: 0, margin: 0 }}>

                        {/* ── Empresa ── */}
                        <SectionTitle>Información de la Empresa</SectionTitle>
                        <div style={gridStyle}>
                            <Field label="NIT o DUI *">
                                <input value={form.data.emisor.nit} onChange={(e) => setE('nit', e.target.value)} placeholder="NIT o DUI del emisor" required />
                            </Field>
                            <Field label="NRC">
                                <input value={form.data.emisor.nrc} onChange={(e) => setE('nrc', e.target.value)} placeholder="000000-0" />
                            </Field>
                            <Field label="Nombre de la Empresa *">
                                <input value={form.data.emisor.nombre_empresa} onChange={(e) => setE('nombre_empresa', e.target.value)} required />
                            </Field>
                            <Field label="Nombre Comercial">
                                <input value={form.data.emisor.nombre_comercial} onChange={(e) => setE('nombre_comercial', e.target.value)} />
                            </Field>
                            <Field label="Tipo de Persona">
                                <select value={form.data.emisor.tipo_persona} onChange={(e) => setE('tipo_persona', e.target.value)}>
                                    <option value="">Seleccionar…</option>
                                    <option value="Natural">Natural</option>
                                    <option value="Jurídica">Jurídica</option>
                                </select>
                            </Field>
                            <Field label="Teléfono">
                                <input type="tel" value={form.data.emisor.telefono} onChange={(e) => setE('telefono', e.target.value)} />
                            </Field>
                            <Field label="Email">
                                <input type="email" value={form.data.emisor.email} onChange={(e) => setE('email', e.target.value)} />
                            </Field>
                            <Field label="Código Establecimiento">
                                <input value={form.data.emisor.codigo_establecimiento} onChange={(e) => setE('codigo_establecimiento', e.target.value.toUpperCase())} maxLength={4} />
                            </Field>
                            <Field label="Punto de Venta">
                                <input value={form.data.emisor.punto_venta} onChange={(e) => setE('punto_venta', e.target.value.toUpperCase())} maxLength={4} />
                            </Field>
                            <Field label="Actividad Económica" full>
                                <input
                                    value={form.data.emisor.actividad_economica}
                                    onChange={(e) => setE('actividad_economica', e.target.value)}
                                    list={activityListId}
                                    placeholder="Buscar por código o descripción…"
                                />
                                <datalist id={activityListId}>
                                    {activities.map((a) => (
                                        <option key={a.codigo} value={`${a.codigo} - ${a.descripcion}`} />
                                    ))}
                                </datalist>
                            </Field>
                            <Field label="Departamento">
                                {depts.length > 0 ? (
                                    <select value={form.data.emisor.departamento} onChange={handleDeptChange}>
                                        <option value="">Seleccionar…</option>
                                        {depts.map((d) => (
                                            <option key={d.codigo} value={d.codigo}>{d.codigo} - {d.nombre}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <input value={form.data.emisor.departamento} onChange={(e) => setE('departamento', e.target.value)} placeholder="Código dept." />
                                )}
                            </Field>
                            <Field label="Municipio">
                                {munis.length > 0 ? (
                                    <select value={form.data.emisor.municipio} onChange={handleMuniChange}>
                                        <option value="">Seleccionar…</option>
                                        {munis.map((m) => (
                                            <option key={m.codigo} value={m.codigo}>{m.codigo} - {m.nombre}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <input value={form.data.emisor.municipio} onChange={(e) => setE('municipio', e.target.value)} placeholder="Código municipio" />
                                )}
                            </Field>
                            <Field label="Distrito">
                                {districts.length > 0 ? (
                                    <select value={form.data.emisor.distrito} onChange={(e) => setE('distrito', e.target.value)}>
                                        <option value="">Seleccionar…</option>
                                        {districts.map((d) => (
                                            <option key={d.codigo} value={d.codigo}>{d.codigo} - {d.nombre}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <input value={form.data.emisor.distrito} onChange={(e) => setE('distrito', e.target.value)} placeholder="Código distrito" />
                                )}
                            </Field>
                            <Field label="Dirección Complementaria *" full>
                                <textarea value={form.data.emisor.direccion} onChange={(e) => setE('direccion', e.target.value)} rows={2} placeholder="Dirección completa" required />
                            </Field>
                            <Field label="Imagen para PDF (logo_path)" full>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                    <input value={form.data.emisor.logo_path} readOnly placeholder="/storage/logos/logo.png" style={{ flex: 1 }} />
                                    <input ref={logoInputRef} type="file" accept=".png,.jpg,.jpeg" style={{ display: 'none' }} onChange={uploadLogo} />
                                    <button type="button" className="btn secondary" style={{ whiteSpace: 'nowrap' }} onClick={() => logoInputRef.current?.click()}>
                                        Seleccionar
                                    </button>
                                    <button type="button" className="btn secondary" onClick={() => setE('logo_path', '')}>
                                        Quitar
                                    </button>
                                </div>
                                <small style={{ fontSize: 11, color: '#6b7280' }}>Formatos: PNG, JPG o JPEG. Se muestra en el encabezado del PDF.</small>
                            </Field>
                        </div>

                        {/* ── Hacienda ── */}
                        <SectionTitle>Configuración de Hacienda</SectionTitle>
                        <div style={gridStyle}>
                            <Field label="Usuario Hacienda">
                                <input value={form.data.hacienda.usuario} onChange={(e) => setH('usuario', e.target.value)} autoComplete="off" />
                            </Field>
                            <Field label="Contraseña Hacienda">
                                <input type="password" value={form.data.hacienda.password} onChange={(e) => setH('password', e.target.value)} autoComplete="off" />
                            </Field>
                            <Field label="Ambiente">
                                <select value={form.data.hacienda.ambiente} onChange={(e) => setH('ambiente', e.target.value)}>
                                    <option value="00">Pruebas</option>
                                    <option value="01">Producción</option>
                                </select>
                            </Field>
                        </div>

                        {/* ── Documentos ── */}
                        <SectionTitle>Documentos a Generar</SectionTitle>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: '8px 16px', marginBottom: 8 }}>
                            {DTE_TYPES.map((t) => (
                                <label key={t.code} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: disabled ? 'not-allowed' : 'pointer' }}>
                                    <input
                                        type="checkbox"
                                        checked={form.data.documentos.includes(t.code)}
                                        onChange={() => toggleDoc(t.code)}
                                    />
                                    <span style={{ fontFamily: 'monospace', fontWeight: 700, minWidth: 24 }}>{t.code}</span>
                                    <span>{t.label}</span>
                                </label>
                            ))}
                        </div>
                        <p style={{ fontSize: 12, color: '#6b7280', marginTop: 4 }}>
                            Los tipos seleccionados aparecerán en la creación de documentos y el filtro de facturas.
                        </p>

                        {/* ── Firma digital ── */}
                        <SectionTitle>Método de Firma Digital</SectionTitle>
                        <div style={gridStyle}>
                            <Field label="Tipo de Firma" full>
                                <select value={form.data.firma.tipo} onChange={(e) => setF('tipo', e.target.value)}>
                                    <option value="svfe">Firmador local MH/SVFE (Recomendado para pruebas)</option>
                                    <option value="web">Firmador Web del MH (experimental)</option>
                                    <option value="local">Certificado Local (.p12/.pfx)</option>
                                </select>
                            </Field>
                            {['svfe', 'web'].includes(firmaTipo) && (
                                <>
                                    <Field label="NIT del certificado">
                                        <input value={form.data.firma.firmadorUsuario} onChange={(e) => setF('firmadorUsuario', e.target.value)} placeholder="NIT sin guiones" />
                                    </Field>
                                    <Field label="URL Firmador SVFE">
                                        <input value={form.data.firma.firmadorUrl} onChange={(e) => setF('firmadorUrl', e.target.value)} placeholder="http://localhost:8113" />
                                    </Field>
                                    <Field label="Contraseña llave privada">
                                        <input type="password" value={form.data.firma.password} onChange={(e) => setF('password', e.target.value)} placeholder="Contraseña del certificado MH" autoComplete="off" />
                                    </Field>
                                </>
                            )}
                            {firmaTipo === 'local' && (
                                <>
                                    <Field label="Ruta del Certificado (.p12, .pfx)" full>
                                        <div style={{ display: 'flex', gap: 8 }}>
                                            <input value={form.data.firma.certificado_path} readOnly placeholder="/ruta/al/certificado.p12" style={{ flex: 1 }} />
                                            <input ref={certInputRef} type="file" accept=".p12,.pfx,.crt" style={{ display: 'none' }} onChange={uploadCert} />
                                            <button type="button" className="btn secondary" onClick={() => certInputRef.current?.click()}>Seleccionar</button>
                                        </div>
                                    </Field>
                                    <Field label="Contraseña del Certificado">
                                        <input type="password" value={form.data.firma.password} onChange={(e) => setF('password', e.target.value)} placeholder="Contraseña del certificado" autoComplete="off" />
                                    </Field>
                                </>
                            )}
                        </div>
                        <p style={{ fontSize: 12, color: '#6b7280' }}>Complete los datos del firmador o certificado antes de probar firma y enviar documentos.</p>

                        {/* ── Correo ── */}
                        <SectionTitle>Correo Electrónico</SectionTitle>
                        <InfoBox>Para Gmail usa smtp.gmail.com, puerto 465, SSL activo y una contraseña de aplicación de Google.</InfoBox>
                        <div style={gridStyle}>
                            <Field label="Servidor SMTP">
                                <input value={form.data.correo.smtpHost} onChange={(e) => setC('smtpHost', e.target.value)} placeholder="smtp.gmail.com" />
                            </Field>
                            <Field label="Puerto SMTP">
                                <input value={form.data.correo.smtpPort} onChange={(e) => setC('smtpPort', e.target.value)} placeholder="465" />
                            </Field>
                            <Field label="Seguridad">
                                <select value={form.data.correo.smtpSecure ? 'true' : 'false'} onChange={(e) => setC('smtpSecure', e.target.value === 'true')}>
                                    <option value="true">SSL/TLS</option>
                                    <option value="false">STARTTLS</option>
                                </select>
                            </Field>
                            <Field label="Usuario correo">
                                <input type="email" value={form.data.correo.username} onChange={(e) => setC('username', e.target.value)} placeholder="tu-correo@gmail.com" />
                            </Field>
                            <Field label="Correo remitente">
                                <input value={form.data.correo.from} onChange={(e) => setC('from', e.target.value)} placeholder="facturacion@empresa.com" />
                            </Field>
                            <Field label="Nombre remitente">
                                <input value={form.data.correo.fromName} onChange={(e) => setC('fromName', e.target.value)} placeholder="Nombre de la empresa" />
                            </Field>
                            <Field label="Contraseña aplicación">
                                <input type="password" value={form.data.correo.password} onChange={(e) => setC('password', e.target.value)} autoComplete="off" />
                            </Field>
                        </div>

                        {/* ── Backup ── */}
                        <SectionTitle>Backup en Servidor</SectionTitle>
                        <InfoBox>La app subirá un backup comprimido de la base local. El servidor debe sobrescribir el último archivo recibido.</InfoBox>
                        <div style={gridStyle}>
                            <Field label="URL del servidor" full>
                                <input type="url" value={form.data.backup.url} onChange={(e) => setB('url', e.target.value)} placeholder="https://tudominio.com/api/backups/latest" />
                            </Field>
                            <Field label="Token de seguridad">
                                <input type="password" value={form.data.backup.token} onChange={(e) => setB('token', e.target.value)} placeholder="Token Bearer del servidor" />
                            </Field>
                            <Field label="Clave de cifrado">
                                <input type="password" value={form.data.backup.encryptionKey} onChange={(e) => setB('encryptionKey', e.target.value)} placeholder="Clave privada para cifrar backup" />
                            </Field>
                            <Field label="Backup automático">
                                <select value={form.data.backup.automatico ? 'true' : 'false'} onChange={(e) => setB('automatico', e.target.value === 'true')}>
                                    <option value="false">Manual</option>
                                    <option value="true">Diario al abrir la app</option>
                                </select>
                            </Field>
                            <Field label="Último backup">
                                <input value={form.data.backup.ultimo} readOnly />
                            </Field>
                        </div>

                        {/* ── Correlativos ── */}
                        <SectionTitle>Correlativos {currentYear}</SectionTitle>
                        <div className="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Año</th>
                                        <th>Serie</th>
                                        <th style={{ textAlign: 'right' }}>Último local</th>
                                        <th>Siguiente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {DTE_TYPES.map((t) => {
                                        const next = Number(form.data.correlativos[t.code] ?? 1);
                                        const serie = `${form.data.emisor.codigo_establecimiento.toUpperCase()}${form.data.emisor.punto_venta.toUpperCase()}`;
                                        return (
                                            <tr key={t.code}>
                                                <td>
                                                    <span style={{ fontFamily: 'monospace', fontWeight: 700 }}>{t.code}</span>
                                                    <div style={{ fontSize: 11, color: '#6b7280' }}>{t.label}</div>
                                                </td>
                                                <td>{currentYear}</td>
                                                <td style={{ fontFamily: 'monospace', fontSize: 12 }}>{serie}</td>
                                                <td style={{ textAlign: 'right', fontFamily: 'monospace' }}>{Math.max(0, next - 1)}</td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={next}
                                                        onChange={(e) => setCorrelativo(t.code, e.target.value)}
                                                        style={{ width: 80, fontFamily: 'monospace' }}
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <p style={{ fontSize: 12, color: '#6b7280', marginTop: 8 }}>
                            Suba el siguiente correlativo cuando Hacienda indique que el número ya existe.
                        </p>

                    </fieldset>
                </div>

                {/* Test result */}
                {testMsg && (
                    <div style={{ marginBottom: 12, padding: '8px 14px', borderRadius: 6, background: testOk ? '#f0fdf4' : '#fef2f2', color: testOk ? '#15803d' : '#dc2626', fontSize: 13, border: `1px solid ${testOk ? '#bbf7d0' : '#fca5a5'}` }}>
                        {testMsg}
                    </div>
                )}

                {/* Actions */}
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                    <button type="button" className="btn secondary" disabled={disabled} onClick={testSigner}>
                        Probar firmador local
                    </button>
                    <button type="button" className="btn secondary" disabled={disabled} onClick={testHacienda}>
                        Probar Hacienda
                    </button>
                    <button type="submit" className="btn" disabled={disabled || form.processing}>
                        {form.processing ? 'Guardando…' : 'Guardar Configuración'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
