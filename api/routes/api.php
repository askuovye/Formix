<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Forms\FormAssetController;
use App\Http\Controllers\Forms\FormController;
use App\Http\Controllers\Forms\FormLifecycleController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\Public\DownloadAttachmentController;
use App\Http\Controllers\Public\PublicFormController;
use App\Http\Controllers\Public\PublicSubmissionController;
use App\Http\Controllers\Submissions\ExportController;
use App\Http\Controllers\Submissions\SubmissionController;
use App\Http\Middleware\LimitRequestSize;
use App\Http\Resources\ExportResource;
use App\Services\FormAssets;
use App\Services\Submissions\AttachmentLinks;
use Illuminate\Support\Facades\Route;

// Prefixo /api/v1 definido em bootstrap/app.php.

Route::get('/health', HealthCheckController::class)
    ->middleware('throttle:health')
    ->name('health');

// Público: a SPA exibe os textos no cadastro e no rodapé.
Route::get('/legal/{document}', LegalDocumentController::class)->name('legal.show');

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/register', RegisterController::class)
        ->middleware('throttle:register')
        ->name('register');

    Route::post('/login', [SessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login');

    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('password.update');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:verify-email')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', CurrentUserController::class)->name('me');

        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:verification-notification')
            ->name('verification.send');
    });
});

// Página pública (sem login). {publicForm} resolve pelo slug só formulários já publicados (AppServiceProvider).
Route::prefix('forms/{publicForm}')->name('public.forms.')->scopeBindings()->group(function (): void {
    Route::get('/', PublicFormController::class)
        ->middleware('throttle:public-form')
        ->name('show');

    Route::post('/submissions', [PublicSubmissionController::class, 'store'])
        ->middleware(['throttle:submissions', LimitRequestSize::class.':'.LimitRequestSize::SUBMISSION_MAX_KILOBYTES])
        ->name('submissions.store');

    Route::get('/submissions/{submission}', [PublicSubmissionController::class, 'show'])
        ->middleware('throttle:submission-edit')
        ->name('submissions.show');

    Route::put('/submissions/{submission}', [PublicSubmissionController::class, 'update'])
        ->middleware(['throttle:submission-edit', LimitRequestSize::class.':'.LimitRequestSize::SUBMISSION_MAX_KILOBYTES])
        ->name('submissions.update');
});

// Imagem de capa/logo de formulário: pública e imutável (D32).
Route::get('/assets/{asset}', [FormAssetController::class, 'show'])
    ->middleware('throttle:assets')
    ->name(FormAssets::ROUTE);

// Download de anexo: só com link assinado e dentro da validade (AGENTS.md §6).
Route::get('/files/{media}', DownloadAttachmentController::class)
    ->middleware(['signed', 'throttle:downloads'])
    ->whereUuid('media')
    ->name(AttachmentLinks::ROUTE);

// Download do CSV de exportação: link assinado e curto, gerado para o dono logado.
Route::get('/exports/{export}/download', [ExportController::class, 'download'])
    ->middleware(['signed', 'throttle:downloads'])
    ->name(ExportResource::DOWNLOAD_ROUTE);

// Painel de respostas e exportações do usuário logado.
Route::middleware('auth:sanctum')->prefix('me')->name('me.')->group(function (): void {
    Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show'])->can('view', 'submission')->name('submissions.show');
    Route::delete('/submissions/{submission}', [SubmissionController::class, 'destroy'])->can('delete', 'submission')->name('submissions.destroy');

    Route::post('/forms/{form}/exports', [ExportController::class, 'store'])
        ->can('view', 'form')
        ->middleware('throttle:exports')
        ->name('exports.store');
    Route::get('/exports/{export}', [ExportController::class, 'show'])->can('view', 'export')->name('exports.show');
});

// Formulários do usuário logado. `/forms/{slug}` fica reservado para a rota pública (AGENTS.md §8).
Route::middleware('auth:sanctum')->prefix('me/forms')->name('me.forms.')->group(function (): void {
    Route::get('/', [FormController::class, 'index'])->name('index');
    Route::post('/', [FormController::class, 'store'])->name('store');
    Route::get('/{form}', [FormController::class, 'show'])->can('view', 'form')->name('show');
    Route::patch('/{form}', [FormController::class, 'update'])->can('update', 'form')->name('update');
    Route::delete('/{form}', [FormController::class, 'destroy'])->can('delete', 'form')->name('destroy');
    Route::post('/{form}/publish', [FormLifecycleController::class, 'publish'])->can('publish', 'form')->name('publish');
    Route::post('/{form}/close', [FormLifecycleController::class, 'close'])->can('close', 'form')->name('close');
    Route::post('/{form}/assets', [FormAssetController::class, 'store'])
        ->can('update', 'form')
        ->middleware('throttle:asset-uploads')
        ->name('assets.store');
    Route::delete('/{form}/assets/{asset}', [FormAssetController::class, 'destroy'])
        ->can('update', 'form')
        ->scopeBindings()
        ->name('assets.destroy');
});
