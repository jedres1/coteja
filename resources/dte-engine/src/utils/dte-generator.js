/**
 * Generador de DTEs (Documentos Tributarios Electrónicos)
 * Basado en los schemas oficiales del Ministerio de Hacienda de El Salvador
 */

const crypto = require('crypto');
const actividadesEconomicas = require('../data/actividades-economicas.json');
const divisionGeografica = require('../data/division-geografica.json');

class DTEGenerator {
  constructor() {
    // Códigos de unidades de medida según catálogo MH
    this.unidadesMedida = {
      'UND': 59,  // Unidad
      '59': 59,
      'UNIDAD': 59,
      'KG': 11,   // Kilogramo
      '11': 11,
      '14': 14,   // Gramo
      'GR': 14,
      'LT': 29,   // Litro
      '29': 29,
      '20': 20,
      'MT': 22,   // Metro
      '22': 22,
      '23': 23,   // Metro cuadrado
      '25': 25,   // Vara
      '26': 26,   // Metro cubico
      'HR': 18,   // Hora
      '18': 18,
      'SV': 58,
      '58': 58,   // Docena
      '99': 99,
      'DOC': 12   // Documento
    };
  }

  construirIdentificacion(config, tipoDte, version, numeroControl, codigoGeneracion, now, opciones = {}, motivoField = 'motivoContin') {
    const tipoOperacion = Number(opciones.tipoTransmision || opciones.tipoOperacion || 1);
    const esContingencia = tipoOperacion === 2;
    const tipoContingencia = esContingencia ? this.normalizarTipoContingencia(opciones.tipoContingencia) : null;
    const motivoContingencia = esContingencia
      ? this.normalizarMotivoContingencia(opciones.motivoContin || opciones.motivoContingencia || opciones.motivoContigencia, tipoContingencia)
      : null;

    return {
      version,
      ambiente: this.obtenerCodigoAmbiente(config.hacienda_ambiente),
      tipoDte,
      numeroControl,
      codigoGeneracion,
      tipoModelo: esContingencia ? 2 : 1,
      tipoOperacion: esContingencia ? 2 : 1,
      tipoContingencia,
      [motivoField]: motivoContingencia,
      fecEmi: this.formatearFecha(now),
      horEmi: this.formatearHora(now),
      tipoMoneda: 'USD'
    };
  }

  normalizarTipoContingencia(valor) {
    const tipo = Number(valor || 5);
    return [1, 2, 3, 4, 5].includes(tipo) ? tipo : 5;
  }

  normalizarMotivoContingencia(valor, tipoContingencia = 5) {
    const motivo = String(valor || (tipoContingencia === 5 ? 'Otro motivo de contingencia' : 'Falla tecnica de transmision'))
      .trim()
      .slice(0, 150);

    return motivo.length >= 5 ? motivo : 'Falla tecnica de transmision';
  }

  /**
   * Generar Factura Electrónica (Tipo 01)
   */
  generarFactura(config, cliente, items, resumen, opciones = {}) {
    const now = new Date();
    const codigoGeneracion = this.generarCodigoGeneracion();
    const correlativo = opciones.correlativo || 1;
    const numeroControl = this.generarNumeroControl('01', config.codigo_establecimiento, config.punto_venta, correlativo);
    const cuerpoDocumento = this.construirCuerpoDocumentoFactura(items);
    const resumenFactura = this.construirResumenFactura(this.ajustarResumenDesdeCuerpo(resumen, cuerpoDocumento, '01'));

    return {
      identificacion: this.construirIdentificacion(config, '01', 1, numeroControl, codigoGeneracion, now, opciones),
      documentoRelacionado: opciones.documentoRelacionado || null,
      emisor: this.construirEmisor(config),
      receptor: this.debeOmitirReceptorFactura(cliente, resumenFactura.montoTotalOperacion)
        ? null
        : this.construirReceptorFactura(cliente),
      otrosDocumentos: opciones.otrosDocumentos || null,
      ventaTercero: opciones.ventaTercero || null,
      cuerpoDocumento,
      resumen: resumenFactura,
      extension: opciones.extension || null,
      apendice: opciones.apendice || null
    };
  }

  /**
   * Generar Comprobante de Crédito Fiscal (Tipo 03)
   */
  generarCreditoFiscal(config, cliente, items, resumen, opciones = {}) {
    const now = new Date();
    const codigoGeneracion = this.generarCodigoGeneracion();
    const correlativo = opciones.correlativo || 1;
    const numeroControl = this.generarNumeroControl('03', config.codigo_establecimiento, config.punto_venta, correlativo);
    const cuerpoDocumento = this.construirCuerpoDocumento(items);

    return {
      identificacion: this.construirIdentificacion(config, '03', 3, numeroControl, codigoGeneracion, now, opciones),
      documentoRelacionado: opciones.documentoRelacionado || null,
      emisor: this.construirEmisor(config),
      receptor: this.construirReceptorCCF(cliente),
      otrosDocumentos: opciones.otrosDocumentos || null,
      ventaTercero: opciones.ventaTercero || null,
      cuerpoDocumento,
      resumen: this.construirResumenCCF(this.ajustarResumenDesdeCuerpo(resumen, cuerpoDocumento, '03')),
      extension: opciones.extension || null,
      apendice: opciones.apendice || null
    };
  }

  /**
   * Generar Nota de Crédito (Tipo 05)
   */
  generarNotaCredito(config, cliente, items, resumen, documentoRelacionado, opciones = {}) {
    const documentosRelacionados = Array.isArray(documentoRelacionado)
      ? documentoRelacionado
      : (documentoRelacionado ? [documentoRelacionado] : []);

    if (documentosRelacionados.length === 0) {
      throw new Error('La Nota de Crédito requiere un documento relacionado según schema MH.');
    }

    const now = new Date();
    const codigoGeneracion = this.generarCodigoGeneracion();
    const correlativo = opciones.correlativo || 1;
    const numeroControl = this.generarNumeroControl('05', config.codigo_establecimiento, config.punto_venta, correlativo);
    const cuerpoDocumento = this.construirCuerpoDocumentoNC(items);

    return {
      identificacion: this.construirIdentificacion(config, '05', 3, numeroControl, codigoGeneracion, now, opciones),
      documentoRelacionado: documentosRelacionados,
      emisor: this.construirEmisorNC(config),
      receptor: this.construirReceptorCCF(cliente),
      ventaTercero: opciones.ventaTercero || null,
      cuerpoDocumento,
      resumen: this.construirResumenNC(this.ajustarResumenDesdeCuerpo(resumen, cuerpoDocumento, '05')),
      extension: opciones.extension || null,
      apendice: opciones.apendice || null
    };
  }

  /**
   * Generar Nota de Débito (Tipo 06)
   */
  generarNotaDebito(config, cliente, items, resumen, documentoRelacionado, opciones = {}) {
    const documentosRelacionados = Array.isArray(documentoRelacionado)
      ? documentoRelacionado
      : (documentoRelacionado ? [documentoRelacionado] : []);

    if (documentosRelacionados.length === 0) {
      throw new Error('La Nota de Débito requiere un documento relacionado según schema MH.');
    }

    const now = new Date();
    const codigoGeneracion = this.generarCodigoGeneracion();
    const correlativo = opciones.correlativo || 1;
    const numeroControl = this.generarNumeroControl('06', config.codigo_establecimiento, config.punto_venta, correlativo);
    const cuerpoDocumento = this.construirCuerpoDocumentoNC(items);

    return {
      identificacion: this.construirIdentificacion(config, '06', 3, numeroControl, codigoGeneracion, now, opciones),
      documentoRelacionado: documentosRelacionados,
      emisor: this.construirEmisorNC(config),
      receptor: this.construirReceptorCCF(cliente),
      ventaTercero: opciones.ventaTercero || null,
      cuerpoDocumento,
      resumen: this.construirResumenND(this.ajustarResumenDesdeCuerpo(resumen, cuerpoDocumento, '06')),
      extension: opciones.extension || null,
      apendice: opciones.apendice || null
    };
  }

  /**
   * Generar Comprobante de Retención (Tipo 07)
   */
  generarComprobanteRetencion(config, cliente, items, resumen, documentoRelacionado, opciones = {}) {
    if (!documentoRelacionado) {
      throw new Error('El Comprobante de Retención requiere un documento relacionado según lineamientos MH.');
    }

    const now = new Date();
    const codigoGeneracion = this.generarCodigoGeneracion();
    const correlativo = opciones.correlativo || 1;
    const numeroControl = this.generarNumeroControl('07', config.codigo_establecimiento, config.punto_venta, correlativo);

    return {
      identificacion: this.construirIdentificacion(config, '07', 1, numeroControl, codigoGeneracion, now, opciones),
      emisor: this.construirEmisorRetencion(config),
      receptor: this.construirReceptorRetencion(cliente),
      cuerpoDocumento: this.construirCuerpoDocumentoRetencion(items, documentoRelacionado, opciones),
      resumen: this.construirResumenRetencion(resumen, items, opciones),
      extension: opciones.extension || null,
      apendice: opciones.apendice || null
    };
  }

  /**
   * Generar Factura de Exportación (Tipo 11)
   */
  generarFacturaExportacion(config, cliente, items, resumen, opciones = {}) {
    const now = new Date();
    const codigoGeneracion = this.generarCodigoGeneracion();
    const correlativo = opciones.correlativo || 1;
    const numeroControl = this.generarNumeroControl('11', config.codigo_establecimiento, config.punto_venta, correlativo);
    const opcionesExportacion = {
      ...opciones,
      tipoItemExpor: Number(opciones.tipoItemExpor || config.tipo_item_expor || 2)
    };
    const cuerpoDocumento = this.construirCuerpoDocumentoExportacion(items);
    const resumenExportacion = this.ajustarResumenExportacionDesdeCuerpo(resumen, cuerpoDocumento, opcionesExportacion);

    return {
      identificacion: this.construirIdentificacion(config, '11', 1, numeroControl, codigoGeneracion, now, opciones, 'motivoContigencia'),
      emisor: this.construirEmisorExportacion(config, opcionesExportacion),
      receptor: this.construirReceptorExportacion(cliente),
      otrosDocumentos: opciones.otrosDocumentos || null,
      ventaTercero: opciones.ventaTercero || null,
      cuerpoDocumento,
      resumen: this.construirResumenExportacion(resumenExportacion, opcionesExportacion),
      apendice: opciones.apendice || null
    };
  }

  /**
   * Generar Factura Sujeto Excluido (Tipo 14)
   */
  generarFacturaSujetoExcluido(config, cliente, items, resumen, opciones = {}) {
    const now = new Date();
    const codigoGeneracion = this.generarCodigoGeneracion();
    const correlativo = opciones.correlativo || 1;
    const numeroControl = this.generarNumeroControl('14', config.codigo_establecimiento, config.punto_venta, correlativo);

    return {
      identificacion: this.construirIdentificacion(config, '14', 1, numeroControl, codigoGeneracion, now, opciones),
      emisor: this.construirEmisorFSE(config),
      sujetoExcluido: this.construirSujetoExcluido(cliente),
      cuerpoDocumento: this.construirCuerpoDocumentoFSE(items),
      resumen: this.construirResumenFSE(resumen),
      apendice: opciones.apendice || null
    };
  }

  /**
   * Construir objeto Emisor
   */
  construirEmisor(config) {
    const tipoPersona = String(config.tipo_persona || '').toLowerCase();
    const tipoEstablecimiento = config.tipo_establecimiento || (tipoPersona.includes('natural') ? '02' : '01');

    return {
      nit: this.limpiarDocumento(config.nit),
      nrc: this.limpiarDocumento(config.nrc),
      nombre: config.nombre_empresa,
      codActividad: this.normalizarCodigoActividad(config.actividad_economica),
      descActividad: this.normalizarDescripcionActividad(config.actividad_economica, config.desc_actividad),
      nombreComercial: config.nombre_comercial || null,
      tipoEstablecimiento,
      direccion: {
        departamento: config.departamento,
        municipio: this.normalizarMunicipio(config.departamento, config.municipio),
        complemento: config.direccion
      },
      telefono: config.telefono,
      correo: config.email,
      codEstableMH: config.codigo_establecimiento || null,
      codEstable: config.codigo_estable || null,
      codPuntoVentaMH: config.punto_venta || null,
      codPuntoVenta: config.cod_punto_venta || null
    };
  }

  /**
   * Construir objeto Emisor para Nota de Crédito
   */
  construirEmisorNC(config) {
    const emisor = this.construirEmisor(config);
    // En NC no se requieren codEstableMH, codEstable, codPuntoVentaMH, codPuntoVenta
    delete emisor.codEstableMH;
    delete emisor.codEstable;
    delete emisor.codPuntoVentaMH;
    delete emisor.codPuntoVenta;
    return emisor;
  }

  /**
   * Construir objeto Emisor para Factura de Exportación.
   */
  construirEmisorExportacion(config, opciones = {}) {
    const tipoItemExpor = Number(opciones.tipoItemExpor || config.tipo_item_expor || 2);

    return {
      ...this.construirEmisor(config),
      tipoItemExpor,
      recintoFiscal: tipoItemExpor === 2 ? null : this.normalizarRecintoFiscal(opciones.recintoFiscal || config.recinto_fiscal),
      regimen: tipoItemExpor === 2 ? null : (opciones.regimen || config.regimen || null)
    };
  }

  /**
   * Construir objeto Emisor para Factura de Sujeto Excluido.
   */
  construirEmisorFSE(config) {
    const emisor = this.construirEmisor(config);
    delete emisor.nombreComercial;
    delete emisor.tipoEstablecimiento;
    return emisor;
  }

  /**
   * Construir objeto Emisor para Comprobante de Retención.
   */
  construirEmisorRetencion(config) {
    const emisor = this.construirEmisor(config);
    emisor.codigoMH = emisor.codEstableMH;
    emisor.codigo = emisor.codEstable;
    emisor.puntoVentaMH = emisor.codPuntoVentaMH;
    emisor.puntoVenta = emisor.codPuntoVenta;
    delete emisor.codEstableMH;
    delete emisor.codEstable;
    delete emisor.codPuntoVentaMH;
    delete emisor.codPuntoVenta;
    return emisor;
  }

  normalizarRecintoFiscal(valor) {
    const limpio = this.limpiarDocumento(valor);
    return limpio ? limpio.padStart(2, '0') : null;
  }

  construirDireccionReceptor(cliente, requerida = false) {
    const complemento = String(cliente?.direccion || '').trim();
    if (complemento.length < 5) {
      return requerida
        ? {
            departamento: cliente?.departamento,
            municipio: this.normalizarMunicipio(cliente?.departamento, cliente?.municipio),
            complemento
          }
        : null;
    }

    return {
      departamento: cliente.departamento,
      municipio: this.normalizarMunicipio(cliente.departamento, cliente.municipio),
      complemento
    };
  }

  /**
   * Construir objeto Receptor para Factura
   */
  construirReceptorFactura(cliente) {
    if (!cliente) return null;
    const tipoDocumento = cliente.tipo_documento || null;
    const esDui = String(tipoDocumento) === '13';
    const direccion = this.construirDireccionReceptor(cliente, false);

    return {
      tipoDocumento,
      numDocumento: this.normalizarDocumentoReceptor(tipoDocumento, cliente.numero_documento) || null,
      nrc: tipoDocumento === '36' ? (this.limpiarDocumento(cliente.nrc) || null) : null,
      nombre: cliente.nombre || null,
      codActividad: !esDui && cliente.giro ? this.normalizarCodigoActividad(cliente.giro) : null,
      descActividad: !esDui && cliente.giro ? this.normalizarDescripcionActividad(cliente.giro, cliente.desc_actividad) : null,
      direccion,
      telefono: cliente.telefono || null,
      correo: cliente.email || null
    };
  }

  /**
   * Construir objeto Receptor para CCF/NC (todos los campos requeridos)
   */
  construirReceptorCCF(cliente) {
    const direccion = this.construirDireccionReceptor(cliente, true);

    return {
      nit: this.limpiarDocumento(cliente.numero_documento),
      nrc: this.limpiarDocumento(cliente.nrc),
      nombre: cliente.nombre,
      codActividad: this.normalizarCodigoActividad(cliente.giro),
      descActividad: this.normalizarDescripcionActividad(cliente.giro, cliente.desc_actividad),
      nombreComercial: cliente.nombre_comercial || null,
      direccion,
      telefono: cliente.telefono || null,
      correo: cliente.email
    };
  }

  /**
   * Construir objeto Receptor para Comprobante de Retención.
   */
  construirReceptorRetencion(cliente) {
    const tipoDocumento = cliente.tipo_documento || '36';
    const direccion = this.construirDireccionReceptor(cliente, true);

    return {
      tipoDocumento,
      numDocumento: this.normalizarDocumentoReceptor(tipoDocumento, cliente.numero_documento),
      nrc: this.limpiarDocumento(cliente.nrc) || null,
      nombre: cliente.nombre,
      codActividad: this.normalizarCodigoActividad(cliente.giro),
      descActividad: this.normalizarDescripcionActividad(cliente.giro, cliente.desc_actividad),
      nombreComercial: cliente.nombre_comercial || null,
      direccion,
      telefono: cliente.telefono || null,
      correo: cliente.email
    };
  }

  /**
   * Construir cuerpo del documento
   */
  construirCuerpoDocumento(items) {
    return items.map((item, index) => {
      const cantidad = parseFloat(item.cantidad);
      const precioUni = parseFloat(item.precio_unitario ?? item.precioUnitario);
      const montoDescu = parseFloat(item.descuento ?? item.montoDescu ?? 0);
      
      let ventaGravada = 0;
      let ventaExenta = 0;
      let ventaNoSuj = 0;

      const subtotal = this.redondear((cantidad * precioUni) - montoDescu, 8);

      if (item.exento) {
        ventaExenta = subtotal;
      } else {
        ventaGravada = subtotal;
      }

      return {
        numItem: index + 1,
        tipoItem: item.tipo_item || 2, // 1=Bien, 2=Servicio, 3=Ambos
        numeroDocumento: null,
        cantidad: this.redondear(cantidad, 8),
        codigo: item.codigo || null,
        codTributo: null,
        uniMedida: this.obtenerCodigoUnidadMedida(item.unidad_medida || item.unidadMedida || 'UND'),
        descripcion: item.descripcion,
        precioUni: this.redondear(precioUni, 8),
        montoDescu: this.redondear(montoDescu, 8),
        ventaNoSuj: this.redondear(ventaNoSuj, 8),
        ventaExenta: this.redondear(ventaExenta, 8),
        ventaGravada: this.redondear(ventaGravada, 8),
        tributos: item.exento ? null : ['20'],
        psv: 0,
        noGravado: 0
      };
    });
  }

  construirCuerpoDocumentoFactura(items) {
    return items.map((item, index) => {
      const cantidad = parseFloat(item.cantidad);
      const precioUni = parseFloat(item.precio_unitario ?? item.precioUnitario);
      const montoDescu = parseFloat(item.descuento ?? item.montoDescu ?? 0);
      const subtotal = this.redondear((cantidad * precioUni) - montoDescu, 8);
      const precioUniFactura = item.exento ? precioUni : this.redondear(precioUni * 1.13, 8);
      const montoDescuFactura = item.exento ? montoDescu : this.redondear(montoDescu * 1.13, 8);
      const subtotalFactura = item.exento ? subtotal : this.redondear(subtotal * 1.13, 8);
      const ventaGravada = item.exento ? 0 : subtotalFactura;

      return {
        numItem: index + 1,
        tipoItem: item.tipo_item || 2,
        numeroDocumento: null,
        cantidad: this.redondear(cantidad, 8),
        codigo: item.codigo || null,
        codTributo: null,
        uniMedida: this.obtenerCodigoUnidadMedida(item.unidad_medida || item.unidadMedida || 'UND'),
        descripcion: item.descripcion,
        precioUni: this.redondear(precioUniFactura, 8),
        montoDescu: this.redondear(montoDescuFactura, 8),
        ventaNoSuj: 0,
        ventaExenta: this.redondear(item.exento ? subtotal : 0, 8),
        ventaGravada: this.redondear(ventaGravada, 8),
        tributos: null,
        psv: 0,
        noGravado: 0,
        ivaItem: item.exento ? 0 : this.redondear(subtotal * 0.13)
      };
    });
  }

  /**
   * Construir cuerpo del documento para Nota de Crédito
   */
  construirCuerpoDocumentoNC(items) {
    return items.map((item, index) => {
      const cantidad = parseFloat(item.cantidad);
      const precioUni = parseFloat(item.precio_unitario ?? item.precioUnitario);
      const montoDescu = parseFloat(item.descuento ?? item.montoDescu ?? 0);
      const numeroDocumento = item.numero_documento || item.numeroDocumento;

      if (!numeroDocumento) {
        throw new Error(`El ítem ${index + 1} de la Nota de Crédito debe tener numeroDocumento del CCF relacionado.`);
      }
      
      let ventaGravada = 0;
      let ventaExenta = 0;
      let ventaNoSuj = 0;

      const subtotal = this.redondear((cantidad * precioUni) - montoDescu, 8);

      if (item.exento) {
        ventaExenta = subtotal;
      } else {
        ventaGravada = subtotal;
      }

      return {
        numItem: index + 1,
        tipoItem: item.tipo_item || 2,
        numeroDocumento: String(numeroDocumento),
        cantidad: this.redondear(cantidad, 8),
        codigo: item.codigo || null,
        codTributo: null,
        uniMedida: this.obtenerCodigoUnidadMedida(item.unidad_medida || item.unidadMedida || 'UND'),
        descripcion: item.descripcion,
        precioUni: this.redondear(precioUni, 8),
        montoDescu: this.redondear(montoDescu, 8),
        ventaNoSuj: this.redondear(ventaNoSuj, 8),
        ventaExenta: this.redondear(ventaExenta, 8),
        ventaGravada: this.redondear(ventaGravada, 8),
        tributos: item.exento ? null : ['20']
      };
    });
  }

  ajustarResumenDesdeCuerpo(resumen, cuerpoDocumento, tipoDte) {
    const cuerpo = Array.isArray(cuerpoDocumento) ? cuerpoDocumento : [];
    const totalNoSuj = this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.ventaNoSuj || 0), 0));
    const totalExenta = this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.ventaExenta || 0), 0));
    const totalGravada = this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.ventaGravada || 0), 0));
    const totalNoGravado = this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.noGravado || 0), 0));
    const subTotalVentas = this.redondear(totalNoSuj + totalExenta + totalGravada);
    // Hacienda espera totalDescu como suma de descuentos de línea y globales.
    const descuentoLineas = this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.montoDescu || 0), 0));
    const descuNoSuj = this.redondear(resumen.descuNoSuj || 0);
    const descuExenta = this.redondear(resumen.descuExenta || 0);
    const descuGravada = this.redondear(resumen.descuGravada || 0);
    const totalNoSujNeto = this.redondear(Math.max(0, totalNoSuj - descuNoSuj));
    const totalExentaNeto = this.redondear(Math.max(0, totalExenta - descuExenta));
    const totalGravadaNeto = this.redondear(Math.max(0, totalGravada - descuGravada));
    const subTotalNeto = this.redondear(totalNoSujNeto + totalExentaNeto + totalGravadaNeto);
    const totalDescu = this.redondear(descuentoLineas + descuNoSuj + descuExenta + descuGravada);
    const esFacturaConsumidorFinal = tipoDte === '01';
    const totalIva = esFacturaConsumidorFinal
      ? this.calcularIvaFacturaConsumidor(cuerpo, totalGravadaNeto, descuGravada)
      : this.redondear(totalGravadaNeto * 0.13);
    const total = esFacturaConsumidorFinal
      ? this.redondear(subTotalNeto + totalNoGravado)
      : this.redondear(subTotalNeto + totalIva + totalNoGravado);
    const ivaRete1 = this.redondear(resumen.ivaRete1 || 0);
    this.validarMaximoIvaRete1(tipoDte, totalGravadaNeto, ivaRete1);
    const ivaPerci1 = tipoDte === '03' ? this.redondear(resumen.ivaPerci1 || 0) : 0;
    const reteRenta = this.redondear(resumen.reteRenta || 0);
    const totalPagar = this.redondear(Math.max(0, total + ivaPerci1 - ivaRete1 - reteRenta));

    return {
      ...resumen,
      totalNoSuj,
      noSuj: totalNoSuj,
      totalExenta,
      exenta: totalExenta,
      totalGravada,
      gravada: totalGravada,
      subTotalVentas,
      subtotal: subTotalNeto,
      subTotal: subTotalNeto,
      descuNoSuj,
      descuExenta,
      descuGravada,
      totalDescu,
      descuento: totalDescu,
      iva: totalIva,
      totalIva,
      total,
      montoTotalOperacion: total,
      totalNoGravado,
      ivaRete1,
      ivaPerci1,
      reteRenta,
      totalPagar,
      pagos: this.ajustarPagosAlTotal(resumen.pagos, totalPagar)
    };
  }

  calcularIvaFacturaConsumidor(cuerpo, totalGravadaNeto, descuGravada = 0) {
    if (this.redondear(descuGravada) > 0) {
      return this.redondear(totalGravadaNeto - (totalGravadaNeto / 1.13));
    }

    return this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.ivaItem || 0), 0));
  }

  ajustarPagosAlTotal(pagos, total) {
    if (!Array.isArray(pagos) || pagos.length === 0) return this.normalizarPagos(pagos, total);
    if (pagos.length > 1) return this.normalizarPagos(pagos, total);

    return this.normalizarPagos([{
      ...pagos[0],
      montoPago: total
    }], total);
  }

  /**
   * Construir resumen para Factura
   */
  construirResumenFactura(resumen) {
    const totalIva = this.redondear(resumen.iva || 0);
    const subtotal = this.redondear(resumen.subtotal || 0);
    const subTotalVentas = this.redondear(resumen.subTotalVentas ?? resumen.subtotal ?? 0);
    const total = this.redondear(resumen.total || 0);
    const montoTotalOperacion = this.redondear(resumen.montoTotalOperacion ?? subtotal);
    const totalDescu = this.redondear(resumen.totalDescu ?? resumen.descuento ?? 0);
    const descuNoSuj = this.redondear(resumen.descuNoSuj || 0);
    const descuExenta = this.redondear(resumen.descuExenta || 0);
    const descuGravada = this.redondear(resumen.descuGravada || 0);
    const ivaRete1 = this.redondear(resumen.ivaRete1 || 0);
    const reteRenta = this.redondear(resumen.reteRenta || 0);
    const totalPagar = this.redondear(resumen.totalPagar ?? Math.max(0, total - ivaRete1 - reteRenta));

    return {
      totalNoSuj: 0,
      totalExenta: this.redondear(resumen.exenta || 0),
      totalGravada: this.redondear(resumen.gravada || 0),
      subTotalVentas,
      descuNoSuj,
      descuExenta,
      descuGravada,
      porcentajeDescuento: 0,
      totalDescu,
      tributos: null,
      subTotal: subtotal,
      ivaRete1,
      reteRenta,
      montoTotalOperacion,
      totalNoGravado: 0,
      totalPagar,
      totalLetras: this.numeroALetras(totalPagar),
      totalIva: totalIva,
      saldoFavor: 0,
      condicionOperacion: resumen.condicion_operacion || resumen.condicionOperacion || 1, // 1=Contado, 2=Crédito, 3=Otro
      pagos: this.normalizarPagos(resumen.pagos, totalPagar),
      numPagoElectronico: null
    };
  }

  /**
   * Construir resumen para Crédito Fiscal
   */
  construirResumenCCF(resumen) {
    const resultado = this.construirResumenFactura(resumen);
    const totalIva = this.redondear(resumen.iva || 0);
    const total = this.redondear(resumen.total || 0);
    const ivaRete1 = this.redondear(resumen.ivaRete1 || 0);
    const ivaPerci1 = this.redondear(resumen.ivaPerci1 || 0);
    const reteRenta = this.redondear(resumen.reteRenta || 0);
    const totalPagar = this.redondear(Math.max(0, total + ivaPerci1 - ivaRete1 - reteRenta));
    resultado.tributos = totalIva > 0
      ? [{ codigo: '20', descripcion: 'Impuesto al Valor Agregado 13%', valor: totalIva }]
      : null;
    resultado.ivaRete1 = ivaRete1;
    resultado.ivaPerci1 = ivaPerci1;
    resultado.montoTotalOperacion = total;
    resultado.totalPagar = totalPagar;
    resultado.totalLetras = this.numeroALetras(totalPagar);
    resultado.pagos = this.normalizarPagos(resumen.pagos, totalPagar);
    delete resultado.totalIva; // No existe en CCF
    return resultado;
  }

  /**
   * Construir resumen para Nota de Crédito
   */
  construirResumenNC(resumen) {
    const totalIva = this.redondear(resumen.iva || 0);
    const subtotal = this.redondear(resumen.subtotal || 0);
    const subTotalVentas = this.redondear(resumen.subTotalVentas ?? resumen.subtotal ?? 0);
    const total = this.redondear(resumen.total || 0);
    const totalNoSuj = this.redondear(resumen.totalNoSuj || resumen.noSuj || 0);
    const totalExenta = this.redondear(resumen.totalExenta || resumen.exenta || 0);
    const totalGravada = this.redondear(resumen.totalGravada || resumen.gravada || 0);
    const totalDescu = this.redondear(resumen.totalDescu ?? resumen.descuento ?? 0);
    const descuNoSuj = this.redondear(resumen.descuNoSuj || 0);
    const descuExenta = this.redondear(resumen.descuExenta || 0);
    const descuGravada = this.redondear(resumen.descuGravada || 0);

    return {
      totalNoSuj,
      totalExenta,
      totalGravada,
      subTotalVentas,
      descuNoSuj,
      descuExenta,
      descuGravada,
      totalDescu,
      tributos: totalIva > 0 ? [{ codigo: '20', descripcion: 'Impuesto al Valor Agregado 13%', valor: totalIva }] : null,
      subTotal: subtotal,
      ivaPerci1: 0,
      ivaRete1: 0,
      reteRenta: 0,
      montoTotalOperacion: total,
      totalLetras: this.numeroALetras(total),
      condicionOperacion: resumen.condicion_operacion || resumen.condicionOperacion || 1
    };
  }

  /**
   * Construir resumen para Nota de Débito.
   * El schema MH de ND exige numPagoElectronico, a diferencia de NC.
   */
  construirResumenND(resumen) {
    return {
      ...this.construirResumenNC(resumen),
      numPagoElectronico: null
    };
  }

  /**
   * Generar código de generación (UUID v4)
   */
  generarCodigoGeneracion() {
    return crypto.randomUUID().toUpperCase();
  }

  /**
   * Generar número de control
   * Formato: DTE-{tipoDte}-{codEstablecimiento}-{correlativo}
   */
  generarNumeroControl(tipoDte, codEstablecimiento, puntoVenta, correlativo = 1) {
    const establecimiento = this.normalizarCodigoControl(codEstablecimiento, '0000');
    const punto = this.normalizarCodigoControl(puntoVenta, '0000');
    const numeroCorrelativo = correlativo.toString().padStart(15, '0');
    return `DTE-${tipoDte}-${establecimiento}${punto}-${numeroCorrelativo}`;
  }

  normalizarCodigoControl(valor, fallback) {
    return String(valor || fallback)
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '')
      .padStart(4, '0')
      .slice(0, 4);
  }

  limpiarDocumento(valor) {
    return String(valor || '').replace(/[^0-9]/g, '');
  }

  limpiarDocumentoAlfanumerico(valor) {
    return String(valor || '').trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
  }

  normalizarTexto(valor) {
    return String(valor || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim()
      .toUpperCase();
  }

  esClienteGenericoFactura(cliente) {
    const nombre = this.normalizarTexto(cliente?.nombre);
    return [
      'CLIENTES VARIOS',
      'CLIENTE VARIOS',
      'CONSUMIDOR FINAL',
      'PUBLICO EN GENERAL'
    ].includes(nombre);
  }

  debeOmitirReceptorFactura(cliente, montoTotalOperacion = 0) {
    return this.esClienteGenericoFactura(cliente) && Number(montoTotalOperacion || 0) < 1095;
  }

  normalizarDocumentoReceptor(tipoDocumento, valor) {
    if (!['13', '36'].includes(String(tipoDocumento || ''))) {
      return this.limpiarDocumentoAlfanumerico(valor);
    }

    const limpio = this.limpiarDocumento(valor);
    if (!limpio) return '';
    if (tipoDocumento === '13' && limpio.length === 9) {
      return `${limpio.slice(0, 8)}-${limpio.slice(8)}`;
    }
    if (tipoDocumento === '36') {
      return limpio;
    }
    return limpio;
  }

  normalizarDocumentoSujetoExcluido(tipoDocumento, valor) {
    const documento = String(valor || '').trim().toUpperCase();
    if (tipoDocumento === '13') {
      return this.limpiarDocumento(documento);
    }

    if (tipoDocumento === '36') {
      return this.limpiarDocumento(documento);
    }

    return documento.replace(/\s+/g, '');
  }

  normalizarMunicipio(departamento, municipio) {
    const depto = this.limpiarDocumento(departamento).padStart(2, '0');
    const valor = String(municipio || '').trim();
    const municipioCatalogo = this.buscarMunicipioCatalogo(depto, valor);
    if (municipioCatalogo) {
      return municipioCatalogo.codigo.slice(2, 4);
    }

    const limpio = this.limpiarDocumento(valor);
    if (!limpio) return limpio;

    if (limpio.length >= 4 && limpio.startsWith(depto)) {
      return limpio.slice(2, 4);
    }

    if (limpio.length > 2) {
      return limpio.slice(-2);
    }

    return limpio.padStart(2, '0');
  }

  buscarMunicipioCatalogo(departamento, valor) {
    const municipios = divisionGeografica.municipios?.[departamento] || [];
    const valorNormalizado = String(valor || '').trim().toLowerCase();
    if (!valorNormalizado) return null;

    return municipios.find(municipio =>
      municipio.codigo === valor ||
      municipio.nombre.toLowerCase() === valorNormalizado
    ) || null;
  }

  normalizarDescripcionActividad(codigo, descripcion) {
    const descripcionLimpia = String(descripcion || '').trim();
    if (descripcionLimpia) return descripcionLimpia;

    const actividad = this.buscarActividad(codigo);

    return actividad?.descripcion || '';
  }

  normalizarCodigoActividad(codigo) {
    return this.buscarActividad(codigo)?.codigo || String(codigo || '').trim();
  }

  buscarActividad(codigo) {
    const codigoLimpio = String(codigo || '').trim();
    if (!codigoLimpio) return null;

    return actividadesEconomicas.find(act => act.codigo === codigoLimpio) ||
      actividadesEconomicas.find(act => act.codigo.startsWith(codigoLimpio)) ||
      null;
  }

  normalizarPagos(pagos, total) {
    const pagosBase = Array.isArray(pagos) && pagos.length > 0
      ? pagos
      : [{
          codigo: '01',
          montoPago: total,
          referencia: null,
          plazo: '01',
          periodo: 1
        }];

    return pagosBase.map(pago => {
      const normalizado = {
        codigo: pago.codigo || '01',
        montoPago: this.redondear(pago.montoPago ?? total),
        referencia: pago.referencia || null
      };

      normalizado.plazo = pago.plazo !== null && pago.plazo !== undefined && String(pago.plazo).trim() !== ''
        ? String(pago.plazo).trim()
        : '01';
      normalizado.periodo = pago.periodo !== null && pago.periodo !== undefined && String(pago.periodo).trim() !== ''
        ? Number(pago.periodo)
        : 1;

      return normalizado;
    });
  }

  redondear(valor, decimales = 2) {
    return Number(Number(valor || 0).toFixed(decimales));
  }

  validarMaximoIvaRete1(tipoDte, base, ivaRete1) {
    const tipo = String(tipoDte || '').padStart(2, '0');
    if (!['01', '03', '14'].includes(tipo)) return;

    const porcentajeMaximo = tipo === '14' ? 0 : 0.01;
    const maximo = this.redondear(Number(base || 0) * porcentajeMaximo);
    const valor = this.redondear(ivaRete1 || 0);

    if (valor > maximo + 0.000001) {
      const regla = tipo === '14'
        ? 'Sujeto Excluido no aplica IVA retenido; use Retención Renta'
        : '1% de la venta gravada';
      throw new Error(`resumen.ivaRete1 excede el máximo permitido: ${valor.toFixed(2)} > ${maximo.toFixed(2)} (${regla})`);
    }
  }

  obtenerCodigoAmbiente(ambiente) {
    const valor = String(ambiente || '').toLowerCase();
    return valor === 'produccion' || valor === '01' ? '01' : '00';
  }

  /**
   * Formatear fecha YYYY-MM-DD
   */
  formatearFecha(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  /**
   * Formatear hora HH:MM:SS
   */
  formatearHora(date) {
    return date.toTimeString().split(' ')[0];
  }

  /**
   * Obtener código de unidad de medida
   */
  obtenerCodigoUnidadMedida(unidad) {
    return this.unidadesMedida[unidad] || 99;
  }

  /**
   * Convertir número a letras (simplificado)
   */
  numeroALetras(numero) {
    const entero = Math.floor(numero);
    const decimales = Math.round((numero - entero) * 100);
    
    // Implementación básica - en producción usar librería completa
    if (entero === 0) return 'CERO DÓLARES';
    
    return `${entero} DÓLARES CON ${decimales}/100`;
  }

  /**
   * Construir receptor para exportación
   */
  construirReceptorExportacion(cliente) {
    const pais = this.obtenerPaisExportacion(cliente);

    return {
      tipoDocumento: cliente.tipo_documento || '37',
      numDocumento: this.normalizarDocumentoReceptor(cliente.tipo_documento || '37', cliente.numero_documento),
      nombre: cliente.nombre,
      nombreComercial: cliente.nombre_comercial || null,
      codPais: pais.codigo,
      nombrePais: pais.nombre,
      complemento: cliente.direccion || 'N/A',
      tipoPersona: this.normalizarTipoPersonaExportacion(cliente),
      descActividad: cliente.desc_actividad_exportacion || cliente.descActividad || cliente.desc_actividad || this.normalizarDescripcionActividad(cliente.giro, null) || 'Exportacion',
      telefono: cliente.telefono || null,
      correo: cliente.email
    };
  }

  obtenerPaisExportacion(cliente = {}) {
    const paisValor = String(cliente.codPais || cliente.cod_pais || cliente.pais || 'US').trim().toUpperCase();
    const paises = {
      US: { codigo: '9300', nombre: 'ESTADOS UNIDOS DE AMERICA' },
      USA: { codigo: '9300', nombre: 'ESTADOS UNIDOS DE AMERICA' },
      '9300': { codigo: '9300', nombre: 'ESTADOS UNIDOS DE AMERICA' },
      GT: { codigo: '9301', nombre: 'GUATEMALA' },
      HN: { codigo: '9302', nombre: 'HONDURAS' },
      NI: { codigo: '9303', nombre: 'NICARAGUA' },
      CR: { codigo: '9304', nombre: 'COSTA RICA' },
      PA: { codigo: '9305', nombre: 'PANAMA' },
      MX: { codigo: '9320', nombre: 'MEXICO' }
    };

    return paises[paisValor] || {
      codigo: paisValor,
      nombre: cliente.nombrePais || cliente.nombre_pais || paisValor
    };
  }

  normalizarTipoPersonaExportacion(cliente = {}) {
    const valor = cliente.tipo_persona_exportacion || cliente.tipoPersona || cliente.tipo_persona;
    if (Number(valor) === 1 || Number(valor) === 2) return Number(valor);
    return String(valor || '').toLowerCase().startsWith('natural') ? 1 : 2;
  }

  /**
   * Construir sujeto excluido
   */
  construirSujetoExcluido(cliente) {
    const tipoDocumento = String(cliente.tipo_documento || '').trim();

    return {
      tipoDocumento,
      numDocumento: this.normalizarDocumentoSujetoExcluido(tipoDocumento, cliente.numero_documento),
      nombre: cliente.nombre,
      codActividad: cliente.giro ? this.normalizarCodigoActividad(cliente.giro) : null,
      descActividad: cliente.giro ? this.normalizarDescripcionActividad(cliente.giro, cliente.desc_actividad) : null,
      direccion: cliente.direccion ? {
        departamento: cliente.departamento,
        municipio: this.normalizarMunicipio(cliente.departamento, cliente.municipio),
        complemento: cliente.direccion
      } : null,
      telefono: cliente.telefono || null,
      correo: cliente.email
    };
  }

  /**
   * Construir cuerpo documento exportación
   */
  construirCuerpoDocumentoExportacion(items) {
    return items.map((item, index) => {
      const cantidad = parseFloat(item.cantidad);
      const precioUni = parseFloat(item.precio_unitario ?? item.precioUnitario);
      const montoDescu = parseFloat(item.descuento ?? item.montoDescu ?? 0);

      return {
        numItem: index + 1,
        cantidad: this.redondear(cantidad, 8),
        codigo: item.codigo || null,
        uniMedida: this.obtenerCodigoUnidadMedida(item.unidad_medida || item.unidadMedida || 'UND'),
        descripcion: item.descripcion,
        precioUni: this.redondear(precioUni, 8),
        montoDescu: this.redondear(montoDescu, 8),
        ventaGravada: this.redondear((cantidad * precioUni) - montoDescu, 8),
        tributos: null,
        noGravado: 0
      };
    });
  }

  ajustarResumenExportacionDesdeCuerpo(resumen, cuerpoDocumento, opciones = {}) {
    const cuerpo = Array.isArray(cuerpoDocumento) ? cuerpoDocumento : [];
    const totalGravada = this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.ventaGravada || 0), 0));
    const descuentoLineas = this.redondear(cuerpo.reduce((sum, item) => sum + Number(item.montoDescu || 0), 0));
    const descuentoGlobal = this.redondear(resumen.descuGravada || resumen.descuentoGlobal || 0);
    const totalDescu = this.redondear(descuentoLineas + descuentoGlobal);
    const totalGravadaNeta = this.redondear(Math.max(0, totalGravada - descuentoGlobal));
    const tipoItemExpor = Number(opciones.tipoItemExpor || 2);
    const flete = tipoItemExpor === 2 ? 0 : this.redondear(opciones.flete ?? resumen.flete ?? 0);
    const seguro = tipoItemExpor === 2 ? 0 : this.redondear(opciones.seguro ?? resumen.seguro ?? 0);
    const total = this.redondear(totalGravadaNeta + flete + seguro);

    return {
      ...resumen,
      subtotal: totalGravada,
      totalGravada,
      descuento: descuentoGlobal,
      totalDescu,
      descuentoGlobal,
      flete,
      seguro,
      total,
      montoTotalOperacion: total,
      totalPagar: total,
      pagos: this.ajustarPagosAlTotal(resumen.pagos, total)
    };
  }

  /**
   * Construir cuerpo documento FSE
   */
  construirCuerpoDocumentoFSE(items) {
    return items.map((item, index) => {
      const cantidad = parseFloat(item.cantidad);
      const precioUni = parseFloat(item.precio_unitario ?? item.precioUnitario);
      const montoDescu = parseFloat(item.descuento ?? item.montoDescu ?? 0);

      return {
        numItem: index + 1,
        tipoItem: item.tipo_item || 2,
        cantidad: this.redondear(cantidad, 8),
        codigo: item.codigo || null,
        uniMedida: this.obtenerCodigoUnidadMedida(item.unidad_medida || item.unidadMedida || 'UND'),
        descripcion: item.descripcion,
        precioUni: this.redondear(precioUni, 8),
        montoDescu: this.redondear(montoDescu, 8),
        compra: this.redondear((cantidad * precioUni) - montoDescu, 8)
      };
    });
  }

  construirCuerpoDocumentoRetencion(items, documentoRelacionado, opciones = {}) {
    const codigoRetencionMH = opciones.codigoRetencionMH || '22';
    const fechaEmision = documentoRelacionado.fechaEmision;
    const tipoDte = this.normalizarTipoDteRelacionadoRetencion(documentoRelacionado.tipoDocumento);
    const numeroDocumento = documentoRelacionado.numeroDocumento;
    const retencion = opciones.retencion || {};
    const porcentaje = parseFloat(retencion.porcentaje || opciones.porcentajeRetencion || 1);
    const itemsRetencion = Array.isArray(items) && items.length > 0
      ? items
      : [{
          descripcion: `Retención IVA ${this.redondear(porcentaje, 2)}%`,
          montoSujetoGravado: retencion.montoSujeto || 0,
          ivaRetenido: retencion.ivaRetenido
        }];

    return itemsRetencion.map((item, index) => {
      const cantidad = parseFloat(item.cantidad || 1);
      const precioUni = parseFloat(item.precio_unitario || item.precioUni || 0);
      const montoDescu = parseFloat(item.descuento || item.montoDescu || 0);
      const montoSujetoGrav = this.redondear(item.montoSujetoGravado ?? ((cantidad * precioUni) - montoDescu), 2);
      const ivaRetenido = this.redondear(item.ivaRetenido ?? (montoSujetoGrav * (porcentaje / 100)), 2);

      return {
        numItem: index + 1,
        tipoDte,
        tipoDoc: documentoRelacionado.tipoGeneracion,
        numDocumento: numeroDocumento,
        fechaEmision,
        montoSujetoGrav,
        codigoRetencionMH,
        ivaRetenido,
        descripcion: item.descripcion || 'Retención IVA'
      };
    });
  }

  normalizarTipoDteRelacionadoRetencion(tipoDocumento) {
    const tipoDte = String(tipoDocumento || '').padStart(2, '0');
    const tiposPermitidos = ['01', '03', '14'];
    if (!tiposPermitidos.includes(tipoDte)) {
      throw new Error(`El Comprobante de Retención solo admite Factura tipo 01, CCF tipo 03 o Sujeto Excluido tipo 14 como documento relacionado. Valor recibido: ${tipoDte || 'vacío'}.`);
    }
    return tipoDte;
  }

  /**
   * Construir resumen exportación
   */
  construirResumenExportacion(resumen, opciones = {}) {
    const subtotal = this.redondear(resumen.totalGravada ?? resumen.subtotal ?? 0);
    const descuento = this.redondear(resumen.descuento ?? resumen.descuentoGlobal ?? 0);
    const totalDescu = this.redondear(resumen.totalDescu ?? descuento);
    const tipoItemExpor = Number(opciones.tipoItemExpor || 2);
    const flete = tipoItemExpor === 2 ? 0 : this.redondear(opciones.flete ?? resumen.flete ?? 0);
    const seguro = tipoItemExpor === 2 ? 0 : this.redondear(opciones.seguro ?? resumen.seguro ?? 0);
    const total = this.redondear(Math.max(0, subtotal - descuento) + flete + seguro);

    const resumenExportacion = {
      totalGravada: subtotal,
      descuento,
      porcentajeDescuento: 0,
      totalDescu,
      montoTotalOperacion: total,
      totalNoGravado: 0,
      totalPagar: total,
      totalLetras: this.numeroALetras(total),
      condicionOperacion: resumen.condicion_operacion || resumen.condicionOperacion || 1,
      pagos: this.normalizarPagos(resumen.pagos, total),
      numPagoElectronico: null,
      codIncoterms: null,
      descIncoterms: null,
      flete,
      seguro,
      observaciones: resumen.observaciones || null
    };

    if (tipoItemExpor !== 2) {
      resumenExportacion.codIncoterms = opciones.codIncoterms || resumen.codIncoterms || '01';
      resumenExportacion.descIncoterms = opciones.descIncoterms || resumen.descIncoterms || 'EXW';
    }

    return resumenExportacion;
  }

  /**
   * Construir resumen FSE
   */
  construirResumenFSE(resumen) {
    const total = this.redondear(resumen.total || 0);
    const descuento = this.redondear(resumen.descuento || 0);
    const subTotal = this.redondear(resumen.subTotal ?? Math.max(0, total - descuento));
    const ivaRete1 = 0;
    this.validarMaximoIvaRete1('14', subTotal, ivaRete1);
    const reteRenta = this.redondear(resumen.reteRenta || 0);
    const totalPagar = this.redondear(Math.max(0, subTotal - ivaRete1 - reteRenta));

    return {
      totalCompra: total,
      descu: descuento,
      totalDescu: descuento,
      subTotal,
      ivaRete1,
      reteRenta,
      totalPagar,
      totalLetras: this.numeroALetras(totalPagar),
      condicionOperacion: resumen.condicion_operacion || resumen.condicionOperacion || 1,
      pagos: this.ajustarPagosAlTotal(resumen.pagos, totalPagar),
      observaciones: resumen.observaciones || null
    };
  }

  construirResumenRetencion(resumen, items, opciones = {}) {
    items = Array.isArray(items) ? items : [];
    const retencion = opciones.retencion || {};
    const porcentaje = parseFloat(retencion.porcentaje || opciones.porcentajeRetencion || 1);
    const totalSujetoRetencion = this.redondear(
      resumen.totalSujetoRetencion ??
      retencion.montoSujeto ??
      items.reduce((sum, item) => {
        const cantidad = parseFloat(item.cantidad || 1);
        const precioUni = parseFloat(item.precio_unitario || item.precioUni || 0);
        const montoDescu = parseFloat(item.descuento || item.montoDescu || 0);
        return sum + (item.montoSujetoGravado ?? ((cantidad * precioUni) - montoDescu));
      }, 0),
      2
    );
    const totalRetenidoItems = items.reduce((sum, item) => sum + (item.ivaRetenido ?? 0), 0);
    const totalIVAretenido = this.redondear(
      resumen.totalIVAretenido ??
      resumen.totalIvaRetenido ??
      retencion.ivaRetenido ??
      (totalRetenidoItems || totalSujetoRetencion * (porcentaje / 100)),
      2
    );

    return {
      totalSujetoRetencion,
      totalIVAretenido,
      totalIVAretenidoLetras: this.numeroALetras(totalIVAretenido)
    };
  }
}

module.exports = DTEGenerator;
