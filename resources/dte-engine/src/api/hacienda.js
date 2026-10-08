const axios = require('axios');
const crypto = require('crypto');

class HaciendaAPI {
  constructor(config = {}) {
    this.ambiente = config.ambiente || 'pruebas';
    this.codigoAmbiente = this.obtenerCodigoAmbiente(this.ambiente);
    this.baseURL = this.codigoAmbiente === '01'
      ? 'https://api.dtes.mh.gob.sv'
      : 'https://apitest.dtes.mh.gob.sv';
    
    this.usuario = config.usuario;
    this.password = config.password;
    this.token = config.token || null;
    
    this.axiosInstance = axios.create({
      baseURL: this.baseURL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json'
      }
    });
  }

  /**
   * Autenticar con el Ministerio de Hacienda
   * Según guía oficial: APIS - Sistema Transmisión de DTE
   */
  async autenticar() {
    try {
      // Preparar datos en formato application/x-www-form-urlencoded
      const params = new URLSearchParams();
      params.append('user', this.usuario);
      params.append('pwd', this.password);

      const response = await this.axiosInstance.post('/seguridad/auth', params, {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'User-Agent': 'FacturacionElectron/1.0'
        }
      });

      // Validar respuesta según estructura oficial
      if (response.data && response.data.status === 'OK' && response.data.body && response.data.body.token) {
        this.token = response.data.body.token;
        return {
          success: true,
          token: this.token,
          user: response.data.body.user,
          rol: response.data.body.rol,
          roles: response.data.body.roles,
          tokenType: response.data.body.tokenType
        };
      }

      throw new Error('No se recibió token de autenticación');
    } catch (error) {
      const errorMsg = error.response?.data?.mensaje || error.response?.data?.body?.mensaje || error.message;
      const authError = new Error(`Error en autenticación: ${errorMsg}`);
      authError.code = error.code;
      authError.response = error.response;
      authError.originalError = error;
      throw authError;
    }
  }

  /**
   * Enviar DTE (Documento Tributario Electrónico) - Modelo uno a uno
   * @param {Object} dte - Documento firmado con estructura completa
   * @param {string} nit - NIT del emisor
   * @param {string} passwordPri - Password de la llave privada (si aplica)
   * @returns {Promise<Object>} Respuesta del MH con estado y sello
   */
  async enviarDTE(dte, nit, passwordPri = null) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const identificacion = this.obtenerIdentificacion(dte);
      const documento = this.obtenerDocumentoFirmado(dte);

      if (!identificacion) {
        throw new Error('No se encontró la sección identificacion del DTE.');
      }

      if (!documento) {
        throw new Error('El DTE no contiene firmaMh/documento firmado. Firme el DTE con el certificado local antes de enviarlo.');
      }

      const payload = {
        ambiente: this.obtenerCodigoAmbiente(identificacion.ambiente || this.ambiente),
        idEnvio: Date.now(),
        version: identificacion.version,
        tipoDte: identificacion.tipoDte,
        documento
      };

      const response = await this.axiosInstance.post('/fesv/recepciondte', payload, {
        headers: {
          'Authorization': this.token,
          'Content-Type': 'application/json'
        }
      });

      // Respuesta esperada: { estado, codigoGeneracion, selloRecibido, observaciones }
      return {
        success: true,
        estado: response.data.estado,
        codigoGeneracion: response.data.codigoGeneracion,
        selloRecibido: response.data.selloRecibido,
        observaciones: response.data.observaciones,
        raw: response.data
      };
    } catch (error) {
      // Manejo detallado de errores del MH
      const errorResponse = this.procesarErrorHacienda(error);
      return {
        success: false,
        error: errorResponse.error,
        errorDetalle: errorResponse
      };
    }
  }

  /**
   * Enviar evento de invalidación/anulación de DTE.
   * El evento debe venir firmado y contener identificacion.version = 2.
   */
  async anularDTE(eventoFirmado) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const identificacion = this.obtenerIdentificacion(eventoFirmado);
      const documento = this.obtenerDocumentoFirmado(eventoFirmado);

      if (!identificacion) {
        throw new Error('No se encontró la sección identificacion del evento de invalidación.');
      }

      if (!documento) {
        throw new Error('El evento de invalidación no contiene firmaMh/documento firmado.');
      }

      const payload = {
        ambiente: this.obtenerCodigoAmbiente(identificacion.ambiente || this.ambiente),
        idEnvio: Date.now(),
        version: identificacion.version || 2,
        documento
      };

      const response = await this.axiosInstance.post('/fesv/anulardte', payload, {
        headers: {
          'Authorization': this.token,
          'Content-Type': 'application/json'
        }
      });

      return {
        success: true,
        estado: response.data.estado,
        codigoGeneracion: response.data.codigoGeneracion,
        selloRecibido: response.data.selloRecibido,
        observaciones: response.data.observaciones,
        raw: response.data
      };
    } catch (error) {
      const errorResponse = this.procesarErrorHacienda(error);
      return {
        success: false,
        error: errorResponse.error,
        errorDetalle: errorResponse
      };
    }
  }

  async enviarContingencia(eventoFirmado, nit = null) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const identificacion = this.obtenerIdentificacion(eventoFirmado);
      const documento = this.obtenerDocumentoFirmado(eventoFirmado);

      if (!identificacion) {
        throw new Error('No se encontró la sección identificacion del evento de contingencia.');
      }

      if (!documento) {
        throw new Error('El evento de contingencia no contiene firmaMh/documento firmado.');
      }

      const payload = {
        ambiente: this.obtenerCodigoAmbiente(identificacion.ambiente || this.ambiente),
        idEnvio: Date.now(),
        version: identificacion.version || 3,
        documento
      };

      if (nit) payload.nit = String(nit).replace(/[^0-9]/g, '');

      const response = await this.axiosInstance.post('/fesv/contingencia', payload, {
        headers: {
          'Authorization': this.token,
          'Content-Type': 'application/json'
        }
      });

      return {
        success: true,
        estado: response.data.estado,
        fechaHora: response.data.fechaHora,
        mensaje: response.data.mensaje,
        numeroValidacion: response.data.numeroValidacion,
        observaciones: response.data.observaciones,
        raw: response.data
      };
    } catch (error) {
      const errorResponse = this.procesarErrorHacienda(error);
      return {
        success: false,
        error: errorResponse.error,
        errorDetalle: errorResponse
      };
    }
  }

  /**
   * Enviar Evento de Operaciones Especiales (EOE) — V2.0
   */
  async enviarEOE(eventoFirmado, nit = null) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const identificacion = this.obtenerIdentificacion(eventoFirmado);
      const documento = this.obtenerDocumentoFirmado(eventoFirmado);

      if (!identificacion) throw new Error('No se encontró la sección identificacion del EOE.');
      if (!documento) throw new Error('El EOE no contiene firmaMh/documento firmado.');

      const payload = {
        ambiente: this.obtenerCodigoAmbiente(identificacion.ambiente || this.ambiente),
        idEnvio: Date.now(),
        version: identificacion.version || 1,
        documento
      };

      if (nit) payload.nit = String(nit).replace(/[^0-9]/g, '');

      const response = await this.axiosInstance.post('/fesv/operacionesEspeciales', payload, {
        headers: { 'Authorization': this.token, 'Content-Type': 'application/json' }
      });

      return {
        success: true,
        estado: response.data.estado,
        codigoGeneracion: response.data.codigoGeneracion,
        selloRecibido: response.data.selloRecibido,
        observaciones: response.data.observaciones,
        raw: response.data
      };
    } catch (error) {
      const errorResponse = this.procesarErrorHacienda(error);
      return { success: false, error: errorResponse.error, errorDetalle: errorResponse };
    }
  }

  /**
   * Enviar Evento de Retorno (ER) — V2.0
   * Aplica cuando mercancías exportadas (FEXE tipo 11) son devueltas.
   */
  async enviarRetorno(eventoFirmado, nit = null) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const identificacion = this.obtenerIdentificacion(eventoFirmado);
      const documento = this.obtenerDocumentoFirmado(eventoFirmado);

      if (!identificacion) {
        throw new Error('No se encontró la sección identificacion del evento de retorno.');
      }

      if (!documento) {
        throw new Error('El evento de retorno no contiene firmaMh/documento firmado.');
      }

      const payload = {
        ambiente: this.obtenerCodigoAmbiente(identificacion.ambiente || this.ambiente),
        idEnvio: Date.now(),
        version: identificacion.version || 1,
        documento
      };

      if (nit) payload.nit = String(nit).replace(/[^0-9]/g, '');

      const response = await this.axiosInstance.post('/fesv/retornodte', payload, {
        headers: {
          'Authorization': this.token,
          'Content-Type': 'application/json'
        }
      });

      return {
        success: true,
        estado: response.data.estado,
        codigoGeneracion: response.data.codigoGeneracion,
        selloRecibido: response.data.selloRecibido,
        observaciones: response.data.observaciones,
        raw: response.data
      };
    } catch (error) {
      const errorResponse = this.procesarErrorHacienda(error);
      return {
        success: false,
        error: errorResponse.error,
        errorDetalle: errorResponse
      };
    }
  }

  /**
   * Enviar DTEs por lote (modelo asíncrono), requerido para DTEs emitidos en contingencia.
   * @param {Array<Object|string>} dtes - Documentos firmados.
   * @param {string} nit - NIT del emisor.
   * @returns {Promise<Object>} Respuesta del MH con código de lote.
   */
  async enviarLoteDTE(dtes, nit) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const documentos = (Array.isArray(dtes) ? dtes : [dtes])
        .map(dte => this.obtenerDocumentoFirmado(dte))
        .filter(Boolean);

      if (!documentos.length) {
        throw new Error('El lote no contiene documentos firmados.');
      }

      const primerDte = Array.isArray(dtes) ? dtes[0] : dtes;
      const identificacion = this.obtenerIdentificacion(primerDte);
      const nitEmisor = String(nit || primerDte?.emisor?.nit || primerDte?.dteJson?.emisor?.nit || '')
        .replace(/[^0-9]/g, '');

      if (!nitEmisor) {
        throw new Error('No se encontró NIT del emisor para enviar el lote.');
      }

      const payload = {
        version: 1,
        ambiente: this.obtenerCodigoAmbiente(identificacion?.ambiente || this.ambiente),
        idEnvio: crypto.randomUUID().toUpperCase(),
        nitEmisor,
        documentos
      };

      const response = await this.axiosInstance.post('/fesv/recepcionlote', payload, {
        headers: {
          'Authorization': this.token,
          'Content-Type': 'application/json'
        }
      });

      return {
        success: true,
        estado: response.data.estado,
        idEnvio: response.data.idEnvio,
        codigoLote: response.data.codigoLote || response.data.codigoGeneracion,
        codigoMsg: response.data.codigoMsg || response.data.codigo,
        descripcionMsg: response.data.descripcionMsg || response.data.mensaje,
        fhProcesamiento: response.data.fhProcesamiento || response.data.fechaHora,
        raw: response.data
      };
    } catch (error) {
      const errorResponse = this.procesarErrorHacienda(error);
      return {
        success: false,
        error: errorResponse.error,
        errorDetalle: errorResponse
      };
    }
  }

  obtenerIdentificacion(dte) {
    if (typeof dte === 'string') return null;
    return dte?.identificacion || dte?.dteJson?.identificacion || null;
  }

  obtenerDocumentoFirmado(dte) {
    if (!dte) return null;
    if (typeof dte === 'string') return dte;
    const documento = dte.firmaMh ||
      dte.documentoFirmado?.firmaMh ||
      dte.documentoFirmado ||
      dte.documento ||
      dte.body ||
      dte.firma ||
      null;
    return typeof documento === 'string' ? documento : null;
  }

  obtenerCodigoAmbiente(ambiente) {
    const valor = String(ambiente || '').toLowerCase();
    return valor === 'produccion' || valor === '01' ? '01' : '00';
  }

  /**
   * Procesar errores específicos del Ministerio de Hacienda
   */
  procesarErrorHacienda(error) {
    const errorData = error.response?.data || {};
    const statusCode = error.response?.status;
    const errorMsg = errorData.descripcionMsg || errorData.mensaje || errorData.descripcion || error.message;
    
    // Códigos de error comunes del MH
    const codigoError = errorData.codigo || errorData.codigoError || errorData.codigoMsg;
    
    let errorDetallado = {
      success: false,
      error: errorMsg,
      codigo: codigoError,
      estado: errorData.estado,
      descripcionMsg: errorData.descripcionMsg,
      clasificaMsg: errorData.clasificaMsg,
      observaciones: errorData.observaciones || [],
      statusHttp: statusCode
    };

    // Error 106: Credenciales inválidas o token expirado
    if (codigoError === '106' || statusCode === 401) {
      errorDetallado.tipo = 'AUTENTICACION';
      errorDetallado.reintentable = true;
      errorDetallado.mensaje = 'Credenciales inválidas o sesión expirada. Re-autentique e intente nuevamente.';
    }
    
    // Error 500: Error interno del servidor MH
    else if (statusCode === 500) {
      errorDetallado.tipo = 'SERVIDOR_MH';
      errorDetallado.reintentable = true;
      errorDetallado.mensaje = 'Error en el servidor del Ministerio de Hacienda. Intente más tarde.';
    }
    
    // Error 400: Datos inválidos
    else if (statusCode === 400) {
      errorDetallado.tipo = 'VALIDACION';
      errorDetallado.reintentable = false;
      errorDetallado.mensaje = errorData.descripcionMsg || 'Datos del DTE inválidos. Revise las observaciones.';
    }
    
    // Error 503: Servicio no disponible
    else if (statusCode === 503) {
      errorDetallado.tipo = 'SERVICIO_NO_DISPONIBLE';
      errorDetallado.reintentable = true;
      errorDetallado.mensaje = 'Servicio del MH temporalmente no disponible. Intente más tarde.';
    }
    
    // Error de timeout
    else if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
      errorDetallado.tipo = 'TIMEOUT';
      errorDetallado.reintentable = true;
      errorDetallado.mensaje = 'Tiempo de espera agotado. Verifique su conexión e intente nuevamente.';
    }
    
    // Error de red
    else if (['ENOTFOUND', 'ECONNREFUSED', 'ENETUNREACH', 'EAI_AGAIN', 'ECONNRESET', 'EHOSTUNREACH'].includes(error.code)) {
      errorDetallado.tipo = 'RED';
      errorDetallado.reintentable = true;
      errorDetallado.mensaje = 'No se pudo conectar con el servidor del MH. Verifique su conexión a internet.';
    }
    
    // Otros errores
    else {
      errorDetallado.tipo = 'DESCONOCIDO';
      errorDetallado.reintentable = false;
      errorDetallado.mensaje = errorMsg;
    }

    // Agregar observaciones detalladas si existen
    if (errorData.observaciones) {
      const observaciones = Array.isArray(errorData.observaciones)
        ? errorData.observaciones
        : [errorData.observaciones];

      errorDetallado.observacionesDetalle = observaciones.map(obs => {
        if (typeof obs === 'string') return obs;
        if (!obs || typeof obs !== 'object') return String(obs);

        const partes = [
          obs.codigo || obs.cod || obs.codigoError,
          obs.campo || obs.path || obs.propiedad,
          obs.mensaje || obs.message || obs.descripcion || obs.error
        ].filter(Boolean);

        return partes.length ? partes.join(' - ') : JSON.stringify(obs);
      });
    } else if (errorData.descripcionMsg) {
      errorDetallado.observacionesDetalle = [errorData.descripcionMsg];
    }

    return errorDetallado;
  }

  /**
   * Consultar estado de un DTE
   */
  async consultarDTE(codigoGeneracion) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const response = await this.axiosInstance.get(
        `/fesv/recepciondte/${codigoGeneracion}`,
        {
          headers: {
            'Authorization': this.token
          }
        }
      );

      return response.data;
    } catch (error) {
      throw new Error(`Error al consultar DTE: ${error.message}`);
    }
  }

  async consultarLoteDTE(codigoLote) {
    if (!this.token) {
      throw new Error('No hay token de autenticación. Autentique primero.');
    }

    try {
      const response = await this.axiosInstance.get(
        `/fesv/recepcion/consultadtelote/${codigoLote}`,
        {
          headers: {
            'Authorization': this.token
          }
        }
      );

      return {
        success: true,
        ...response.data,
        raw: response.data
      };
    } catch (error) {
      const errorResponse = this.procesarErrorHacienda(error);
      return {
        success: false,
        error: errorResponse.error,
        errorDetalle: errorResponse
      };
    }
  }

  /**
   * Generar código de generación único
   */
  generarCodigoGeneracion() {
    const uuid = crypto.randomUUID().toUpperCase();
    return uuid;
  }

  /**
   * Generar número de control
   */
  generarNumeroControl(tipoDocumento, codigoEstablecimiento, puntoVenta, numeroDocumento) {
    // Formato: DTE-{tipo}-{establecimiento}-{punto venta}-{número (15 dígitos)}
    const numero = numeroDocumento.toString().padStart(15, '0');
    return `DTE-${tipoDocumento}-${codigoEstablecimiento}-${puntoVenta}-${numero}`;
  }

  /**
   * Construir estructura JSON del DTE según especificaciones de Hacienda
   */
  construirDTE(tipo, datos) {
    const fechaEmision = new Date().toISOString().split('T')[0];
    const horaEmision = new Date().toTimeString().split(' ')[0];

    const dteBase = {
      identificacion: {
        version: 1,
        ambiente: this.obtenerCodigoAmbiente(this.ambiente),
        tipoDte: tipo,
        numeroControl: datos.numeroControl,
        codigoGeneracion: datos.codigoGeneracion || this.generarCodigoGeneracion(),
        tipoModelo: 1,
        tipoOperacion: 1,
        tipoContingencia: null,
        motivoContin: null,
        fecEmi: fechaEmision,
        horEmi: horaEmision,
        tipoMoneda: 'USD'
      },
      emisor: {
        nit: datos.emisor.nit,
        nrc: datos.emisor.nrc,
        nombre: datos.emisor.nombre,
        codActividad: datos.emisor.codActividad,
        descActividad: datos.emisor.descActividad,
        nombreComercial: datos.emisor.nombreComercial,
        tipoEstablecimiento: datos.emisor.tipoEstablecimiento || '01',
        direccion: {
          departamento: datos.emisor.departamento,
          municipio: datos.emisor.municipio,
          complemento: datos.emisor.direccion
        },
        telefono: datos.emisor.telefono,
        correo: datos.emisor.correo,
        codEstableMH: datos.emisor.codEstableMH,
        codEstable: datos.emisor.codEstable,
        codPuntoVentaMH: datos.emisor.codPuntoVentaMH,
        codPuntoVenta: datos.emisor.codPuntoVenta
      },
      receptor: {
        tipoDocumento: datos.receptor.tipoDocumento,
        numDocumento: datos.receptor.numDocumento,
        nrc: datos.receptor.nrc || null,
        nombre: datos.receptor.nombre,
        codActividad: datos.receptor.codActividad || null,
        descActividad: datos.receptor.descActividad || null,
        direccion: datos.receptor.direccion ? {
          departamento: datos.receptor.departamento,
          municipio: datos.receptor.municipio,
          complemento: datos.receptor.direccion
        } : null,
        telefono: datos.receptor.telefono,
        correo: datos.receptor.correo
      },
      cuerpoDocumento: datos.items.map((item, index) => ({
        numItem: index + 1,
        tipoItem: item.tipoItem || '1',
        numeroDocumento: null,
        cantidad: item.cantidad,
        codigo: item.codigo,
        codTributo: null,
        uniMedida: item.uniMedida || '99',
        descripcion: item.descripcion,
        precioUni: item.precioUni,
        montoDescu: item.montoDescu || 0,
        ventaNoSuj: item.ventaNoSuj || 0,
        ventaExenta: item.ventaExenta || 0,
        ventaGravada: item.ventaGravada || 0,
        tributos: item.tributos || null,
        psv: 0,
        noGravado: 0
      })),
      resumen: {
        totalNoSuj: datos.resumen.totalNoSuj || 0,
        totalExenta: datos.resumen.totalExenta || 0,
        totalGravada: datos.resumen.totalGravada,
        subTotalVentas: datos.resumen.subTotalVentas,
        descuNoSuj: 0,
        descuExenta: 0,
        descuGravada: datos.resumen.descuGravada || 0,
        porcentajeDescuento: 0,
        totalDescu: datos.resumen.totalDescu || 0,
        tributos: datos.resumen.tributos || null,
        subTotal: datos.resumen.subTotal,
        ivaRete1: 0,
        reteRenta: 0,
        montoTotalOperacion: datos.resumen.montoTotalOperacion,
        totalNoGravado: 0,
        totalPagar: datos.resumen.totalPagar,
        totalLetras: datos.resumen.totalLetras,
        totalIva: datos.resumen.totalIva || 0,
        saldoFavor: 0,
        condicionOperacion: datos.resumen.condicionOperacion || '1',
        pagos: datos.resumen.pagos || null,
        numPagoElectronico: null
      },
      extension: datos.extension || null,
      apendice: datos.apendice || null
    };

    return dteBase;
  }

  /**
   * Calcular resumen de factura
   */
  calcularResumen(items, condicionOperacion = '1') {
    let totalNoSuj = 0;
    let totalExenta = 0;
    let totalGravada = 0;
    let totalDescu = 0;

    items.forEach(item => {
      const subtotalItem = item.cantidad * item.precioUni;
      totalDescu += item.montoDescu || 0;
      
      if (item.ventaNoSuj) totalNoSuj += item.ventaNoSuj;
      if (item.ventaExenta) totalExenta += item.ventaExenta;
      if (item.ventaGravada) totalGravada += item.ventaGravada;
    });

    const subTotalVentas = totalNoSuj + totalExenta + totalGravada;
    const subTotal = subTotalVentas - totalDescu;
    const totalIva = totalGravada * 0.13; // IVA 13%
    const montoTotalOperacion = subTotal + totalIva;
    const totalPagar = montoTotalOperacion;

    return {
      totalNoSuj,
      totalExenta,
      totalGravada,
      subTotalVentas,
      descuGravada: totalDescu,
      totalDescu,
      subTotal,
      totalIva,
      montoTotalOperacion,
      totalPagar,
      totalLetras: this.numeroALetras(totalPagar),
      condicionOperacion,
      tributos: totalIva > 0 ? [{
        codigo: '20',
        descripcion: 'Impuesto al Valor Agregado 13%',
        valor: totalIva
      }] : null
    };
  }

  /**
   * Convertir número a letras
   */
  numeroALetras(numero) {
    // Implementación básica - se puede mejorar con una librería
    const entero = Math.floor(numero);
    const decimales = Math.round((numero - entero) * 100);
    return `${entero} DÓLARES CON ${decimales}/100`;
  }
}

module.exports = HaciendaAPI;
