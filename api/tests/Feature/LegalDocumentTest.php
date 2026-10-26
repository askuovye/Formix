<?php

declare(strict_types=1);

use App\Enums\LegalDocument;

beforeEach(function () {
    config(['legal.operator' => [
        'name' => 'Fulana de Tal',
        'contact_email' => 'contato@exemplo.org',
        'privacy_email' => 'privacidade@exemplo.org',
        'jurisdiction' => 'Curitiba/PR',
        'hosting_provider' => 'Hospedagem Exemplo',
        'email_provider' => 'Email Exemplo',
    ]]);
});

function legalContent(string $document): string
{
    return (string) test()->getJson("/api/v1/legal/{$document}")->json('data.content_markdown');
}

it('devolve o documento com título, versão e texto', function (string $document, string $title) {
    $this->getJson("/api/v1/legal/{$document}")
        ->assertOk()
        ->assertJsonPath('data.document', $document)
        ->assertJsonPath('data.title', $title)
        ->assertJsonPath('data.version', '1.0')
        ->assertJsonPath('data.updated_at', '2026-10-23');

    expect(legalContent($document))->toStartWith("# {$title}");
})->with([
    'termos' => ['terms', 'Termos de Uso'],
    'privacidade' => ['privacy', 'Política de Privacidade'],
]);

it('preenche os dados do operador da instância nos textos', function () {
    expect(legalContent('terms'))->toContain('Fulana de Tal', 'contato@exemplo.org', 'Curitiba/PR')
        ->and(legalContent('privacy'))->toContain('Fulana de Tal', 'privacidade@exemplo.org', 'Hospedagem Exemplo', 'Email Exemplo');
});

it('não deixa nenhum marcador sem substituir', function (string $document) {
    expect(legalContent($document))->not->toContain('{{', '}}');
})->with(['terms', 'privacy']);

it('sinaliza no texto o dado que o operador não configurou', function () {
    config(['legal.operator.jurisdiction' => null]);

    expect(legalContent('terms'))
        ->toContain(LegalDocument::NOT_CONFIGURED)
        ->not->toContain('{{jurisdiction}}');
});

it('entrega o texto também em HTML pronto para exibir', function () {
    $html = (string) $this->getJson('/api/v1/legal/privacy')->json('data.content_html');

    expect($html)
        ->toContain('<h1>Política de Privacidade</h1>', '<table>', '<li>', 'Fulana de Tal')
        ->not->toContain('{{', '# ');
});

it('escapa HTML vindo dos dados do operador', function () {
    config(['legal.operator.name' => '<script>alert(1)</script>']);

    $html = (string) $this->getJson('/api/v1/legal/terms')->json('data.content_html');

    expect($html)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('responde 404 para documento inexistente', function () {
    $this->getJson('/api/v1/legal/contrato-secreto')->assertNotFound();
});

it('não aceita caminho de arquivo como documento', function () {
    $this->getJson('/api/v1/legal/..%2F..%2F.env')->assertNotFound();
});
