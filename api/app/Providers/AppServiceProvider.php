<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\CheckSystemHealth;
use App\Models\Form;
use App\Services\Health\DatabaseHealthCheck;
use App\Services\Health\HealthCheck;
use App\Services\Health\QueueBacklogHealthCheck;
use App\Services\Submissions\CaptchaVerifier;
use App\Services\Submissions\NullCaptchaVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    private const API_REQUESTS_PER_MINUTE = 120;

    private const HEALTH_REQUESTS_PER_MINUTE = 30;

    private const PASSWORD_MIN_LENGTH = 8;

    // Limite do bcrypt: bytes além de 72 são ignorados no hash.
    private const PASSWORD_MAX_LENGTH = 72;

    public function register(): void
    {
        $this->app->tag([
            DatabaseHealthCheck::class,
            QueueBacklogHealthCheck::class,
        ], HealthCheck::class);

        $this->app->when(CheckSystemHealth::class)
            ->needs(HealthCheck::class)
            ->giveTagged(HealthCheck::class);

        $this->app->bind(CaptchaVerifier::class, NullCaptchaVerifier::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->configureTrustedProxies();
        $this->configurePasswordPolicy();
        $this->configureRateLimiting();
        $this->configureAuthRateLimiting();
        $this->configurePublicRoutes();
    }

    /**
     * Rota pública: o formulário é achado pelo slug e só se já foi publicado
     * alguma vez (rascunho nunca publicado é 404, igual a inexistente).
     * Excluídos (SoftDeletes) também são 404.
     */
    private function configurePublicRoutes(): void
    {
        Route::bind('publicForm', fn (string $slug): Form => Form::query()
            ->where('slug', $slug)
            ->where('schema_version', '>', 0)
            ->firstOrFail());

        RateLimiter::for('public-form', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('public-form:'.$request->ip()));

        // Por IP no formulário (abuso direcionado), por IP no geral (varredura) e por
        // formulário (protege o banco de uma enxurrada distribuída num formulário só).
        RateLimiter::for('submissions', fn (Request $request): array => [
            Limit::perMinute(10)->by('submissions:'.$this->formKey($request).'|'.$request->ip()),
            Limit::perHour(200)->by('submissions:'.$request->ip()),
            Limit::perMinute(300)->by('submissions:'.$this->formKey($request)),
        ]);

        RateLimiter::for('submission-edit', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('submission-edit:'.$request->ip()));

        RateLimiter::for('downloads', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('downloads:'.$request->ip()));

        // Imagens públicas: uma página carrega capa e logo; generoso para muitos visitantes atrás do mesmo IP.
        RateLimiter::for('assets', fn (Request $request): Limit => Limit::perMinute(120)
            ->by('assets:'.$request->ip()));

        RateLimiter::for('asset-uploads', fn (Request $request): Limit => Limit::perHour(30)
            ->by('asset-uploads:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('exports', fn (Request $request): Limit => Limit::perHour(10)
            ->by('exports:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * O rate limit roda antes do route binding: o parâmetro ainda é o slug.
     */
    private function formKey(Request $request): string
    {
        $form = $request->route('publicForm');

        return $form instanceof Form ? $form->slug : (string) $form;
    }

    /**
     * Atrás de proxy reverso, o IP real do cliente só é confiável se o proxy
     * estiver na lista; sem isso, rate limit e hash de IP usariam o IP do proxy.
     */
    private function configureTrustedProxies(): void
    {
        /** @var list<string> $proxies */
        $proxies = config('app.trusted_proxies');

        if ($proxies !== []) {
            TrustProxies::at($proxies);
        }
    }

    private function configurePasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(self::PASSWORD_MIN_LENGTH)
                ->max(self::PASSWORD_MAX_LENGTH)
                ->letters()
                ->numbers();

            // Consulta a base de senhas vazadas (HIBP, k-anonymity) só em produção:
            // nos testes e em dev não há chamada de rede.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(self::API_REQUESTS_PER_MINUTE)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('health', fn (Request $request): Limit => Limit::perMinute(self::HEALTH_REQUESTS_PER_MINUTE)
            ->by((string) $request->ip()));
    }

    /**
     * Limites por IP (contra varredura) e por email (contra ataque a uma conta
     * ou spam para uma caixa de entrada). O email entra na chave como hash.
     */
    private function configureAuthRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by('login:'.$this->emailKey($request).'|'.$request->ip()),
            Limit::perMinute(20)->by('login:'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request): array => [
            Limit::perHour(10)->by('register:'.$request->ip()),
            Limit::perHour(3)->by('register:'.$this->emailKey($request)),
        ]);

        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perMinute(5)->by('password-reset:'.$request->ip()),
            Limit::perHour(5)->by('password-reset:'.$this->emailKey($request)),
        ]);

        RateLimiter::for('verify-email', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('verify-email:'.$request->ip()));

        RateLimiter::for('verification-notification', fn (Request $request): Limit => Limit::perMinute(3)
            ->by('verification-notification:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    private function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return sha1(is_string($email) ? mb_strtolower(trim($email)) : '');
    }
}
