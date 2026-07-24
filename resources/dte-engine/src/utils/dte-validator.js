const path = require('path');
const Ajv = require('ajv');

const schemas = {
  '01': require(path.join(__dirname, '../schemas/fe-fc-v1.json')),
  '03': require(path.join(__dirname, '../schemas/fe-ccf-v3.json')),
  '05': require(path.join(__dirname, '../schemas/fe-nc-v3.json')),
  '07': require(path.join(__dirname, '../schemas/fe-cr-v1.json')),
  '11': require(path.join(__dirname, '../schemas/fe-fex-v1.json')),
  '14': require(path.join(__dirname, '../schemas/fe-fse-v1.json'))
};

const eventSchemas = {
  anulacion: require(path.join(__dirname, '../schemas/anulacion-schema-v2.json')),
  contingencia: require(path.join(__dirname, '../schemas/contingencia-schema-v3.json'))
};

const receptorRetencionAllOf = schemas['07']?.properties?.receptor?.allOf;
if (Array.isArray(receptorRetencionAllOf)) {
  const reglaDuiRetencion = receptorRetencionAllOf.find(regla => regla?.if?.properties?.tipoDocumento?.const === '13');
  if (reglaDuiRetencion?.then?.properties?.numDocumento) {
    reglaDuiRetencion.then.properties.numDocumento.pattern = '^([0-9]{9}|[0-9]{8}-[0-9]{1})$';
  }
}

class DTEValidator {
  constructor() {
    this.ajv = new Ajv({
      allErrors: true,
      jsonPointers: true,
      multipleOfPrecision: 12,
      strict: false
    });

    this.validators = {};
    for (const [tipoDte, schema] of Object.entries(schemas)) {
      this.validators[tipoDte] = this.ajv.compile(schema);
    }

    this.eventValidators = {};
    for (const [tipoEvento, schema] of Object.entries(eventSchemas)) {
      this.eventValidators[tipoEvento] = this.ajv.compile(schema);
    }
  }

  validar(dte) {
    const dteFiscal = this.normalizarDTEFiscal(dte);
    const tipoDte = dteFiscal?.identificacion?.tipoDte;
    const validator = this.validators[tipoDte];

    if (!validator) {
      return {
        valido: true,
        advertencias: [`No hay schema local para validar DTE tipo ${tipoDte || 'desconocido'}.`],
        errores: []
      };
    }

    const valido = validator(dteFiscal);
    const errores = this.filtrarErroresNumericos(dteFiscal, validator.errors || []);
    return {
      valido: valido || errores.length === 0,
      advertencias: [],
      errores: valido ? [] : this.formatearErrores(errores)
    };
  }

  validarEvento(tipoEvento, evento) {
    const eventoFiscal = this.normalizarEventoFiscal(evento);
    const validator = this.eventValidators[tipoEvento];

    if (!validator) {
      return {
        valido: true,
        advertencias: [`No hay schema local para validar evento ${tipoEvento || 'desconocido'}.`],
        errores: []
      };
    }

    const valido = validator(eventoFiscal);
    const errores = this.filtrarErroresNumericos(eventoFiscal, validator.errors || []);
    return {
      valido: valido || errores.length === 0,
      advertencias: [],
      errores: valido ? [] : this.formatearErrores(errores)
    };
  }

  normalizarDTEFiscal(dte) {
    const contenido = this.obtenerContenidoDTE(dte);
    if (!contenido || typeof contenido !== 'object') return contenido;

    const copia = JSON.parse(JSON.stringify(contenido));
    delete copia.firmaMh;
    delete copia.documentoFirmado;
    delete copia.documento;
    delete copia.firma;
    delete copia.firmaElectronica;
    delete copia.selloRecibido;
    delete copia.respuestaHacienda;

    return copia;
  }

  normalizarEventoFiscal(evento) {
    const contenido = this.obtenerContenidoDTE(evento);
    if (!contenido || typeof contenido !== 'object') return contenido;

    const copia = JSON.parse(JSON.stringify(contenido));
    delete copia.firmaMh;
    delete copia.documentoFirmado;
    delete copia.firma;
    delete copia.firmaElectronica;
    delete copia.selloRecibido;
    delete copia.respuestaHacienda;

    return copia;
  }

  obtenerContenidoDTE(dte) {
    if (typeof dte === 'string') {
      const texto = dte.trim();
      if (this.pareceJWS(texto)) return this.parseJWSPayload(texto);
      try {
        return this.obtenerContenidoDTE(JSON.parse(texto));
      } catch {
        return dte;
      }
    }

    const candidatos = [
      dte,
      dte?.dteJson,
      dte?.dte,
      dte?.documentoFirmado,
      dte?.documento
    ];

    for (const candidato of candidatos) {
      if (!candidato) continue;
      const contenido = typeof candidato === 'string' ? this.obtenerContenidoDTE(candidato) : candidato;
      if (contenido?.identificacion) return contenido;
    }

    return dte;
  }

  pareceJWS(valor) {
    return typeof valor === 'string' && valor.split('.').length === 3;
  }

  parseJWSPayload(jws) {
    try {
      const payload = jws.split('.')[1];
      const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
      return JSON.parse(Buffer.from(base64, 'base64').toString('utf8'));
    } catch {
      return jws;
    }
  }

  filtrarErroresNumericos(dte, errors) {
    return errors.filter((error) => {
      if (error.keyword !== 'multipleOf') return true;

      const valor = this.obtenerValorPorRuta(dte, error.dataPath);
      const multipleOf = error.params?.multipleOf;
      if (typeof valor !== 'number' || typeof multipleOf !== 'number') return true;

      const cociente = valor / multipleOf;
      return Math.abs(cociente - Math.round(cociente)) > 1e-6;
    });
  }

  obtenerValorPorRuta(objeto, dataPath) {
    if (!dataPath || dataPath === '/') return objeto;
    return dataPath
      .split('/')
      .filter(Boolean)
      .reduce((valor, segmento) => {
        if (valor === undefined || valor === null) return undefined;
        return valor[segmento];
      }, objeto);
  }

  formatearErrores(errors) {
    return errors.map((error) => {
      const campo = error.dataPath || error.schemaPath || '/';
      const valor = Object.prototype.hasOwnProperty.call(error, 'data')
        ? ` Valor: ${JSON.stringify(error.data)}.`
        : '';
      return `${campo} ${error.message}.${valor}`;
    });
  }
}

module.exports = DTEValidator;
