<?php

use App\Models\BillingDteCorrelative;
use App\Models\BillingSetting;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sqlitePath = '/Users/macgzres/Library/Application Support/facturacion-electron/facturacion.db';

if (! is_file($sqlitePath)) {
    fwrite(STDERR, "No se encontro la base de Electron: {$sqlitePath}\n");
    exit(1);
}

$pdo = new PDO('sqlite:'.$sqlitePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$config = $pdo->query('SELECT * FROM configuracion LIMIT 1')->fetch(PDO::FETCH_ASSOC);

if (! $config) {
    fwrite(STDERR, "No hay configuracion en facturacion-electron.\n");
    exit(1);
}

$settings = [
    'electron_raw_config' => $config,
    'emisor' => [
        'nit' => $config['nit'] ?? '',
        'nrc' => $config['nrc'] ?? '',
        'nombre_empresa' => $config['nombre_empresa'] ?? '',
        'nombre_comercial' => $config['nombre_comercial'] ?? '',
        'tipo_persona' => $config['tipo_persona'] ?? '',
        'actividad_economica' => $config['actividad_economica'] ?? '',
        'telefono' => $config['telefono'] ?? '',
        'email' => $config['email'] ?? '',
        'direccion' => $config['direccion'] ?? '',
        'departamento' => $config['departamento'] ?? '',
        'municipio' => $config['municipio'] ?? '',
        'distrito' => $config['distrito'] ?? '',
        'codigo_establecimiento' => $config['codigo_establecimiento'] ?? '',
        'punto_venta' => $config['punto_venta'] ?? '',
        'logo_path' => $config['logo_path'] ?? '',
    ],
    'hacienda' => [
        'usuario' => $config['hacienda_usuario'] ?? '',
        'password' => $config['hacienda_password'] ?? '',
        'ambiente' => ($config['hacienda_ambiente'] ?? 'pruebas') === 'produccion' ? '01' : '00',
        'ambiente_electron' => $config['hacienda_ambiente'] ?? 'pruebas',
    ],
    'firma' => [
        'tipo_firma' => $config['tipo_firma'] ?? 'local',
        'firmador_usuario' => $config['firmador_usuario'] ?? '',
        'firmador_password' => $config['firmador_password'] ?? '',
        'firmador_pin' => $config['firmador_pin'] ?? '',
        'certificado_path' => $config['certificado_path'] ?? '',
        'certificado_password' => $config['certificado_password'] ?? '',
    ],
    'correo' => [
        'smtpHost' => $config['correo_smtp_host'] ?? 'smtp.gmail.com',
        'smtpPort' => (string) ($config['correo_smtp_port'] ?? 465),
        'smtpSecure' => (bool) ($config['correo_smtp_secure'] ?? 1),
        'username' => $config['correo_usuario'] ?? '',
        'password' => $config['correo_password'] ?? '',
        'from' => $config['correo_remitente'] ?? '',
        'fromName' => $config['correo_nombre'] ?? '',
    ],
    'documentos' => json_decode($config['tipos_dte_habilitados'] ?? '[]', true) ?: ['01', '03', '05', '06', '07', '11', '14'],
];

foreach ($settings as $key => $value) {
    BillingSetting::put($key, $value);
}

$rows = $pdo->query('SELECT * FROM correlativos_dte')->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    BillingDteCorrelative::updateOrCreate(
        [
            'document_type' => $row['tipo_dte'],
            'year' => (int) $row['anio'],
            'establishment' => $row['establecimiento'] ?? '',
            'point_of_sale' => $row['punto_venta'] ?? '',
        ],
        [
            'next_number' => (int) $row['siguiente'],
        ]
    );
}

echo "Configuracion importada desde Electron.\n";
echo "Parametros guardados: ".count($settings)."\n";
echo "Correlativos importados: ".count($rows)."\n";
