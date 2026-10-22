<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role'           => \App\Http\Middleware\EnsureRole::class,
            'module'         => \App\Http\Middleware\EnsureModuleAccess::class,
            'ensure-company' => \App\Http\Middleware\EnsureCompanySelected::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }
            return response()->view('errors.404', [], 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Acceso denegado.'], 403);
            }
            return response()->view('errors.403', [], 403);
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->routeIs('admin.factura-sv.autenticar', 'admin.factura-sv.firmador.estado')
                && $e instanceof \Illuminate\Session\TokenMismatchException) {
                return response()->json([
                    'success' => false,
                    'message' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.',
                ], 419);
            }

            if ($request->expectsJson()) {
                if ($request->routeIs('admin.factura-sv.autenticar', 'admin.factura-sv.firmador.estado')) {
                    \Illuminate\Support\Facades\Log::error('Fallo inesperado en prueba de integración de facturación.', [
                        'user_id' => $request->user()?->id,
                        'exception' => $e,
                    ]);
                }

                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                return response()->json(['message' => 'Error interno del servidor.'], $status);
            }
        });
    })->create();
