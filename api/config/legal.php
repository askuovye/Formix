<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Documentos legais
|--------------------------------------------------------------------------
|
| O projeto é open-source: cada instalação ("instância") tem seu próprio
| responsável, que preenche os dados abaixo no .env. Os textos em
| resources/legal usam marcadores {{chave}} substituídos por estes valores.
|
| Ao alterar um texto de forma relevante, suba a `version`: o aceite de cada
| usuário fica registrado com a versão aceita (tabela legal_acceptances).
|
*/

return [

    'operator' => [
        // Pessoa ou organização responsável por esta instância (com CPF/CNPJ, se quiser).
        'name' => env('LEGAL_OPERATOR_NAME'),
        'contact_email' => env('LEGAL_CONTACT_EMAIL'),
        // Canal para pedidos de titulares (LGPD art. 18). Padrão: o email de contato.
        'privacy_email' => env('LEGAL_PRIVACY_EMAIL', env('LEGAL_CONTACT_EMAIL')),
        // Comarca do foro (ex.: "São Paulo/SP").
        'jurisdiction' => env('LEGAL_JURISDICTION'),
        'hosting_provider' => env('LEGAL_HOSTING_PROVIDER'),
        'email_provider' => env('LEGAL_EMAIL_PROVIDER'),
    ],

    'documents' => [
        'terms' => [
            'title' => 'Termos de Uso',
            'version' => '1.0',
            'updated_at' => '2026-10-23',
            'path' => resource_path('legal/terms.md'),
        ],
        'privacy' => [
            'title' => 'Política de Privacidade',
            'version' => '1.0',
            'updated_at' => '2026-10-23',
            'path' => resource_path('legal/privacy.md'),
        ],
    ],

];
