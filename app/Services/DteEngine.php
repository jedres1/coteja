<?php

namespace App\Services;

use RuntimeException;

class DteEngine
{
    public function generate(string $tipo, array $config, array $cliente, array $items, array $resumen, array $opciones = []): array
    {
        return $this->run('generate', compact('tipo', 'config', 'cliente', 'items', 'resumen', 'opciones'));
    }

    public function validateEvent(string $tipoEvento, array|string $evento): array
    {
        return $this->run('validate-event', compact('tipoEvento', 'evento'));
    }

    public function signInternal(array $documento, string $nit, string $passwordPri, string $certificadoPath): array
    {
        return $this->run('sign-internal', compact('documento', 'nit', 'passwordPri', 'certificadoPath'));
    }

    public function validateCertificate(string $nit, string $passwordPri, string $certificadoPath): array
    {
        return $this->run('validate-certificate', compact('nit', 'passwordPri', 'certificadoPath'));
    }

    public function authenticate(array $config): array
    {
        return $this->run('auth', compact('config'));
    }

    public function send(array|string $dte, string $nit, array $config, ?string $passwordPri = null): array
    {
        return $this->run('send', compact('dte', 'nit', 'config', 'passwordPri'));
    }

    public function voidDte(array|string $eventoFirmado, array $config): array
    {
        return $this->run('void', compact('eventoFirmado', 'config'));
    }

    public function pdf(array $factura, array|string $dte, array $config, string $filename = 'dte.pdf'): array
    {
        return $this->run('pdf', compact('factura', 'dte', 'config', 'filename'));
    }

    private function run(string $action, array $payload): array
    {
        $command = [config('services.dte_engine.node', 'node'), base_path('resources/dte-engine/cli.cjs')];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, base_path('resources/dte-engine'));

        if (! is_resource($process)) {
            throw new RuntimeException('No se pudo iniciar el motor de facturacion DTE.');
        }

        fwrite($pipes[0], json_encode(['action' => $action, 'payload' => $payload], JSON_UNESCAPED_UNICODE));
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $result = json_decode($stdout, true);

        if (! is_array($result)) {
            throw new RuntimeException(trim($stderr) ?: 'El motor DTE devolvio una respuesta invalida.');
        }

        if ($exitCode !== 0 && ($result['success'] ?? false) !== false) {
            throw new RuntimeException($result['error'] ?? trim($stderr) ?: 'El motor DTE fallo.');
        }

        return $result;
    }
}
