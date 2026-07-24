# Coteja

Administrador web para clientes, planes, licencias, cobros, descargas de la app y backups cifrados.

## Stack

- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- Sesiones y colas en base de datos

## Instalacion

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configura MySQL en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coteja
DB_USERNAME=usuario
DB_PASSWORD=clave
```

Luego ejecuta:

```bash
php artisan migrate --seed
```

## Usuarios iniciales

Admin:

```txt
jandres.gerardo@outlook.com
Hokagesama1
```

Cliente demo:

```txt
cliente@demo.test
Cliente12345
```

## Modulos

- Clientes
- Empresas emisoras
- Planes
- Licencias
- Cobros
- Descargas de instaladores
- Backups cifrados
- Portal cliente

## Extraccion de facturas de compra

El boton **Extraer** del panel de compras lee correos no leidos del buzon configurado y registra los adjuntos JSON como facturas de compra.

```env
PURCHASE_INVOICE_MAIL_HOST=imap.gmail.com
PURCHASE_INVOICE_MAIL_PORT=993
PURCHASE_INVOICE_MAIL_USERNAME=facturacioncoteja@gmail.com
PURCHASE_INVOICE_MAIL_PASSWORD=
PURCHASE_INVOICE_MAILBOX=INBOX
PURCHASE_INVOICE_MAIL_ONLY_UNSEEN=true
PURCHASE_INVOICE_MAIL_LIMIT=25
```

Para Gmail, `PURCHASE_INVOICE_MAIL_PASSWORD` debe ser una clave de aplicacion valida para acceso IMAP del buzon.

## API para Electron

Validar licencia:

```http
POST /api/licenses/validate
```

Body:

```json
{
  "licenseKey": "COT-DEMO-0001",
  "deviceId": "equipo-001",
  "deviceName": "Caja principal"
}
```

Subir ultimo backup:

```http
POST /api/backups/latest
```

Multipart:

```txt
licenseKey=COT-DEMO-0001
deviceId=equipo-001
checksum=sha256_del_archivo_cifrado
backup=facturacion-latest.db.gz.enc
```

El sistema conserva un solo backup por licencia usando `updateOrCreate`, por lo que cada subida reemplaza la referencia del ultimo respaldo y sobrescribe el archivo.

## Hostinger

En hosting compartido, apunta el dominio al directorio `public`. Los backups quedan en `storage/app/private/backups`, fuera de acceso publico. Para descargar un backup siempre se pasa por Laravel y se valida el usuario autenticado.
