import { useState, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout';

// ─── Constants ───────────────────────────────────────────────────────────────

const DTE_TYPES = [
    { codigo: '01', nombre: 'Factura' },
    { codigo: '03', nombre: 'Comprobante de Crédito Fiscal' },
    { codigo: '04', nombre: 'Nota de Remisión' },
    { codigo: '05', nombre: 'Nota de Crédito' },
    { codigo: '06', nombre: 'Nota de Débito' },
    { codigo: '07', nombre: 'Comprobante de Retención' },
    { codigo: '08', nombre: 'Comprobante de Liquidación' },
    { codigo: '11', nombre: 'Factura de Exportación' },
    { codigo: '14', nombre: 'Factura de Sujeto Excluido' },
    { codigo: '15', nombre: 'Comprobante de Donación' },
];

const RELATED_DOC_TYPES = [
    { codigo: '03', nombre: 'Comprobante de Crédito Fiscal' },
    { codigo: '01', nombre: 'Factura' },
    { codigo: '04', nombre: 'Nota de Remisión' },
    { codigo: '07', nombre: 'Comprobante de Retención' },
    { codigo: '11', nombre: 'Factura de Exportación' },
    { codigo: '14', nombre: 'Sujeto Excluido' },
];

const INCOTERM_OPTIONS = [
    { value: '01', label: 'EXW - En fábrica' },
    { value: '02', label: 'FCA - Franco transportista' },
    { value: '03', label: 'FAS - Franco al costado del buque' },
    { value: '04', label: 'FOB - Franco a bordo' },
    { value: '05', label: 'CFR - Costo y flete' },
    { value: '06', label: 'CIF - Costo, seguro y flete' },
    { value: '07', label: 'CPT - Transporte pagado hasta' },
    { value: '08', label: 'CIP - Transporte y seguro pagados hasta' },
    { value: '09', label: 'DAP - Entregado en lugar' },
    { value: '10', label: 'DPU - Entregado en lugar descargado' },
    { value: '11', label: 'DDP - Entregado derechos pagados' },
];

const RECINTOS_FISCALES = ['01','02','03','04','05','09','10','11','15','16','18','21','31','76','99'];
const REGIMENES = [
    'EX-1.1000.000','EX-1.1040.000','EX-1.1054.000','EX-1.1100.000','EX-1.1200.000',
    'EX-1.1300.000','EX-1.1400.000','EX-1.1500.000','EX-2.2100.000','EX-2.2200.000',
    'EX-3.3050.000','EX-3.3054.000',
];

const EMPTY_CLIENTE = {
    tipo_documento: '', numero_documento: '', nrc: '', nombre: '', nombre_comercial: '',
    giro: '', desc_actividad: '', email: '', telefono: '', direccion: '',
    departamento: '', municipio: '', pais: '', nombre_pais: '',
};

// ─── Math helpers ─────────────────────────────────────────────────────────────

function roundMoney(v) { return Math.round((Number(v || 0) + Number.EPSILON) * 100) / 100; }
function money(v) { return `$${roundMoney(v).toFixed(2)}`; }

function lineGross(item) { return Number(item.cantidad || 0) * Number(item.precio_unitario || 0); }
function lineBase(item) { return Math.max(0, roundMoney(lineGross(item) - Number(item.descuento || 0))); }
function lineIva(item, tipo) {
    return item.exento || ['07', '11', '14', '15'].includes(tipo) ? 0 : roundMoney(lineBase(item) * 0.13);
}
function lineTotal(item, tipo) { return roundMoney(lineBase(item) + lineIva(item, tipo)); }

function computeTotals(items, factura, tipo, retencion, opciones) {
    const subtotalBruto = items.reduce((s, i) => s + lineGross(i), 0);
    const descuentoGeneralAplicado = (() => {
        const val = Number(factura.descuentoGeneral || 0);
        if (factura.descuentoTipo === 'porcentaje') return roundMoney(subtotalBruto * (val / 100));
        return roundMoney(Math.min(val, subtotalBruto));
    })();
    const distributeDiscount = (exempt) => {
        if (!subtotalBruto) return 0;
        const base = items.reduce((s, i) => s + (i.exento === exempt ? lineBase(i) : 0), 0);
        return roundMoney(descuentoGeneralAplicado * (base / subtotalBruto));
    };
    const dscGravado = distributeDiscount(false);
    const dscExento = distributeDiscount(true);
    const subtotalGravado = Math.max(0, items.reduce((s, i) => s + (i.exento ? 0 : lineBase(i)), 0) - dscGravado);
    const subtotalExento = Math.max(0, items.reduce((s, i) => s + (i.exento ? lineBase(i) : 0), 0) - dscExento);
    const subtotalTotal = roundMoney(subtotalGravado + subtotalExento);
    const ivaEstimado = ['07', '11', '14', '15'].includes(tipo) ? 0 : roundMoney(subtotalGravado * 0.13);
    const retencionCalculada = roundMoney(Number(retencion.montoSujeto || 0) * (Number(retencion.porcentaje || 0) / 100));
    const exportExtras = tipo === '11' && Number(opciones.tipoItemExpor) !== 2
        ? Number(opciones.flete || 0) + Number(opciones.seguro || 0) : 0;
    const totalEstimado = tipo === '07' ? retencionCalculada
        : roundMoney(Math.max(0, subtotalTotal + ivaEstimado + exportExtras + Number(factura.ivaPerci1 || 0) - Number(factura.ivaRete1 || 0) - Number(factura.reteRenta || 0)));

    return { subtotalBruto, subtotalGravado, subtotalExento, subtotalTotal, ivaEstimado,
        descuentoGeneralAplicado, retencionCalculada, totalEstimado };
}

function discountForItemForm(form) {
    const base = Number(form.cantidad || 0) * Number(form.precioUnitario || 0);
    const val = Number(form.valorDescuento || 0);
    return form.tipoDescuento === 'porcentaje'
        ? roundMoney(Math.min(base, base * (val / 100)))
        : roundMoney(Math.min(base, val));
}

function normalizeCodeValue(v) {
    return String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '').padStart(4, '0').slice(0, 4);
}

function incotermDescription(code) {
    return { '01':'EXW','02':'FCA','03':'FAS','04':'FOB','05':'CFR','06':'CIF',
        '07':'CPT','08':'CIP','09':'DAP','10':'DPU','11':'DDP' }[String(code || '01')] || 'EXW';
}

function cleanDigits(v) { return String(v || '').replace(/\D/g, ''); }

// ─── Combobox ─────────────────────────────────────────────────────────────────

function Combobox({ options, value, search, onSearch, onSelect, onClear, placeholder, renderLabel, renderMeta }) {
    const [open, setOpen] = useState(false);
    const closeTimer = useRef(null);

    const filtered = options.filter((o) => {
        const term = search.trim().toLowerCase();
        return !term || renderLabel(o).toLowerCase().includes(term) || (renderMeta?.(o) || '').toLowerCase().includes(term);
    });

    function scheduleClose() { closeTimer.current = setTimeout(() => setOpen(false), 200); }
    function cancelClose() { if (closeTimer.current) clearTimeout(closeTimer.current); }

    return (
        <div className={`combobox${open ? ' open' : ''}`}>
            <div className="combobox-inner">
                <input
                    className="combobox-input"
                    type="text"
                    autoComplete="off"
                    placeholder={placeholder}
                    value={search}
                    onFocus={() => setOpen(true)}
                    onBlur={scheduleClose}
                    onChange={(e) => { onSearch(e.target.value); setOpen(true); }}
                />
                {value && (
                    <button className="combobox-clear" type="button" onMouseDown={(e) => { e.preventDefault(); cancelClose(); onClear(); }}>×</button>
                )}
                <span className="combobox-arrow" onMouseDown={(e) => { e.preventDefault(); cancelClose(); setOpen((v) => !v); }}>▾</span>
            </div>
            {open && (
                <ul className="combobox-list">
                    {!filtered.length && <li className="combobox-empty">Sin resultados</li>}
                    {filtered.map((o) => (
                        <li
                            key={o.id ?? o.codigo ?? o.code}
                            className={`combobox-option${String(value) === String(o.id ?? o.codigo ?? o.code) ? ' selected' : ''}`}
                            onMouseDown={(e) => { e.preventDefault(); cancelClose(); onSelect(o); setOpen(false); }}
                        >
                            <span className="combobox-option-name">{renderLabel(o)}</span>
                            {renderMeta && <span className="combobox-option-meta">{renderMeta(o)}</span>}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

// ─── Progress Overlay ─────────────────────────────────────────────────────────

const STEP_ICON = { done: '✓', error: '✗', warning: '⚠' };

function ProgressOverlay({ overlay }) {
    if (!overlay.visible) return null;
    const pct = (() => {
        const total = Math.max(overlay.steps.length, 1);
        const done = Math.max(overlay.current + 1, 0);
        return `${Math.round((done / total) * 100)}%`;
    })();
    return (
        <div className="modal-backdrop">
            <div className="modal-content" style={{ maxWidth: 480 }}>
                <div className="modal-header">
                    <h3>{overlay.title}</h3>
                    <span className={`badge ${overlay.status === 'success' ? 'badge-ok' : overlay.status === 'error' ? 'badge-error' : 'badge-pending'}`}>
                        {overlay.badge}
                    </span>
                </div>
                <p style={{ margin: '8px 0 12px', color: 'var(--muted)' }}>{overlay.subtitle}</p>
                <div style={{ background: 'var(--line)', borderRadius: 4, height: 6, marginBottom: 16 }}>
                    <div style={{ background: 'var(--brand)', borderRadius: 4, height: 6, width: pct, transition: 'width .3s' }} />
                </div>
                <ol style={{ listStyle: 'none', padding: 0, margin: 0 }}>
                    {overlay.steps.map((step, i) => (
                        <li key={i} style={{ display: 'flex', gap: 8, padding: '4px 0', alignItems: 'flex-start' }}>
                            <span style={{ minWidth: 20, textAlign: 'center', color: step.status === 'done' ? 'var(--ok)' : step.status === 'error' ? 'var(--bad)' : 'var(--muted)' }}>
                                {STEP_ICON[step.status] ?? (i === overlay.current ? '…' : '○')}
                            </span>
                            <span>{step.label}{step.message ? ` — ${step.message}` : ''}</span>
                        </li>
                    ))}
                </ol>
            </div>
        </div>
    );
}

// ─── Item Modal ───────────────────────────────────────────────────────────────

function ItemModal({ products, hasInventory, relatedDocs, usesMultipleRelated, tipo, onAdd, onClose }) {
    const [form, setForm] = useState({
        productId: '', descripcion: '', tipoItem: 2, cantidad: 1,
        precioUnitario: 0, tipoDescuento: 'monto', valorDescuento: 0,
        numeroDocumentoRelacionado: '',
    });
    const [productSearch, setProductSearch] = useState('');

    const pool = hasInventory ? products : products.filter((p) => String(p.type) === '2');
    const discount = discountForItemForm(form);

    function selectProduct(p) {
        setProductSearch(`${p.code} - ${p.description}`);
        setForm((f) => ({ ...f, productId: p.id, descripcion: p.description, precioUnitario: Number(p.price || 0), tipoItem: 2 }));
    }
    function clearProduct() {
        setProductSearch('');
        setForm((f) => ({ ...f, productId: '', descripcion: '', precioUnitario: 0 }));
    }
    function set(field, value) { setForm((f) => ({ ...f, [field]: value })); }

    function handleAdd() {
        const product = pool.find((p) => String(p.id) === String(form.productId));
        if (!product) return;
        onAdd({
            codigo: product.code,
            descripcion: hasInventory ? product.description : (form.descripcion.trim() || product.description),
            tipo_item: hasInventory ? Number(product.type || 2) : Number(form.tipoItem || 2),
            cantidad: Number(form.cantidad || 1),
            precio_unitario: Number(form.precioUnitario || 0),
            unidad_medida: product.unit,
            exento: Boolean(product.isExempt),
            descuento: discount,
            tipoDescuento: form.tipoDescuento,
            valorDescuento: Number(form.valorDescuento || 0),
            numeroDocumentoRelacionado: form.numeroDocumentoRelacionado || null,
        });
    }

    return (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="modal-content">
                <div className="modal-header">
                    <h3>Agregar Producto a Factura</h3>
                    <button className="btn secondary" type="button" onClick={onClose}>Cerrar</button>
                </div>
                <div className="form-grid">
                    <div className="full-width">
                        <label>Producto</label>
                        <Combobox
                            options={pool}
                            value={form.productId}
                            search={productSearch}
                            onSearch={(v) => { setProductSearch(v); if (form.productId) clearProduct(); }}
                            onSelect={selectProduct}
                            onClear={clearProduct}
                            placeholder="Buscar por código o descripción..."
                            renderLabel={(p) => p.description}
                            renderMeta={(p) => p.code}
                        />
                    </div>
                    {!hasInventory && (
                        <>
                            <label className="full-width">Descripción
                                <input value={form.descripcion} onChange={(e) => set('descripcion', e.target.value)} placeholder="Descripción del ítem en la factura" />
                            </label>
                            <label className="full-width">Tipo de ítem
                                <select value={form.tipoItem} onChange={(e) => set('tipoItem', Number(e.target.value))}>
                                    <option value={2}>Servicio</option>
                                    <option value={1}>Bien</option>
                                </select>
                            </label>
                        </>
                    )}
                    <label className="full-width">Cantidad
                        <input type="number" min="0.000001" step="0.01" value={form.cantidad} onChange={(e) => set('cantidad', e.target.value)} />
                    </label>
                    <label className="full-width">Precio Unitario
                        <input type="number" min="0" step="0.01" value={form.precioUnitario} onChange={(e) => set('precioUnitario', e.target.value)} />
                    </label>
                    <label className="full-width">Descuento
                        <select value={form.tipoDescuento} onChange={(e) => set('tipoDescuento', e.target.value)}>
                            <option value="monto">Monto ($)</option>
                            <option value="porcentaje">Porcentaje (%)</option>
                        </select>
                    </label>
                    <label className="full-width">Valor descuento
                        <input type="number" min="0" step="0.01" value={form.valorDescuento} onChange={(e) => set('valorDescuento', e.target.value)} />
                    </label>
                    {usesMultipleRelated && (
                        <label className="full-width">CCF relacionado
                            <select value={form.numeroDocumentoRelacionado} onChange={(e) => set('numeroDocumentoRelacionado', e.target.value)}>
                                <option value="">Seleccionar CCF...</option>
                                {relatedDocs.map((d) => (
                                    <option key={d.numeroDocumento} value={d.numeroDocumento}>{d.numeroDocumento}</option>
                                ))}
                            </select>
                        </label>
                    )}
                    <div className="full-width" style={{ color: 'var(--muted)', fontSize: '0.85em' }}>
                        Subtotal: {money(lineTotal({ cantidad: form.cantidad, precio_unitario: form.precioUnitario, descuento: discount, exento: false }, tipo))}
                    </div>
                </div>
                <div className="actions wrap modal-actions">
                    <button className="btn secondary" type="button" onClick={onClose}>Cancelar</button>
                    <button className="btn" type="button" onClick={handleAdd} disabled={!form.productId}>Agregar a Factura</button>
                </div>
            </div>
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

const STEP_LABELS = [
    'Validando datos del cliente y documento',
    'Creando factura local',
    'Firmando documento',
    'Enviando a Hacienda',
    'Esperando respuesta de Hacienda',
    'Generando PDF y JSON para correo',
    'Enviando correo',
];

export default function NuevaFactura({ customers, products, settings, correlativos, clientesVariosId, hasInventory }) {
    const enabledDteTypes = (() => {
        const docs = settings.documentos || [];
        const filtered = docs.length ? DTE_TYPES.filter((t) => docs.includes(t.codigo)) : DTE_TYPES;
        return filtered.length ? filtered : DTE_TYPES;
    })();

    // ── Customer state ──
    const [customerSearch, setCustomerSearch] = useState('');
    const [selectedCustomer, setSelectedCustomer] = useState(null);
    const [clienteData, setClienteData] = useState({ ...EMPTY_CLIENTE });

    // ── Invoice state ──
    const [tipo, setTipo] = useState(enabledDteTypes[0]?.codigo || '01');
    const [factura, setFactura] = useState({ condicionOperacion: 1, descuentoTipo: 'monto', descuentoGeneral: 0, ivaRete1: 0, ivaPerci1: 0, reteRenta: 0, notas: '' });
    const [items, setItems] = useState([]);
    const [showItemModal, setShowItemModal] = useState(false);

    // ── Documento relacionado ──
    const [docRelacionado, setDocRelacionado] = useState({ tipoDocumento: '03', tipoGeneracion: 2, numeroDocumento: '', fechaEmision: '' });
    const [docRelacionados, setDocRelacionados] = useState([]);

    // ── Retención (tipo 07) ──
    const [retencion, setRetencion] = useState({ codigo: '22', montoSujeto: 0, porcentaje: 1 });

    // ── Exportación (tipo 11) ──
    const [opciones, setOpciones] = useState({ tipoItemExpor: 2, recintoFiscal: '', regimen: '', codIncoterms: '01', flete: 0, seguro: 0 });

    // ── UI state ──
    const [formError, setFormError] = useState('');
    const [overlay, setOverlay] = useState({ visible: false, title: '', subtitle: '', badge: 'En proceso', status: '', current: -1, steps: [] });
    const [result, setResult] = useState(null);

    const usesMultipleRelated = ['05', '06'].includes(tipo);
    const needsRelated = ['05', '06', '07'].includes(tipo);

    const totals = computeTotals(items, factura, tipo, retencion, opciones);

    // ── Derived related doc types ──
    const relatedDocTypesForCurrent = tipo === '07'
        ? RELATED_DOC_TYPES.filter((t) => ['01', '03', '14'].includes(t.codigo))
        : usesMultipleRelated ? RELATED_DOC_TYPES.filter((t) => t.codigo === '03')
        : RELATED_DOC_TYPES;

    // ── Customer handlers ──
    function selectCustomer(customer) {
        setSelectedCustomer(customer);
        setCustomerSearch(customer.name);
        setClienteData({ ...EMPTY_CLIENTE, ...(customer.receptor || {}) });
        if (customer.preferredDteType) setTipo(customer.preferredDteType);
    }
    function clearCustomer() {
        setSelectedCustomer(null);
        setCustomerSearch('');
        setClienteData({ ...EMPTY_CLIENTE });
    }

    // ── DTE type change ──
    function handleDteTypeChange(newTipo) {
        setTipo(newTipo);
        if (newTipo === '07' && !['01', '03', '14'].includes(docRelacionado.tipoDocumento)) {
            setDocRelacionado((d) => ({ ...d, tipoDocumento: '03' }));
        }
        if (['05', '06'].includes(newTipo)) {
            setDocRelacionado((d) => ({ ...d, tipoDocumento: '03' }));
        }
        setDocRelacionados([]);
        setItems((prev) => prev.map((i) => ({ ...i, numeroDocumentoRelacionado: null })));
        if (!['01', '03'].includes(newTipo)) setFactura((f) => ({ ...f, notas: '' }));
    }

    // ── Related doc handlers ──
    function addRelatedDoc() {
        setFormError('');
        const num = String(docRelacionado.numeroDocumento || '').trim().toUpperCase();
        const fecha = docRelacionado.fechaEmision;
        if (!num || !fecha) { setFormError('Complete el número y fecha del documento relacionado.'); return; }
        if (docRelacionado.tipoGeneracion === 2 && !/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/.test(num)) {
            setFormError('Para DTE ingrese el código de generación UUID de 36 caracteres.');
            return;
        }
        if (docRelacionados.some((d) => d.numeroDocumento === num)) { setFormError('Ese CCF ya fue agregado.'); return; }
        if (docRelacionados.length >= 50) { setFormError('Máximo 50 documentos relacionados.'); return; }
        setDocRelacionados((prev) => [...prev, { tipoDocumento: docRelacionado.tipoDocumento, tipoGeneracion: Number(docRelacionado.tipoGeneracion), numeroDocumento: num, fechaEmision: fecha }]);
        setDocRelacionado((d) => ({ ...d, numeroDocumento: '', fechaEmision: '' }));
    }

    function removeRelatedDoc(num) {
        setDocRelacionados((prev) => prev.filter((d) => d.numeroDocumento !== num));
        setItems((prev) => prev.map((i) => i.numeroDocumentoRelacionado === num ? { ...i, numeroDocumentoRelacionado: null } : i));
    }

    // ── Add item ──
    function addItem(item) {
        if (usesMultipleRelated && !item.numeroDocumentoRelacionado) {
            setFormError(`Seleccione el CCF al que aplica este ítem.`);
            return;
        }
        setItems((prev) => [...prev, item]);
        setShowItemModal(false);
    }

    function openItemModal() {
        if (usesMultipleRelated && docRelacionados.length === 0) {
            setFormError('Agregue al menos un CCF relacionado antes de agregar ítems.');
            return;
        }
        setShowItemModal(true);
    }

    // ── Validate ──
    function validateInvoice() {
        if (!selectedCustomer) return 'Seleccione un cliente. Para ventas sin datos del receptor use "CLIENTES VARIOS".';
        if (tipo !== '07' && items.length === 0) return 'Agregue al menos un producto o servicio.';
        if (['03', '04', '05', '06'].includes(tipo)) {
            const nit = cleanDigits(clienteData.numero_documento);
            if (!nit || nit.length !== 14) return 'Para CCF/NR/Notas el receptor debe tener NIT de 14 dígitos.';
            if (!clienteData.nrc) return 'Para CCF/NR/Notas el receptor debe tener NRC.';
            if (!clienteData.giro) return 'Para CCF/NR/Notas el receptor debe tener código de actividad económica.';
            if (!clienteData.desc_actividad) return 'Para CCF/NR/Notas el receptor debe tener descripción de actividad.';
            if (!clienteData.departamento || !clienteData.municipio) return 'Para CCF/NR/Notas el receptor debe tener departamento y municipio.';
            if (!clienteData.direccion) return 'Para CCF/NR/Notas el receptor debe tener dirección.';
        }
        if (usesMultipleRelated && docRelacionados.length === 0) return `Agregue al menos un CCF relacionado.`;
        if (usesMultipleRelated && items.some((i) => !i.numeroDocumentoRelacionado)) return `Cada ítem debe seleccionar el CCF al que aplica.`;
        if (needsRelated && !usesMultipleRelated && (!docRelacionado.numeroDocumento || !docRelacionado.fechaEmision)) return 'Complete el documento relacionado antes de enviar.';
        if (tipo === '07' && (!retencion.montoSujeto || !retencion.porcentaje)) return 'Ingrese el monto sujeto y el porcentaje de retención.';
        return '';
    }

    // ── Payload builders ──
    function buildConfig() {
        const e = settings.emisor || {};
        const h = settings.hacienda || {};
        return { ...e, hacienda_ambiente: h.ambiente || '00', codigo_establecimiento: normalizeCodeValue(e.codigo_establecimiento), punto_venta: normalizeCodeValue(e.punto_venta) };
    }

    function buildCliente() {
        if (selectedCustomer?.isClientesVarios) return { ...EMPTY_CLIENTE, nombre: 'CONSUMIDOR FINAL' };
        return { ...clienteData };
    }

    function buildItems() {
        if (tipo === '07') return [];
        return items.map((i) => ({ ...i, precio_unitario: Number(i.precio_unitario), cantidad: Number(i.cantidad), descuento: Number(i.descuento || 0), montoDescu: Number(i.descuento || 0), numero_documento: i.numeroDocumentoRelacionado || undefined }));
    }

    function buildResumen() {
        if (tipo === '07') return { totalSujetoRetencion: Number(retencion.montoSujeto || 0), totalIVAretenido: totals.retencionCalculada };
        return {
            condicionOperacion: factura.condicionOperacion,
            subtotal: totals.subtotalTotal,
            total: totals.totalEstimado,
            iva: totals.ivaEstimado,
            gravada: totals.subtotalGravado,
            exenta: totals.subtotalExento,
            descuento: totals.descuentoGeneralAplicado,
            totalGravada: totals.subtotalGravado,
            totalExenta: totals.subtotalExento,
            subTotalVentas: totals.subtotalTotal,
            subTotal: totals.subtotalTotal,
            totalDescu: totals.descuentoGeneralAplicado + items.reduce((s, i) => s + Number(i.descuento || 0), 0),
            ivaRete1: Number(factura.ivaRete1 || 0),
            ivaPerci1: Number(factura.ivaPerci1 || 0),
            reteRenta: Number(factura.reteRenta || 0),
            flete: Number(opciones.flete || 0),
            seguro: Number(opciones.seguro || 0),
        };
    }

    function buildOpciones() {
        const opts = { ...opciones, correlativo: correlativos[tipo] || 1 };
        if (['01', '03'].includes(tipo) && factura.notas) {
            opts.apendice = [{ campo: 'notas', etiqueta: 'Notas del documento', valor: factura.notas.slice(0, 150) }];
        }
        if (usesMultipleRelated) {
            opts.documentoRelacionado = docRelacionados;
        } else if (needsRelated && docRelacionado.numeroDocumento && docRelacionado.fechaEmision) {
            const related = [{
                tipoDocumento: docRelacionado.tipoDocumento,
                tipoGeneracion: Number(docRelacionado.tipoGeneracion),
                numeroDocumento: String(docRelacionado.numeroDocumento).trim().toUpperCase(),
                fechaEmision: docRelacionado.fechaEmision,
            }];
            opts.documentoRelacionado = tipo === '07' ? related[0] : related;
        }
        if (tipo === '07') {
            opts.codigoRetencionMH = retencion.codigo;
            opts.retencion = { montoSujeto: Number(retencion.montoSujeto), porcentaje: Number(retencion.porcentaje), ivaRetenido: totals.retencionCalculada };
        }
        if (tipo === '11') {
            opts.flete = Number(opciones.flete || 0);
            opts.seguro = Number(opciones.seguro || 0);
            opts.descIncoterms = incotermDescription(opciones.codIncoterms);
        }
        return opts;
    }

    // ── Submit ──
    async function handleSubmit(e) {
        e.preventDefault();
        setFormError('');
        const err = validateInvoice();
        if (err) { setFormError(err); return; }

        const firma = settings.firma || {};
        const hacienda = settings.hacienda || {};
        const correo = settings.correo || {};

        const payload = {
            tipo,
            config: buildConfig(),
            cliente: buildCliente(),
            items: buildItems(),
            resumen: buildResumen(),
            opciones: buildOpciones(),
            customerId: selectedCustomer?.id || null,
            firma: {
                tipo: firma.tipo || 'svfe',
                nit: firma.firmador_usuario || firma.firmadorUsuario || (settings.emisor || {}).nit || '',
                certificado_path: firma.certificado_path || firma.certificadoPath || '',
                password: firma.certificado_password || firma.certificadoPassword || firma.firmador_pin || firma.firmadorPin || null,
            },
            hacienda: { usuario: hacienda.usuario || '', password: hacienda.password || '' },
            correo,
        };

        const steps = STEP_LABELS.map((label) => ({ label, message: '', status: '' }));
        setOverlay({ visible: true, title: 'Envío de documento a Hacienda', subtitle: 'Preparando documento...', badge: 'En proceso', status: '', current: 0, steps });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        try {
            const response = await fetch('/admin/factura-sv/procesar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();

            if (!response.ok) {
                setOverlay((o) => ({ ...o, visible: false }));
                setFormError(data.message || 'Error al procesar la factura.');
                return;
            }

            const receivedSteps = data.steps || [];
            setOverlay((o) => {
                const updated = [...o.steps];
                receivedSteps.forEach((step, i) => {
                    if (updated[i]) { updated[i].message = step.message; updated[i].status = step.status; }
                });
                return { ...o, current: Math.min(receivedSteps.length - 1, updated.length - 1), subtitle: data.message || 'Proceso completado.', badge: data.success ? 'Finalizado' : 'Revisar', status: data.success ? 'success' : 'error', steps: updated };
            });

            setTimeout(() => {
                setOverlay((o) => ({ ...o, visible: false }));
                setResult(data);
            }, data.success ? 1200 : 2000);

        } catch (err) {
            setOverlay((o) => ({ ...o, visible: false }));
            setFormError(err.message || 'Error de conexión al procesar la factura.');
        }
    }

    function resetForm() {
        setSelectedCustomer(null); setCustomerSearch(''); setClienteData({ ...EMPTY_CLIENTE });
        setTipo(enabledDteTypes[0]?.codigo || '01');
        setFactura({ condicionOperacion: 1, descuentoTipo: 'monto', descuentoGeneral: 0, ivaRete1: 0, ivaPerci1: 0, reteRenta: 0, notas: '' });
        setItems([]); setDocRelacionado({ tipoDocumento: '03', tipoGeneracion: 2, numeroDocumento: '', fechaEmision: '' });
        setDocRelacionados([]); setRetencion({ codigo: '22', montoSujeto: 0, porcentaje: 1 });
        setOpciones({ tipoItemExpor: 2, recintoFiscal: '', regimen: '', codIncoterms: '01', flete: 0, seguro: 0 });
        setFormError(''); setResult(null);
    }

    const dteLabel = (code) => DTE_TYPES.find((t) => t.codigo === code)?.nombre || `DTE ${code}`;

    // ── Render ──
    return (
        <AppLayout title="Nueva Factura">
            <div className="section-top">
                <div>
                    <h1>Nueva Factura</h1>
                    <p className="muted">Emisión de documentos tributarios electrónicos</p>
                </div>
            </div>

            {result && (
                <div className={result.success ? 'notice' : 'errors'} style={{ marginBottom: 16, display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                    <span><strong>{result.success ? '✓ Factura generada exitosamente.' : '⚠ Proceso completado con advertencias.'}</strong></span>
                    <div style={{ display: 'flex', gap: 10, marginLeft: 'auto', flexWrap: 'wrap' }}>
                        <a href={route('admin.factura-sv.billing.facturas')} className="btn secondary" style={{ textDecoration: 'none', fontSize: 13 }}>Ver facturas →</a>
                        <button className="btn" type="button" onClick={resetForm}>+ Nueva factura</button>
                    </div>
                </div>
            )}

            {formError && (
                <div className="errors" style={{ marginBottom: 16 }}>{formError}</div>
            )}

            <form onSubmit={handleSubmit}>
                {/* ── Sección cliente ── */}
                <div className="card" style={{ marginBottom: 16 }}>
                    <h3>Información del Cliente</h3>
                    <div className="form-grid">
                        <div className="form-group">
                            <label>Cliente</label>
                            <Combobox
                                options={customers}
                                value={selectedCustomer?.id ?? ''}
                                search={customerSearch}
                                onSearch={(v) => { setCustomerSearch(v); if (selectedCustomer) clearCustomer(); }}
                                onSelect={selectCustomer}
                                onClear={clearCustomer}
                                placeholder="Buscar cliente por nombre o documento..."
                                renderLabel={(c) => c.name}
                                renderMeta={(c) => c.isClientesVarios ? 'Sistema' : dteLabel(c.preferredDteType)}
                            />
                        </div>
                        <label className="form-group">Tipo de Documento
                            <select value={tipo} onChange={(e) => handleDteTypeChange(e.target.value)}>
                                {enabledDteTypes.map((t) => (
                                    <option key={t.codigo} value={t.codigo}>{t.codigo} - {t.nombre}</option>
                                ))}
                            </select>
                        </label>
                        <label className="form-group">Condición de Operación
                            <select value={factura.condicionOperacion} onChange={(e) => setFactura((f) => ({ ...f, condicionOperacion: Number(e.target.value) }))}>
                                <option value={1}>Contado</option>
                                <option value={2}>Crédito</option>
                                <option value={3}>Otro</option>
                            </select>
                        </label>
                    </div>

                    {selectedCustomer && (
                        <div className="receptor-resumen">
                            <div className="receptor-resumen-titulo">Datos del receptor cargados</div>
                            <div className="receptor-resumen-grid">
                                <span className="receptor-label">Nombre</span>
                                <span className="receptor-valor">{clienteData.nombre || '—'}</span>
                                <span className="receptor-label">Documento</span>
                                <span className="receptor-valor">
                                    {clienteData.numero_documento
                                        ? <>{clienteData.tipo_documento} {clienteData.numero_documento}{clienteData.nrc && ` · NRC ${clienteData.nrc}`}</>
                                        : <span className="receptor-faltante">Sin documento</span>}
                                    {!clienteData.nrc && ['03','04','05','06'].includes(tipo) && <span className="receptor-faltante"> · NRC faltante</span>}
                                </span>
                                <span className="receptor-label">Actividad</span>
                                <span className="receptor-valor">
                                    {clienteData.giro
                                        ? `${clienteData.giro} — ${clienteData.desc_actividad || '—'}`
                                        : <span className={['03','04','05','06'].includes(tipo) ? 'receptor-faltante' : 'receptor-opcional'}>
                                            {['03','04','05','06'].includes(tipo) ? 'Faltante (requerido para CCF/NR)' : 'No configurada'}
                                        </span>}
                                </span>
                                <span className="receptor-label">Dirección</span>
                                <span className="receptor-valor">
                                    {clienteData.direccion
                                        ? `${clienteData.departamento} / ${clienteData.municipio} · ${clienteData.direccion}`
                                        : <span className={['03','04','05','06'].includes(tipo) ? 'receptor-faltante' : 'receptor-opcional'}>
                                            {['03','04','05','06'].includes(tipo) ? 'Faltante (requerido para CCF/NR)' : 'No configurada'}
                                        </span>}
                                </span>
                                <span className="receptor-label">Contacto</span>
                                <span className="receptor-valor">
                                    {(clienteData.email || clienteData.telefono)
                                        ? [clienteData.email, clienteData.telefono].filter(Boolean).join(' · ')
                                        : <span className="receptor-opcional">Sin contacto</span>}
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                {/* ── Documento relacionado ── */}
                {needsRelated && (
                    <div className="card" style={{ marginBottom: 16 }}>
                        <h3>Documento Relacionado</h3>
                        <div className="alert alert-info" style={{ marginBottom: 12 }}>
                            <small>
                                {usesMultipleRelated
                                    ? 'Agregue uno o más Comprobantes de Crédito Fiscal afectados y seleccione el CCF correspondiente al agregar cada ítem.'
                                    : tipo === '07' ? 'Hacienda requiere el documento tributario sujeto a retención.'
                                    : 'Hacienda requiere relacionar el documento tributario afectado.'}
                            </small>
                        </div>
                        <div className="form-grid">
                            <label className="form-group">Tipo de Documento Relacionado
                                <select value={docRelacionado.tipoDocumento} onChange={(e) => setDocRelacionado((d) => ({ ...d, tipoDocumento: e.target.value }))}>
                                    {relatedDocTypesForCurrent.map((t) => (
                                        <option key={t.codigo} value={t.codigo}>{t.codigo} - {t.nombre}</option>
                                    ))}
                                </select>
                            </label>
                            <label className="form-group">Tipo de Generación
                                <select value={docRelacionado.tipoGeneracion} onChange={(e) => setDocRelacionado((d) => ({ ...d, tipoGeneracion: Number(e.target.value) }))}>
                                    <option value={2}>2 - DTE</option>
                                    <option value={1}>1 - Físico</option>
                                </select>
                            </label>
                            <label className="form-group">Número / Código de Generación
                                <input
                                    value={docRelacionado.numeroDocumento}
                                    maxLength={docRelacionado.tipoGeneracion === 2 ? 36 : 50}
                                    placeholder={docRelacionado.tipoGeneracion === 2 ? 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX' : 'Número del documento físico'}
                                    onChange={(e) => setDocRelacionado((d) => ({ ...d, numeroDocumento: e.target.value }))}
                                />
                            </label>
                            <label className="form-group">Fecha de Emisión Relacionada
                                <input type="date" value={docRelacionado.fechaEmision} onChange={(e) => setDocRelacionado((d) => ({ ...d, fechaEmision: e.target.value }))} />
                            </label>
                            {usesMultipleRelated && (
                                <label className="form-group">&nbsp;
                                    <button className="btn secondary" type="button" onClick={addRelatedDoc}>Agregar CCF</button>
                                </label>
                            )}
                            {tipo === '07' && (
                                <>
                                    <label className="form-group">Código Retención IVA MH
                                        <select value={retencion.codigo} onChange={(e) => setRetencion((r) => ({ ...r, codigo: e.target.value }))}>
                                            <option value="22">22 - Retención IVA 1%</option>
                                            <option value="C4">C4 - Retención IVA</option>
                                            <option value="C9">C9 - Otra retención IVA</option>
                                        </select>
                                    </label>
                                    <label className="form-group">Monto Sujeto a Retención
                                        <input type="number" min="0" step="0.01" value={retencion.montoSujeto} onChange={(e) => setRetencion((r) => ({ ...r, montoSujeto: e.target.value }))} />
                                    </label>
                                    <label className="form-group">Porcentaje a Aplicar
                                        <input type="number" min="0" step="0.01" value={retencion.porcentaje} onChange={(e) => setRetencion((r) => ({ ...r, porcentaje: e.target.value }))} />
                                    </label>
                                    <label className="form-group">IVA Retenido Calculado
                                        <input value={money(totals.retencionCalculada)} readOnly />
                                    </label>
                                </>
                            )}
                        </div>
                        {usesMultipleRelated && docRelacionados.length > 0 && (
                            <table className="data-table" style={{ marginTop: 12 }}>
                                <thead><tr><th>CCF Relacionado</th><th>Fecha</th><th>Acciones</th></tr></thead>
                                <tbody>
                                    {docRelacionados.map((d) => (
                                        <tr key={d.numeroDocumento}>
                                            <td><strong>{d.tipoDocumento}</strong><br /><span className="muted">{d.numeroDocumento}</span></td>
                                            <td>{d.fechaEmision}</td>
                                            <td><button className="btn secondary" type="button" onClick={() => removeRelatedDoc(d.numeroDocumento)}>Quitar</button></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                )}

                {/* ── Exportación (tipo 11) ── */}
                {tipo === '11' && (
                    <div className="card" style={{ marginBottom: 16 }}>
                        <h3>Datos de Exportación</h3>
                        <div className="form-grid">
                            <label>Tipo item exportado
                                <select value={opciones.tipoItemExpor} onChange={(e) => setOpciones((o) => ({ ...o, tipoItemExpor: Number(e.target.value) }))}>
                                    <option value={2}>Servicios</option>
                                    <option value={1}>Bienes</option>
                                    <option value={3}>Bienes y servicios</option>
                                </select>
                            </label>
                            <label>País destino<input value={clienteData.pais || ''} onChange={(e) => setClienteData((c) => ({ ...c, pais: e.target.value }))} placeholder="US" /></label>
                            <label>Nombre país<input value={clienteData.nombre_pais || ''} onChange={(e) => setClienteData((c) => ({ ...c, nombre_pais: e.target.value }))} placeholder="Estados Unidos" /></label>
                            {opciones.tipoItemExpor !== 2 && (
                                <>
                                    <label>Recinto Fiscal
                                        <input list="recintos-list" value={opciones.recintoFiscal} maxLength={2} placeholder="03" onChange={(e) => setOpciones((o) => ({ ...o, recintoFiscal: e.target.value }))} />
                                        <datalist id="recintos-list">{RECINTOS_FISCALES.map((v) => <option key={v} value={v} />)}</datalist>
                                    </label>
                                    <label>Régimen
                                        <input list="regimenes-list" value={opciones.regimen} maxLength={13} placeholder="EX-1.1000.000" onChange={(e) => setOpciones((o) => ({ ...o, regimen: e.target.value }))} />
                                        <datalist id="regimenes-list">{REGIMENES.map((v) => <option key={v} value={v} />)}</datalist>
                                    </label>
                                    <label>Incoterm
                                        <select value={opciones.codIncoterms} onChange={(e) => setOpciones((o) => ({ ...o, codIncoterms: e.target.value }))}>
                                            {INCOTERM_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                        </select>
                                    </label>
                                    <label>Flete<input type="number" min="0" step="0.01" value={opciones.flete} onChange={(e) => setOpciones((o) => ({ ...o, flete: e.target.value }))} /></label>
                                    <label>Seguro<input type="number" min="0" step="0.01" value={opciones.seguro} onChange={(e) => setOpciones((o) => ({ ...o, seguro: e.target.value }))} /></label>
                                </>
                            )}
                        </div>
                    </div>
                )}

                {/* ── Items ── */}
                {tipo !== '07' && (
                    <div className="card" style={{ marginBottom: 16 }}>
                        <div className="items-header">
                            <h3>Items de la Factura</h3>
                            <button className="btn secondary" type="button" onClick={openItemModal}>Agregar Item</button>
                        </div>
                        <table className="data-table">
                            <thead>
                                <tr><th>Producto</th><th>Cantidad</th><th>Precio Unit.</th><th>IVA</th><th>Subtotal</th><th></th></tr>
                            </thead>
                            <tbody>
                                {items.length === 0 && <tr><td colSpan={6} className="empty">No hay items agregados</td></tr>}
                                {items.map((item, i) => (
                                    <tr key={i}>
                                        <td>{item.descripcion}<br /><span className="muted">{item.codigo}</span></td>
                                        <td>{Number(item.cantidad).toFixed(2)}</td>
                                        <td>{money(item.precio_unitario)}</td>
                                        <td>{item.exento ? 'Exento' : money(lineIva(item, tipo))}</td>
                                        <td>{money(lineTotal(item, tipo))}</td>
                                        <td><button className="btn secondary" type="button" onClick={() => setItems((prev) => prev.filter((_, j) => j !== i))}>Quitar</button></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* ── Resumen ── */}
                <div className="card" style={{ marginBottom: 16 }}>
                    <h3>Resumen</h3>
                    {tipo !== '07' ? (
                        <div className="resumen-grid">
                            <div className="resumen-row"><span>Subtotal Gravado:</span><strong>{money(totals.subtotalGravado)}</strong></div>
                            <div className="resumen-row"><span>Subtotal Exento:</span><strong>{money(totals.subtotalExento)}</strong></div>
                            <div className="resumen-row"><span>Subtotal Total:</span><strong>{money(totals.subtotalTotal)}</strong></div>
                            <div className="resumen-row">
                                <span>Descuento General:</span>
                                <span className="summary-controls">
                                    <select value={factura.descuentoTipo} onChange={(e) => setFactura((f) => ({ ...f, descuentoTipo: e.target.value }))}>
                                        <option value="monto">Monto</option>
                                        <option value="porcentaje">%</option>
                                    </select>
                                    <input type="number" min="0" step="0.01" value={factura.descuentoGeneral} className="summary-input" onChange={(e) => setFactura((f) => ({ ...f, descuentoGeneral: e.target.value }))} />
                                </span>
                            </div>
                            <div className="resumen-row"><span>Descuento Aplicado:</span><strong>{money(totals.descuentoGeneralAplicado)}</strong></div>
                            <div className="resumen-row"><span>IVA (13%):</span><strong>{money(totals.ivaEstimado)}</strong></div>
                            {['03','05','06'].includes(tipo) && (
                                <div className="resumen-row"><span>IVA Retenido:</span>
                                    <input className="summary-input" type="number" min="0" step="0.01" value={factura.ivaRete1} onChange={(e) => setFactura((f) => ({ ...f, ivaRete1: e.target.value }))} />
                                </div>
                            )}
                            {tipo === '03' && (
                                <div className="resumen-row"><span>IVA Percibido:</span>
                                    <input className="summary-input" type="number" min="0" step="0.01" value={factura.ivaPerci1} onChange={(e) => setFactura((f) => ({ ...f, ivaPerci1: e.target.value }))} />
                                </div>
                            )}
                            {tipo === '14' && (
                                <div className="resumen-row"><span>Retención Renta:</span>
                                    <input className="summary-input" type="number" min="0" step="0.01" value={factura.reteRenta} onChange={(e) => setFactura((f) => ({ ...f, reteRenta: e.target.value }))} />
                                </div>
                            )}
                            <div className="resumen-row total"><span>Total a Pagar:</span><strong>{money(totals.totalEstimado)}</strong></div>
                            <div className="resumen-row"><span>Total en Letras:</span><span>{money(totals.totalEstimado)} DOLARES</span></div>
                        </div>
                    ) : (
                        <div className="resumen-grid">
                            <div className="resumen-row"><span>Monto sujeto a retención:</span><strong>{money(Number(retencion.montoSujeto || 0))}</strong></div>
                            <div className="resumen-row"><span>IVA retenido:</span><strong>{money(totals.retencionCalculada)}</strong></div>
                            <div className="resumen-row"><span>Total en Letras:</span><span>{money(totals.retencionCalculada)} DOLARES</span></div>
                        </div>
                    )}
                </div>

                {/* ── Notas ── */}
                {['01','03'].includes(tipo) && (
                    <div className="card" style={{ marginBottom: 16 }}>
                        <h3>Notas</h3>
                        <label className="form-group full-width">Notas opcionales
                            <textarea value={factura.notas} rows={2} maxLength={150} placeholder="Notas para factura o comprobante de crédito fiscal" onChange={(e) => setFactura((f) => ({ ...f, notas: e.target.value }))} />
                            <small>Opcional. Máximo 150 caracteres.</small>
                        </label>
                    </div>
                )}

                {/* ── Actions ── */}
                <div className="card" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                    <span className="muted" style={{ fontSize: 13 }}>
                        {items.length > 0 ? `${items.length} ítem${items.length !== 1 ? 's' : ''} · Total: ${money(totals.totalEstimado)}` : 'Sin ítems aún'}
                    </span>
                    <div className="actions">
                        <button className="btn secondary" type="button" onClick={resetForm}>Cancelar</button>
                        <button className="btn" type="submit" style={{ minWidth: 160 }}>Generar Factura →</button>
                    </div>
                </div>
            </form>

            {showItemModal && (
                <ItemModal
                    key={items.length}
                    products={products}
                    hasInventory={hasInventory}
                    relatedDocs={docRelacionados}
                    usesMultipleRelated={usesMultipleRelated}
                    tipo={tipo}
                    onAdd={addItem}
                    onClose={() => setShowItemModal(false)}
                />
            )}

            <ProgressOverlay overlay={overlay} />
        </AppLayout>
    );
}
