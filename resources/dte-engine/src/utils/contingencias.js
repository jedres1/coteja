/**
 * Módulo de gestión de contingencias para DTEs
 * Según especificación del Ministerio de Hacienda de El Salvador
 */

class ContingenciaManager {
  constructor(db) {
    this.db = db;
  }

  /**
   * Registrar DTE en contingencia
   * Tipo de contingencia:
   * 1 = No hay conexión a Internet
   * 2 = Sistema del MH no disponible
   * 3 = Error en transmisión
   * 4 = Error en el sistema local
   * 5 = Otro
   */
  async registrarContingencia(facturaId, tipoContingencia, motivo) {
    try {
      const now = new Date();
      const tipo = this.normalizarTipoContingencia(tipoContingencia);
      const motivoNormalizado = this.normalizarMotivoContingencia(motivo, tipo);
      const existente = this.db.prepare(`
        SELECT id FROM contingencias
        WHERE factura_id = ? AND estado = 'PENDIENTE'
        ORDER BY id DESC
        LIMIT 1
      `).get(facturaId);

      if (existente) {
        this.db.prepare(`
          UPDATE contingencias
          SET tipo_contingencia = ?,
              motivo = ?,
              fecha_contingencia = ?
          WHERE id = ?
        `).run(String(tipo), motivoNormalizado, now.toISOString(), existente.id);

        return {
          success: true,
          contingenciaId: existente.id
        };
      }
      
      const campos = ['factura_id', 'tipo_contingencia', 'motivo', 'fecha_contingencia', 'estado'];
      const valores = [facturaId, String(tipo), motivoNormalizado, now.toISOString(), 'PENDIENTE'];

      const stmt = this.db.prepare(`
        INSERT INTO contingencias (${campos.join(', ')})
        VALUES (${campos.map(() => '?').join(', ')})
      `);
      
      const result = stmt.run(...valores);
      
      return {
        success: true,
        contingenciaId: result.lastInsertRowid
      };
    } catch (error) {
      console.error('Error registrando contingencia:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Obtener DTEs pendientes de transmisión por contingencia
   */
  async obtenerDTEsPendientes() {
    try {
      const stmt = this.db.prepare(`
        SELECT 
          c.id as contingencia_id,
          c.factura_id,
          c.tipo_contingencia,
          c.motivo,
          c.fecha_contingencia,
          c.intentos_reenvio,
          f.*
        FROM contingencias c
        INNER JOIN facturas f ON c.factura_id = f.id
        WHERE c.estado = 'PENDIENTE'
        ORDER BY c.fecha_contingencia ASC
      `);
      
      const pendientes = stmt.all();
      
      return {
        success: true,
        pendientes: pendientes
      };
    } catch (error) {
      console.error('Error obteniendo DTEs pendientes:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Intentar reenviar DTEs en contingencia
   */
  async reintentarEnvio(contingenciaId) {
    try {
      // Incrementar contador de intentos
      const updateStmt = this.db.prepare(`
        UPDATE contingencias 
        SET intentos_reenvio = intentos_reenvio + 1,
            ultimo_intento = ?
        WHERE id = ?
      `);
      
      updateStmt.run(new Date().toISOString(), contingenciaId);
      
      return { success: true };
    } catch (error) {
      console.error('Error actualizando intento:', error);
      return { success: false, error: error.message };
    }
  }

  /**
   * Marcar contingencia como resuelta
   */
  async resolverContingencia(contingenciaId, selloRecepcion = null, datos = {}) {
    try {
      const stmt = this.db.prepare(`
        UPDATE contingencias 
        SET estado = 'RESUELTO',
            fecha_resolucion = ?,
            sello_resolucion = ?,
            numero_validacion = ?,
            json_evento = ?
        WHERE id = ?
      `);
      
      stmt.run(
        new Date().toISOString(),
        selloRecepcion,
        datos.numeroValidacion || null,
        datos.jsonEvento
          ? (typeof datos.jsonEvento === 'string' ? datos.jsonEvento : JSON.stringify(datos.jsonEvento))
          : null,
        contingenciaId
      );
      
      return { success: true };
    } catch (error) {
      console.error('Error resolviendo contingencia:', error);
      return { success: false, error: error.message };
    }
  }

  /**
   * Generar DTE con tipo de contingencia
   * Modifica el DTE para indicar que fue generado en contingencia
   */
  modificarDTEParaContingencia(dte, tipoContingencia, motivo) {
    const tipo = this.normalizarTipoContingencia(tipoContingencia);
    const motivoNormalizado = this.normalizarMotivoContingencia(motivo, tipo);
    const motivoKey = Object.prototype.hasOwnProperty.call(dte?.identificacion || {}, 'motivoContigencia')
      ? 'motivoContigencia'
      : 'motivoContin';

    const identificacion = {
      ...dte.identificacion,
      tipoModelo: 2,
      tipoOperacion: 2,
      tipoContingencia: tipo,
      [motivoKey]: motivoNormalizado
    };

    if (motivoKey === 'motivoContin') {
      delete identificacion.motivoContigencia;
    } else {
      delete identificacion.motivoContin;
    }

    return {
      ...dte,
      identificacion
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
   * Verificar si se debe activar modo contingencia
   * Evalúa intentos de conexión fallidos recientes
   */
  async debeActivarContingencia() {
    try {
      // Verificar si hay más de 3 intentos fallidos en los últimos 5 minutos
      const cincoMinutosAtras = new Date(Date.now() - 5 * 60 * 1000).toISOString();
      
      const stmt = this.db.prepare(`
        SELECT COUNT(*) as intentos_fallidos
        FROM contingencias
        WHERE fecha_contingencia > ?
        AND estado = 'PENDIENTE'
      `);
      
      const result = stmt.get(cincoMinutosAtras);
      
      return result.intentos_fallidos >= 3;
    } catch (error) {
      console.error('Error verificando contingencia:', error);
      return false;
    }
  }

  /**
   * Obtener estadísticas de contingencias
   */
  async obtenerEstadisticas(fechaDesde = null, fechaHasta = null) {
    try {
      let query = `
        SELECT 
          tipo_contingencia,
          estado,
          COUNT(*) as cantidad
        FROM contingencias
      `;
      
      const params = [];
      
      if (fechaDesde) {
        query += ` WHERE fecha_contingencia >= ?`;
        params.push(fechaDesde);
      }
      
      if (fechaHasta) {
        query += fechaDesde ? ` AND fecha_contingencia <= ?` : ` WHERE fecha_contingencia <= ?`;
        params.push(fechaHasta);
      }
      
      query += ` GROUP BY tipo_contingencia, estado`;
      
      const stmt = this.db.prepare(query);
      const stats = stmt.all(...params);
      
      return {
        success: true,
        estadisticas: stats
      };
    } catch (error) {
      console.error('Error obteniendo estadísticas:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Limpiar contingencias antiguas resueltas
   * Elimina registros resueltos con más de 90 días
   */
  async limpiarContingenciasAntiguas() {
    try {
      const noventaDiasAtras = new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString();
      
      const stmt = this.db.prepare(`
        DELETE FROM contingencias
        WHERE estado = 'RESUELTO'
        AND fecha_resolucion < ?
      `);
      
      const result = stmt.run(noventaDiasAtras);
      
      return {
        success: true,
        eliminados: result.changes
      };
    } catch (error) {
      console.error('Error limpiando contingencias:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }
}

module.exports = ContingenciaManager;
