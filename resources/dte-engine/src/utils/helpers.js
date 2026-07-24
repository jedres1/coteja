/**
 * Utilidades generales para el sistema de facturación
 */

/**
 * Formatear número a moneda USD
 */
function formatearMoneda(valor) {
  return new Intl.NumberFormat('es-SV', {
    style: 'currency',
    currency: 'USD'
  }).format(valor);
}

/**
 * Formatear fecha
 */
function formatearFecha(fecha, formato = 'completo') {
  const date = new Date(fecha);
  
  if (formato === 'corto') {
    return date.toLocaleDateString('es-SV');
  } else if (formato === 'hora') {
    return date.toLocaleTimeString('es-SV');
  } else {
    return date.toLocaleString('es-SV');
  }
}

/**
 * Validar NIT salvadoreño
 */
function validarNIT(nit) {
  // Formato: 9999-999999-999-9
  const regex = /^\d{4}-\d{6}-\d{3}-\d{1}$/;
  return regex.test(nit);
}

/**
 * Validar DUI (Documento Único de Identidad)
 */
function validarDUI(dui) {
  // Formato: 99999999-9
  const regex = /^\d{8}-\d{1}$/;
  return regex.test(dui);
}

/**
 * Generar UUID v4
 */
function generarUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

/**
 * Calcular dígito verificador para NIT
 */
function calcularDigitoVerificadorNIT(nit) {
  // Implementación del algoritmo de verificación del NIT
  const nitLimpio = nit.replace(/-/g, '');
  const factores = [14, 13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2];
  
  let suma = 0;
  for (let i = 0; i < 13; i++) {
    suma += parseInt(nitLimpio[i]) * factores[i];
  }
  
  const residuo = suma % 11;
  const digito = residuo <= 1 ? 0 : 11 - residuo;
  
  return digito;
}

/**
 * Convertir número a letras (para montos)
 */
function numeroALetras(numero) {
  const unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
  const decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
  const especiales = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
  const centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

  function convertirGrupo(n) {
    let texto = '';
    
    const c = Math.floor(n / 100);
    const d = Math.floor((n % 100) / 10);
    const u = n % 10;

    if (c > 0) {
      if (c === 1 && d === 0 && u === 0) {
        texto += 'CIEN';
      } else {
        texto += centenas[c];
      }
    }

    if (d === 1 && u > 0) {
      if (texto) texto += ' ';
      texto += especiales[u];
    } else {
      if (d > 0) {
        if (texto) texto += ' ';
        texto += decenas[d];
      }
      if (u > 0) {
        if (texto) texto += ' Y ';
        texto += unidades[u];
      }
    }

    return texto;
  }

  const entero = Math.floor(numero);
  const decimales = Math.round((numero - entero) * 100);

  let resultado = '';

  if (entero === 0) {
    resultado = 'CERO';
  } else if (entero < 1000) {
    resultado = convertirGrupo(entero);
  } else if (entero < 1000000) {
    const miles = Math.floor(entero / 1000);
    const resto = entero % 1000;
    
    if (miles === 1) {
      resultado = 'MIL';
    } else {
      resultado = convertirGrupo(miles) + ' MIL';
    }
    
    if (resto > 0) {
      resultado += ' ' + convertirGrupo(resto);
    }
  } else {
    const millones = Math.floor(entero / 1000000);
    const miles = Math.floor((entero % 1000000) / 1000);
    const resto = entero % 1000;

    if (millones === 1) {
      resultado = 'UN MILLÓN';
    } else {
      resultado = convertirGrupo(millones) + ' MILLONES';
    }

    if (miles > 0) {
      resultado += ' ' + (miles === 1 ? 'MIL' : convertirGrupo(miles) + ' MIL');
    }

    if (resto > 0) {
      resultado += ' ' + convertirGrupo(resto);
    }
  }

  return `${resultado} DÓLARES CON ${decimales}/100`;
}

/**
 * Sanitizar entrada de texto
 */
function sanitizarTexto(texto) {
  if (!texto) return '';
  return texto.trim().replace(/[<>]/g, '');
}

/**
 * Exportar funciones
 */
module.exports = {
  formatearMoneda,
  formatearFecha,
  validarNIT,
  validarDUI,
  generarUUID,
  calcularDigitoVerificadorNIT,
  numeroALetras,
  sanitizarTexto
};
