const { PDFDocument, degrees, rgb, StandardFonts } = require('pdf-lib');
const QRCode = require('qrcode');
const fs = require('fs').promises;
const path = require('path');
const divisionGeografica = require('../data/division-geografica.json');

/**
 * Generador de PDF para Documentos Tributarios Electrónicos
 * Cumple con los requisitos del Ministerio de Hacienda de El Salvador
 */
class PDFGenerator {
  constructor() {
    this.pageWidth = 612; // Letter width in points
    this.pageHeight = 792; // Letter height in points
    this.margin = 50;
    this.currentY = this.pageHeight - this.margin;
  }

  /**
   * Generar PDF de factura electrónica
   * @param {Object} factura - Datos de la factura
   * @param {Object} dte - DTE generado con toda la información
   * @param {Object} config - Configuración de la empresa
   * @returns {Promise<Buffer>} - PDF en formato Buffer
   */
  async generarPDFFactura(factura, dte, config) {
    try {
      const dteData = this.normalizarDTE(dte);
      
      // Validar que el DTE tiene la estructura necesaria
      if (!dteData.identificacion) {
        console.error('Estructura del DTE recibida:', JSON.stringify(dte, null, 2));
        throw new Error('DTE no tiene la estructura esperada. Falta propiedad "identificacion"');
      }

      const pdfDoc = await PDFDocument.create();
      const page = pdfDoc.addPage([this.pageWidth, this.pageHeight]);
      
      // Cargar fuentes
      const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
      const fontRegular = await pdfDoc.embedFont(StandardFonts.Helvetica);
      const fontSmall = await pdfDoc.embedFont(StandardFonts.Helvetica);

      this.currentY = this.pageHeight - this.margin;

      const logoInfo = await this.drawLogoPDF(pdfDoc, page, config);

      // 1. Título del documento
      await this.drawTitle(page, fontBold, this.getTipoDocumentoNombre(dteData.identificacion.tipoDte));

      // 2. Leyenda legal obligatoria
      this.currentY -= 15;
      page.drawText('DOCUMENTO TRIBUTARIO ELECTRÓNICO', {
        x: this.margin,
        y: this.currentY,
        size: 8,
        font: fontSmall,
        color: rgb(0.3, 0.3, 0.3)
      });

      // 3. Información del emisor (izquierda) y datos DTE (derecha)
      this.currentY -= 30;
      if (logoInfo?.bottomY) {
        this.currentY = Math.min(this.currentY, logoInfo.bottomY - 20);
      }
      await this.drawEmisorYDTE(page, fontBold, fontRegular, config, dteData);

      // 4. Información del receptor
      this.currentY -= 40;
      await this.drawReceptor(page, fontBold, fontRegular, dteData.receptor || dteData.sujetoExcluido);

      // 4.1. Secciones fiscales adicionales
      this.currentY -= 20;
      await this.drawSeccionesFiscalesAdicionales(page, fontBold, fontRegular, dteData);

      // 5. Tabla de productos/servicios
      this.currentY -= 14;
      await this.drawItemsTable(page, fontBold, fontRegular, dteData.cuerpoDocumento);

      // 6. Resumen de totales
      this.currentY -= 20;
      await this.drawResumen(page, fontBold, fontRegular, dteData.resumen);

      // 7. Código QR (obligatorio)
      const qrDataUrl = await this.generarQR(dteData);
      const qrImage = await pdfDoc.embedPng(qrDataUrl);
      const qrSize = 85;
      page.drawImage(qrImage, {
        x: this.margin,
        y: this.margin + 20,
        width: qrSize,
        height: qrSize
      });

      this.drawWatermarks(page, fontBold, dteData, factura, config);

      // Generar PDF
      const pdfBytes = await pdfDoc.save();
      return Buffer.from(pdfBytes);
    } catch (error) {
      console.error('Error generando PDF:', error);
      throw new Error(`Error al generar PDF: ${error.message}`);
    }
  }

  /**
   * Dibujar título del documento
   */
  async drawTitle(page, font, titulo) {
    page.drawText(titulo, {
      x: this.margin,
      y: this.currentY,
      size: 20,
      font: font,
      color: rgb(0, 0, 0)
    });
  }

  async generarPDFConsultaFactura(factura, dteData, config) {
    const pdfDoc = await PDFDocument.create();
    const page = pdfDoc.addPage([this.pageWidth, this.pageHeight]);
    const fontBold = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
    const fontRegular = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const fontSmall = await pdfDoc.embedFont(StandardFonts.Helvetica);

    this.drawPageFrame(page);
    this.drawConsultaHeader(page, fontBold, fontRegular, dteData, factura);
    await this.drawConsultaQr(pdfDoc, page, dteData);
    this.drawConsultaEmisorReceptor(page, fontBold, fontRegular, dteData, config);
    this.drawConsultaSeccionesRelacionadas(page, fontBold, fontRegular, dteData);
    this.drawConsultaItemsYResumen(page, fontBold, fontRegular, dteData);
    this.drawConsultaFooter(page, fontRegular);
    this.drawWatermarks(page, fontBold, dteData, factura, config);

    const pdfBytes = await pdfDoc.save();
    return Buffer.from(pdfBytes);
  }

  drawPageFrame(page) {
    page.drawRectangle({
      x: 20,
      y: 20,
      width: this.pageWidth - 40,
      height: this.pageHeight - 40,
      borderColor: rgb(0, 0, 0),
      borderWidth: 0.8
    });
  }

  drawConsultaHeader(page, fontBold, fontRegular, dte, factura) {
    const centerX = this.pageWidth / 2;

    this.drawCenteredText(page, 'DOCUMENTO DE CONSULTA PORTAL OPERATIVO', centerX, 748, 10, fontRegular);
    this.drawCenteredText(page, 'DOCUMENTO TRIBUTARIO ELECTRÓNICO', centerX, 732, 10, fontRegular);
    this.drawCenteredText(page, 'FACTURA', centerX, 716, 11, fontBold);
    page.drawText('Ver.1', { x: this.pageWidth - 54, y: 756, size: 8, font: fontBold });

    const sello = factura?.sello_recepcion || dte.selloRecibido || dte.respuestaHacienda?.selloRecibido || '';
    this.drawLabelValue(page, 'Código de Generación:', dte.identificacion.codigoGeneracion, 26, 688, 8, fontBold, fontRegular, { valueMaxWidth: 145, maxLines: 1 });
    this.drawLabelValue(page, 'Número de Control :', dte.identificacion.numeroControl, 36, 676, 8, fontBold, fontRegular, { valueMaxWidth: 145, maxLines: 1 });
    this.drawLabelValue(page, 'Sello de Recepción:', sello, 36, 664, 8, fontBold, fontRegular, { valueMaxWidth: 145, maxLines: 1 });

    const modelo = Number(dte.identificacion.tipoModelo) === 1 ? 'Previo' : `Modelo ${dte.identificacion.tipoModelo || ''}`.trim();
    const transmision = Number(dte.identificacion.tipoOperacion) === 1 ? 'Normal' : 'Contingencia';
    this.drawLabelValue(page, 'Modelo de Facturación:', modelo, 430, 688, 8, fontBold, fontRegular, { valueMaxWidth: 95, maxLines: 1 });
    this.drawLabelValue(page, 'Tipo de Transmisión:', transmision, 441, 676, 8, fontBold, fontRegular, { valueMaxWidth: 95, maxLines: 1 });
    this.drawLabelValue(page, 'Fecha y Hora de Generación:', `${dte.identificacion.fecEmi || ''} ${dte.identificacion.horEmi || ''}`.trim(), 414, 664, 8, fontBold, fontRegular, { valueMaxWidth: 75, maxLines: 1 });
  }

  async drawConsultaQr(pdfDoc, page, dte) {
    const qrDataUrl = await this.generarQR(dte);
    const qrImage = await pdfDoc.embedPng(qrDataUrl);
    page.drawImage(qrImage, {
      x: (this.pageWidth - 56) / 2,
      y: 654,
      width: 56,
      height: 56
    });
  }

  drawConsultaEmisorReceptor(page, fontBold, fontRegular, dte, config) {
    const emisor = dte.emisor || {};
    const receptor = dte.receptor || {};
    const emisorBox = { x: 30, y: 505, w: 270, h: 135 };
    const receptorBox = { x: 312, y: 525, w: 270, h: 115 };

    this.drawCenteredText(page, 'EMISOR', emisorBox.x + emisorBox.w / 2, emisorBox.y + emisorBox.h + 5, 8, fontBold);
    this.drawRoundedBox(page, emisorBox.x, emisorBox.y, emisorBox.w, emisorBox.h);
    this.drawCenteredText(page, 'RECEPTOR', receptorBox.x + receptorBox.w / 2, receptorBox.y + receptorBox.h + 5, 8, fontBold);
    this.drawRoundedBox(page, receptorBox.x, receptorBox.y, receptorBox.w, receptorBox.h);

    let y = emisorBox.y + emisorBox.h - 16;
    this.drawLabelValue(page, 'Nombre o razón social:', emisor.nombre || config?.nombre_empresa || '', emisorBox.x + 8, y, 7.5, fontBold, fontRegular, { valueMaxWidth: 170 });
    y -= 18;
    this.drawCenteredLabelValue(page, 'NIT:', emisor.nit, emisorBox.x, emisorBox.w, y, 7.5, fontBold, fontRegular);
    y -= 13;
    this.drawCenteredLabelValue(page, 'NRC:', emisor.nrc, emisorBox.x, emisorBox.w, y, 7.5, fontBold, fontRegular);
    y -= 14;
    this.drawLabelValue(page, 'Actividad económica :', emisor.descActividad || '', emisorBox.x + 8, y, 7.5, fontBold, fontRegular, { valueMaxWidth: 170 });
    y -= 22;
    this.drawLabelValue(page, 'Dirección:', this.formatearDireccion(emisor.direccion), emisorBox.x + 48, y, 7.5, fontBold, fontRegular, { valueMaxWidth: 185 });
    y -= 24;
    this.drawLabelValue(page, 'Número de teléfono:', emisor.telefono || '', emisorBox.x + 24, y, 7.5, fontBold, fontRegular);
    y -= 13;
    this.drawLabelValue(page, 'Correo electrónico:', emisor.correo || '', emisorBox.x + 28, y, 7.5, fontBold, fontRegular);
    this.drawLabelValue(page, 'Tipo de establecimiento:', this.getTipoEstablecimientoNombre(emisor.tipoEstablecimiento), emisorBox.x + 12, emisorBox.y + 6, 7, fontBold, fontRegular);

    y = receptorBox.y + receptorBox.h - 16;
    this.drawLabelValue(page, 'Nombre o razón social:', receptor.nombre || '', receptorBox.x + 8, y, 7.5, fontBold, fontRegular, { valueMaxWidth: 165 });
    y -= 15;
    const etiquetaDocumento = receptor.tipoDocumento === '13' ? 'DUI:' : receptor.nit ? 'NIT:' : 'Documento:';
    this.drawCenteredLabelValue(page, etiquetaDocumento, receptor.numDocumento || receptor.nit || '', receptorBox.x, receptorBox.w, y, 7.5, fontBold, fontRegular);
    y -= 13;
    this.drawCenteredLabelValue(page, 'Correo electrónico:', receptor.correo || '', receptorBox.x, receptorBox.w, y, 7.5, fontBold, fontRegular);
    y -= 13;
    this.drawCenteredLabelValue(page, 'Dirección:', this.formatearDireccion(receptor.direccion), receptorBox.x, receptorBox.w, y, 7.5, fontBold, fontRegular, { maxWidth: receptorBox.w - 20 });
    this.drawLabelValue(page, 'Número de teléfono:', receptor.telefono || '', receptorBox.x + 14, receptorBox.y + 35, 7.5, fontBold, fontRegular);
  }

  drawConsultaSeccionesRelacionadas(page, fontBold, fontRegular, dte) {
    this.drawCenteredText(page, 'VENTA A CUENTA DE TERCEROS', this.pageWidth / 2, 492, 8, fontBold);
    this.drawRoundedBox(page, 25, 458, this.pageWidth - 50, 28);
    this.drawLabelValue(page, 'NIT:', dte.ventaTercero?.nit || '-', 58, 470, 7.5, fontBold, fontRegular);
    this.drawLabelValue(page, 'Nombre, denominación o\nrazón social:', dte.ventaTercero?.nombre || '-', 265, 470, 7.5, fontBold, fontRegular);

    this.drawCenteredText(page, 'DOCUMENTOS RELACIONADOS', this.pageWidth / 2, 445, 8, fontBold);
    this.drawMiniTable(page, fontBold, fontRegular, 26, 428, this.pageWidth - 52, [
      ['Tipo de Documento', 'N° de Documento', 'Fecha de Documento']
    ], this.normalizarDocumentosRelacionados(dte.documentoRelacionado));

    this.drawCenteredText(page, 'OTROS DOCUMENTOS ASOCIADOS', this.pageWidth / 2, 390, 8, fontBold);
    this.drawMiniTable(page, fontBold, fontRegular, 26, 376, this.pageWidth - 52, [
      ['Identificación del documento', 'Descripción']
    ], this.normalizarOtrosDocumentos(dte.otrosDocumentos));
  }

  drawConsultaItemsYResumen(page, fontBold, fontRegular, dte) {
    const x = 26;
    const yTop = 342;
    const widths = [28, 30, 30, 174, 52, 57, 47, 47, 47, 48];
    const headers = ['N°', 'Cantidad', 'Unidad', 'Descripción', 'Precio\nUnitario', 'Otros montos\nno afectos', 'Descuento\npor ítem', 'Ventas No\nSujetas', 'Ventas\nExentas', 'Ventas\nGravadas'];
    const rowH = 26;
    const detailH = 300;

    this.drawTableRow(page, fontBold, x, yTop, widths, headers, rowH, 7, true);
    let y = yTop - rowH;
    const items = Array.isArray(dte.cuerpoDocumento) ? dte.cuerpoDocumento : [];
    items.slice(0, 18).forEach(item => {
      const values = [
        item.numItem || '',
        this.formatQuantity(item.cantidad),
        this.getUnidadNombre(item.uniMedida),
        item.descripcion || '',
        this.formatNumber(item.precioUni),
        this.formatNumber(item.noGravado || 0),
        this.formatNumber(item.montoDescu || 0),
        this.formatNumber(item.ventaNoSuj || 0),
        this.formatNumber(item.ventaExenta || 0),
        this.formatNumber(item.ventaGravada || item.compra || 0)
      ];
      this.drawTableRow(page, fontRegular, x, y, widths, values, 15, 7, false);
      y -= 15;
    });

    page.drawRectangle({
      x,
      y: yTop - detailH,
      width: widths.reduce((sum, w) => sum + w, 0),
      height: detailH,
      borderColor: rgb(0, 0, 0),
      borderWidth: 0.5
    });

    this.drawResumenConsulta(page, fontBold, fontRegular, dte.resumen || {}, x + widths.slice(0, 4).reduce((sum, w) => sum + w, 0), yTop - rowH - 15);
  }

  drawResumenConsulta(page, fontBold, fontRegular, resumen, x, yStart) {
    const labelW = 210;
    const valueW = 70;
    const rowH = 13;
    const rows = [
      { label: 'Suma de Ventas:', values: [resumen.totalNoSuj, resumen.totalExenta, resumen.totalGravada], bold: true, multi: true },
      { label: 'Sumatoria de ventas:', value: resumen.subTotalVentas },
      { label: 'Monto global Desc., Rebajas y otros a ventas no sujetas:', value: resumen.descuNoSuj },
      { label: 'Monto global Desc., Rebajas y otros a ventas Exentas:', value: resumen.descuExenta },
      { label: 'Monto global Desc., Rebajas y otros a ventas gravadas:', value: resumen.descuGravada },
      { label: 'Sub-Total:', value: resumen.subTotal },
      { label: 'IVA Retenido', value: resumen.ivaRete1 },
      { label: 'Monto Total de la Operación:', value: resumen.montoTotalOperacion },
      { label: 'Total Otros Montos No Afectos:', value: resumen.totalNoGravado },
      { label: 'Total a Pagar:', value: resumen.totalPagar, bold: true }
    ];

    let y = yStart;
    rows.forEach(row => {
      const currentLabelW = row.multi ? 130 : labelW;
      const currentValueW = row.multi ? 150 : valueW;
      const totalW = currentLabelW + currentValueW;
      page.drawRectangle({ x, y: y - rowH + 2, width: totalW, height: rowH, borderColor: rgb(0, 0, 0), borderWidth: 0.45 });
      const labelFont = row.bold ? fontBold : fontRegular;
      const safeLabel = this.truncateToWidth(row.label, currentLabelW - 6, labelFont, 7.2);
      page.drawText(safeLabel, { x: x + currentLabelW - labelFont.widthOfTextAtSize(safeLabel, 7.2) - 4, y: y - 8, size: 7.2, font: labelFont });

      if (row.multi) {
        const vals = row.values.map(v => this.formatNumber(v || 0));
        const colW = currentValueW / 3;
        vals.forEach((valor, index) => {
          const safeValor = this.truncateToWidth(valor, colW - 2, row.bold ? fontBold : fontRegular, 7.2);
          page.drawText(safeValor, { x: x + currentLabelW + colW * index + colW - fontRegular.widthOfTextAtSize(safeValor, 7.2) - 2, y: y - 8, size: 7.2, font: row.bold ? fontBold : fontRegular });
        });
      } else {
        const valor = this.formatNumber(row.value || 0);
        page.drawText(valor, { x: x + currentLabelW + currentValueW - fontRegular.widthOfTextAtSize(valor, 7.2) - 3, y: y - 8, size: 7.2, font: row.bold ? fontBold : fontRegular });
      }
      y -= rowH;
    });
  }

  drawConsultaFooter(page, fontRegular) {
    page.drawText('Página 1 de 1', {
      x: this.pageWidth - 78,
      y: 28,
      size: 7.5,
      font: fontRegular
    });
  }

  async drawLogoPDF(pdfDoc, page, config = {}) {
    const logoPath = String(config.logo_path || '').trim();
    if (!logoPath) return;

    try {
      const imageBytes = await fs.readFile(logoPath);
      const extension = path.extname(logoPath).toLowerCase();
      let image;

      if (extension === '.png') {
        image = await pdfDoc.embedPng(imageBytes);
      } else if (['.jpg', '.jpeg'].includes(extension)) {
        image = await pdfDoc.embedJpg(imageBytes);
      } else {
        console.warn(`Imagen para PDF no soportada: ${logoPath}`);
        return;
      }

      const maxWidth = 185;
      const maxHeight = 115;
      const scale = Math.min(maxWidth / image.width, maxHeight / image.height, 1);
      const width = image.width * scale;
      const height = image.height * scale;
      const x = this.pageWidth - this.margin - width;
      const y = this.pageHeight - this.margin - height + 4;

      page.drawImage(image, {
        x,
        y,
        width,
        height
      });

      return { width, height, x, y, bottomY: y };
    } catch (error) {
      console.warn(`No se pudo cargar la imagen del PDF (${logoPath}): ${error.message}`);
    }

    return null;
  }

  normalizarDTE(dte) {
    if (typeof dte === 'string') {
      dte = JSON.parse(dte);
    }

    const candidatos = [
      dte,
      dte?.dteJson,
      dte?.dte,
      dte?.documentoFirmado,
      dte?.documento
    ];

    for (let candidato of candidatos) {
      if (typeof candidato === 'string') {
        try {
          candidato = JSON.parse(candidato);
        } catch {
          continue;
        }
      }

      if (candidato?.identificacion) {
        return candidato;
      }
    }

    return dte || {};
  }

  esDocumentoPrueba(dte, config = {}) {
    const ambienteDte = String(dte?.identificacion?.ambiente || '').toLowerCase();
    const ambienteConfig = String(config?.hacienda_ambiente || '').toLowerCase();
    return ambienteDte === '00' || ambienteDte === 'pruebas' || ambienteConfig === 'pruebas';
  }

  drawWatermarks(page, font, dte, factura, config) {
    if (this.esDocumentoPrueba(dte, config)) {
      this.drawCenteredWatermark(page, font, 'DOCUMENTO DE PRUEBA', {
        size: 54,
        yOffset: this.esDocumentoAnulado(factura) ? 95 : 0,
        color: rgb(0.45, 0.45, 0.45),
        opacity: 0.36
      });
    }

    if (this.esDocumentoAnulado(factura)) {
      this.drawCenteredWatermark(page, font, 'ANULADO', {
        size: 96,
        yOffset: -20,
        color: rgb(0.7, 0, 0),
        opacity: 0.58
      });
    }
  }

  drawCenteredWatermark(page, font, texto, opciones = {}) {
    const size = opciones.size || 62;
    const textWidth = font.widthOfTextAtSize(texto, size);
    const textHeight = font.heightAtSize(size);
    const pageWidth = page.getWidth();
    const pageHeight = page.getHeight();
    const angle = opciones.rotate ?? 35;
    const radians = angle * Math.PI / 180;
    const cos = Math.cos(radians);
    const sin = Math.sin(radians);
    const centerX = pageWidth / 2;
    const centerY = (pageHeight / 2) + (opciones.yOffset || 0);
    const rotatedCenterOffsetX = (textWidth * cos - textHeight * sin) / 2;
    const rotatedCenterOffsetY = (textWidth * sin + textHeight * cos) / 2;

    page.drawText(texto, {
      x: centerX - rotatedCenterOffsetX,
      y: centerY - rotatedCenterOffsetY,
      size,
      font,
      color: opciones.color || rgb(0.45, 0.45, 0.45),
      opacity: opciones.opacity ?? 0.34,
      rotate: degrees(angle)
    });
  }

  esDocumentoAnulado(factura = {}) {
    const estado = String(factura?.estado || '').toUpperCase();
    return ['ANULADO', 'INVALIDADO', 'ANULADA', 'INVALIDADA'].includes(estado) ||
      Boolean(factura?.sello_anulacion) ||
      Boolean(factura?.json_anulacion) ||
      Boolean(factura?.fecha_anulacion) ||
      Boolean(factura?.motivo_anulacion);
  }

  /**
   * Dibujar información del emisor y datos del DTE
   */
  async drawEmisorYDTE(page, fontBold, fontRegular, config, dte) {
    const leftX = this.margin;
    const rightX = this.pageWidth - this.margin - 200;
    let leftY = this.currentY;
    let rightY = this.currentY;

    // Emisor (izquierda)
    page.drawText('EMISOR', { x: leftX, y: leftY, size: 12, font: fontBold });
    leftY -= 15;
    
    page.drawText(config.nombre_empresa || dte.emisor.nombre, { 
      x: leftX, y: leftY, size: 10, font: fontBold 
    });
    leftY -= 12;
    
    if (config.nombre_comercial) {
      page.drawText(config.nombre_comercial, { 
        x: leftX, y: leftY, size: 9, font: fontRegular 
      });
      leftY -= 12;
    }
    
    page.drawText(`NIT: ${dte.emisor.nit}`, { 
      x: leftX, y: leftY, size: 9, font: fontRegular 
    });
    leftY -= 12;
    
    page.drawText(`NRC: ${dte.emisor.nrc}`, { 
      x: leftX, y: leftY, size: 9, font: fontRegular 
    });
    leftY -= 12;
    
    page.drawText(`Actividad económica: ${dte.emisor.descActividad}`, { 
      x: leftX, y: leftY, size: 8, font: fontRegular 
    });
    leftY -= 12;
    
    const direccionEmisor = `${dte.emisor.direccion.complemento}`;
    page.drawText(this.wrapText(direccionEmisor, 40), { 
      x: leftX, y: leftY, size: 8, font: fontRegular 
    });
    leftY -= 12;
    
    page.drawText(`Número de teléfono: ${dte.emisor.telefono}`, { 
      x: leftX, y: leftY, size: 8, font: fontRegular 
    });
    leftY -= 12;

    page.drawText(`Correo electrónico: ${dte.emisor.correo || ''}`, {
      x: leftX, y: leftY, size: 8, font: fontRegular
    });
    leftY -= 12;

    page.drawText(`Tipo de establecimiento: ${this.getTipoEstablecimientoNombre(dte.emisor.tipoEstablecimiento)}`, {
      x: leftX, y: leftY, size: 8, font: fontRegular
    });

    // Datos del DTE (derecha)
    page.drawText('DATOS DEL DOCUMENTO', { x: rightX, y: rightY, size: 10, font: fontBold });
    rightY -= 15;
    
    page.drawText(`Número de Control:`, { x: rightX, y: rightY, size: 8, font: fontBold });
    rightY -= 10;
    page.drawText(dte.identificacion.numeroControl, { x: rightX, y: rightY, size: 8, font: fontRegular });
    rightY -= 15;
    
    page.drawText(`Código de Generación:`, { x: rightX, y: rightY, size: 8, font: fontBold });
    rightY -= 10;
    page.drawText(this.formatCodigoGeneracion(dte.identificacion.codigoGeneracion), { 
      x: rightX, y: rightY, size: 7, font: fontRegular 
    });
    rightY -= 15;

    const sello = dte.selloRecibido || dte.respuestaHacienda?.selloRecibido;
    if (sello) {
      page.drawText(`Sello de Recepción:`, { x: rightX, y: rightY, size: 8, font: fontBold });
      rightY -= 10;
      page.drawText(this.truncateText(sello, 42), {
        x: rightX, y: rightY, size: 6, font: fontRegular
      });
      rightY -= 15;
    }
    
    page.drawText(`Fecha y Hora de Generación:`, { x: rightX, y: rightY, size: 8, font: fontBold });
    rightY -= 10;
    page.drawText(`${dte.identificacion.fecEmi} ${dte.identificacion.horEmi}`, { 
      x: rightX, y: rightY, size: 8, font: fontRegular 
    });
    rightY -= 15;
    
    page.drawText(`Modelo de Facturación:`, { x: rightX, y: rightY, size: 8, font: fontBold });
    rightY -= 10;
    page.drawText(Number(dte.identificacion.tipoModelo) === 1 ? 'Previo' : `Modelo ${dte.identificacion.tipoModelo}`, { 
      x: rightX, y: rightY, size: 8, font: fontRegular 
    });
    rightY -= 15;
    
    page.drawText(`Tipo de Transmisión:`, { x: rightX, y: rightY, size: 8, font: fontBold });
    rightY -= 10;
    page.drawText(dte.identificacion.tipoOperacion === 1 ? 'Normal' : 'Contingencia', { 
      x: rightX, y: rightY, size: 8, font: fontRegular 
    });

    this.currentY = Math.min(leftY, rightY);
  }

  /**
   * Dibujar información del receptor
   */
  async drawReceptor(page, fontBold, fontRegular, receptor) {
    const boxY = this.currentY;
    const boxHeight = 82;
    
    // Fondo gris claro
    page.drawRectangle({
      x: this.margin,
      y: boxY - boxHeight,
      width: this.pageWidth - 2 * this.margin,
      height: boxHeight,
      color: rgb(0.95, 0.95, 0.95)
    });

    let y = boxY - 14;
    const leftX = this.margin + 10;
    const rightX = this.margin + 270;
    
    page.drawText('RECEPTOR', { 
      x: leftX, y, size: 11, font: fontBold 
    });
    y -= 15;
    
    page.drawText(`Nombre: ${this.truncateText(receptor.nombre || '', 45)}`, { 
      x: leftX, y, size: 8.5, font: fontRegular 
    });
    
    if (receptor.nit) {
      page.drawText(`NIT: ${receptor.nit}`, { 
        x: rightX, y, size: 8.5, font: fontRegular 
      });
    }
    
    if (receptor.numDocumento) {
      const etiqueta = receptor.tipoDocumento === '13' ? 'DUI' : 'Documento';
      page.drawText(`${etiqueta}: ${receptor.numDocumento}`, { 
        x: rightX, y, size: 8.5, font: fontRegular 
      });
    }

    y -= 13;

    if (receptor.correo) {
    page.drawText(`Correo electrónico: ${receptor.correo}`, {
        x: leftX, y, size: 8, font: fontRegular
      });
    }

    if (receptor.telefono) {
      page.drawText(`Teléfono: ${receptor.telefono}`, {
        x: rightX, y, size: 8, font: fontRegular
      });
    }
    y -= 13;
    
    if (receptor.direccion) {
      page.drawText(`Dirección: ${this.truncateText(this.formatearDireccion(receptor.direccion), 100)}`, { 
        x: leftX, y, size: 8, font: fontRegular 
      });
    }

    this.currentY = boxY - boxHeight;
  }

  async drawSeccionesFiscalesAdicionales(page, fontBold, fontRegular, dte) {
    const ventaTercero = dte.ventaTercero || {};
    const tieneVentaTercero = ventaTercero.nit || ventaTercero.nombre;
    const documentosRelacionados = this.normalizarDocumentosRelacionados(dte.documentoRelacionado);
    const otrosDocumentos = this.normalizarOtrosDocumentos(dte.otrosDocumentos);
    const boxWidth = this.pageWidth - 2 * this.margin;
    let y = this.currentY;
    const nombreTercero = tieneVentaTercero ? (ventaTercero.nombre || '-') : '-';
    const nitTercero = tieneVentaTercero ? (ventaTercero.nit || '-') : '-';
    const docRelacionado = documentosRelacionados[0] || ['-', '-', '-'];
    const otroDocumento = otrosDocumentos[0] || ['-', '-'];

    page.drawText('Venta a Cuenta de Terceros:', { x: this.margin, y, size: 8, font: fontBold });
    page.drawText(`NIT ${nitTercero} | ${this.truncateText(nombreTercero, 58)}`, {
      x: this.margin + 120,
      y,
      size: 7.5,
      font: fontRegular
    });
    y -= 12;

    page.drawText('Documento Relacionado:', { x: this.margin, y, size: 8, font: fontBold });
    page.drawText(this.truncateText(`${docRelacionado[0]} | ${docRelacionado[1]} | ${docRelacionado[2]}`, 80), {
      x: this.margin + 120,
      y,
      size: 7.5,
      font: fontRegular
    });
    y -= 12;

    page.drawText('Otro Documento Asociado:', { x: this.margin, y, size: 8, font: fontBold });
    page.drawText(this.truncateText(`${otroDocumento[0]} | ${otroDocumento[1]}`, 80), {
      x: this.margin + 120,
      y,
      size: 7.5,
      font: fontRegular
    });

    this.currentY = y - 8;
  }

  /**
   * Dibujar tabla de items
   */
  async drawItemsTable(page, fontBold, fontRegular, items) {
    items = Array.isArray(items) ? items : [];
    const tableWidth = this.pageWidth - 2 * this.margin;
    const colWidths = [24, 36, 38, 126, 58, 48, 42, 46, 46, 48];
    
    // Encabezado
    let x = this.margin;
    let y = this.currentY;
    
    // Fondo del encabezado
    page.drawRectangle({
      x: this.margin,
      y: y - 15,
      width: tableWidth,
      height: 15,
      color: rgb(0.2, 0.2, 0.2)
    });
    
    y -= 11;
    const headers = ['N°', 'Cantidad', 'Unidad', 'Descripción', 'Precio Unitario', 'Otros montos no afectos', 'Descuento por ítem', 'Ventas No Sujetas', 'Ventas Exentas', 'Ventas Gravadas'];
    headers.forEach((header, i) => {
      const label = this.truncateToWidth(header, colWidths[i] - 6, fontBold, 7);
      page.drawText(label, {
        x: x + 3,
        y: y,
        size: 7,
        font: fontBold,
        color: rgb(1, 1, 1)
      });
      x += colWidths[i];
    });
    
    y -= 15;
    
    // Items
    items.forEach((item, index) => {
      if (y < this.margin + 150) {
        // Nueva página si no hay espacio
        // TODO: Implementar paginación
        return;
      }
      
      // Fondo alternado
      if (index % 2 === 0) {
        page.drawRectangle({
          x: this.margin,
          y: y - 12,
          width: tableWidth,
          height: 12,
          color: rgb(0.98, 0.98, 0.98)
        });
      }
      
      x = this.margin;
      const cantidad = this.toMoneyNumber(item.cantidad);
      const montoDescu = this.toMoneyNumber(item.montoDescu ?? item.descuento);
      const ventaGravada = this.toMoneyNumber(item.ventaGravada);
      const ventaExenta = this.toMoneyNumber(item.ventaExenta);
      const compra = this.toMoneyNumber(item.compra);
      const ivaRetenido = this.toMoneyNumber(item.ivaRetenido);
      const montoSujetoGrav = this.toMoneyNumber(item.montoSujetoGrav);
      const esRetencion = montoSujetoGrav > 0 || ivaRetenido > 0;
      const precioUni = this.obtenerPrecioUnitarioPDF(item, cantidad);
      
      const values = [
        item.numItem || index + 1,
        esRetencion ? '-' : this.formatQuantity(cantidad),
        this.getUnidadNombre(item.uniMedida),
        this.truncateText(String(item.descripcion || ''), 26),
        esRetencion ? '-' : this.formatNumber(precioUni),
        this.formatNumber(item.noGravado || 0),
        this.formatNumber(montoDescu),
        this.formatNumber(item.ventaNoSuj || 0),
        this.formatNumber(ventaExenta),
        this.formatNumber(ventaGravada || compra || montoSujetoGrav || ivaRetenido)
      ];

      values.forEach((value, i) => {
        const text = this.truncateText(String(value), i === 3 ? 24 : 10);
        const rightAligned = i >= 4;
        const textWidth = fontRegular.widthOfTextAtSize(text, 7);
        page.drawText(text, {
          x: rightAligned ? x + colWidths[i] - textWidth - 4 : x + 3,
          y: y - 8,
          size: 7,
          font: fontRegular
        });
        x += colWidths[i];
      });
      
      y -= 12;
    });
    
    // Línea de cierre
    page.drawLine({
      start: { x: this.margin, y: y },
      end: { x: this.pageWidth - this.margin, y: y },
      thickness: 1,
      color: rgb(0, 0, 0)
    });
    
    this.currentY = y - 5;
  }

  obtenerPrecioUnitarioPDF(item, cantidad) {
    const cantidadValida = this.toMoneyNumber(cantidad);
    const ventaGravada = this.toMoneyNumber(item.ventaGravada);
    const ventaExenta = this.toMoneyNumber(item.ventaExenta);
    const ventaNoSuj = this.toMoneyNumber(item.ventaNoSuj);
    const noGravado = this.toMoneyNumber(item.noGravado);
    const compra = this.toMoneyNumber(item.compra);
    const montoSujetoGrav = this.toMoneyNumber(item.montoSujetoGrav);
    const montoDescu = this.toMoneyNumber(item.montoDescu ?? item.descuento);
    const totalLinea = ventaGravada || ventaExenta || ventaNoSuj || noGravado || compra || montoSujetoGrav;

    if (cantidadValida > 0 && totalLinea > 0) {
      return (totalLinea + montoDescu) / cantidadValida;
    }

    return this.toMoneyNumber(item.precioUni ?? item.precio_unitario);
  }

  /**
   * Dibujar resumen de totales
   */
  async drawResumen(page, fontBold, fontRegular, resumen) {
    resumen = resumen || {};
    const rightX = this.pageWidth - this.margin - 150;
    let y = this.currentY;
    
    const totales = [
      { label: 'Suma Ventas No Sujetas:', valor: resumen.totalNoSuj || 0 },
      { label: 'Suma Ventas Exentas:', valor: resumen.totalExenta || 0 },
      { label: 'Suma Ventas Gravadas:', valor: resumen.totalGravada || 0 },
      { label: 'Sumatoria de ventas:', valor: resumen.subTotalVentas || resumen.subTotal || 0 },
      { label: 'Descuentos No Sujetos:', valor: resumen.descuNoSuj || 0 },
      { label: 'Descuentos Exentos:', valor: resumen.descuExenta || 0 },
      { label: 'Descuentos Gravados:', valor: resumen.descuGravada || 0 },
      { label: 'Descuentos:', valor: resumen.totalDescu || 0 },
      { label: 'Subtotal:', valor: resumen.subTotal, bold: true },
      { label: 'IVA Retenido:', valor: resumen.ivaRete1 || 0 },
      { label: 'Total IVA Retenido:', valor: resumen.totalIVAretenido || 0 },
      { label: 'Retención Renta:', valor: resumen.reteRenta || 0 },
      { label: 'Monto Total Operación:', valor: resumen.montoTotalOperacion || 0 },
      { label: 'Otros Montos No Afectos:', valor: resumen.totalNoGravado || 0 },
      { label: 'TOTAL A PAGAR:', valor: resumen.totalPagar ?? resumen.totalIVAretenido ?? resumen.montoTotalOperacion ?? 0, bold: true, large: true }
    ];
    
    totales.forEach(item => {
      const valor = this.toMoneyNumber(item.valor);
      if (valor === 0 && !item.bold) return; // Omitir líneas en cero
      
      const font = item.bold ? fontBold : fontRegular;
      const size = item.large ? 12 : 9;
      
      page.drawText(item.label, {
        x: rightX,
        y: y,
        size: size,
        font: font
      });
      
      page.drawText(this.formatMoney(valor), {
        x: rightX + 120,
        y: y,
        size: size,
        font: font
      });
      
      y -= item.large ? 18 : 13;
    });
    
    this.currentY = y - 10;
  }

  /**
   * Generar código QR
   */
  async generarQR(dte, ambiente) {
    const valorAmbiente = String(ambiente || dte?.identificacion?.ambiente || '').toLowerCase();
    const ambienteCode = valorAmbiente === 'produccion' || valorAmbiente === '01' ? '01' : '00';
    const codigoGeneracion = encodeURIComponent(dte.identificacion.codigoGeneracion || '');
    const fechaEmision = encodeURIComponent(dte.identificacion.fecEmi || '');
    const url = `https://admin.factura.gob.sv/consultaPublica?ambiente=${ambienteCode}&codGen=${codigoGeneracion}&fechaEmi=${fechaEmision}`;
    
    try {
      const qrDataUrl = await QRCode.toDataURL(url, {
        width: 300,
        margin: 1,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        }
      });
      
      return qrDataUrl;
    } catch (error) {
      console.error('Error generando QR:', error);
      throw error;
    }
  }

  getAmbienteNombre(ambiente) {
    const valor = String(ambiente || '').toLowerCase();
    return valor === 'produccion' || valor === '01' ? 'Producción' : 'Pruebas';
  }

  /**
   * Dibujar información adicional junto al QR
   */
  drawInfoAdicional(page, font, dte, factura, qrSize) {
    const qrInfoX = this.margin + qrSize + 20;
    let y = this.margin + 110;
    
    page.drawText('Escanee para validar en línea', {
      x: qrInfoX,
      y: y,
      size: 8,
      font: font,
      color: rgb(0.3, 0.3, 0.3)
    });
    y -= 12;
    
    page.drawText('www.factura.gob.sv', {
      x: qrInfoX,
      y: y,
      size: 8,
      font: font,
      color: rgb(0, 0, 0.8)
    });
    y -= 20;
    
    page.drawText(`Ambiente: ${this.getAmbienteNombre(dte.identificacion.ambiente)}`, {
      x: qrInfoX,
      y: y,
      size: 7,
      font: font
    });
    y -= 12;
    
    page.drawText(`Versión: ${dte.identificacion.version}`, {
      x: qrInfoX,
      y: y,
      size: 7,
      font: font
    });

    const nota = this.obtenerNotaApendice(dte);
    if (nota) {
      y -= 18;
      page.drawText('Notas:', {
        x: qrInfoX,
        y,
        size: 7,
        font
      });
      y -= 10;
      page.drawText(this.wrapText(nota, 48), {
        x: qrInfoX,
        y,
        size: 7,
        font
      });
    }
  }

  obtenerNotaApendice(dte = {}) {
    const nota = Array.isArray(dte.apendice)
      ? dte.apendice.find(item => item.campo === 'notas' || item.etiqueta === 'Notas del documento')
      : null;
    return nota?.valor || '';
  }

  /**
   * Dibujar sello de recepción del MH
   */
  drawSelloRecepcion(page, font, sello) {
    const y = this.margin + 5;
    
    page.drawText('SELLO DE RECEPCIÓN MH:', {
      x: this.margin,
      y: y,
      size: 7,
      font: font,
      color: rgb(0, 0.5, 0)
    });
    
    page.drawText(this.truncateText(sello, 80), {
      x: this.margin + 130,
      y: y,
      size: 6,
      font: font,
      color: rgb(0, 0.5, 0)
    });
  }

  drawCenteredText(page, text, centerX, y, size, font, options = {}) {
    const texto = String(text || '');
    const width = font.widthOfTextAtSize(texto, size);
    page.drawText(texto, {
      x: centerX - width / 2,
      y,
      size,
      font,
      color: options.color || rgb(0, 0, 0)
    });
  }

  drawRoundedBox(page, x, y, width, height) {
    page.drawRectangle({
      x,
      y,
      width,
      height,
      borderColor: rgb(0, 0, 0),
      borderWidth: 0.6
    });
  }

  drawLabelValue(page, label, value, x, y, size, fontBold, fontRegular, options = {}) {
    const labelLines = String(label || '').split('\n');
    let labelY = y;
    labelLines.forEach(line => {
      page.drawText(line, { x, y: labelY, size, font: fontBold });
      labelY -= size + 2;
    });

    const labelWidth = Math.max(...labelLines.map(line => fontBold.widthOfTextAtSize(line, size)));
    const valueX = x + (options.labelWidth || labelWidth + 5);
    const maxWidth = options.valueMaxWidth || 220;
    const lines = this.wrapTextLines(String(value ?? ''), maxWidth, fontRegular, size, options.maxLines || 2);
    lines.forEach((line, index) => {
      page.drawText(line, { x: valueX, y: y - index * (size + 2), size, font: fontRegular });
    });
  }

  drawCenteredLabelValue(page, label, value, boxX, boxW, y, size, fontBold, fontRegular, options = {}) {
    const maxWidth = options.maxWidth || boxW - 16;
    const safeValue = this.truncateToWidth(String(value || ''), Math.max(20, maxWidth - fontBold.widthOfTextAtSize(label, size) - 4), fontRegular, size);
    const labelWidth = fontBold.widthOfTextAtSize(label, size);
    const valueWidth = fontRegular.widthOfTextAtSize(safeValue, size);
    const startX = boxX + (boxW - labelWidth - valueWidth - 4) / 2;
    page.drawText(label, { x: startX, y, size, font: fontBold });
    page.drawText(safeValue, { x: startX + labelWidth + 4, y, size, font: fontRegular });
  }

  drawMiniTable(page, fontBold, fontRegular, x, y, width, headers, rows) {
    const columns = headers[0].length;
    const colW = width / columns;
    const headerH = 14;
    const rowH = 13;

    this.drawTableRow(page, fontBold, x, y, Array(columns).fill(colW), headers[0], headerH, 7.2, true);
    const dataRows = rows.length ? rows : [Array(columns).fill('-')];
    dataRows.slice(0, 2).forEach((row, index) => {
      this.drawTableRow(page, fontRegular, x, y - headerH - (index * rowH), Array(columns).fill(colW), row, rowH, 7, false);
    });
  }

  drawTableRow(page, font, x, yTop, widths, values, height, size, header = false) {
    let xCursor = x;
    widths.forEach((width, index) => {
      page.drawRectangle({
        x: xCursor,
        y: yTop - height,
        width,
        height,
        borderColor: rgb(0, 0, 0),
        borderWidth: 0.45
      });

      const text = String(values[index] ?? '');
      const lines = text.split('\n');
      lines.slice(0, 2).forEach((line, lineIndex) => {
        const safeLine = this.truncateToWidth(line, width - 6, font, size);
        const textWidth = font.widthOfTextAtSize(safeLine, size);
        const centered = header || index < 3 || index >= 4;
        const textX = centered ? xCursor + (width - textWidth) / 2 : xCursor + 3;
        page.drawText(safeLine, {
          x: Math.max(xCursor + 2, textX),
          y: yTop - 9 - (lineIndex * (size + 1)),
          size,
          font
        });
      });

      xCursor += width;
    });
  }

  drawTextTableLine(page, fontBold, fontRegular, y, values, width, header = false) {
    const columns = values.length;
    const colWidth = width / columns;
    const font = header ? fontBold : fontRegular;
    let x = this.margin;

    values.forEach(value => {
      const text = this.truncateToWidth(String(value ?? ''), colWidth - 8, font, 7);
      page.drawText(text, {
        x: x + 4,
        y,
        size: 7,
        font
      });
      x += colWidth;
    });
  }

  wrapTextLines(text, maxWidth, font, size, maxLines = 2) {
    const words = String(text || '').split(/\s+/).filter(Boolean);
    const lines = [];
    let current = '';

    words.forEach(word => {
      const next = current ? `${current} ${word}` : word;
      if (font.widthOfTextAtSize(next, size) <= maxWidth) {
        current = next;
        return;
      }
      if (current) lines.push(current);
      current = word;
    });
    if (current) lines.push(current);

    const limited = lines.slice(0, maxLines).map(line => this.truncateToWidth(line, maxWidth, font, size));
    if (lines.length > maxLines && limited.length) {
      limited[limited.length - 1] = this.truncateToWidth(`${limited[limited.length - 1]}...`, maxWidth, font, size);
    }
    return limited.length ? limited : ['-'];
  }

  truncateToWidth(text, maxWidth, font, size) {
    let result = String(text || '');
    if (font.widthOfTextAtSize(result, size) <= maxWidth) return result;
    while (result.length > 0 && font.widthOfTextAtSize(`${result}...`, size) > maxWidth) {
      result = result.slice(0, -1);
    }
    return `${result}...`;
  }

  normalizarDocumentosRelacionados(documentos) {
    const lista = Array.isArray(documentos) ? documentos : (documentos ? [documentos] : []);
    return lista.map(doc => [
      doc.tipoDocumento || doc.tipoDte || '-',
      doc.numeroDocumento || doc.codigoGeneracion || '-',
      doc.fechaEmision || doc.fecEmi || '-'
    ]);
  }

  normalizarOtrosDocumentos(documentos) {
    const lista = Array.isArray(documentos) ? documentos : (documentos ? [documentos] : []);
    return lista.map(doc => [
      doc.codDocAsociado || doc.identificacionDocumento || doc.identificacion || '-',
      doc.descDocumento || doc.descripcion || doc.detalleDocumento || '-'
    ]);
  }

  formatearDireccion(direccion = {}) {
    if (!direccion) return '';
    return [
      direccion.complemento,
      this.getMunicipioNombre(direccion.departamento, direccion.municipio),
      this.getDepartamentoNombre(direccion.departamento)
    ].filter(Boolean).join(', ');
  }

  getDepartamentoNombre(codigo) {
    const departamentos = {
      '01': 'AHUACHAPÁN',
      '02': 'SANTA ANA',
      '03': 'SONSONATE',
      '04': 'CHALATENANGO',
      '05': 'LA LIBERTAD',
      '06': 'SAN SALVADOR',
      '07': 'CUSCATLÁN',
      '08': 'LA PAZ',
      '09': 'CABAÑAS',
      '10': 'SAN VICENTE',
      '11': 'USULUTÁN',
      '12': 'SAN MIGUEL',
      '13': 'MORAZÁN',
      '14': 'LA UNIÓN'
    };
    return departamentos[String(codigo || '').padStart(2, '0')] || '';
  }

  getMunicipioNombre(departamento, municipio) {
    if (!departamento || !municipio) return '';
    const depto = String(departamento).padStart(2, '0');
    const muni = String(municipio).padStart(2, '0');
    const codigoCompleto = `${depto}${muni}`;
    const municipioCatalogo = (divisionGeografica.municipios?.[depto] || [])
      .find(item => item.codigo === codigoCompleto || item.codigo?.slice(2) === muni);
    if (municipioCatalogo?.nombre) return municipioCatalogo.nombre.toUpperCase();

    const legacy = {
      '0607': 'APOPA',
      '0622': 'APOPA'
    };
    return legacy[codigoCompleto] || '';
  }

  getTipoEstablecimientoNombre(tipo) {
    const tipos = {
      '01': 'Casa matriz',
      '02': 'Sucursal / Agencia',
      '04': 'Bodega',
      '07': 'Predio'
    };
    return tipos[String(tipo || '').padStart(2, '0')] || tipo || '';
  }

  getUnidadNombre(codigo) {
    const unidades = {
      59: 'Unidad',
      11: 'Kilogramo',
      14: 'Gramo',
      22: 'Metro',
      23: 'Metro cuadrado',
      26: 'Metro cúbico',
      29: 'Litro',
      58: 'Docena',
      99: 'Otra'
    };
    return unidades[Number(codigo)] || String(codigo || '');
  }

  formatNumber(valor) {
    return this.toMoneyNumber(valor).toFixed(2);
  }

  formatQuantity(valor) {
    return this.toMoneyNumber(valor).toFixed(2);
  }

  // Utilidades

  getTipoDocumentoNombre(tipo) {
    const tipos = {
      '01': 'FACTURA',
      '03': 'COMPROBANTE DE CRÉDITO FISCAL',
      '05': 'NOTA DE CRÉDITO',
      '06': 'NOTA DE DÉBITO',
      '07': 'COMPROBANTE DE RETENCIÓN',
      '11': 'FACTURA DE EXPORTACIÓN',
      '14': 'FACTURA SUJETO EXCLUIDO'
    };
    return tipos[tipo] || 'DOCUMENTO TRIBUTARIO ELECTRÓNICO';
  }

  toMoneyNumber(valor) {
    const numero = Number(valor);
    return Number.isFinite(numero) ? numero : 0;
  }

  formatMoney(valor) {
    return `$${this.toMoneyNumber(valor).toFixed(2)}`;
  }

  formatCodigoGeneracion(codigo) {
    // Formato: XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
    return codigo;
  }

  wrapText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength - 3) + '...';
  }

  truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength - 3) + '...';
  }

  /**
   * Guardar PDF en archivo
   */
  async guardarPDF(pdfBuffer, rutaDestino) {
    await fs.writeFile(rutaDestino, pdfBuffer);
    return rutaDestino;
  }
}

module.exports = PDFGenerator;
