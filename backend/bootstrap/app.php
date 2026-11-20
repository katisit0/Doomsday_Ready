<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every API error uses one shape: { "error": { "code", "message", "fields"? } }
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => $e->getMessage(),
                        'fields'  => $e->errors(),
                    ],
                ], 422);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                $code = match ($status) {
                    404     => 'NOT_FOUND',
                    405     => 'METHOD_NOT_ALLOWED',
                    default => 'HTTP_ERROR',
                };
                $message = $status === 404 ? 'Not found.' : ($e->getMessage() ?: 'Request failed.');

                return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
            }
        });
    })->create();
