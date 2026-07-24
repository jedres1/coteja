const crypto = require('crypto');
const fs = require('fs').promises;

class FirmadorInternoMH {
  constructor(config = {}) {
    this.certificadoPath = config.certificadoPath || null;
    this.nit = this.limpiarDocumento(config.nit);
    this.passwordPri = config.passwordPri || null;
    this.certificado = null;
  }

  async cargarCertificado(certificadoPath = this.certificadoPath, passwordPri = this.passwordPri) {
    if (!certificadoPath) {
      throw new Error('La ruta del certificado .crt es requerida para firmar internamente.');
    }

    if (!passwordPri) {
      throw new Error('La contraseña de la llave privada es requerida para firmar internamente.');
    }

    const contenido = await fs.readFile(certificadoPath, 'utf8');
    const certificado = this.parsearCertificadoMH(contenido);
    const nitEsperado = this.nit;

    if (nitEsperado && certificado.nit !== nitEsperado) {
      throw new Error(`El certificado pertenece al NIT ${certificado.nit}, no al NIT ${nitEsperado}.`);
    }

    const hashClave = crypto.createHash('sha512').update(passwordPri).digest('hex');
    if (certificado.privateKey.clave !== hashClave) {
      throw new Error('La contraseña de la llave privada no coincide con el certificado.');
    }

    certificado.privateKeyObject = crypto.createPrivateKey({
      key: Buffer.from(certificado.privateKey.encodied, 'base64'),
      format: 'der',
      type: 'pkcs8'
    });

    this.certificado = certificado;
    this.certificadoPath = certificadoPath;
    this.passwordPri = passwordPri;

    return {
      success: true,
      info: this.obtenerInfoCertificado()
    };
  }

  async firmarDocumento(documento, opciones = {}) {
    if (!this.certificado) {
      await this.cargarCertificado(
        opciones.certificadoPath || this.certificadoPath,
        opciones.passwordPri || this.passwordPri
      );
    }

    const header = { alg: 'RS512' };
    const payload = this.clonarSinFirma(documento);
    const signingInput = [
      this.base64Url(JSON.stringify(header)),
      this.base64Url(JSON.stringify(payload))
    ].join('.');

    const signature = crypto.sign(
      'RSA-SHA512',
      Buffer.from(signingInput, 'utf8'),
      this.certificado.privateKeyObject
    );

    const firmaMh = `${signingInput}.${this.base64Url(signature)}`;

    return {
      success: true,
      documentoFirmado: {
        ...payload,
        firmaMh
      },
      firmaMh,
      raw: {
        metodo: 'interno',
        nit: this.certificado.nit,
        alg: header.alg
      }
    };
  }

  validarCertificado() {
    if (!this.certificado) {
      return { valido: false, error: 'Certificado no cargado' };
    }

    return {
      valido: true,
      info: this.obtenerInfoCertificado()
    };
  }

  obtenerInfoCertificado() {
    if (!this.certificado) return null;

    return {
      nit: this.certificado.nit,
      activo: this.certificado.activo,
      verificado: this.certificado.verificado,
      sujeto: this.certificado.subject?.organizationName || this.certificado.subject?.commonName || this.certificado.nit,
      emisor: this.certificado.issuer?.organizationalName || this.certificado.issuer?.commonName || 'Ministerio de Hacienda',
      validoDesde: this.certificado.validity?.notBefore || null,
      validoHasta: this.certificado.validity?.notAfter || null
    };
  }

  parsearCertificadoMH(xml) {
    const nit = this.extraerTag(xml, 'nit');
    const privateKeyXml = this.extraerBloque(xml, 'privateKey');
    const publicKeyXml = this.extraerBloque(xml, 'publicKey');

    if (!nit || !privateKeyXml) {
      throw new Error('El archivo no parece ser un certificado .crt de Hacienda válido.');
    }

    const privateKey = {
      algorithm: this.extraerTag(privateKeyXml, 'algorithm'),
      encodied: this.normalizarBase64(this.extraerTag(privateKeyXml, 'encodied')),
      format: this.extraerTag(privateKeyXml, 'format'),
      clave: this.extraerTag(privateKeyXml, 'clave')
    };

    if (!privateKey.encodied || !privateKey.clave) {
      throw new Error('El certificado no contiene llave privada utilizable.');
    }

    return {
      nit: this.limpiarDocumento(nit),
      activo: this.extraerTag(xml, 'activo') === 'true',
      verificado: this.extraerTag(xml, 'verificado') === 'true',
      privateKey,
      publicKey: {
        algorithm: this.extraerTag(publicKeyXml, 'algorithm'),
        encodied: this.normalizarBase64(this.extraerTag(publicKeyXml, 'encodied')),
        format: this.extraerTag(publicKeyXml, 'format'),
        clave: this.extraerTag(publicKeyXml, 'clave')
      },
      issuer: this.parsearBloqueSimple(this.extraerBloque(xml, 'issuer')),
      subject: this.parsearBloqueSimple(this.extraerBloque(xml, 'subject')),
      validity: this.parsearVigencia(this.extraerBloque(xml, 'validity'))
    };
  }

  parsearBloqueSimple(xml = '') {
    const resultado = {};
    const regex = /<([A-Za-z0-9_]+)>([^<]*)<\/\1>/g;
    let match;
    while ((match = regex.exec(xml)) !== null) {
      resultado[match[1]] = this.decodificarXml(match[2]);
    }
    return resultado;
  }

  parsearVigencia(xml = '') {
    const convertir = (valor) => {
      const numero = Number(valor);
      if (!Number.isFinite(numero)) return null;
      return new Date(numero * 1000);
    };

    return {
      notBefore: convertir(this.extraerTag(xml, 'notBefore')),
      notAfter: convertir(this.extraerTag(xml, 'notAfter'))
    };
  }

  extraerBloque(xml, tag) {
    const match = String(xml || '').match(new RegExp(`<${tag}>([\\s\\S]*?)<\\/${tag}>`));
    return match ? match[1] : '';
  }

  extraerTag(xml, tag) {
    const match = String(xml || '').match(new RegExp(`<${tag}>([\\s\\S]*?)<\\/${tag}>`));
    return match ? this.decodificarXml(match[1].trim()) : '';
  }

  decodificarXml(valor) {
    return String(valor || '')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"')
      .replace(/&apos;/g, "'")
      .replace(/&amp;/g, '&');
  }

  normalizarBase64(valor) {
    return String(valor || '').replace(/\s+/g, '');
  }

  base64Url(valor) {
    const buffer = Buffer.isBuffer(valor) ? valor : Buffer.from(String(valor), 'utf8');
    return buffer
      .toString('base64')
      .replace(/=/g, '')
      .replace(/\+/g, '-')
      .replace(/\//g, '_');
  }

  clonarSinFirma(documento) {
    const copia = JSON.parse(JSON.stringify(documento || {}));
    delete copia.firmaMh;
    delete copia.documentoFirmado;
    if (typeof copia.documento === 'string') {
      delete copia.documento;
    }
    delete copia.firma;
    return copia;
  }

  limpiarDocumento(valor) {
    return String(valor || '').replace(/[^0-9]/g, '');
  }
}

module.exports = FirmadorInternoMH;
