<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\ReportQueryExceptionWithoutBindings;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum em modo SPA: sessão por cookie + CSRF nas rotas da API.
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->append(SetSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A aplicação é somente API: toda resposta de erro é JSON.
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);
        $exceptions->render(new ApiExceptionRenderer);
        // Erro de banco sem os valores das consultas no log (LGPD).
        $exceptions->report(new ReportQueryExceptionWithoutBindings);
    })->create();
