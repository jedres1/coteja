const fs = require('fs');
const DTEGenerator = require('./src/utils/dte-generator');
const DTEValidator = require('./src/utils/dte-validator');
const HaciendaAPI = require('./src/api/hacienda');
const FirmadorInternoMH = require('./src/utils/firmador-interno-mh');
const PDFGenerator = require('./src/utils/pdf-generator');

function readPayload() {
  const input = fs.readFileSync(0, 'utf8');
  return input.trim() ? JSON.parse(input) : {};
}

function buildDte(tipo, generator, payload) {
  const config = payload.config || {};
  const cliente = payload.cliente || {};
  const items = payload.items || [];
  const resumen = payload.resumen || {};
  const opciones = payload.opciones || {};

  switch (tipo) {
    case '01':
      return generator.generarFactura(config, cliente, items, resumen, opciones);
    case '03':
      return generator.generarCreditoFiscal(config, cliente, items, resumen, opciones);
    case '04':
      return generator.generarNotaRemision(config, cliente, items, resumen, opciones);
    case '05':
      return generator.generarNotaCredito(config, cliente, items, resumen, opciones.documentoRelacionado, opciones);
    case '06':
      return generator.generarNotaDebito(config, cliente, items, resumen, opciones.documentoRelacionado, opciones);
    case '07':
      return generator.generarComprobanteRetencion(config, cliente, items, resumen, opciones.documentoRelacionado, opciones);
    case '08':
      return generator.generarComprobanteLiquidacion(config, cliente, items, resumen, opciones);
    case '11':
      return generator.generarFacturaExportacion(config, cliente, items, resumen, opciones);
    case '14':
      return generator.generarFacturaSujetoExcluido(config, cliente, items, resumen, opciones);
    case '15':
      return generator.generarComprobanteDonacion(config, cliente, items, resumen, opciones);
    default:
      throw new Error(`Tipo de DTE no soportado: ${tipo}`);
  }
}

async function run() {
  const request = readPayload();
  const action = request.action;
  const payload = request.payload || {};

  if (!action) {
    throw new Error('Debe especificar action.');
  }

  if (action === 'generate') {
    const generator = new DTEGenerator();
    const validator = new DTEValidator();
    const dte = buildDte(payload.tipo || '01', generator, payload);
    const validation = validator.validar(dte);
    return { success: validation.valido, dte, validation };
  }

  if (action === 'validate') {
    const validator = new DTEValidator();
    return { success: true, validation: validator.validar(payload.dte) };
  }

  if (action === 'validate-event') {
    const validator = new DTEValidator();
    return { success: true, validation: validator.validarEvento(payload.tipoEvento, payload.evento) };
  }

  if (action === 'sign-internal') {
    const signer = new FirmadorInternoMH({
      certificadoPath: payload.certificadoPath,
      nit: payload.nit,
      passwordPri: payload.passwordPri
    });
    return await signer.firmarDocumento(payload.documento);
  }

  if (action === 'validate-certificate') {
    const signer = new FirmadorInternoMH({
      certificadoPath: payload.certificadoPath,
      nit: payload.nit,
      passwordPri: payload.passwordPri
    });
    const result = await signer.cargarCertificado();
    return { valido: true, info: result.info };
  }

  if (action === 'auth') {
    const api = new HaciendaAPI(payload.config || {});
    return await api.autenticar();
  }

  if (action === 'send') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return await api.enviarDTE(payload.dte, payload.nit, payload.passwordPri);
  }

  if (action === 'send-batch') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return await api.enviarLoteDTE(payload.dtes, payload.nit);
  }

  if (action === 'void') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return await api.anularDTE(payload.eventoFirmado);
  }

  if (action === 'contingency') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return await api.enviarContingencia(payload.eventoFirmado, payload.nit);
  }

  if (action === 'query') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return { success: true, result: await api.consultarDTE(payload.codigoGeneracion) };
  }

  if (action === 'query-batch') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return await api.consultarLoteDTE(payload.codigoLote);
  }

  if (action === 'generate-eoe') {
    const generator = new DTEGenerator();
    const validator = new DTEValidator();
    const eoe = generator.generarEventoOperacionesEspeciales(
      payload.config || {},
      payload.detalle || [],
      payload.resumen || {},
      payload.receptor || null,
      payload.opciones || {}
    );
    const validation = validator.validarEvento('eoe', eoe);
    return { success: validation.valido, eoe, validation };
  }

  if (action === 'send-eoe') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return await api.enviarEOE(payload.eventoFirmado, payload.nit);
  }

  if (action === 'generate-er') {
    const generator = new DTEGenerator();
    const validator = new DTEValidator();
    const er = generator.generarEventoRetorno(
      payload.config || {},
      payload.receptor || {},
      payload.items || [],
      payload.resumen || {},
      payload.documentoRelacionado || {},
      payload.opciones || {}
    );
    const validation = validator.validarEvento('retorno', er);
    return { success: validation.valido, er, validation };
  }

  if (action === 'send-er') {
    const api = new HaciendaAPI(payload.config || {});
    if (!api.token) await api.autenticar();
    return await api.enviarRetorno(payload.eventoFirmado, payload.nit);
  }

  if (action === 'pdf') {
    const pdf = new PDFGenerator();
    const buffer = await pdf.generarPDFFactura(payload.factura || {}, payload.dte, payload.config || {});
    return {
      success: true,
      filename: payload.filename || 'dte.pdf',
      contentType: 'application/pdf',
      base64: buffer.toString('base64')
    };
  }

  throw new Error(`Action no soportada: ${action}`);
}

run()
  .then((result) => {
    process.stdout.write(JSON.stringify(result));
  })
  .catch((error) => {
    process.stdout.write(JSON.stringify({
      success: false,
      error: error.message,
      stack: process.env.APP_DEBUG ? error.stack : undefined
    }));
    process.exitCode = 1;
  });
