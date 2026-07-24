const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');

/**
 * Módulo para automatizar el firmado de documentos usando el Firmador de Hacienda
 * con Puppeteer.
 * 
 * IMPORTANTE: Requiere credenciales del Firmador de Hacienda (usuario y contraseña)
 * para poder firmar documentos electrónicamente.
 */
class Firmador {
  constructor(credenciales = {}) {
    this.browser = null;
    this.page = null;
    // URL del firmador de Hacienda (ajustar según sea necesario)
    this.firmadorURL = 'https://apps.mh.gob.sv/firmador/';
    // Credenciales del firmador (usuario y contraseña)
    this.usuario = credenciales.usuario;
    this.password = credenciales.password;
  }

  /**
   * Inicializar el navegador
   */
  async inicializar() {
    if (!this.browser) {
      this.browser = await puppeteer.launch({
        headless: false, // Mostrar navegador para debugging
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-web-security'
        ]
      });
      this.page = await this.browser.newPage();
      await this.page.setViewport({ width: 1280, height: 800 });
    }
  }

  /**
   * Cerrar el navegador
   */
  async cerrar() {
    if (this.browser) {
      await this.browser.close();
      this.browser = null;
      this.page = null;
    }
  }

  /**
   * Firmar documento JSON
   * @param {Object} documento - Documento DTE en formato JSON
   * @param {String} pin - PIN del certificado digital
   * @returns {Object} - Documento firmado
   * 
   * NOTA: Este método utiliza las credenciales del Firmador de Hacienda
   * (usuario y contraseña) proporcionadas en el constructor.
   */
  async firmarDocumento(documento, pin) {
    try {
      await this.inicializar();

      // Navegar al firmador
      await this.page.goto(this.firmadorURL, { waitUntil: 'networkidle2' });

      // Login con credenciales del firmador (si es necesario)
      const loginForm = await this.page.$('input[name="usuario"], input[name="username"]');
      if (loginForm && this.usuario && this.password) {
        await this.page.type('input[name="usuario"], input[name="username"]', this.usuario);
        await this.page.type('input[name="password"], input[name="clave"]', this.password);
        await this.page.click('button[type="submit"]');
        await this.page.waitForNavigation({ waitUntil: 'networkidle2' });
      }

      // Esperar a que cargue la interfaz del firmador
      await this.page.waitForSelector('input[type="file"]', { timeout: 10000 });

      // Convertir documento a JSON string
      const jsonString = JSON.stringify(documento, null, 2);

      // Guardar temporalmente el JSON
      const tempPath = path.join(__dirname, '../../temp');
      await fs.mkdir(tempPath, { recursive: true });
      const tempFile = path.join(tempPath, `dte_${Date.now()}.json`);
      await fs.writeFile(tempFile, jsonString);

      // Subir el archivo
      const inputFile = await this.page.$('input[type="file"]');
      await inputFile.uploadFile(tempFile);

      // Esperar a que se procese el archivo
      await this.page.waitForTimeout(2000);

      // Ingresar PIN si es requerido
      const pinInput = await this.page.$('input[type="password"]');
      if (pinInput) {
        await pinInput.type(pin);
      }

      // Hacer click en el botón de firmar
      await this.page.click('button[type="submit"]');

      // Esperar a que se complete el firmado
      await this.page.waitForSelector('.firma-exitosa, .firma-completada', { 
        timeout: 30000 
      });

      // Descargar el documento firmado
      const [download] = await Promise.all([
        this.page.waitForEvent('download'),
        this.page.click('button.descargar, a.descargar')
      ]);

      const downloadPath = await download.path();
      const documentoFirmado = await fs.readFile(downloadPath, 'utf-8');

      // Limpiar archivos temporales
      await fs.unlink(tempFile);

      return JSON.parse(documentoFirmado);

    } catch (error) {
      throw new Error(`Error al firmar documento: ${error.message}`);
    } finally {
      await this.cerrar();
    }
  }

  /**
   * Método alternativo: Firmar usando certificado local
   * (Para cuando se tiene acceso directo al certificado .p12)
   */
  async firmarConCertificadoLocal(documento, certificadoPath, password) {
    // NOTA: Esta funcionalidad requeriría una librería adicional de firma digital
    // como node-forge o similar. Se deja como placeholder.
    
    throw new Error('Firma con certificado local no implementada aún. Use el firmador web.');
  }

  /**
   * Validar firma de un documento
   */
  async validarFirma(documentoFirmado) {
    try {
      await this.inicializar();

      // Navegar a la sección de validación
      await this.page.goto(`${this.firmadorURL}/validar`, { waitUntil: 'networkidle2' });

      // Similar al proceso de firmado pero para validación
      const jsonString = JSON.stringify(documentoFirmado, null, 2);
      const tempPath = path.join(__dirname, '../../temp');
      await fs.mkdir(tempPath, { recursive: true });
      const tempFile = path.join(tempPath, `dte_validar_${Date.now()}.json`);
      await fs.writeFile(tempFile, jsonString);

      const inputFile = await this.page.$('input[type="file"]');
      await inputFile.uploadFile(tempFile);

      await this.page.click('button[type="submit"]');

      // Esperar resultado de validación
      await this.page.waitForSelector('.validacion-resultado', { timeout: 10000 });

      const resultado = await this.page.evaluate(() => {
        const elemento = document.querySelector('.validacion-resultado');
        return {
          valido: elemento.classList.contains('valido'),
          mensaje: elemento.textContent
        };
      });

      // Limpiar
      await fs.unlink(tempFile);

      return resultado;

    } catch (error) {
      throw new Error(`Error al validar firma: ${error.message}`);
    } finally {
      await this.cerrar();
    }
  }

  /**
   * Método simplificado para firmar múltiples documentos en batch
   */
  async firmarLote(documentos, pin) {
    const resultados = [];
    
    await this.inicializar();

    for (const doc of documentos) {
      try {
        const firmado = await this.firmarDocumento(doc, pin);
        resultados.push({ 
          success: true, 
          documento: firmado,
          codigoGeneracion: doc.identificacion?.codigoGeneracion 
        });
      } catch (error) {
        resultados.push({ 
          success: false, 
          error: error.message,
          codigoGeneracion: doc.identificacion?.codigoGeneracion 
        });
      }
    }

    await this.cerrar();

    return resultados;
  }
}

module.exports = Firmador;
