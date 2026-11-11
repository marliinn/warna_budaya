<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\TrackPublicAnalytics;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (): string => route('admin.login'));
        $middleware->appendToGroup('web', TrackPublicAnalytics::class);
        $middleware->alias([
            'admin.access' => EnsureAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (
                ! $request->is('admin/api/*')
                || ! $request->expectsJson()
                || $exception instanceof HttpExceptionInterface
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof ModelNotFoundException
            ) {
                return null;
            }

            return response()->json([
                'message' => 'Terjadi kesalahan pada layanan. Silakan coba kembali.',
            ], 500);
        });
    })->create();
