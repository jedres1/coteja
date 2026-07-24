const forge = require('node-forge');
const fs = require('fs').promises;

/**
 * Firmador usando certificado digital local con node-forge
 */
class FirmadorLocal {
  constructor() {
    this.certificado = null;
    this.privateKey = null;
  }

  /**
   * Cargar certificado desde archivo
   * @param {string} certificadoPath - Ruta al archivo del certificado
   * @param {string} password - Contraseña del certificado
   */
  async cargarCertificado(certificadoPath, password) {
    try {
      const certificadoData = await fs.readFile(certificadoPath);
      
      // Determinar tipo de certificado por extensión
      const ext = certificadoPath.split('.').pop().toLowerCase();
      
      if (ext === 'p12' || ext === 'pfx') {
        // Certificado PKCS#12
        const p12Der = certificadoData.toString('binary');
        const p12Asn1 = forge.asn1.fromDer(p12Der);
        const p12 = forge.pkcs12.pkcs12FromAsn1(p12Asn1, password);
        
        // Obtener certificado y clave privada
        const certBags = p12.getBags({ bagType: forge.pki.oids.certBag });
        const pkeyBags = p12.getBags({ bagType: forge.pki.oids.pkcs8ShroudedKeyBag });
        
        if (certBags[forge.pki.oids.certBag] && certBags[forge.pki.oids.certBag].length > 0) {
          this.certificado = certBags[forge.pki.oids.certBag][0].cert;
        }
        
        if (pkeyBags[forge.pki.oids.pkcs8ShroudedKeyBag] && pkeyBags[forge.pki.oids.pkcs8ShroudedKeyBag].length > 0) {
          this.privateKey = pkeyBags[forge.pki.oids.pkcs8ShroudedKeyBag][0].key;
        }
      } else if (ext === 'crt' || ext === 'pem') {
        // Certificado PEM
        const pemString = certificadoData.toString('utf-8');
        this.certificado = forge.pki.certificateFromPem(pemString);
        
        // Para .crt, puede que necesites un archivo .key separado
        // Intentar cargar la clave privada desde el mismo archivo
        try {
          this.privateKey = forge.pki.privateKeyFromPem(pemString);
        } catch (e) {
          console.warn('No se pudo extraer la clave privada del certificado. Puede necesitar un archivo .key separado.');
        }
      }
      
      if (!this.certificado) {
        throw new Error('No se pudo cargar el certificado');
      }
      
      // Validar certificado
      const validacion = this.validarCertificado();
      if (!validacion.valido) {
        throw new Error(`Certificado inválido: ${validacion.error}`);
      }
      
      return { success: true, info: validacion.info };
    } catch (error) {
      console.error('Error cargando certificado:', error);
      throw new Error(`Error al cargar certificado: ${error.message}`);
    }
  }

  /**
   * Validar certificado digital
   */
  validarCertificado() {
    if (!this.certificado) {
      return { valido: false, error: 'Certificado no cargado' };
    }

    const ahora = new Date();
    const validoDesde = this.certificado.validity.notBefore;
    const validoHasta = this.certificado.validity.notAfter;

    // Verificar vigencia
    if (ahora < validoDesde) {
      return { 
        valido: false, 
        error: `Certificado aún no es válido. Válido desde: ${validoDesde.toLocaleDateString()}` 
      };
    }

    if (ahora > validoHasta) {
      return { 
        valido: false, 
        error: `Certificado expirado. Expiró el: ${validoHasta.toLocaleDateString()}` 
      };
    }

    // Verificar que tenga clave privada
    if (!this.privateKey) {
      return { 
        valido: false, 
        error: 'No se encontró clave privada en el certificado' 
      };
    }

    // Advertencia si expira pronto (dentro de 30 días)
    const diasParaExpiracion = Math.floor((validoHasta - ahora) / (1000 * 60 * 60 * 24));
    const advertencia = diasParaExpiracion < 30 
      ? `Advertencia: El certificado expira en ${diasParaExpiracion} días` 
      : null;

    return { 
      valido: true, 
      info: {
        validoDesde: validoDesde,
        validoHasta: validoHasta,
        diasRestantes: diasParaExpiracion,
        advertencia: advertencia,
        sujeto: this.certificado.subject.getField('CN')?.value || 'Desconocido',
        emisor: this.certificado.issuer.getField('CN')?.value || 'Desconocido'
      }
    };
  }

  /**
   * Firmar documento JSON
   * @param {Object} documento - Documento a firmar
   * @returns {Object} - Documento firmado con firma digital
   */
  async firmarDocumento(documento) {
    try {
      if (!this.certificado || !this.privateKey) {
        throw new Error('Certificado no cargado. Use cargarCertificado() primero.');
      }
      
      // Convertir documento a JSON string canónico
      const documentoJson = JSON.stringify(documento, null, 0);
      
      // Crear hash del documento
      const md = forge.md.sha256.create();
      md.update(documentoJson, 'utf8');
      
      // Firmar el hash
      const signature = this.privateKey.sign(md);
      
      // Convertir firma a Base64
      const signatureBase64 = forge.util.encode64(signature);
      
      // Obtener información del certificado
      const certPem = forge.pki.certificateToPem(this.certificado);
      const certBase64 = certPem
        .replace('-----BEGIN CERTIFICATE-----', '')
        .replace('-----END CERTIFICATE-----', '')
        .replace(/\n/g, '');
      
      // Construir documento firmado según especificación de Hacienda
      const documentoFirmado = {
        ...documento,
        firma: {
          firmante: this.certificado.subject.getField('CN')?.value || 'Firmante',
          fechaFirma: new Date().toISOString(),
          algoritmo: 'SHA256withRSA',
          valorFirma: signatureBase64,
          certificado: certBase64,
          nit: this.certificado.subject.getField('serialNumber')?.value || ''
        }
      };
      
      return { success: true, documentoFirmado };
    } catch (error) {
      console.error('Error firmando documento:', error);
      throw new Error(`Error al firmar documento: ${error.message}`);
    }
  }

  /**
   * Validar firma de un documento
   * @param {Object} documentoFirmado - Documento con firma
   * @returns {Boolean} - true si la firma es válida
   */
  async validarFirma(documentoFirmado) {
    try {
      if (!documentoFirmado.firma) {
        throw new Error('El documento no contiene firma');
      }
      
      // Extraer firma y certificado
      const { valorFirma, certificado: certBase64 } = documentoFirmado.firma;
      
      // Reconstruir documento sin firma
      const { firma, ...documentoSinFirma } = documentoFirmado;
      const documentoJson = JSON.stringify(documentoSinFirma, null, 0);
      
      // Convertir certificado de Base64 a objeto
      const certPem = `-----BEGIN CERTIFICATE-----\n${certBase64}\n-----END CERTIFICATE-----`;
      const cert = forge.pki.certificateFromPem(certPem);
      
      // Crear hash del documento
      const md = forge.md.sha256.create();
      md.update(documentoJson, 'utf8');
      
      // Decodificar firma de Base64
      const signature = forge.util.decode64(valorFirma);
      
      // Verificar firma
      const publicKey = cert.publicKey;
      const verified = publicKey.verify(md.digest().bytes(), signature);
      
      return verified;
    } catch (error) {
      console.error('Error validando firma:', error);
      return false;
    }
  }

  /**
   * Obtener información del certificado cargado
   */
  getInfoCertificado() {
    if (!this.certificado) {
      return null;
    }
    
    return {
      sujeto: this.certificado.subject.attributes.map(attr => ({
        nombre: attr.name,
        valor: attr.value
      })),
      emisor: this.certificado.issuer.attributes.map(attr => ({
        nombre: attr.name,
        valor: attr.value
      })),
      validoDesde: this.certificado.validity.notBefore,
      validoHasta: this.certificado.validity.notAfter,
      numeroSerie: this.certificado.serialNumber
    };
  }
}

module.exports = FirmadorLocal;
