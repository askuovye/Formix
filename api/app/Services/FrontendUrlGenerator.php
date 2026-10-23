<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Links enviados por email apontam para a SPA, que chama a API.
 * As rotas da SPA abaixo fazem parte do contrato com o front (docs/api.md).
 */
final class FrontendUrlGenerator
{
    public const VERIFY_EMAIL_PATH = '/verificar-email';

    public const RESET_PASSWORD_PATH = '/redefinir-senha';

    public const LOGIN_PATH = '/entrar';

    public const FORGOT_PASSWORD_PATH = '/esqueci-a-senha';

    public const PUBLIC_FORM_PATH = '/f/';

    public const PRIVACY_POLICY_PATH = '/privacidade';

    public const DASHBOARD_FORMS_PATH = '/painel/formularios/';

    /**
     * A SPA repassa `id`, `hash`, `expires` e `signature` para
     * GET /api/v1/auth/email/verify/{id}/{hash}?expires=…&signature=…
     */
    public function emailVerification(User $user): string
    {
        $signedPath = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addMinutes($this->verificationExpiresInMinutes()),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            absolute: false,
        );

        parse_str((string) parse_url($signedPath, PHP_URL_QUERY), $signedQuery);

        return $this->to(self::VERIFY_EMAIL_PATH, [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
            'expires' => $signedQuery['expires'] ?? null,
            'signature' => $signedQuery['signature'] ?? null,
        ]);
    }

    public function passwordReset(string $email, #[\SensitiveParameter] string $token): string
    {
        return $this->to(self::RESET_PASSWORD_PATH, ['token' => $token, 'email' => $email]);
    }

    public function login(): string
    {
        return $this->to(self::LOGIN_PATH);
    }

    public function forgotPassword(): string
    {
        return $this->to(self::FORGOT_PASSWORD_PATH);
    }

    public function publicForm(string $slug): string
    {
        return $this->to(self::PUBLIC_FORM_PATH.$slug);
    }

    /**
     * O token vai no fragmento (#): o navegador não o envia ao servidor da SPA,
     * então ele não aparece em logs de acesso nem no cabeçalho Referer.
     * A SPA o repassa à API no cabeçalho X-Edit-Token.
     */
    public function submissionEdit(string $slug, string $submissionId, #[\SensitiveParameter] string $token): string
    {
        return $this->to(self::PUBLIC_FORM_PATH."{$slug}/respostas/{$submissionId}/editar").'#token='.$token;
    }

    public function submissionInDashboard(string $formId, string $submissionId): string
    {
        return $this->to(self::DASHBOARD_FORMS_PATH."{$formId}/respostas/{$submissionId}");
    }

    public function formSettingsInDashboard(string $formId): string
    {
        return $this->to(self::DASHBOARD_FORMS_PATH."{$formId}/configuracoes");
    }

    public function privacyPolicy(): string
    {
        return $this->to(self::PRIVACY_POLICY_PATH);
    }

    public function verificationExpiresInMinutes(): int
    {
        return (int) config('auth.verification.expire', 60);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function to(string $path, array $query = []): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/').$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
