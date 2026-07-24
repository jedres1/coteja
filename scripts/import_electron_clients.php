<?php

use App\Models\Customer;
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

$activitiesPath = __DIR__.'/../public/catalogs/actividades-economicas.json';
$activities = is_file($activitiesPath)
    ? collect(json_decode(file_get_contents($activitiesPath), true) ?: [])->keyBy('codigo')
    : collect();

$rows = $pdo->query('SELECT * FROM clientes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

$created = 0;
$updated = 0;

function uniqueCustomerEmail(string $email, ?int $ignoreId, string $documentNumber, int $electronId): string
{
    $email = trim($email);
    $existing = Customer::where('email', $email)
        ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
        ->first();

    if (! $existing) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $suffix = preg_replace('/[^A-Za-z0-9]/', '', $documentNumber) ?: 'electron'.$electronId;
    $candidate = "{$local}+{$suffix}@{$domain}";
    $counter = 2;

    while (
        Customer::where('email', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
    ) {
        $candidate = "{$local}+{$suffix}{$counter}@{$domain}";
        $counter++;
    }

    return $candidate;
}

foreach ($rows as $row) {
    $documentNumber = trim((string) ($row['numero_documento'] ?? ''));
    if ($documentNumber === '') {
        continue;
    }

    $customer = Customer::where('document_number', $documentNumber)->first();
    $email = uniqueCustomerEmail((string) $row['email'], $customer?->id, $documentNumber, (int) $row['id']);
    $activityCode = trim((string) ($row['giro'] ?? ''));
    $address = trim((string) ($row['direccion'] ?? ''));

    $payload = [
        'name' => trim((string) $row['nombre']),
        'email' => $email,
        'phone' => $row['telefono'] ?: null,
        'document_type' => $row['tipo_documento'] ?: '36',
        'document_number' => $documentNumber,
        'nrc' => $row['nrc'] ?: null,
        'trade_name' => $row['nombre_comercial'] ?: null,
        'business_activity' => $activityCode ?: null,
        'activity_description' => $activities->get($activityCode)['descripcion'] ?? null,
        'address_department' => $row['departamento'] ?: null,
        'address_municipality' => $row['municipio'] ?: null,
        'address' => $address !== '' ? $address : 'Sin direccion registrada',
        'preferred_dte_type' => $row['tipo_dte_default'] ?: '01',
        'billing_email' => $row['email'] ?: null,
        'billing_phone' => $row['telefono'] ?: null,
        'status' => 'active',
        'notes' => trim(sprintf(
            "Importado desde facturacion-electron. Electron ID: %s. Condicion IVA: %s. Plazo pago: %s. Periodo pago: %s. Distrito: %s. Pais: %s %s",
            $row['id'] ?? '',
            $row['condicion_iva'] ?? '',
            $row['plazo_pago'] ?? '',
            $row['periodo_pago'] ?? '',
            $row['distrito'] ?? '',
            $row['cod_pais'] ?? '',
            $row['nombre_pais'] ?? ''
        )),
    ];

    if ($customer) {
        $customer->update($payload);
        $updated++;
    } else {
        Customer::create($payload);
        $created++;
    }
}

echo "Clientes Electron leidos: ".count($rows).PHP_EOL;
echo "Clientes creados: {$created}".PHP_EOL;
echo "Clientes actualizados: {$updated}".PHP_EOL;
echo "Total clientes en Coteja: ".Customer::count().PHP_EOL;
