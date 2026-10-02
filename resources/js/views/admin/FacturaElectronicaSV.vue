<template>
  <div class="fe">
    <div class="top">
      <div>
        <h1>Factura Electrónica SV</h1>
        <span class="muted">Motor DTE integrado desde facturacion-electron</span>
      </div>
    </div>

    <section v-if="message || error" ref="statusCard" class="card status-card">
      <div v-if="message" class="notice">{{ message }}</div>
      <div v-if="error" class="errors">{{ error }}</div>
    </section>

    <div v-if="processOverlay.visible" class="process-overlay">
      <div class="process-card">
        <div class="process-header">
          <div>
            <h3>{{ processOverlay.title }}</h3>
            <p>{{ processOverlay.subtitle }}</p>
          </div>
          <span class="process-badge" :class="processOverlay.status">{{ processOverlay.badge }}</span>
        </div>
        <div class="process-track">
          <div class="process-track-fill" :class="processOverlay.status" :style="{ width: processProgress }"></div>
        </div>
        <ul class="process-steps">
          <li
            v-for="(step, index) in processOverlay.steps"
            :key="step.label"
            :class="{ active: index === processOverlay.current, done: step.status === 'done', error: step.status === 'error', warning: step.status === 'warning' }"
          >
            {{ step.message || step.label }}
          </li>
        </ul>
      </div>
    </div>

    <section v-if="activeView === 'dashboard'" class="card">
      <div class="top compact">
        <h3>Dashboard</h3>
        <button class="btn" type="button" @click="setView('nueva-factura')">Nueva Factura</button>
      </div>
      <div class="stats-grid">
        <div class="stat"><span class="stat-icon">📄</span><div class="stat-info"><span>Facturas Hoy</span><strong>{{ dashboardStats.todayCount }}</strong></div></div>
        <div class="stat"><span class="stat-icon">💰</span><div class="stat-info"><span>Total Enviado Hoy</span><strong>{{ money(dashboardStats.todaySentTotal) }}</strong></div></div>
        <div class="stat"><span class="stat-icon">✅</span><div class="stat-info"><span>Enviadas a Hacienda</span><strong>{{ dashboardStats.sentCount }}</strong></div></div>
        <div class="stat"><span class="stat-icon">⏳</span><div class="stat-info"><span>Pendientes</span><strong>{{ dashboardStats.pendingCount }}</strong></div></div>
        <div class="stat"><span class="stat-icon">↩</span><div class="stat-info"><span>Anuladas</span><strong>{{ dashboardStats.voidedCount }}</strong></div></div>
      </div>
      <div class="form-grid filters-grid" style="margin-top:16px;">
        <label>Buscar
          <input v-model="invoiceFilters.search" type="search" placeholder="Buscar por factura, cliente, documento, sello o estado...">
        </label>
        <label>Desde<input v-model="invoiceFilters.from" type="date"></label>
        <label>Hasta<input v-model="invoiceFilters.to" type="date"></label>
        <label>Estado
          <select v-model="invoiceFilters.status">
            <option value="">Todos los estados</option>
            <option value="PENDIENTE">Pendientes / acción</option>
            <option value="CONTINGENCIA">Contingencia</option>
            <option value="ENVIADO">Enviado</option>
            <option value="RECHAZADO">Rechazado</option>
            <option value="ANULADO">Anulado</option>
          </select>
        </label>
        <label>Documento
          <select v-model="invoiceFilters.type">
            <option value="">Todos los documentos</option>
            <option v-for="documentType in dteTypes" :key="documentType.codigo" :value="documentType.codigo">
              {{ documentType.codigo }} - {{ documentType.nombre }}
            </option>
          </select>
        </label>
        <button class="btn" type="button" @click="loadInvoices">Filtrar</button>
      </div>
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>No. Control</th>
            <th>Cliente</th>
            <th>Total</th>
            <th>Estado</th>
            <th>Contiene Error</th>
            <th>Aceptado</th>
            <th>Enviado por Correo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="invoices.length === 0"><td colspan="9" class="empty">No hay facturas</td></tr>
          <tr v-for="invoice in invoices" :key="invoice.id">
            <td>{{ invoice.date }}</td>
            <td>{{ invoice.numberControl }}<br><span class="muted">{{ invoice.generationCode }}</span></td>
            <td>{{ invoice.customerName }}</td>
            <td>{{ money(invoice.total) }}</td>
            <td><span class="badge" :class="invoice.status.toLowerCase()">{{ invoice.status }}</span></td>
            <td>{{ invoice.hasError ? 'Sí' : 'No' }}</td>
            <td>{{ invoice.accepted ? 'Sí' : 'No' }}</td>
            <td>{{ invoice.emailSent ? 'Sí' : 'No' }}</td>
            <td>
              <div class="actions wrap table-actions">
                <button class="btn secondary" type="button" @click="showInvoiceObservations(invoice)">Ver</button>
                <button v-if="['FIRMADO', 'PENDIENTE'].includes(invoice.status)" class="btn" type="button" @click="sendStoredInvoice(invoice)" :disabled="loading">Enviar</button>
                <button v-if="canVoidInvoice(invoice)" class="btn danger" type="button" @click="openVoidInvoice(invoice)" :disabled="loading">Anular</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="voidOverlay.visible" class="overlay-layer">
        <button class="overlay-backdrop" type="button" aria-label="Cerrar" @click="closeVoidOverlay"></button>
        <div class="card overlay-panel">
          <div class="overlay-header">
            <div>
              <h3>Anular factura</h3>
              <span class="muted">{{ voidOverlay.invoice?.numberControl }}</span>
            </div>
            <button class="btn secondary overlay-close" type="button" @click="closeVoidOverlay">Cerrar</button>
          </div>
          <div class="alert alert-info">
            <small>{{ voidHelpText }}</small>
          </div>
          <div class="form-grid">
            <label>Tipo de anulación CAT-024
              <select v-model.number="voidForm.tipoAnulacion" @change="handleVoidTypeChanged">
                <option v-for="option in allowedVoidTypeOptions" :key="option.value" :value="option.value">
                  {{ option.value }} - {{ option.label }}
                </option>
              </select>
            </label>
            <label v-if="voidRequiresReplacement" class="full-width">Código de generación reemplazo
              <input v-model="voidForm.codigoGeneracionR" maxlength="36" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX">
              <small>{{ voidReplacementHelp }}</small>
            </label>
            <label class="full-width">Motivo
              <textarea v-model="voidForm.motivo" rows="4" maxlength="250" placeholder="Detalle el motivo de la anulación"></textarea>
            </label>
          </div>
          <div class="actions wrap modal-actions">
            <button class="btn secondary" type="button" @click="closeVoidOverlay">Cancelar</button>
            <button class="btn danger" type="button" @click="submitVoidInvoice" :disabled="loading">Enviar anulación</button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="activeView === 'nueva-factura'" class="form-section factura-form">
        <h3>Información del Cliente</h3>
        <div class="form-grid">
          <label class="form-group">Cliente
            <select v-model="selectedCustomerId" @change="applySelectedCustomer">
              <option value="">Seleccionar cliente...</option>
              <option v-for="customer in billingCustomers" :key="customer.id" :value="customer.id">
                {{ customerOptionLabel(customer) }}
              </option>
            </select>
          </label>
          <label class="form-group">Tipo de Documento
            <select v-model="tipo" @change="handleDteTypeChanged">
              <option v-for="documentType in enabledDteTypes" :key="documentType.codigo" :value="documentType.codigo">
                {{ documentType.codigo }} - {{ documentType.nombre }}
              </option>
            </select>
          </label>
          <label class="form-group">Condición de Operación
            <select v-model.number="factura.condicionOperacion">
              <option :value="1">Contado</option>
              <option :value="2">Crédito</option>
              <option :value="3">Otro</option>
            </select>
          </label>
        </div>

        <div v-if="selectedCustomerId && receptorCargado" class="receptor-resumen">
          <div class="receptor-resumen-titulo">Datos del receptor cargados</div>
          <div class="receptor-resumen-grid">
            <span class="receptor-label">Nombre</span>
            <span class="receptor-valor">{{ receptorCargado.nombre || '—' }}</span>

            <span class="receptor-label">Documento</span>
            <span class="receptor-valor">
              <span v-if="receptorCargado.numero_documento">{{ receptorCargado.tipo_documento }} {{ receptorCargado.numero_documento }}</span>
              <span v-else class="receptor-faltante">Sin documento</span>
              <span v-if="receptorCargado.nrc" class="receptor-nrc"> · NRC {{ receptorCargado.nrc }}</span>
              <span v-else-if="['03','05','06'].includes(tipo)" class="receptor-faltante"> · NRC faltante</span>
            </span>

            <span class="receptor-label">Actividad</span>
            <span class="receptor-valor">
              <span v-if="receptorCargado.giro">{{ receptorCargado.giro }} — {{ receptorCargado.desc_actividad || '—' }}</span>
              <span v-else :class="['03','05','06'].includes(tipo) ? 'receptor-faltante' : 'receptor-opcional'">
                {{ ['03','05','06'].includes(tipo) ? 'Faltante (requerido para CCF)' : 'No configurada' }}
              </span>
            </span>

            <span class="receptor-label">Dirección</span>
            <span class="receptor-valor">
              <span v-if="receptorCargado.direccion">
                {{ receptorCargado.departamento }} / {{ receptorCargado.municipio }} · {{ receptorCargado.direccion }}
              </span>
              <span v-else :class="['03','05','06'].includes(tipo) ? 'receptor-faltante' : 'receptor-opcional'">
                {{ ['03','05','06'].includes(tipo) ? 'Faltante (requerido para CCF)' : 'No configurada' }}
              </span>
            </span>

            <span class="receptor-label">Contacto</span>
            <span class="receptor-valor">
              <span v-if="receptorCargado.email || receptorCargado.telefono">
                {{ [receptorCargado.email, receptorCargado.telefono].filter(Boolean).join(' · ') }}
              </span>
              <span v-else class="receptor-opcional">Sin contacto</span>
            </span>
          </div>
        </div>
      </section>

    <section v-if="activeView === 'nueva-factura' && ['05', '06', '07'].includes(tipo)" class="form-section">
      <h3>Documento Relacionado</h3>
      <div class="alert alert-info"><small>{{ relatedDocumentHelp }}</small></div>
      <div class="form-grid">
        <label class="form-group">Tipo de Documento Relacionado
          <select v-model="documentoRelacionado.tipoDocumento">
            <option v-for="documentType in relatedDocumentTypesForCurrent" :key="documentType.codigo" :value="documentType.codigo">
              {{ documentType.codigo }} - {{ documentType.nombre }}
            </option>
          </select>
        </label>
        <label class="form-group">Tipo de Generación
          <select v-model.number="documentoRelacionado.tipoGeneracion">
            <option :value="2">2 - DTE</option>
            <option :value="1">1 - Físico</option>
          </select>
        </label>
        <label class="form-group">Número / Código de Generación
          <input v-model="documentoRelacionado.numeroDocumento" :maxlength="documentoRelacionado.tipoGeneracion === 2 ? 36 : 50" :placeholder="relatedDocumentPlaceholder">
        </label>
        <label class="form-group">Fecha de Emisión Relacionada<input v-model="documentoRelacionado.fechaEmision" type="date"></label>
        <label v-if="usesMultipleRelatedDocuments" class="form-group">&nbsp;
          <button class="btn secondary" type="button" @click="addRelatedDocument">Agregar CCF</button>
        </label>
        <label v-if="tipo === '07'" class="form-group">Código Retención IVA MH
          <select v-model="retencion.codigo">
            <option value="22">22 - Retención IVA 1%</option>
            <option value="C4">C4 - Retención IVA</option>
            <option value="C9">C9 - Otra retención IVA</option>
          </select>
        </label>
        <label v-if="tipo === '07'" class="form-group">Monto Sujeto a Retención<input v-model.number="retencion.montoSujeto" type="number" min="0" step="0.01"></label>
        <label v-if="tipo === '07'" class="form-group">Porcentaje a Aplicar<input v-model.number="retencion.porcentaje" type="number" min="0" step="0.01"></label>
        <label v-if="tipo === '07'" class="form-group">IVA Retenido Calculado<input :value="money(retencionCalculada)" readonly></label>
      </div>
      <table v-if="usesMultipleRelatedDocuments" class="data-table related-documents-table">
        <thead><tr><th>CCF Relacionado</th><th>Fecha</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-if="documentosRelacionados.length === 0"><td colspan="3" class="empty">No hay CCF relacionados</td></tr>
          <tr v-for="document in documentosRelacionados" :key="document.numeroDocumento">
            <td><strong>{{ document.tipoDocumento }}</strong><br><span class="muted">{{ document.numeroDocumento }}</span></td>
            <td>{{ document.fechaEmision }}</td>
            <td><button class="btn secondary" type="button" @click="removeRelatedDocument(document.numeroDocumento)">Quitar</button></td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="activeView === 'nueva-factura' && tipo === '11'" class="form-section">
      <h3>Datos de Exportación</h3>
      <div class="form-grid">
        <label>Tipo item exportado
          <select v-model.number="opciones.tipoItemExpor">
            <option :value="2">Servicios</option>
            <option :value="1">Bienes</option>
            <option :value="3">Bienes y servicios</option>
          </select>
        </label>
        <label>País destino<input v-model="cliente.pais" placeholder="US"></label>
        <label>Nombre país<input v-model="cliente.nombre_pais" placeholder="Estados Unidos"></label>
        <label v-if="opciones.tipoItemExpor !== 2">Recinto Fiscal<input v-model="opciones.recintoFiscal" list="recintos-fiscales" maxlength="2" placeholder="03"></label>
        <label v-if="opciones.tipoItemExpor !== 2">Régimen<input v-model="opciones.regimen" list="regimenes-exportacion" maxlength="13" placeholder="EX-1.1000.000"></label>
        <label v-if="opciones.tipoItemExpor !== 2">Incoterm
          <select v-model="opciones.codIncoterms">
            <option value="01">EXW - En fábrica</option>
            <option value="02">FCA - Franco transportista</option>
            <option value="03">FAS - Franco al costado del buque</option>
            <option value="04">FOB - Franco a bordo</option>
            <option value="05">CFR - Costo y flete</option>
            <option value="06">CIF - Costo, seguro y flete</option>
            <option value="07">CPT - Transporte pagado hasta</option>
            <option value="08">CIP - Transporte y seguro pagados hasta</option>
            <option value="09">DAP - Entregado en lugar</option>
            <option value="10">DPU - Entregado en lugar descargado</option>
            <option value="11">DDP - Entregado derechos pagados</option>
          </select>
        </label>
        <label v-if="opciones.tipoItemExpor !== 2">Flete<input v-model.number="opciones.flete" type="number" min="0" step="0.01"></label>
        <label v-if="opciones.tipoItemExpor !== 2">Seguro<input v-model.number="opciones.seguro" type="number" min="0" step="0.01"></label>
      </div>
      <datalist id="recintos-fiscales">
        <option value="01">Terrestre San Bartolo</option>
        <option value="02">Marítima de Acajutla</option>
        <option value="03">Aérea Monseñor Óscar Arnulfo Romero</option>
        <option value="04">Terrestre Las Chinamas</option>
        <option value="05">Terrestre La Hachadura</option>
        <option value="09">Terrestre El Amatillo</option>
        <option value="10">Marítima La Unión</option>
        <option value="11">Terrestre El Poy</option>
        <option value="15">Fardos Postales</option>
        <option value="16">Z.F. San Marcos</option>
        <option value="18">Z.F. San Bartolo</option>
        <option value="21">Z.F. American Park</option>
        <option value="31">Aérea Ilopango</option>
        <option value="76">DHL</option>
        <option value="99">San Bartolo Envío HN/GT</option>
      </datalist>
      <datalist id="regimenes-exportacion">
        <option value="EX-1.1000.000">Exportación Definitiva, Régimen Común</option>
        <option value="EX-1.1040.000">Exportación Definitiva Sustitución de Mercancías</option>
        <option value="EX-1.1054.000">Exportación Definitiva de Zona Franca con origen en compras locales</option>
        <option value="EX-1.1100.000">Exportación Definitiva de Envíos de Socorro</option>
        <option value="EX-1.1200.000">Exportación Definitiva de Envíos Postales</option>
        <option value="EX-1.1300.000">Exportación Definitiva Envíos con despacho urgente</option>
        <option value="EX-1.1400.000">Exportación Definitiva Courier</option>
        <option value="EX-1.1500.000">Exportación Definitiva Menaje de casa</option>
        <option value="EX-2.2100.000">Exportación Temporal para Perfeccionamiento Pasivo</option>
        <option value="EX-2.2200.000">Exportación Temporal con Reimportación en el mismo estado</option>
        <option value="EX-3.3050.000">Reexportación proveniente de Importación Temporal</option>
        <option value="EX-3.3054.000">Reexportación proveniente de Régimen de Zona Franca</option>
      </datalist>
    </section>

    <section v-if="activeView === 'nueva-factura' && tipo !== '07'" class="form-section">
      <div class="items-header">
        <h3>Items de la Factura</h3>
        <button class="btn secondary" type="button" @click="openItemModal">Agregar Item</button>
      </div>
      <table class="data-table">
        <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio Unit.</th><th>IVA</th><th>Subtotal</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-if="items.length === 0"><td colspan="6" class="empty">No hay items agregados</td></tr>
          <tr v-for="(item, index) in items" :key="index">
            <td>{{ item.descripcion }}<br><span class="muted">{{ item.codigo }} · {{ unitName(item.unidad_medida) }}</span></td>
            <td>{{ Number(item.cantidad).toFixed(2) }}</td>
            <td>{{ money(item.precio_unitario) }}</td>
            <td>{{ item.exento ? 'Exento' : money(lineIva(item)) }}</td>
            <td>{{ money(lineTotal(item)) }}</td>
            <td><button class="btn secondary" type="button" @click="removeItem(index)">Quitar</button></td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="showItemForm" class="modal-backdrop" @click.self="closeItemModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Agregar Producto a Factura</h3>
          <button class="btn secondary" type="button" @click="closeItemModal">Cerrar</button>
        </div>
        <div class="form-grid">
          <label class="full-width">Producto
            <select v-model="itemForm.productId" @change="applyItemProduct">
              <option value="">Seleccionar producto...</option>
              <option v-for="product in invoiceProducts" :key="product.id" :value="product.id">
                {{ product.code }} - {{ product.description }}
              </option>
            </select>
          </label>
          <template v-if="!hasInventoryModule">
            <label class="full-width">Descripción
              <input v-model="itemForm.descripcion" placeholder="Descripción del ítem en la factura">
            </label>
            <label class="full-width">Tipo de ítem
              <select v-model.number="itemForm.tipoItem">
                <option :value="2">Servicio</option>
                <option :value="1">Bien</option>
              </select>
            </label>
          </template>
          <label class="full-width">Cantidad<input v-model.number="itemForm.cantidad" type="number" min="0.000001" step="0.01"></label>
          <label class="full-width">Precio Unitario<input v-model.number="itemForm.precioUnitario" type="number" min="0" step="0.01"></label>
          <label class="full-width">Descuento
            <select v-model="itemForm.tipoDescuento">
              <option value="monto">Monto ($)</option>
              <option value="porcentaje">Porcentaje (%)</option>
            </select>
          </label>
          <label class="full-width">Valor descuento<input v-model.number="itemForm.valorDescuento" type="number" min="0" step="0.01"></label>
          <label v-if="usesMultipleRelatedDocuments" class="full-width">CCF relacionado
            <select v-model="itemForm.numeroDocumentoRelacionado">
              <option value="">Seleccionar CCF...</option>
              <option v-for="document in documentosRelacionados" :key="document.numeroDocumento" :value="document.numeroDocumento">
                {{ document.numeroDocumento }}
              </option>
            </select>
          </label>
        </div>
        <div class="actions wrap modal-actions">
          <button class="btn secondary" type="button" @click="closeItemModal">Cancelar</button>
          <button class="btn" type="button" @click="addItemFromForm" :disabled="!itemForm.productId">Agregar a Factura</button>
        </div>
      </div>
    </div>

    <section v-if="activeView === 'nueva-factura'" class="form-section">
      <h3>Resumen</h3>
      <div v-if="tipo !== '07'" class="resumen-grid">
        <div class="resumen-row"><span>Subtotal Gravado:</span><strong>{{ money(subtotalGravado) }}</strong></div>
        <div class="resumen-row"><span>Subtotal Exento:</span><strong>{{ money(subtotalExento) }}</strong></div>
        <div class="resumen-row"><span>Subtotal Total:</span><strong>{{ money(subtotalTotal) }}</strong></div>
        <div class="resumen-row">
          <span>Descuento General:</span>
          <span class="summary-controls">
            <select v-model="factura.descuentoTipo">
              <option value="monto">Monto</option>
              <option value="porcentaje">%</option>
            </select>
            <input v-model.number="factura.descuentoGeneral" type="number" min="0" step="0.01">
          </span>
        </div>
        <div class="resumen-row"><span>Descuento Aplicado:</span><strong>{{ money(descuentoGeneralAplicado) }}</strong></div>
        <div class="resumen-row"><span>IVA (13%):</span><strong>{{ money(ivaEstimado) }}</strong></div>
        <div v-if="['03', '05', '06'].includes(tipo)" class="resumen-row"><span>IVA Retenido:</span><input v-model.number="factura.ivaRete1" class="summary-input" type="number" min="0" step="0.01"></div>
        <div v-if="tipo === '03'" class="resumen-row"><span>IVA Percibido:</span><input v-model.number="factura.ivaPerci1" class="summary-input" type="number" min="0" step="0.01"></div>
        <div v-if="tipo === '14'" class="resumen-row"><span>Retención Renta:</span><input v-model.number="factura.reteRenta" class="summary-input" type="number" min="0" step="0.01"></div>
        <div class="resumen-row total"><span>Total a Pagar:</span><strong>{{ money(totalEstimado) }}</strong></div>
        <div class="resumen-row"><span>Total en Letras:</span><span>{{ totalEnLetras }}</span></div>
      </div>
      <div v-else class="resumen-grid">
        <div class="resumen-row"><span>Monto sujeto a retención:</span><strong>{{ money(Number(retencion.montoSujeto || 0)) }}</strong></div>
        <div class="resumen-row"><span>IVA retenido:</span><strong>{{ money(retencionCalculada) }}</strong></div>
        <div class="resumen-row"><span>Total en Letras:</span><span>{{ numeroALetrasSimple(retencionCalculada) }}</span></div>
      </div>
    </section>

    <section v-if="activeView === 'nueva-factura' && ['01', '03'].includes(tipo)" class="form-section">
      <h3>Notas</h3>
      <label class="form-group full-width">Notas opcionales<textarea v-model="factura.notas" rows="2" maxlength="150" placeholder="Notas para factura o comprobante de crédito fiscal"></textarea><small>Opcional para Factura y CCF. Máximo 150 caracteres.</small></label>
    </section>

    <section v-if="activeView === 'nueva-factura'" class="invoice-actions">
      <div class="actions wrap">
        <button class="btn secondary" type="button" @click="resetInvoiceForm">Cancelar</button>
        <button class="btn" type="button" @click="emitir" :disabled="loading">Generar Factura</button>
      </div>
    </section>


    <section v-if="activeView === 'cuentas-por-cobrar'" class="card" style="margin-top:16px;">
      <h3>Cuentas por Cobrar</h3>
      <div class="stats-grid">
        <div class="stat"><span class="stat-icon">💳</span><div class="stat-info"><span>Por cobrar</span><strong>{{ money(arStats.totalPorCobrar) }}</strong></div></div>
        <div class="stat"><span class="stat-icon">✅</span><div class="stat-info"><span>Cobrado</span><strong>{{ money(arStats.totalCobrado) }}</strong></div></div>
        <div class="stat"><span class="stat-icon">⏳</span><div class="stat-info"><span>Pendientes</span><strong>{{ arStats.countPendiente }}</strong></div></div>
        <div class="stat"><span class="stat-icon">◑</span><div class="stat-info"><span>Parciales</span><strong>{{ arStats.countParcial }}</strong></div></div>
        <div class="stat"><span class="stat-icon">✔</span><div class="stat-info"><span>Pagadas</span><strong>{{ arStats.countPagado }}</strong></div></div>
      </div>
      <div class="form-grid filters-grid">
        <label>Buscar<input v-model="arFilters.search" type="search" placeholder="Factura o cliente..."></label>
        <label>Desde<input v-model="arFilters.from" type="date"></label>
        <label>Hasta<input v-model="arFilters.to" type="date"></label>
        <label>Estado de pago
          <select v-model="arFilters.payment_status">
            <option value="">Todos</option>
            <option value="pendiente">Pendiente</option>
            <option value="parcial">Parcial</option>
            <option value="pagado">Pagado</option>
          </select>
        </label>
        <button class="btn" type="button" @click="loadAccountsReceivable">Filtrar</button>
      </div>
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>No. Control</th>
            <th>Cliente</th>
            <th>Total</th>
            <th>Abonado</th>
            <th>Saldo</th>
            <th>Estado pago</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="arInvoices.length === 0"><td colspan="8" class="empty">No hay facturas aceptadas en cuentas por cobrar</td></tr>
          <tr v-for="invoice in arInvoices" :key="invoice.id">
            <td>{{ invoice.date }}</td>
            <td>{{ invoice.numberControl }}</td>
            <td>{{ invoice.customerName }}</td>
            <td>{{ money(invoice.total) }}</td>
            <td>{{ money(invoice.amountPaid) }}</td>
            <td><strong>{{ money(invoice.balance) }}</strong></td>
            <td><span class="badge" :class="'ar-' + invoice.paymentStatus">{{ arPaymentStatusLabel(invoice.paymentStatus) }}</span></td>
            <td>
              <button v-if="invoice.paymentStatus !== 'pagado'" class="btn" type="button" @click="openPaymentOverlay(invoice)">Registrar pago</button>
              <span v-else class="muted">{{ invoice.paidAt }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="showPaymentOverlay" class="overlay-layer">
        <button class="overlay-backdrop" type="button" aria-label="Cerrar" @click="closePaymentOverlay"></button>
        <div class="card overlay-panel">
          <div class="overlay-header">
            <div>
              <h3>Registrar pago</h3>
              <span class="muted">{{ arSelectedInvoice?.numberControl }} — Saldo: {{ money(arSelectedInvoice?.balance) }}</span>
            </div>
            <button class="btn secondary overlay-close" type="button" @click="closePaymentOverlay">Cerrar</button>
          </div>
          <div class="form-grid">
            <label>Monto recibido *<input v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01"></label>
            <label>Método de pago
              <select v-model="paymentForm.method">
                <option value="">Seleccionar...</option>
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia bancaria</option>
                <option value="Cheque">Cheque</option>
                <option value="Tarjeta">Tarjeta</option>
                <option value="Otro">Otro</option>
              </select>
            </label>
            <label class="full-width">Referencia / No. comprobante<input v-model="paymentForm.reference" placeholder="Número de comprobante, cheque o referencia..."></label>
            <label class="full-width">Notas<input v-model="paymentForm.notes" placeholder="Observaciones adicionales..."></label>
          </div>
          <div class="actions wrap modal-actions">
            <button class="btn secondary" type="button" @click="closePaymentOverlay">Cancelar</button>
            <button class="btn" type="button" @click="submitPayment" :disabled="loading">Guardar pago</button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="activeView === 'productos'" class="card" style="margin-top:16px;">
      <div class="top compact">
        <h3>Productos / Servicios</h3>
        <button class="btn" type="button" @click="openProductCreate">Nuevo producto</button>
      </div>
      <div class="form-grid mini">
        <label>Buscar producto<input v-model="productSearch" type="search" placeholder="Buscar producto..."></label>
      </div>
      <table>
        <thead><tr><th>Código</th><th>Descripción</th><th>Tipo DTE</th><th v-if="hasInventoryModule">Tipo Producto</th><th>Precio Base</th><th>IVA</th><th>Precio Final</th><th>Stock</th><th>Mín.</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-if="filteredProducts.length === 0"><td :colspan="hasInventoryModule ? 10 : 9" class="empty">No hay productos</td></tr>
          <tr v-for="product in filteredProducts" :key="product.id">
            <td>{{ product.code }}</td>
            <td>{{ product.description }}</td>
            <td>{{ productTypeName(product.type) }}</td>
            <td v-if="hasInventoryModule" style="font-size:12px">
              {{ product.productTypeName || '—' }}
              <span v-if="product.controlsInventory" style="color:var(--ok);font-size:10px;display:block">● stock</span>
            </td>
            <td>${{ Number(product.price).toFixed(2) }}</td>
            <td>{{ product.isExempt ? 'Exento' : '13%' }}</td>
            <td>${{ productFinalPrice(product).toFixed(2) }}</td>
            <td>
              <span v-if="product.stockQuantity !== null"
                :style="product.minStock !== null && product.stockQuantity <= product.minStock ? 'color:var(--bad);font-weight:700' : ''">
                {{ product.stockQuantity }}
                <span v-if="product.minStock !== null && product.stockQuantity <= product.minStock" title="Stock bajo">⚠</span>
              </span>
              <span v-else class="muted">—</span>
            </td>
            <td>
              <span v-if="product.minStock !== null">{{ product.minStock }}</span>
              <span v-else class="muted">—</span>
            </td>
            <td><button class="btn secondary" type="button" @click="openProductEdit(product)">Editar</button></td>
          </tr>
        </tbody>
      </table>

      <div v-if="showProductOverlay" class="overlay-layer product-overlay">
        <button class="overlay-backdrop" type="button" aria-label="Cerrar" @click="closeProductOverlay"></button>
        <div class="card overlay-panel">
          <div class="overlay-header">
            <div>
              <h3>{{ productForm.id ? 'Editar producto' : 'Nuevo producto' }}</h3>
              <span class="muted">Configure el producto o servicio para usarlo en facturación.</span>
            </div>
            <button class="btn secondary overlay-close" type="button" @click="closeProductOverlay">Cerrar</button>
          </div>
          <div class="form-grid product-form-grid">
            <label>Tipo DTE
              <select v-model="productForm.type">
                <option value="1">Bien</option>
                <option value="2">Servicio</option>
                <option v-if="hasInventoryModule" value="3">Ambos</option>
                <option v-if="hasInventoryModule" value="4">Otros</option>
              </select>
            </label>
            <label v-if="hasInventoryModule && productTypes.length">Tipo de producto
              <select v-model="productForm.productTypeId">
                <option :value="null">Sin clasificar</option>
                <option v-for="pt in productTypes" :key="pt.id" :value="pt.id">
                  {{ pt.name }}{{ pt.controlsInventory ? ' (controla stock)' : '' }}
                </option>
              </select>
            </label>
            <label>Código<input v-model="productForm.code" placeholder="PROD-001"></label>
            <label>Descripción<input v-model="productForm.description"></label>
            <label>Precio<input v-model.number="productForm.price" type="number" min="0" step="0.01"></label>
            <label>Unidad
              <select v-model="productForm.unit">
                <option v-for="unit in unitOptions" :key="unit.codigo" :value="unit.codigo">
                  {{ unit.codigo }} - {{ unit.nombre }}
                </option>
              </select>
            </label>
            <label>IVA
              <select v-model="productForm.isExempt">
                <option :value="false">Gravado</option>
                <option :value="true">Exento</option>
              </select>
            </label>
            <label class="full-width">Notas<input v-model="productForm.notes"></label>
            <template v-if="['1','3'].includes(productForm.type)">
              <label>Stock actual
                <input v-model.number="productForm.stockQuantity" type="number" min="0" step="1" placeholder="Ej: 100">
                <small>Unidades disponibles en inventario</small>
              </label>
              <label>Stock mínimo
                <input v-model.number="productForm.minStock" type="number" min="0" step="1" placeholder="Ej: 10">
                <small>Alerta cuando el stock llegue a este nivel</small>
              </label>
            </template>
            <div class="actions wrap modal-actions full-width">
              <button class="btn secondary" type="button" @click="closeProductOverlay">Cancelar</button>
              <button class="btn" type="button" @click="saveProduct" :disabled="loading">Guardar producto</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section v-if="activeView === 'productos-facturacion'" class="card" style="margin-top:16px;">
      <div class="top compact">
        <h3>Servicios</h3>
        <button class="btn" type="button" @click="openProductCreate">Nuevo servicio</button>
      </div>
      <div class="form-grid mini">
        <label>Buscar servicio<input v-model="productSearch" type="search" placeholder="Buscar servicio..."></label>
      </div>
      <table>
        <thead><tr><th>Código</th><th>Descripción</th><th>Precio Base</th><th>IVA</th><th>Precio Final</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-if="filteredServices.length === 0"><td colspan="6" class="empty">No hay servicios registrados</td></tr>
          <tr v-for="product in filteredServices" :key="product.id">
            <td>{{ product.code }}</td>
            <td>{{ product.description }}</td>
            <td>${{ Number(product.price).toFixed(2) }}</td>
            <td>{{ product.isExempt ? 'Exento' : '13%' }}</td>
            <td>${{ productFinalPrice(product).toFixed(2) }}</td>
            <td><button class="btn secondary" type="button" @click="openProductEdit(product)">Editar</button></td>
          </tr>
        </tbody>
      </table>

      <div v-if="showProductOverlay" class="overlay-layer product-overlay">
        <button class="overlay-backdrop" type="button" aria-label="Cerrar" @click="closeProductOverlay"></button>
        <div class="card overlay-panel">
          <div class="overlay-header">
            <div>
              <h3>{{ productForm.id ? 'Editar servicio' : 'Nuevo servicio' }}</h3>
              <span class="muted">Configure el servicio para usarlo en facturación.</span>
            </div>
            <button class="btn secondary overlay-close" type="button" @click="closeProductOverlay">Cerrar</button>
          </div>
          <div class="form-grid product-form-grid">
            <label>Código<input v-model="productForm.code" placeholder="SERV-001"></label>
            <label>Descripción<input v-model="productForm.description"></label>
            <label>Precio<input v-model.number="productForm.price" type="number" min="0" step="0.01"></label>
            <label>Unidad
              <select v-model="productForm.unit">
                <option v-for="unit in unitOptions" :key="unit.codigo" :value="unit.codigo">
                  {{ unit.codigo }} - {{ unit.nombre }}
                </option>
              </select>
            </label>
            <label>IVA
              <select v-model="productForm.isExempt">
                <option :value="false">Gravado</option>
                <option :value="true">Exento</option>
              </select>
            </label>
            <label class="full-width">Notas<input v-model="productForm.notes"></label>
            <div class="actions wrap modal-actions full-width">
              <button class="btn secondary" type="button" @click="closeProductOverlay">Cancelar</button>
              <button class="btn" type="button" @click="saveProduct" :disabled="loading">Guardar servicio</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section v-if="activeView === 'configuracion'" class="card" style="margin-top:16px;">
      <h3>Configuración</h3>
      <div class="config-lock-panel" :class="{ unlocked: configUnlocked }">
        <div>
          <h3>{{ configUnlocked ? 'Configuración desbloqueada' : 'Configuración bloqueada' }}</h3>
          <p class="muted">{{ configUnlocked ? 'Edita solo si vas a corregir datos fiscales, conexión, firma o correlativos.' : 'Los campos permanecen protegidos para evitar cambios accidentales.' }}</p>
        </div>
        <label class="config-switch" title="Bloquear o desbloquear configuración">
          <span class="config-lock-icon" :class="{ unlocked: configUnlocked }">{{ configUnlocked ? '🔓' : '🔒' }}</span>
          <input :checked="configUnlocked" type="checkbox" @change="toggleConfigLock">
          <span class="config-switch-track" aria-hidden="true"></span>
        </label>
      </div>

      <div v-if="configUnlocked" class="config-warning" role="alert">
        <strong>Advertencia de configuración activa</strong>
        <p>Cambiar estos datos puede provocar rechazos de Hacienda, errores de firma, números de control incorrectos o envío a un ambiente equivocado.</p>
        <ul>
          <li>Verifica NIT, NRC, actividad económica y dirección antes de emitir documentos.</li>
          <li>No alteres correlativos salvo que Hacienda indique que el número ya existe.</li>
          <li>Si cambias credenciales o certificado, prueba Hacienda y el firmador local antes de generar facturas.</li>
        </ul>
      </div>

      <fieldset class="config-fieldset" :disabled="!configUnlocked">
        <div class="config-section">
          <h4>Información de la Empresa</h4>
          <div class="form-grid">
            <label>NIT o DUI *<input v-model="emisor.nit" placeholder="NIT o DUI del emisor" required></label>
            <label>NRC (Número de Registro de Contribuyente)<input v-model="emisor.nrc" placeholder="000000-0"></label>
            <label>Nombre de la Empresa *<input v-model="emisor.nombre_empresa" required></label>
            <label>Nombre Comercial<input v-model="emisor.nombre_comercial"></label>
            <label class="full-width">Imagen para PDF
              <span class="inline-field">
                <input v-model="emisor.logo_path" placeholder="/ruta/al/logo.png" readonly>
                <button class="btn secondary" type="button" disabled>Seleccionar</button>
                <button class="btn secondary" type="button" @click="emisor.logo_path = ''">Quitar</button>
              </span>
              <small>Se mostrará en el encabezado del PDF. Formatos: PNG, JPG o JPEG.</small>
            </label>
            <label>Tipo de Persona
              <select v-model="emisor.tipo_persona">
                <option value="">Seleccionar...</option>
                <option value="Natural">Natural</option>
                <option value="Jurídica">Jurídica</option>
              </select>
            </label>
            <label class="full-width">Actividad Económica *
              <input v-model="activityInput" list="catalogo-actividades" placeholder="Buscar por código o descripción..." required @change="applyActivityInput">
            </label>
            <datalist id="catalogo-actividades">
              <option v-for="activity in economicActivities" :key="activity.codigo" :value="`${activity.codigo} - ${activity.descripcion}`"></option>
            </datalist>
            <label>Código actividad<input v-model="emisor.actividad_economica" readonly></label>
            <label>Descripción actividad<input v-model="emisor.desc_actividad" readonly></label>
            <label>Teléfono<input v-model="emisor.telefono" type="tel"></label>
            <label>Email<input v-model="emisor.email" type="email"></label>
            <label>Departamento
              <select v-model="emisor.departamento" @change="handleDepartmentChanged">
                <option value="">Seleccionar departamento...</option>
                <option v-for="department in departmentOptions" :key="department.codigo" :value="department.codigo">
                  {{ department.codigo }} - {{ department.nombre }}
                </option>
              </select>
            </label>
            <label>Municipio
              <select v-model="emisor.municipio" @change="handleMunicipalityChanged">
                <option value="">Seleccionar municipio...</option>
                <option v-for="municipality in municipalityOptions" :key="municipality.codigo" :value="municipality.codigo">
                  {{ municipality.codigo }} - {{ municipality.nombre }}
                </option>
              </select>
            </label>
            <label>Distrito
              <select v-model="emisor.distrito">
                <option value="">Seleccionar distrito...</option>
                <option v-for="district in districtOptions" :key="district.codigo" :value="district.codigo">
                  {{ district.codigo }} - {{ district.nombre }}
                </option>
              </select>
            </label>
            <label class="full-width config-address-field">Dirección Complementaria *<textarea v-model="emisor.direccion" rows="2" placeholder="Dirección completa, referencias y puntos de referencia" required></textarea></label>
          </div>
        </div>

        <div class="config-section">
          <h4>Configuración de Hacienda</h4>
          <div class="form-grid">
            <label>Usuario Hacienda<input v-model="hacienda.usuario" autocomplete="off"></label>
            <label>Contraseña Hacienda<input v-model="hacienda.password" type="password" autocomplete="off"></label>
            <label>Ambiente
              <select v-model="config.hacienda_ambiente">
                <option value="00">Pruebas</option>
                <option value="01">Producción</option>
              </select>
            </label>
            <label>Código Establecimiento<input v-model="config.codigo_establecimiento" maxlength="4" @input="normalizeControlCode('codigo_establecimiento')"></label>
            <label>Punto de Venta<input v-model="config.punto_venta" maxlength="4" @input="normalizeControlCode('punto_venta')"></label>
          </div>
        </div>

        <div class="config-section">
          <h4>Documentos a Generar</h4>
          <div class="document-type-grid">
            <label v-for="documentType in dteTypes" :key="documentType.codigo" class="document-type-option">
              <input v-model="selectedDteTypes" type="checkbox" :value="documentType.codigo">
              <span>{{ documentType.codigo }} - {{ documentType.nombre }}</span>
            </label>
          </div>
          <p class="form-hint">Los tipos seleccionados aparecerán en la creación de documentos, el filtro de facturas y el documento por defecto del cliente.</p>
        </div>

        <div class="config-section">
          <h4>Método de Firma Digital</h4>
          <div class="form-grid">
            <label class="full-width">Tipo de Firma
              <select v-model="firma.tipo">
                <option value="svfe">Firmador local MH/SVFE (Recomendado para pruebas)</option>
                <option value="web">Firmador Web del MH (experimental)</option>
                <option value="local">Certificado Local (.p12/.pfx)</option>
              </select>
            </label>
          </div>
          <div v-if="['svfe', 'web'].includes(firma.tipo)" class="form-grid nested-config-grid">
            <label>NIT del certificado<input v-model="firma.firmadorUsuario" placeholder="NIT sin guiones"></label>
            <label>URL Firmador SVFE<input v-model="firma.firmadorUrl" placeholder="http://localhost:8113"></label>
            <label>Contraseña llave privada<input v-model="passwordPri" type="password" placeholder="Contraseña del certificado MH" autocomplete="off"></label>
          </div>
          <div v-if="firma.tipo === 'local'" class="form-grid nested-config-grid">
            <label class="full-width">Ruta del Certificado (.p12, .pfx)
              <span class="inline-field">
                <input v-model="certificado.path" placeholder="/ruta/al/certificado.p12">
                <button class="btn secondary" type="button" disabled>Seleccionar</button>
              </span>
            </label>
            <label>Contraseña del Certificado<input v-model="passwordPri" type="password" placeholder="Contraseña del certificado" autocomplete="off"></label>
          </div>
          <p class="muted">Complete los datos del firmador o certificado antes de probar firma y enviar documentos.</p>
        </div>

        <div class="config-section">
          <h4>Correo Electrónico</h4>
          <div class="alert alert-info"><small>Para Gmail usa smtp.gmail.com, puerto 465, SSL activo y una contraseña de aplicación de Google.</small></div>
          <div class="form-grid">
            <label>Servidor SMTP<input v-model="correo.smtpHost" placeholder="smtp.gmail.com"></label>
            <label>Puerto SMTP<input v-model="correo.smtpPort" placeholder="465"></label>
            <label>Seguridad
              <select v-model="correo.smtpSecure">
                <option :value="true">SSL/TLS</option>
                <option :value="false">STARTTLS</option>
              </select>
            </label>
            <label>Usuario correo<input v-model="correo.username" type="email" placeholder="tu-correo@gmail.com"></label>
            <label>Correo remitente<input v-model="correo.from" placeholder="facturacion@empresa.com"></label>
            <label>Nombre remitente<input v-model="correo.fromName" placeholder="Nombre de la empresa"></label>
            <label>Contraseña aplicación<input v-model="correo.password" type="password" autocomplete="off"></label>
          </div>
        </div>

        <div class="config-section">
          <h4>Backup en Servidor</h4>
          <div class="alert alert-info"><small>La app subirá un backup comprimido de la base local. El servidor debe sobrescribir el último archivo recibido.</small></div>
          <div class="form-grid">
            <label class="full-width">URL del servidor<input v-model="backup.url" type="url" placeholder="https://tudominio.com/api/backups/latest"></label>
            <label>Token de seguridad<input v-model="backup.token" type="password" placeholder="Token Bearer del servidor"></label>
            <label>Clave de cifrado<input v-model="backup.encryptionKey" type="password" placeholder="Clave privada para cifrar backup"></label>
            <label>Backup automático
              <select v-model="backup.automatico">
                <option :value="false">Manual</option>
                <option :value="true">Diario al abrir la app</option>
              </select>
            </label>
            <label>Último backup<input v-model="backup.ultimo" readonly></label>
          </div>
          <div class="actions wrap config-actions">
            <button class="btn secondary" type="button" disabled>Subir Backup Ahora</button>
          </div>
        </div>

        <div class="config-section">
          <h4>Correlativos</h4>
          <div class="actions wrap config-actions">
            <button class="btn secondary" type="button" @click="message = 'Correlativos actualizados en pantalla.'">Actualizar</button>
          </div>
          <div class="table-container">
            <table class="data-table correlative-table">
              <thead><tr><th>Tipo</th><th>Año</th><th>Serie</th><th>Último local</th><th>Siguiente</th><th>Acción</th></tr></thead>
              <tbody>
                <tr v-for="documentType in dteTypes" :key="documentType.codigo">
                  <td><strong>{{ documentType.codigo }}</strong><br><small>{{ documentType.nombre }}</small></td>
                  <td>{{ currentYear }}</td>
                  <td>{{ normalizeCodeValue(config.codigo_establecimiento) }}/{{ normalizeCodeValue(config.punto_venta) }}</td>
                  <td>{{ Math.max(0, Number(correlativos[documentType.codigo] || 1) - 1) }}</td>
                  <td><input v-model.number="correlativos[documentType.codigo]" class="table-input" type="number" min="1"></td>
                  <td><button class="btn secondary" type="button" @click="guardarConfiguracion" :disabled="loading">Guardar</button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="form-hint">Suba el siguiente correlativo cuando Hacienda indique que el número ya existe. No se permite bajarlo por debajo del último usado localmente.</p>
        </div>
      </fieldset>
      <div class="actions wrap">
        <button class="btn secondary" type="button" @click="checkSigner" :disabled="loading || !configUnlocked">Probar firmador local</button>
        <button class="btn secondary" type="button" @click="autenticar" :disabled="loading || !configUnlocked">Probar Hacienda</button>
        <button class="btn" type="button" @click="guardarConfiguracion" :disabled="loading || !configUnlocked">Guardar Configuración</button>
      </div>
    </section>

    <details v-if="dte || result" class="debug-details">
      <summary>Detalle técnico</summary>
      <section class="result-grid">
      <div class="card">
        <h3>DTE</h3>
        <pre>{{ format(dte) }}</pre>
      </div>
      <div class="card">
        <h3>Resultado</h3>
        <pre>{{ format(result) }}</pre>
      </div>
      </section>
    </details>

    <div v-if="invoiceDetail.visible" class="modal-backdrop detail-modal" @click.self="closeInvoiceDetail">
      <div class="modal-content invoice-detail-modal">
        <div class="modal-header">
          <h3>Detalle de Factura</h3>
          <button class="btn secondary" type="button" @click="closeInvoiceDetail">Cerrar</button>
        </div>

        <div class="factura-detalle">
          <section class="factura-section">
            <h3>Documento</h3>
            <div class="info-grid">
              <div><span>Número de Control</span><strong>{{ selectedInvoice.numberControl || 'N/A' }}</strong></div>
              <div><span>Código de Generación</span><strong>{{ selectedInvoice.generationCode || 'N/A' }}</strong></div>
              <div><span>Sello de Recepción MH</span><strong :class="{ successText: selectedInvoice.receptionStamp }">{{ selectedInvoice.receptionStamp || 'Pendiente de envío a Hacienda' }}</strong></div>
              <div><span>Fecha de Emisión</span><strong>{{ selectedInvoice.date || 'N/A' }}</strong></div>
              <div><span>Estado</span><strong><span class="badge" :class="String(selectedInvoice.status || '').toLowerCase()">{{ selectedInvoice.status }}</span></strong></div>
              <div><span>Correo enviado</span><strong>{{ selectedInvoice.emailSent ? 'Sí' : 'No' }}</strong></div>
            </div>
            <div v-if="selectedInvoice.observations" class="detail-observations">
              <strong>Observaciones</strong>
              <pre>{{ selectedInvoice.observations }}</pre>
            </div>
          </section>

          <section class="factura-section">
            <h3>Cliente</h3>
            <div class="info-grid">
              <div><span>Nombre</span><strong>{{ selectedInvoice.customer?.name || 'N/A' }}</strong></div>
              <div><span>Documento</span><strong>{{ selectedInvoice.customer?.document || 'N/A' }}</strong></div>
              <div><span>Dirección</span><strong>{{ selectedInvoice.customer?.address || 'N/A' }}</strong></div>
              <div><span>Email</span><strong>{{ selectedInvoice.customer?.email || 'N/A' }}</strong></div>
            </div>
          </section>

          <section class="factura-section">
            <h3>Detalle de Productos/Servicios</h3>
            <table class="data-table">
              <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio Unit.</th><th>IVA</th><th>Subtotal</th></tr></thead>
              <tbody>
                <tr v-if="!selectedInvoice.items?.length"><td colspan="5" class="empty">No hay ítems</td></tr>
                <tr v-for="(item, index) in selectedInvoice.items" :key="index">
                  <td>{{ item.description }}</td>
                  <td>{{ Number(item.quantity || 0).toFixed(2) }}</td>
                  <td>{{ money(item.unitPrice) }}</td>
                  <td>{{ money(item.iva) }}</td>
                  <td>{{ money(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section class="factura-section">
            <h3>Resumen</h3>
            <div class="resumen-grid">
              <div class="resumen-row"><span>Subtotal:</span><strong>{{ money(selectedInvoice.subtotal) }}</strong></div>
              <div class="resumen-row"><span>IVA (13%):</span><strong>{{ money(selectedInvoice.iva) }}</strong></div>
              <div v-if="selectedInvoice.ivaRetenido" class="resumen-row"><span>IVA Retenido:</span><strong>{{ money(selectedInvoice.ivaRetenido) }}</strong></div>
              <div v-if="selectedInvoice.ivaPercibido" class="resumen-row"><span>IVA Percibido:</span><strong>{{ money(selectedInvoice.ivaPercibido) }}</strong></div>
              <div v-if="selectedInvoice.rentaRetenida" class="resumen-row"><span>Retención Renta:</span><strong>{{ money(selectedInvoice.rentaRetenida) }}</strong></div>
              <div class="resumen-row total"><span>Total:</span><strong>{{ money(selectedInvoice.total) }}</strong></div>
            </div>
          </section>

          <div class="actions wrap modal-actions">
            <button class="btn secondary" type="button" @click="closeInvoiceDetail">Cerrar</button>
            <button v-if="['FIRMADO', 'PENDIENTE'].includes(selectedInvoice.status)" class="btn" type="button" @click="sendStoredInvoice(selectedInvoice)" :disabled="loading">Enviar a Hacienda</button>
            <button v-if="selectedInvoice.accepted && !selectedInvoice.emailSent" class="btn secondary" type="button" @click="sendInvoiceEmail(selectedInvoice)" :disabled="loading">Enviar por Correo</button>
            <button v-if="selectedInvoice.accepted" class="btn secondary" type="button" @click="downloadInvoicePdf(selectedInvoice)" :disabled="loading">Generar PDF</button>
            <button class="btn secondary" type="button" @click="viewInvoiceJson(selectedInvoice)">Ver JSON</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const loading = ref(false);
const message = ref('');
const error = ref('');
const statusCard = ref(null);
const activeView = ref(new URLSearchParams(window.location.search).get('view') || 'dashboard');
const tipo = ref('01');
const selectedCustomerId = ref('');
const configUnlocked = ref(false);
const activityInput = ref('');
const productSearch = ref('');
const showItemForm = ref(false);
const showProductOverlay = ref(false);
const dte = ref(null);
const result = ref(null);
const passwordPri = ref('');

const config = reactive({
  hacienda_ambiente: '00',
  codigo_establecimiento: 'M001',
  punto_venta: 'P001'
});

const emisor = reactive({
  nit: '06140000000001',
  nrc: '1234567',
  nombre_empresa: 'CONSULTING AND TECH JANDRES S.A.S DE C.V.',
  nombre_comercial: 'CONSULTING AND TECH JANDRES',
  logo_path: '',
  tipo_persona: 'Jurídica',
  actividad_economica: '62020',
  desc_actividad: 'Consultorias y gestion de servicios informaticos',
  tipo_establecimiento: '01',
  departamento: '06',
  municipio: '0601',
  distrito: '',
  direccion: 'San Salvador',
  telefono: '2222-2222',
  email: 'demo@example.com'
});

const cliente = reactive({
  tipo_documento: '',
  numero_documento: '',
  nrc: '',
  nombre: '',
  nombre_comercial: '',
  giro: '',
  desc_actividad: '',
  email: '',
  telefono: '',
  direccion: '',
  departamento: '',
  municipio: '',
  pais: '',
  nombre_pais: ''
});

const factura = reactive({
  condicionOperacion: 1,
  descuentoTipo: 'monto',
  descuentoGeneral: 0,
  ivaRete1: 0,
  ivaPerci1: 0,
  reteRenta: 0,
  notas: ''
});

const documentoRelacionado = reactive({
  tipoDocumento: '03',
  tipoGeneracion: 2,
  numeroDocumento: '',
  fechaEmision: ''
});
const documentosRelacionados = ref([]);

const retencion = reactive({
  codigo: '22',
  montoSujeto: 0,
  porcentaje: 1
});

const itemForm = reactive({
  productId: '',
  descripcion: '',
  tipoItem: 2,
  cantidad: 1,
  precioUnitario: 0,
  tipoDescuento: 'monto',
  valorDescuento: 0,
  numeroDocumentoRelacionado: ''
});

const items = ref([]);

const hacienda = reactive({ usuario: '', password: '' });
const certificado = reactive({ path: '' });
const firma = reactive({ tipo: 'svfe', firmadorUsuario: '', firmadorUrl: 'http://localhost:8113' });
const correo = reactive({ smtpHost: '', smtpPort: '465', smtpSecure: true, username: '', from: '', fromName: '', password: '' });
const backup = reactive({ url: '', token: '', encryptionKey: '', automatico: false, ultimo: 'Sin backup' });
const opciones = reactive({
  correlativo: 1,
  tipoOperacion: 1,
  tipoItemExpor: 2,
  recintoFiscal: '',
  regimen: '',
  codIncoterms: '01',
  flete: 0,
  seguro: 0
});
const correlativos = reactive({ '01': 1, '03': 1, '05': 1, '06': 1, '07': 1, '11': 1, '14': 1 });
const billingCustomers = window.cotejaBilling?.customers || [];
const importedSettings = window.cotejaBilling?.settings || {};
const importedCorrelatives = window.cotejaBilling?.correlatives || {};
const clientesVariosId = window.cotejaBilling?.clientesVariosId || null;
const products = ref([]);
const invoices = ref([]);
const selectedInvoice = ref({});
const invoiceDetail = reactive({ visible: false });
const invoiceFilters = reactive({ search: '', from: formatLocalDate(new Date()), to: formatLocalDate(new Date()), status: '', type: '' });
const voidOverlay = reactive({ visible: false, invoice: null });
const voidForm = reactive({ tipoAnulacion: 2, motivo: '', codigoGeneracionR: '' });
const dashboardStats = reactive({ todayCount: 0, todaySentTotal: 0, sentCount: 0, pendingCount: 0, voidedCount: 0 });
const arStats = reactive({ totalPorCobrar: 0, totalCobrado: 0, countPendiente: 0, countParcial: 0, countPagado: 0 });
const arInvoices = ref([]);
const arFilters = reactive({ search: '', from: '', to: '', payment_status: '' });
const showPaymentOverlay = ref(false);
const arSelectedInvoice = ref(null);
const paymentForm = reactive({ amount: 0, method: '', reference: '', notes: '' });
const processOverlay = reactive({
  visible: false,
  title: 'Procesando documento',
  subtitle: 'Preparando operación...',
  badge: 'En proceso',
  status: '',
  current: -1,
  steps: []
});
const economicActivities = ref([]);
const geographicCatalog = ref({ departamentos: [], municipios: {}, distritos: {} });
const dteTypes = ref([
  { codigo: '01', nombre: 'Factura' },
  { codigo: '03', nombre: 'Comprobante de Credito Fiscal' },
  { codigo: '05', nombre: 'Nota de Credito' },
  { codigo: '06', nombre: 'Nota de Debito' },
  { codigo: '07', nombre: 'Comprobante de Retencion' },
  { codigo: '11', nombre: 'Factura de Exportacion' },
  { codigo: '14', nombre: 'Factura de Sujeto Excluido' }
]);
const relatedDocumentTypes = ref([
  { codigo: '03', nombre: 'Comprobante de Credito Fiscal' },
  { codigo: '01', nombre: 'Factura' },
  { codigo: '07', nombre: 'Comprobante de Retencion' },
  { codigo: '14', nombre: 'Sujeto Excluido' }
]);
const selectedDteTypes = ref(['01', '03', '05', '06', '07', '11', '14']);
const unitOptions = [
  { codigo: '59', nombre: 'Unidad' },
  { codigo: '11', nombre: 'Kilogramo' },
  { codigo: '14', nombre: 'Gramo' },
  { codigo: '22', nombre: 'Metro' },
  { codigo: '23', nombre: 'Metro cuadrado' },
  { codigo: '26', nombre: 'Metro cubico' },
  { codigo: '29', nombre: 'Litro' },
  { codigo: '58', nombre: 'Docena' },
  { codigo: '99', nombre: 'Otros' }
];
const productForm = reactive({
  id: null,
  code: '',
  description: '',
  type: '2',
  price: 0,
  unit: '59',
  isExempt: false,
  notes: '',
  stockQuantity: null,
  minStock: null,
  productTypeId: null,
});
const productTypes = ref([]);
const hasInventoryModule = ref(false);

const subtotalBruto = computed(() => items.value.reduce((sum, item) => sum + lineGross(item), 0));
const subtotalGravado = computed(() => Math.max(0, items.value.reduce((sum, item) => sum + (item.exento ? 0 : lineBase(item)), 0) - descuentoGeneralGravado.value));
const subtotalExento = computed(() => Math.max(0, items.value.reduce((sum, item) => sum + (item.exento ? lineBase(item) : 0), 0) - descuentoGeneralExento.value));
const subtotalTotal = computed(() => subtotalGravado.value + subtotalExento.value);
const descuentoGeneralAplicado = computed(() => {
  const value = Number(factura.descuentoGeneral || 0);
  if (factura.descuentoTipo === 'porcentaje') return roundMoney(subtotalBruto.value * (value / 100));
  return roundMoney(Math.min(value, subtotalBruto.value));
});
const descuentoGeneralGravado = computed(() => distributeGeneralDiscount(false));
const descuentoGeneralExento = computed(() => distributeGeneralDiscount(true));
const ivaEstimado = computed(() => ['07', '11', '14'].includes(tipo.value) ? 0 : roundMoney(subtotalGravado.value * 0.13));
const retencionCalculada = computed(() => roundMoney(Number(retencion.montoSujeto || 0) * (Number(retencion.porcentaje || 0) / 100)));
const totalEstimado = computed(() => {
  if (tipo.value === '07') return retencionCalculada.value;
  const exportExtras = tipo.value === '11' && Number(opciones.tipoItemExpor) !== 2
    ? Number(opciones.flete || 0) + Number(opciones.seguro || 0)
    : 0;
  return roundMoney(Math.max(0, subtotalTotal.value + ivaEstimado.value + exportExtras + Number(factura.ivaPerci1 || 0) - Number(factura.ivaRete1 || 0) - Number(factura.reteRenta || 0)));
});
const totalEnLetras = computed(() => numeroALetrasSimple(totalEstimado.value));
const processProgress = computed(() => {
  const total = Math.max(processOverlay.steps.length, 1);
  const current = Math.max(processOverlay.current + 1, 0);
  return `${Math.round((current / total) * 100)}%`;
});
const enabledDteTypes = computed(() => {
  const enabled = dteTypes.value.filter((documentType) => selectedDteTypes.value.includes(documentType.codigo));
  return enabled.length ? enabled : dteTypes.value;
});
const relatedDocumentTypesForCurrent = computed(() => {
  if (tipo.value === '07') return relatedDocumentTypes.value.filter((documentType) => ['01', '03', '14'].includes(documentType.codigo));
  if (['05', '06'].includes(tipo.value)) return relatedDocumentTypes.value.filter((documentType) => documentType.codigo === '03');
  return relatedDocumentTypes.value;
});
const usesMultipleRelatedDocuments = computed(() => ['05', '06'].includes(tipo.value));
const relatedDocumentHelp = computed(() => {
  if (usesMultipleRelatedDocuments.value) return 'Agregue uno o más Comprobantes de Crédito Fiscal afectados y seleccione el CCF correspondiente al agregar cada ítem.';
  if (tipo.value === '07') return 'Hacienda requiere el documento tributario sujeto a retención.';
  return 'Hacienda requiere relacionar el documento tributario afectado.';
});
const relatedDocumentPlaceholder = computed(() => documentoRelacionado.tipoGeneracion === 2
  ? 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX'
  : 'Número del documento físico relacionado');
const departmentOptions = computed(() => geographicCatalog.value.departamentos || []);
const municipalityOptions = computed(() => geographicCatalog.value.municipios?.[emisor.departamento] || []);
const districtOptions = computed(() => geographicCatalog.value.distritos?.[emisor.municipio] || []);
const currentDocumentDate = computed(() => formatLocalDate(new Date()));
const currentYear = computed(() => new Date().getFullYear());
const receptorCargado = computed(() => selectedCustomerId.value ? { ...cliente } : null);
const numeroControlPreview = computed(() => controlPreviewFor(tipo.value));
const filteredProducts = computed(() => {
  const term = productSearch.value.trim().toLowerCase();
  if (!term) return products.value;

  return products.value.filter((product) => [
    product.code,
    product.description,
    productTypeName(product.type),
    unitName(product.unit)
  ].some((value) => String(value || '').toLowerCase().includes(term)));
});

const invoiceProducts = computed(() =>
  hasInventoryModule.value ? products.value : products.value.filter((p) => String(p.type) === '2')
);

const filteredServices = computed(() => {
  const term = productSearch.value.trim().toLowerCase();
  const services = products.value.filter((p) => String(p.type) === '2');
  if (!term) return services;
  return services.filter((product) => [
    product.code,
    product.description,
    unitName(product.unit)
  ].some((value) => String(value || '').toLowerCase().includes(term)));
});
const voidTypeOptions = [
  { value: 1, label: 'Error en el documento' },
  { value: 2, label: 'Rescindir operación' },
  { value: 3, label: 'Otro' }
];
const allowedVoidTypeOptions = computed(() => {
  const allowed = allowedVoidTypes(voidOverlay.invoice || {});
  return voidTypeOptions.filter((option) => allowed.includes(option.value));
});
const voidRequiresReplacement = computed(() => requiresVoidReplacement(voidOverlay.invoice?.documentType, voidForm.tipoAnulacion));
const voidReplacementHelp = computed(() => {
  const type = String(voidOverlay.invoice?.documentType || '').padStart(2, '0');
  if (type === '03') return 'Para tipo 1 o 3 debe indicar el código de generación del CCF corregido que reemplaza al documento.';
  return 'Ingrese el código de generación del DTE que reemplaza al documento a invalidar.';
});
const voidHelpText = computed(() => {
  const type = String(voidOverlay.invoice?.documentType || '').padStart(2, '0');
  if (type === '03') return 'El CCF puede invalidarse directamente hasta las 23:59 del día siguiente a su emisión. Después de ese plazo debe corregirse con Nota de Crédito.';
  if (['01', '11', '14'].includes(type)) return 'Este documento puede anularse directamente durante 90 días contados desde su fecha de emisión.';
  return 'Se enviará un evento de invalidación a Hacienda. Si es aceptado, la factura quedará marcada como ANULADO.';
});

function controlPreviewFor(documentType) {
  const serie = `${normalizeCodeValue(config.codigo_establecimiento)}${normalizeCodeValue(config.punto_venta)}`;
  const correlativo = String(correlativos[documentType] || 1).padStart(15, '0');
  return `DTE-${documentType}-${serie}-${correlativo}`;
}

function formatLocalDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function roundMoney(value) {
  return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
}

function money(value) {
  return `$${roundMoney(value).toFixed(2)}`;
}

function numeroALetrasSimple(value) {
  return `${money(value)} DOLARES`;
}

function distributeGeneralDiscount(exempt) {
  if (!subtotalBruto.value) return 0;
  const base = items.value.reduce((sum, item) => sum + ((item.exento === exempt) ? lineBase(item) : 0), 0);
  return roundMoney(descuentoGeneralAplicado.value * (base / subtotalBruto.value));
}

const mhConfig = computed(() => ({
  ambiente: config.hacienda_ambiente,
  usuario: hacienda.usuario,
  password: hacienda.password
}));

function pretty(value) {
  return JSON.stringify(value, null, 2);
}

function setView(view, updateUrl = true) {
  activeView.value = view;
  if (!updateUrl) return;

  const url = new URL(window.location.href);
  url.searchParams.set('view', view);
  window.history.replaceState({}, '', url);
}

function handleExternalNavigation(event) {
  setView(event.detail?.view || 'dashboard', false);
}

function toggleConfigLock(event) {
  const unlocked = event.target.checked;
  configUnlocked.value = unlocked;

  if (unlocked) {
    alert('Advertencia: va a modificar la configuración de facturación electrónica. Verifique los datos fiscales, firma, Hacienda, correo y correlativos antes de emitir documentos.');
  }
}

onMounted(async () => {
  window.addEventListener('coteja:factura-view', handleExternalNavigation);
  applyImportedSettings();
  setActivityInputFromEmitter();
  await Promise.all([loadProducts(), loadCatalogs(), loadInvoices(), loadDashboard(), loadAccountsReceivable()]);
});

onBeforeUnmount(() => {
  window.removeEventListener('coteja:factura-view', handleExternalNavigation);
});

function applyImportedSettings() {
  const settingsEmisor = importedSettings.emisor || {};
  const settingsHacienda = importedSettings.hacienda || {};
  const settingsFirma = importedSettings.firma || {};
  const settingsCorreo = importedSettings.correo || {};
  const settingsDocs = importedSettings.documentos || [];

  Object.assign(emisor, {
    nit: settingsEmisor.nit || emisor.nit,
    nrc: settingsEmisor.nrc || emisor.nrc,
    nombre_empresa: settingsEmisor.nombre_empresa || emisor.nombre_empresa,
    nombre_comercial: settingsEmisor.nombre_comercial || emisor.nombre_comercial,
    logo_path: settingsEmisor.logo_path || emisor.logo_path,
    actividad_economica: settingsEmisor.actividad_economica || emisor.actividad_economica,
    tipo_persona: settingsEmisor.tipo_persona || emisor.tipo_persona,
    departamento: settingsEmisor.departamento || emisor.departamento,
    municipio: settingsEmisor.municipio || emisor.municipio,
    distrito: settingsEmisor.distrito || emisor.distrito,
    direccion: settingsEmisor.direccion || emisor.direccion,
    telefono: settingsEmisor.telefono || emisor.telefono,
    email: settingsEmisor.email || emisor.email
  });

  Object.assign(config, {
    hacienda_ambiente: settingsHacienda.ambiente || config.hacienda_ambiente,
    codigo_establecimiento: settingsEmisor.codigo_establecimiento || config.codigo_establecimiento,
    punto_venta: settingsEmisor.punto_venta || config.punto_venta
  });

  Object.assign(hacienda, {
    usuario: settingsHacienda.usuario || hacienda.usuario,
    password: settingsHacienda.password || hacienda.password
  });

  Object.assign(firma, {
    tipo: settingsFirma.tipo || firma.tipo,
    firmadorUsuario: settingsFirma.firmador_usuario || settingsFirma.firmadorUsuario || firma.firmadorUsuario,
    firmadorUrl: settingsFirma.firmador_url || settingsFirma.firmadorUrl || firma.firmadorUrl
  });

  certificado.path = settingsFirma.certificado_path || settingsFirma.certificadoPath || certificado.path;
  passwordPri.value = settingsFirma.certificado_password || settingsFirma.certificadoPassword || settingsFirma.firmador_pin || settingsFirma.firmadorPin || passwordPri.value;

  Object.assign(correo, {
    smtpHost: settingsCorreo.smtpHost || correo.smtpHost,
    smtpPort: settingsCorreo.smtpPort || correo.smtpPort,
    smtpSecure: settingsCorreo.smtpSecure ?? correo.smtpSecure,
    username: settingsCorreo.username || correo.username,
    from: settingsCorreo.from || correo.from,
    fromName: settingsCorreo.fromName || correo.fromName,
    password: settingsCorreo.password || correo.password
  });

  if (Array.isArray(settingsDocs) && settingsDocs.length) {
    selectedDteTypes.value = settingsDocs;
  }

  Object.assign(backup, {
    url: importedSettings.backup?.url || backup.url,
    token: importedSettings.backup?.token || backup.token,
    encryptionKey: importedSettings.backup?.encryptionKey || importedSettings.backup?.encryption_key || backup.encryptionKey,
    automatico: importedSettings.backup?.automatico ?? backup.automatico,
    ultimo: importedSettings.backup?.ultimo || backup.ultimo
  });

  Object.entries(importedCorrelatives).forEach(([documentType, nextNumber]) => {
    correlativos[documentType] = Number(nextNumber || 1);
  });
}

async function loadCatalogs() {
  const [activitiesResponse, documentsResponse, geographyResponse] = await Promise.all([
    axios.get('/catalogs/actividades-economicas.json'),
    axios.get('/catalogs/documentos.json'),
    axios.get('/catalogs/division-geografica.json')
  ]);

  economicActivities.value = activitiesResponse.data || [];
  dteTypes.value = documentsResponse.data?.dte || dteTypes.value;
  relatedDocumentTypes.value = documentsResponse.data?.relacionado || relatedDocumentTypes.value;
  geographicCatalog.value = geographyResponse.data || geographicCatalog.value;
  if (!selectedDteTypes.value.length) {
    selectedDteTypes.value = dteTypes.value.map((documentType) => documentType.codigo);
  }
  normalizeEmitterMunicipality();
  setActivityInputFromEmitter();
}

function setActivityInputFromEmitter() {
  activityInput.value = emisor.actividad_economica
    ? `${emisor.actividad_economica} - ${emisor.desc_actividad || ''}`.trim()
    : '';
}

function applyActivityInput() {
  const raw = String(activityInput.value || '').trim();
  const code = raw.split(' - ')[0]?.trim();
  const activity = economicActivities.value.find((item) => item.codigo === code)
    || economicActivities.value.find((item) => raw.toLowerCase().includes(item.descripcion.toLowerCase()));

  if (!activity) return;

  emisor.actividad_economica = activity.codigo;
  emisor.desc_actividad = activity.descripcion;
  setActivityInputFromEmitter();
}

function normalizeEmitterMunicipality() {
  const municipalities = geographicCatalog.value.municipios?.[emisor.departamento] || [];
  const current = String(emisor.municipio || '');
  const match = municipalities.find((municipality) => municipality.codigo === current)
    || municipalities.find((municipality) => municipality.codigo.slice(2, 4) === current.padStart(2, '0'));

  if (match) emisor.municipio = match.codigo;

  const districts = geographicCatalog.value.distritos?.[emisor.municipio] || [];
  const currentDistrict = String(emisor.distrito || '');
  const districtMatch = districts.find((district) => district.codigo === currentDistrict)
    || districts.find((district) => district.codigo.slice(-2) === currentDistrict.padStart(2, '0'));

  emisor.distrito = districtMatch?.codigo || districts[0]?.codigo || '';
}

function handleDepartmentChanged() {
  const municipalities = municipalityOptions.value;
  emisor.municipio = municipalities[0]?.codigo || '';
  handleMunicipalityChanged();
}

function handleMunicipalityChanged() {
  const districts = districtOptions.value;
  emisor.distrito = districts[0]?.codigo || '';
}

function handleDteTypeChanged() {
  if (tipo.value === '07' && !['01', '03', '14'].includes(documentoRelacionado.tipoDocumento)) {
    documentoRelacionado.tipoDocumento = '03';
  }
  if (usesMultipleRelatedDocuments.value) documentoRelacionado.tipoDocumento = '03';
  documentosRelacionados.value = [];
  items.value = items.value.map((item) => ({ ...item, numero_documento: null, numeroDocumentoRelacionado: null }));
  if (!['01', '03'].includes(tipo.value)) factura.notas = '';
}

const clienteVacio = { tipo_documento: '', numero_documento: '', nrc: '', nombre: '', nombre_comercial: '', giro: '', desc_actividad: '', email: '', telefono: '', direccion: '', departamento: '', municipio: '' };

function applySelectedCustomer() {
  const customer = billingCustomers.find((item) => String(item.id) === String(selectedCustomerId.value));
  if (!customer) {
    Object.assign(cliente, clienteVacio);
    return;
  }

  tipo.value = customer.preferredDteType || tipo.value;
  Object.assign(cliente, { ...clienteVacio, ...(customer.receptor || {}) });
}

if (['hacienda', 'eventos'].includes(activeView.value)) {
  activeView.value = 'configuracion';
}

function facturaConfig() {
  return {
    ...emisor,
    ...config,
    hacienda_ambiente: config.hacienda_ambiente
  };
}

function configPayload() {
  return {
    emisor: {
      ...emisor,
      codigo_establecimiento: normalizeCodeValue(config.codigo_establecimiento),
      punto_venta: normalizeCodeValue(config.punto_venta)
    },
    hacienda: {
      ambiente: config.hacienda_ambiente,
      usuario: hacienda.usuario,
      password: hacienda.password
    },
    firma: {
      tipo: firma.tipo,
      firmador_usuario: firma.firmadorUsuario,
      firmador_url: firma.firmadorUrl,
      certificado_path: certificado.path,
      certificado_password: passwordPri.value,
      firmador_pin: passwordPri.value
    },
    correo: { ...correo },
    backup: { ...backup },
    documentos: [...selectedDteTypes.value],
    correlativos: { ...correlativos }
  };
}

function facturaCliente() {
  const esClientesVarios = String(selectedCustomerId.value) === String(clientesVariosId);
  if (esClientesVarios) {
    return { ...clienteVacio, nombre: 'CONSUMIDOR FINAL' };
  }
  return { ...cliente };
}

function facturaItems() {
  if (tipo.value === '07') return [];
  return items.value.map((item) => ({
    ...item,
    precio_unitario: Number(item.precio_unitario || 0),
    cantidad: Number(item.cantidad || 0),
    descuento: Number(item.descuento || 0),
    montoDescu: Number(item.descuento || 0),
    numero_documento: item.numeroDocumentoRelacionado || item.numero_documento || undefined,
  }));
}

function facturaResumen() {
  if (tipo.value === '07') {
    return {
      totalSujetoRetencion: Number(retencion.montoSujeto || 0),
      totalIVAretenido: retencionCalculada.value
    };
  }

  return {
    condicionOperacion: factura.condicionOperacion,
    subtotal: subtotalTotal.value,
    total: totalEstimado.value,
    iva: ivaEstimado.value,
    gravada: subtotalGravado.value,
    exenta: subtotalExento.value,
    descuento: descuentoGeneralAplicado.value,
    totalGravada: subtotalGravado.value,
    totalExenta: subtotalExento.value,
    subTotalVentas: subtotalTotal.value,
    subTotal: subtotalTotal.value,
    totalDescu: descuentoGeneralAplicado.value + items.value.reduce((sum, item) => sum + Number(item.descuento || 0), 0),
    ivaRete1: Number(factura.ivaRete1 || 0),
    ivaPerci1: Number(factura.ivaPerci1 || 0),
    reteRenta: Number(factura.reteRenta || 0),
    flete: Number(opciones.flete || 0),
    seguro: Number(opciones.seguro || 0)
  };
}

function facturaOpciones() {
  const opts = {
    ...opciones,
    correlativo: correlativos[tipo.value] || opciones.correlativo,
    apendice: ['01', '03'].includes(tipo.value) && factura.notas ? [{ campo: 'notas', etiqueta: 'Notas del documento', valor: factura.notas.slice(0, 150) }] : null
  };

  if (usesMultipleRelatedDocuments.value) {
    opts.documentoRelacionado = documentosRelacionados.value;
  } else if (['05', '06', '07'].includes(tipo.value) && documentoRelacionado.numeroDocumento && documentoRelacionado.fechaEmision) {
    const related = [{
      tipoDocumento: documentoRelacionado.tipoDocumento,
      tipoGeneracion: documentoRelacionado.tipoGeneracion,
      numeroDocumento: documentoRelacionado.numeroDocumento,
      fechaEmision: documentoRelacionado.fechaEmision
    }];
    opts.documentoRelacionado = related;
    if (tipo.value === '07') opts.documentoRelacionado = related[0];
  }

  if (tipo.value === '07') {
    opts.codigoRetencionMH = retencion.codigo;
    opts.retencion = {
      montoSujeto: Number(retencion.montoSujeto || 0),
      porcentaje: Number(retencion.porcentaje || 0),
      ivaRetenido: retencionCalculada.value
    };
  }

  if (tipo.value === '11') {
    opts.flete = Number(opciones.flete || 0);
    opts.seguro = Number(opciones.seguro || 0);
    opts.descIncoterms = incotermDescription(opciones.codIncoterms);
  }

  return opts;
}

function lineGross(item) {
  return Number(item.cantidad || 0) * Number(item.precio_unitario || 0);
}

function lineBase(item) {
  return Math.max(0, roundMoney(lineGross(item) - Number(item.descuento || 0)));
}

function lineIva(item) {
  return item.exento || ['07', '11', '14'].includes(tipo.value) ? 0 : roundMoney(lineBase(item) * 0.13);
}

function lineTotal(item) {
  return roundMoney(lineBase(item) + lineIva(item));
}

function discountForItem() {
  const base = Number(itemForm.cantidad || 0) * Number(itemForm.precioUnitario || 0);
  const value = Number(itemForm.valorDescuento || 0);
  return itemForm.tipoDescuento === 'porcentaje'
    ? roundMoney(Math.min(base, base * (value / 100)))
    : roundMoney(Math.min(base, value));
}

function removeItem(index) {
  items.value.splice(index, 1);
}

function openItemModal() {
  if (usesMultipleRelatedDocuments.value && documentosRelacionados.value.length === 0) {
    error.value = 'Agregue al menos un CCF relacionado antes de agregar ítems.';
    return;
  }
  showItemForm.value = true;
}

function closeItemModal() {
  showItemForm.value = false;
  resetItemForm();
}

function applyItemProduct() {
  const product = products.value.find((item) => String(item.id) === String(itemForm.productId));
  if (!product) return;
  itemForm.precioUnitario = Number(product.price || 0);
  itemForm.descripcion = product.description || '';
  itemForm.tipoItem = 2;
}

function addItemFromForm() {
  const product = products.value.find((item) => String(item.id) === String(itemForm.productId));
  if (!product) return;
  if (usesMultipleRelatedDocuments.value && !itemForm.numeroDocumentoRelacionado) {
    error.value = `Seleccione el CCF al que aplica este ítem de la ${documentTypeName(tipo.value)}.`;
    return;
  }

  items.value.push({
    codigo: product.code,
    descripcion: hasInventoryModule.value ? product.description : (itemForm.descripcion.trim() || product.description),
    tipo_item: hasInventoryModule.value ? Number(product.type || 2) : Number(itemForm.tipoItem || 2),
    cantidad: Number(itemForm.cantidad || 1),
    precio_unitario: Number(itemForm.precioUnitario || 0),
    unidad_medida: product.unit,
    exento: Boolean(product.isExempt),
    descuento: discountForItem(),
    tipoDescuento: itemForm.tipoDescuento,
    valorDescuento: Number(itemForm.valorDescuento || 0),
    numeroDocumentoRelacionado: itemForm.numeroDocumentoRelacionado || null,
    numero_documento: itemForm.numeroDocumentoRelacionado || null
  });
  resetItemForm();
  closeItemModal();
}

function resetItemForm() {
  Object.assign(itemForm, { productId: '', descripcion: '', tipoItem: 2, cantidad: 1, precioUnitario: 0, tipoDescuento: 'monto', valorDescuento: 0, numeroDocumentoRelacionado: '' });
}

function resetInvoiceForm() {
  selectedCustomerId.value = '';
  Object.assign(cliente, {
    tipo_documento: '',
    numero_documento: '',
    nrc: '',
    nombre: '',
    nombre_comercial: '',
    giro: '',
    desc_actividad: '',
    email: '',
    telefono: '',
    direccion: '',
    departamento: '',
    municipio: '',
    pais: '',
    nombre_pais: ''
  });
  items.value = [];
  resetItemForm();
  Object.assign(factura, { condicionOperacion: 1, descuentoTipo: 'monto', descuentoGeneral: 0, ivaRete1: 0, ivaPerci1: 0, reteRenta: 0, notas: '' });
  Object.assign(documentoRelacionado, { tipoDocumento: '03', tipoGeneracion: 2, numeroDocumento: '', fechaEmision: '' });
  documentosRelacionados.value = [];
  Object.assign(retencion, { codigo: '22', montoSujeto: 0, porcentaje: 1 });
  dte.value = null;
  result.value = null;
  message.value = '';
  error.value = '';
}

function documentTypeName(code) {
  return dteTypes.value.find((documentType) => documentType.codigo === code)?.nombre || `DTE ${code}`;
}

function customerOptionLabel(customer) {
  const documentNumber = customer.receptor?.numero_documento ? ` - ${customer.receptor.numero_documento}` : '';
  return `${customer.name}${documentNumber} (${documentTypeName(customer.preferredDteType)})`;
}

function normalizeRelatedDocumentNumber(value) {
  return String(value || '').trim().toUpperCase();
}

function relatedDocumentFromForm() {
  const numeroDocumento = normalizeRelatedDocumentNumber(documentoRelacionado.numeroDocumento);
  const fechaEmision = documentoRelacionado.fechaEmision;

  if (!numeroDocumento || !fechaEmision) {
    error.value = 'Complete el número y fecha del documento relacionado.';
    return null;
  }

  if (documentoRelacionado.tipoGeneracion === 2 && !/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/.test(numeroDocumento)) {
    error.value = 'Para documento relacionado DTE, ingrese el código de generación UUID de 36 caracteres.';
    return null;
  }

  return {
    tipoDocumento: documentoRelacionado.tipoDocumento,
    tipoGeneracion: Number(documentoRelacionado.tipoGeneracion || 2),
    numeroDocumento,
    fechaEmision
  };
}

function addRelatedDocument() {
  error.value = '';
  const related = relatedDocumentFromForm();
  if (!related) return;
  if (related.tipoDocumento !== '03') {
    error.value = `${documentTypeName(tipo.value)} debe relacionar Comprobantes de Crédito Fiscal tipo 03.`;
    return;
  }
  if (documentosRelacionados.value.some((document) => document.numeroDocumento === related.numeroDocumento)) {
    error.value = `Ese CCF ya fue agregado a la ${documentTypeName(tipo.value)}.`;
    return;
  }
  if (documentosRelacionados.value.length >= 50) {
    error.value = `${documentTypeName(tipo.value)} admite hasta 50 documentos relacionados.`;
    return;
  }

  documentosRelacionados.value.push(related);
  documentoRelacionado.numeroDocumento = '';
  documentoRelacionado.fechaEmision = '';
}

function removeRelatedDocument(numeroDocumento) {
  documentosRelacionados.value = documentosRelacionados.value.filter((document) => document.numeroDocumento !== numeroDocumento);
  items.value = items.value.map((item) => (
    item.numeroDocumentoRelacionado === numeroDocumento
      ? { ...item, numeroDocumentoRelacionado: null, numero_documento: null }
      : item
  ));
}

function incotermDescription(code) {
  return {
    '01': 'EXW',
    '02': 'FCA',
    '03': 'FAS',
    '04': 'FOB',
    '05': 'CFR',
    '06': 'CIF',
    '07': 'CPT',
    '08': 'CIP',
    '09': 'DAP',
    '10': 'DPU',
    '11': 'DDP'
  }[String(code || '01')] || 'EXW';
}

function cleanFiscalDocument(value) {
  return String(value || '').replace(/\D/g, '');
}

function validateInvoice() {
  if (!selectedCustomerId.value) {
    return 'Seleccione un cliente. Para ventas sin datos del receptor use "CLIENTES VARIOS".';
  }

  if (tipo.value !== '07' && items.value.length === 0) return 'Agregue al menos un producto o servicio.';

  if (['03', '05', '06'].includes(tipo.value)) {
    const nitReceptor = cleanFiscalDocument(cliente.numero_documento);
    if (!nitReceptor || nitReceptor.length !== 14) {
      return 'Para CCF/Notas el receptor debe tener NIT de 14 dígitos. Edite el cliente y complete el campo.';
    }
    const nitEmisor = cleanFiscalDocument(emisor.nit);
    if (nitEmisor && nitReceptor === nitEmisor) {
      return 'El receptor no puede tener el mismo NIT que el emisor. Seleccione un cliente distinto.';
    }
    if (!cliente.nrc) {
      return 'Para CCF/Notas el receptor debe tener NRC. Edite el cliente y complete el campo.';
    }
    if (!cliente.giro) {
      return 'Para CCF/Notas el receptor debe tener código de actividad económica. Edite el cliente y complete el campo.';
    }
    if (!cliente.desc_actividad) {
      return 'Para CCF/Notas el receptor debe tener descripción de actividad. Edite el cliente y complete el campo.';
    }
    if (!cliente.departamento || !cliente.municipio) {
      return 'Para CCF/Notas el receptor debe tener departamento y municipio. Edite el cliente y complete los campos.';
    }
    if (!cliente.direccion) {
      return 'Para CCF/Notas el receptor debe tener dirección. Edite el cliente y complete el campo.';
    }
  }

  if (usesMultipleRelatedDocuments.value && documentosRelacionados.value.length === 0) {
    return `Agregue al menos un CCF relacionado para la ${documentTypeName(tipo.value)}.`;
  }
  if (usesMultipleRelatedDocuments.value && items.value.some((item) => !item.numeroDocumentoRelacionado)) {
    return `Cada ítem de la ${documentTypeName(tipo.value)} debe seleccionar el CCF relacionado al que aplica.`;
  }
  if (['05', '06', '07'].includes(tipo.value) && (!documentoRelacionado.numeroDocumento || !documentoRelacionado.fechaEmision)) {
    if (usesMultipleRelatedDocuments.value && documentosRelacionados.value.length > 0) return '';
    return 'Complete el documento relacionado antes de enviar.';
  }
  if (tipo.value === '07' && (!retencion.montoSujeto || !retencion.porcentaje)) {
    return 'Ingrese el monto sujeto y el porcentaje de retención.';
  }
  return '';
}

function scrollToStatus() {
  requestAnimationFrame(() => {
    statusCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

function wait(ms = 450) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function startProcess(title, labels) {
  Object.assign(processOverlay, {
    visible: true,
    title,
    subtitle: 'Preparando documento...',
    badge: 'En proceso',
    status: '',
    current: -1,
    steps: labels.map((label) => ({ label, message: '', status: '' }))
  });
}

async function advanceProcess(message, status = 'active') {
  processOverlay.current = Math.min(processOverlay.current + 1, processOverlay.steps.length - 1);
  processOverlay.subtitle = message;
  const step = processOverlay.steps[processOverlay.current];
  if (step) {
    step.message = message;
    step.status = status === 'active' ? '' : status;
  }
  await wait();
}

async function finishProcess(message, status = 'success') {
  processOverlay.subtitle = message;
  processOverlay.badge = status === 'success' ? 'Finalizado' : 'Revisar';
  processOverlay.status = status;
  processOverlay.steps.forEach((step) => {
    if (!step.status) step.status = status === 'success' ? 'done' : 'warning';
  });
  await wait(status === 'success' ? 900 : 1400);
  processOverlay.visible = false;
}

function normalizeCodeValue(value) {
  return String(value || '')
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '')
    .padStart(4, '0')
    .slice(0, 4);
}

function normalizeControlCode(key) {
  config[key] = normalizeCodeValue(config[key]);
}

function syncCorrelativoFromControl(numberControl) {
  const match = String(numberControl || '').match(/^DTE-([0-9]{2})-[A-Z0-9]{8}-([0-9]{15})$/);
  if (!match) return;
  correlativos[match[1]] = Number(match[2]) + 1;
}

function canVoidInvoice(invoice) {
  return Boolean(invoice?.accepted && invoice?.receptionStamp && ['ENVIADO', 'ACEPTADO'].includes(invoice.status) && invoiceWithinVoidWindow(invoice));
}

function invoiceWithinVoidWindow(invoice) {
  const type = String(invoice?.documentType || '').padStart(2, '0');
  const issued = parseInvoiceDate(invoice?.date);
  if (!issued) return false;

  if (type === '03') {
    const limit = endOfLocalDay(addLocalDays(issued, 1));
    return Date.now() <= limit.getTime();
  }

  if (['01', '11', '14'].includes(type)) {
    const limit = endOfLocalDay(addLocalDays(issued, 90));
    return Date.now() <= limit.getTime();
  }

  return true;
}

function allowedVoidTypes(invoice) {
  const type = String(invoice?.documentType || '').padStart(2, '0');
  if (['01', '11'].includes(type)) return [1, 2, 3];
  if (type === '03') return invoiceWithinVoidWindow(invoice) ? [1, 2, 3] : [1, 3];
  if (['05', '08'].includes(type)) return [2];
  return [1, 3];
}

function requiresVoidReplacement(documentType, voidType) {
  const type = String(documentType || '').padStart(2, '0');
  if (![1, 3].includes(Number(voidType))) return false;
  return !['05', '08'].includes(type);
}

function normalizeGenerationCode(value) {
  const code = String(value || '').trim().toUpperCase();
  return /^[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/.test(code) ? code : '';
}

function parseInvoiceDate(value) {
  const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return null;
  const [, year, month, day] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day), 0, 0, 0, 0);
  return Number.isNaN(date.getTime()) ? null : date;
}

function addLocalDays(date, days) {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

function endOfLocalDay(date) {
  const end = new Date(date);
  end.setHours(23, 59, 59, 999);
  return end;
}

async function loadAccountsReceivable() {
  const response = await axios.get('/admin/factura-sv/cuentas-por-cobrar', { params: { ...arFilters } });
  Object.assign(arStats, response.data?.stats || {});
  arInvoices.value = response.data?.invoices?.data || [];
}

function openPaymentOverlay(invoice) {
  arSelectedInvoice.value = invoice;
  Object.assign(paymentForm, { amount: invoice.balance, method: '', reference: '', notes: '' });
  showPaymentOverlay.value = true;
}

function closePaymentOverlay() {
  showPaymentOverlay.value = false;
  arSelectedInvoice.value = null;
}

async function submitPayment() {
  const invoice = arSelectedInvoice.value;
  if (!invoice) return;
  try {
    const data = await request(`/admin/factura-sv/facturas/${invoice.id}/pagar`, { ...paymentForm });
    message.value = data.message || 'Pago registrado.';
    closePaymentOverlay();
    await loadAccountsReceivable();
  } catch (e) {
    if (!error.value) error.value = e.response?.data?.message || e.message;
  } finally {
    scrollToStatus();
  }
}

function arPaymentStatusLabel(status) {
  return { pendiente: 'Pendiente', parcial: 'Parcial', pagado: 'Pagado' }[status] || status;
}

async function loadProducts() {
  const response = await axios.get('/admin/factura-sv/productos');
  const data = response.data;
  products.value = data.products || [];
  productTypes.value = data.productTypes || [];
  hasInventoryModule.value = data.hasInventory || false;
}

async function loadInvoices() {
  const response = await axios.get('/admin/factura-sv/facturas', { params: { ...invoiceFilters } });
  invoices.value = response.data?.invoices?.data || [];
}

async function loadDashboard() {
  const response = await axios.get('/admin/factura-sv/dashboard');
  Object.assign(dashboardStats, response.data?.stats || {});
}

async function saveProduct() {
  const data = await request('/admin/factura-sv/productos', { ...productForm });
  const index = products.value.findIndex((product) => product.id === data.product.id);
  if (index >= 0) products.value[index] = data.product;
  else products.value.push(data.product);
  resetProductForm();
  closeProductOverlay();
  message.value = 'Producto guardado.';
}

async function guardarConfiguracion() {
  const data = await request('/admin/factura-sv/configuracion', configPayload());
  message.value = data.message || 'Configuración guardada.';
}

function openProductCreate() {
  resetProductForm();
  showProductOverlay.value = true;
}

function openProductEdit(product) {
  Object.assign(productForm, { ...product });
  showProductOverlay.value = true;
}

function closeProductOverlay() {
  showProductOverlay.value = false;
}

function resetProductForm() {
  Object.assign(productForm, { id: null, code: '', description: '', type: '2', price: 0, unit: '59', isExempt: false, notes: '', stockQuantity: null, minStock: null, productTypeId: null });
}

function productTypeName(type) {
  return { 1: 'Bien', 2: 'Servicio', 3: 'Ambos', 4: 'Otros' }[Number(type)] || 'Servicio';
}

function unitName(unit) {
  return unitOptions.find((option) => option.codigo === String(unit))?.nombre || unit;
}

function productFinalPrice(product) {
  const price = Number(product.price || 0);
  return product.isExempt ? price : price * 1.13;
}

async function showInvoiceObservations(invoice) {
  const response = await axios.get(`/admin/factura-sv/facturas/${invoice.id}`);
  selectedInvoice.value = response.data.invoice || {};
  invoiceDetail.visible = true;
  dte.value = selectedInvoice.value.dte || null;
  result.value = selectedInvoice.value;
}

async function sendStoredInvoice(invoice) {
  error.value = '';
  message.value = '';
  startProcess('Envío de documento a Hacienda', [
    'Firmando documento',
    'Enviando a Hacienda',
    'Esperando respuesta de Hacienda',
    'Generando PDF y JSON para correo',
    'Enviando correo'
  ]);

  try {
    await advanceProcess('Preparando factura guardada...');
    const data = await request(`/admin/factura-sv/facturas/${invoice.id}/enviar`, {});
    (data.steps || []).forEach((step, index) => {
      if (processOverlay.steps[index]) {
        processOverlay.steps[index].message = step.message;
        processOverlay.steps[index].status = step.status === 'done' ? 'done' : step.status;
      }
    });
    processOverlay.current = Math.min((data.steps || []).length - 1, processOverlay.steps.length - 1);
    await loadInvoices();
    await loadDashboard();
    if (invoiceDetail.visible) {
      const refreshed = invoices.value.find((item) => item.id === invoice.id);
      if (refreshed) await showInvoiceObservations(refreshed);
    }
    result.value = data;
    message.value = data.message || 'Proceso de envío finalizado.';
    if (!data.success) error.value = data.message || 'El documento no fue aceptado por Hacienda.';
    await finishProcess(message.value, data.success ? 'success' : 'error');
  } catch (e) {
    if (!error.value) error.value = e.message;
    await finishProcess('El envío se detuvo por un error.', 'error');
  } finally {
    scrollToStatus();
  }
}

function openVoidInvoice(invoice) {
  if (!canVoidInvoice(invoice)) {
    error.value = 'Solo se pueden anular facturas enviadas, aceptadas y dentro del plazo permitido por Hacienda.';
    scrollToStatus();
    return;
  }

  const allowed = allowedVoidTypes(invoice);
  Object.assign(voidOverlay, { visible: true, invoice });
  Object.assign(voidForm, {
    tipoAnulacion: allowed.includes(2) ? 2 : allowed[0],
    motivo: allowed.includes(2) ? 'Rescindir operación realizada' : 'Error en la información del documento',
    codigoGeneracionR: ''
  });
}

function closeVoidOverlay() {
  Object.assign(voidOverlay, { visible: false, invoice: null });
  Object.assign(voidForm, { tipoAnulacion: 2, motivo: '', codigoGeneracionR: '' });
}

function handleVoidTypeChanged() {
  if (!voidRequiresReplacement.value) {
    voidForm.codigoGeneracionR = '';
  }
}

async function submitVoidInvoice() {
  const invoice = voidOverlay.invoice;
  if (!invoice) return;

  const reason = String(voidForm.motivo || '').trim();
  const replacementCode = normalizeGenerationCode(voidForm.codigoGeneracionR);

  if (!reason) {
    error.value = 'Ingrese el motivo de anulación.';
    scrollToStatus();
    return;
  }

  if (voidRequiresReplacement.value && !replacementCode) {
    error.value = voidReplacementHelp.value;
    scrollToStatus();
    return;
  }

  startProcess('Anulación de documento', [
    'Validando evento de anulación',
    'Firmando evento',
    'Enviando evento a Hacienda',
    'Registrando anulación'
  ]);

  try {
    await advanceProcess('Validando reglas de anulación...');
    const data = await request(`/admin/factura-sv/facturas/${invoice.id}/anular`, {
      tipoAnulacion: voidForm.tipoAnulacion,
      motivo: reason,
      codigoGeneracionR: replacementCode || null
    });

    processOverlay.steps[0].status = 'done';
    processOverlay.steps[1].status = 'done';
    processOverlay.steps[2].status = data.success ? 'done' : 'error';
    processOverlay.steps[3].status = data.success ? 'done' : 'warning';
    processOverlay.current = processOverlay.steps.length - 1;

    await loadInvoices();
    await loadDashboard();
    closeVoidOverlay();
    result.value = data;
    message.value = data.message || 'Factura anulada exitosamente en Hacienda.';
    if (!data.success) error.value = data.message || 'Hacienda rechazó la anulación.';
    await finishProcess(message.value, data.success ? 'success' : 'error');
  } catch (e) {
    if (!error.value) error.value = e.response?.data?.message || e.message;
    await finishProcess('La anulación se detuvo por un error.', 'error');
  } finally {
    scrollToStatus();
  }
}

function closeInvoiceDetail() {
  invoiceDetail.visible = false;
}

function viewInvoiceJson(invoice) {
  dte.value = invoice.dte || null;
  result.value = invoice;
  message.value = 'JSON DTE cargado en el detalle técnico.';
  error.value = '';
  closeInvoiceDetail();
  scrollToStatus();
}

async function downloadInvoicePdf(invoice) {
  const data = await request('/admin/factura-sv/pdf', {
    factura: {
      numero_control: invoice.numberControl,
      codigo_generacion: invoice.generationCode,
      fecha_emision: invoice.date,
      cliente: invoice.customer?.name,
      subtotal: invoice.subtotal,
      iva: invoice.iva,
      total: invoice.total,
      estado: invoice.status,
      sello_recepcion: invoice.receptionStamp
    },
    dte: invoice.dte,
    config: facturaConfig(),
    filename: `${invoice.generationCode || invoice.numberControl || 'dte'}.pdf`
  });
  if (data.base64) {
    const link = document.createElement('a');
    link.href = `data:${data.contentType};base64,${data.base64}`;
    link.download = data.filename;
    link.click();
  }
}

async function sendInvoiceEmail(invoice) {
  try {
    const data = await request(`/admin/factura-sv/facturas/${invoice.id}/correo`, {});
    message.value = data.message || 'Correo enviado.';
    error.value = data.success ? '' : message.value;
    await loadInvoices();
    await loadDashboard();
    await showInvoiceObservations(invoice);
  } catch (e) {
    if (!error.value) error.value = e.message;
    scrollToStatus();
  }
}

async function request(url, payload) {
  loading.value = true;
  error.value = '';
  message.value = '';
  try {
    const response = await axios.post(url, payload);
    result.value = response.data;
    return response.data;
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.error || e.message;
    throw e;
  } finally {
    loading.value = false;
  }
}

async function emitir() {
  error.value = '';
  message.value = '';
  result.value = null;
  const validationError = validateInvoice();
  if (validationError) {
    error.value = validationError;
    scrollToStatus();
    return;
  }

  startProcess('Envío de documento a Hacienda', [
    'Validando datos del cliente y documento',
    'Creando factura local',
    'Firmando documento',
    'Enviando a Hacienda',
    'Esperando respuesta de Hacienda',
    'Generando PDF y JSON para correo',
    'Enviando correo'
  ]);

  try {
    await advanceProcess('Validando datos del cliente y documento...');
    await advanceProcess('Creando DTE y guardando factura local...');

    const data = await request('/admin/factura-sv/procesar', {
      tipo: tipo.value,
      config: facturaConfig(),
      cliente: facturaCliente(),
      items: facturaItems(),
      resumen: facturaResumen(),
      opciones: facturaOpciones(),
      customerId: selectedCustomerId.value,
      firma: {
        tipo: firma.tipo,
        nit: firma.firmadorUsuario || emisor.nit,
        certificado_path: certificado.path,
        password: passwordPri.value || null
      },
      hacienda: {
        usuario: hacienda.usuario,
        password: hacienda.password
      },
      correo: { ...correo }
    });

    (data.steps || []).forEach((step, index) => {
      if (processOverlay.steps[index]) {
        processOverlay.steps[index].message = step.message;
        processOverlay.steps[index].status = step.status === 'done' ? 'done' : step.status;
      }
    });
    processOverlay.current = Math.min((data.steps || []).length - 1, processOverlay.steps.length - 1);

    dte.value = data.dte;
    syncCorrelativoFromControl(data.dte?.identificacion?.numeroControl);
    result.value = data;
    await loadInvoices();
    await loadDashboard();

    const hasBlockingStep = (data.steps || []).some((step) => step.status === 'error');
    const hasPendingStep = (data.steps || []).some((step) => step.status === 'warning');
    setView('dashboard');
    message.value = hasPendingStep
      ? 'Factura generada y guardada. Hay acciones pendientes de firma o envío.'
      : 'Factura generada, guardada y procesada.';
    if (hasBlockingStep) error.value = 'El proceso terminó con errores. Revise el detalle de la factura.';
    await finishProcess(message.value, hasBlockingStep ? 'error' : 'success');
  } catch (e) {
    if (!error.value) error.value = e.message;
    await finishProcess('El proceso se detuvo por un error inesperado.', 'error');
  } finally {
    scrollToStatus();
  }
}

async function checkSigner() {
  const data = await request('/admin/factura-sv/firmador/estado', {
    certificadoPath: certificado.path,
    nit: firma.firmadorUsuario || emisor.nit,
    passwordPri: passwordPri.value
  });
  message.value = data.valido ? 'Certificado local válido.' : 'Certificado local inválido.';
}

async function autenticar() {
  const data = await request('/admin/factura-sv/autenticar', { config: mhConfig.value });
  message.value = data.success ? 'Autenticación correcta.' : 'No se pudo autenticar.';
}

function format(value) {
  return value ? JSON.stringify(value, null, 2) : '';
}
</script>

<style scoped>
.fe-grid, .result-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 16px;
}

.actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.wrap {
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.json-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 12px;
}

.json-grid.two {
  margin-top: 12px;
}

textarea {
  min-height: 230px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
}

.mini {
  margin-top: 14px;
  align-items: end;
}

.customer-select {
  margin-bottom: 12px;
}

.factura-form,
.form-section {
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 1.5rem;
  margin-top: 16px;
  box-shadow: 0 1px 2px rgb(0 0 0 / .04);
}

.form-section h3 {
  margin: 0 0 1rem;
  color: var(--ink);
  font-size: 1.15rem;
}

.form-group {
  display: grid;
  gap: .5rem;
}

.form-group small,
.form-hint {
  color: var(--muted);
  font-size: .82rem;
  font-weight: 400;
}

.form-section textarea {
  min-height: 80px;
  font-family: inherit;
  font-size: inherit;
}

.receptor-resumen {
  margin-top: 1rem;
  padding: .85rem 1rem;
  background: var(--bg, #f9fafb);
  border: 1px solid var(--line);
  border-radius: 8px;
  font-size: .88rem;
}

.receptor-resumen-titulo {
  font-weight: 600;
  color: var(--muted);
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: .6rem;
}

.receptor-resumen-grid {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: .3rem .75rem;
  align-items: baseline;
}

.receptor-label {
  color: var(--muted);
  font-size: .82rem;
  white-space: nowrap;
}

.receptor-valor {
  color: var(--ink);
}

.receptor-nrc {
  color: var(--muted);
}

.receptor-faltante {
  color: #dc2626;
  font-weight: 500;
}

.receptor-opcional {
  color: var(--muted);
  font-style: italic;
}

:global(body.dark-mode) .receptor-resumen {
  background: #1a1a2e;
}

.alert {
  padding: .85rem 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
}

.alert-info {
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

:global(body.dark-mode) .alert-info {
  background: #0f1d35;
  color: #bfdbfe;
  border-color: #1d4ed8;
}

.items-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 1rem;
}

.items-header h3 {
  margin: 0;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  white-space: nowrap;
}

.invoice-actions {
  margin-top: 16px;
}

.table-actions {
  margin: 0;
  gap: 6px;
}

.resumen-grid {
  display: grid;
  gap: 10px;
}

.resumen-row {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #e5e7eb;
}

.resumen-row.total {
  font-size: 18px;
  font-weight: 800;
}

.summary-controls {
  display: grid;
  grid-template-columns: 120px 140px;
  gap: 8px;
}

.summary-input {
  max-width: 150px;
}

.btn.danger {
  background: #dc2626;
}

.related-documents-table {
  margin-top: 14px;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgb(15 23 42 / .62);
}

.modal-content {
  width: min(500px, 100%);
  max-height: calc(100vh - 36px);
  overflow: auto;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--panel);
  padding: 18px;
  box-shadow: 0 24px 60px rgb(0 0 0 / .22);
}

.invoice-detail-modal {
  width: min(880px, 100%);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.modal-header h3 {
  margin: 0;
}

.modal-actions {
  margin: 16px 0 0;
  justify-content: flex-end;
}

.factura-detalle {
  display: grid;
  gap: 14px;
}

.factura-section {
  display: grid;
  gap: 12px;
}

.factura-section h3 {
  margin: 0;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 10px;
}

.info-grid > div {
  display: grid;
  gap: 4px;
  padding: 10px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: #f9fafb;
}

.info-grid span {
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.info-grid strong {
  overflow-wrap: anywhere;
}

.successText {
  color: var(--ok);
}

.detail-observations {
  display: grid;
  gap: 8px;
}

.detail-observations pre {
  max-height: 180px;
  margin: 0;
}

.process-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgb(15 23 42 / .48);
}

.process-card {
  width: min(560px, 100%);
  padding: 20px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: var(--panel);
  box-shadow: 0 24px 70px rgb(0 0 0 / .28);
}

.process-header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
  margin-bottom: 14px;
}

.process-header h3,
.process-header p {
  margin: 0;
}

.process-header p {
  margin-top: 4px;
  color: var(--muted);
}

.process-badge {
  flex: 0 0 auto;
  padding: 6px 10px;
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 12px;
  font-weight: 800;
}

.process-badge.success {
  background: #d1fae5;
  color: #047857;
}

.process-badge.error {
  background: #fee2e2;
  color: #b91c1c;
}

.process-track {
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: #e5e7eb;
  margin-bottom: 14px;
}

.process-track-fill {
  height: 100%;
  width: 0;
  border-radius: inherit;
  background: var(--brand);
  transition: width .28s ease;
}

.process-track-fill.success {
  background: var(--ok);
}

.process-track-fill.error {
  background: var(--bad);
}

.process-steps {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.process-steps li {
  padding: 9px 10px;
  border: 1px solid var(--line);
  border-radius: 8px;
  color: var(--muted);
  background: #f9fafb;
}

.process-steps li.active {
  color: #1d4ed8;
  border-color: #93c5fd;
  background: #eff6ff;
}

.process-steps li.done {
  color: #047857;
  border-color: #86efac;
  background: #f0fdf4;
}

.process-steps li.warning {
  color: #92400e;
  border-color: #fbbf24;
  background: #fffbeb;
}

.process-steps li.error {
  color: #b91c1c;
  border-color: #fecaca;
  background: #fef2f2;
}

.product-overlay {
  z-index: 100;
}

.product-form-grid {
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
}

.config-section {
  margin-top: 14px;
}

.config-section h4 {
  margin: 0 0 12px;
}

.config-fieldset {
  border: 0;
  margin: 0;
  padding: 0;
}

.config-fieldset:disabled {
  opacity: 0.72;
}

.config-lock-panel {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
  margin-top: 12px;
}

:global(body.dark-mode) .config-lock-panel {
  border-color: #263244;
  background: #0f172a;
}

.config-lock-panel.unlocked {
  border-color: #22c55e;
  background: #f0fdf4;
}

:global(body.dark-mode) .config-lock-panel.unlocked {
  border-color: #22c55e;
  background: #052e16;
}

.config-lock-panel h3 {
  margin: 0 0 4px;
}

.config-warning {
  margin-top: 12px;
  padding: 14px;
  border: 1px solid #f59e0b;
  border-radius: 8px;
  background: #fffbeb;
  color: #92400e;
}

.config-warning strong {
  display: block;
  margin-bottom: 6px;
}

.config-warning p {
  margin: 0 0 8px;
}

.config-warning ul {
  margin: 0;
  padding-left: 18px;
}

:global(body.dark-mode) .config-warning {
  border-color: #f59e0b;
  background: #2a1f08;
  color: #fde68a;
}

.config-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.config-switch input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.config-lock-icon {
  display: inline-flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: #111827;
  color: #fff;
  transform: rotate(0deg) scale(1);
  transition: transform 180ms ease, background 180ms ease;
}

.config-lock-icon.unlocked {
  background: #16a34a;
  transform: rotate(-12deg) scale(1.08);
}

.config-switch-track {
  width: 52px;
  height: 28px;
  border-radius: 999px;
  background: #9ca3af;
  position: relative;
  transition: background 180ms ease;
}

.config-switch-track::after {
  content: '';
  position: absolute;
  top: 4px;
  left: 4px;
  width: 20px;
  height: 20px;
  border-radius: 999px;
  background: #fff;
  transition: transform 180ms ease;
}

.config-switch input:checked + .config-switch-track {
  background: #16a34a;
}

.config-switch input:checked + .config-switch-track::after {
  transform: translateX(24px);
}

.full-width {
  grid-column: 1 / -1;
}

.document-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
  margin-bottom: 12px;
}

.document-type-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}

.document-type-option input[type="checkbox"] {
  flex: 0 0 auto;
  width: 16px;
  height: 16px;
  margin: 0;
}

.document-type-option span {
  min-width: 0;
  line-height: 1.25;
}

:global(body.dark-mode) .document-type-option {
  border-color: #263244;
  background: #0f172a;
}

.inline-field {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 8px;
  align-items: center;
}

.nested-config-grid {
  margin-top: 12px;
}

.config-address-field textarea {
  min-height: 40px;
}

.table-container {
  width: 100%;
  overflow-x: auto;
}

.table-input {
  width: 120px;
  max-width: 100%;
}

.config-actions {
  margin: 10px 0 12px;
}

.correlative-table input {
  max-width: 120px;
}

.config-grid textarea {
  min-height: 190px;
}

.compact {
  margin-bottom: 12px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.stat {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
}

:global(body.dark-mode) .stat {
  border-color: #263244;
  background: #0f172a;
}

.stat-icon {
  font-size: 2rem;
  flex: 0 0 auto;
  line-height: 1;
}

.stat-info {
  display: grid;
  gap: 4px;
}

.stat-info span {
  color: #6b7280;
  font-size: 13px;
}

:global(body.dark-mode) .stat-info span {
  color: #9ca3af;
}

.stat-info strong {
  font-size: 24px;
}

.empty {
  text-align: center;
  color: #6b7280;
}

:global(body.dark-mode) .empty {
  color: #9ca3af;
}

.status-card {
  margin-top: 16px;
}

.result-grid {
  margin-top: 16px;
}

pre {
  max-height: 520px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 12px;
}

summary {
  cursor: pointer;
  font-weight: 700;
  margin: 8px 0 12px;
}

/* Invoice / document status badge colors (includes normalized states from Hacienda) */
.pendiente   { background: #fef3c7; color: #92400e; }
.firmado     { background: #dbeafe; color: #1e40af; }
.enviado     { background: #dbeafe; color: #1e40af; }
.procesado   { background: #dbeafe; color: #1e40af; }
.recibido    { background: #dbeafe; color: #1e40af; }
.aceptado    { background: #d1fae5; color: #065f46; }
.rechazado   { background: #fee2e2; color: #991b1b; }
.anulado     { background: #f1f5f9; color: #475569; }
.invalidado  { background: #f1f5f9; color: #475569; }
.contingencia { background: #fff7ed; color: #9a3412; }

/* Accounts receivable payment status badges */
.ar-pendiente  { background: #fef3c7; color: #92400e; }
.ar-parcial    { background: #dbeafe; color: #1e40af; }
.ar-pagado     { background: #d1fae5; color: #065f46; }

:global(body.dark-mode) .ar-pendiente { background: #451a03; color: #fde68a; }
:global(body.dark-mode) .ar-parcial   { background: #1e3a5f; color: #bfdbfe; }
:global(body.dark-mode) .ar-pagado    { background: #052e16; color: #86efac; }

:global(body.dark-mode) .pendiente    { background: #451a03; color: #fde68a; }
:global(body.dark-mode) .firmado      { background: #1e3a5f; color: #bfdbfe; }
:global(body.dark-mode) .enviado      { background: #1e3a5f; color: #bfdbfe; }
:global(body.dark-mode) .procesado    { background: #1e3a5f; color: #bfdbfe; }
:global(body.dark-mode) .recibido     { background: #1e3a5f; color: #bfdbfe; }
:global(body.dark-mode) .aceptado     { background: #052e16; color: #86efac; }
:global(body.dark-mode) .rechazado    { background: #450a0a; color: #fecaca; }
:global(body.dark-mode) .anulado      { background: #1e293b; color: #94a3b8; }
:global(body.dark-mode) .invalidado   { background: #1e293b; color: #94a3b8; }
:global(body.dark-mode) .contingencia { background: #431407; color: #fed7aa; }

:global(body.dark-mode) .resumen-row {
  border-bottom-color: #263244;
}
</style>
