{{-- PDF das respostas (SubmissionsPdfRenderer). Todo conteúdo dinâmico sai com {{ }} (escapado). --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 56px 48px 64px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1c1f24; line-height: 1.45; }
        .footer { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 9px; color: #5b6270; }
        .footer__page { float: right; }
        .footer__page:after { content: counter(page); }
        .entry { page-break-after: always; }
        .entry:last-child { page-break-after: auto; }
        .entry__form { margin: 0; font-size: 10px; color: #5b6270; text-transform: uppercase; letter-spacing: 0.5px; }
        .entry__title { margin: 4px 0 2px; font-size: 18px; }
        .entry__meta { margin: 0 0 20px; color: #5b6270; }
        .entry__section { margin: 20px 0 8px; padding-bottom: 4px; font-size: 12px; border-bottom: 1px solid #d9dde3; }
        .answer { margin: 0 0 12px; page-break-inside: avoid; }
        .answer__label { margin: 0 0 2px; font-size: 10px; font-weight: bold; color: #5b6270; }
        .answer__value { margin: 0; font-size: 12px; white-space: pre-wrap; }
        .answer__value--empty { color: #8a919e; font-style: italic; }
        .file { margin: 0 0 2px; }
        .file__size { color: #5b6270; }
        .note { margin-top: 6px; font-size: 9px; color: #5b6270; }
    </style>
</head>
<body>
    <div class="footer">
        {{ config('app.name') }} · gerado em {{ $generatedAt }}
        <span class="footer__page">Página </span>
    </div>

    @foreach ($entries as $index => $entry)
        <div class="entry">
            <p class="entry__form">{{ $title }}</p>
            <h1 class="entry__title">Resposta {{ count($entries) > 1 ? ($index + 1).' de '.count($entries) : '' }}</h1>
            <p class="entry__meta">
                Recebida em {{ $entry['received_at'] }}@if ($entry['edited_at']) · editada em {{ $entry['edited_at'] }}@endif
            </p>

            <h2 class="entry__section">Respostas</h2>
            @foreach ($entry['answers'] as $answer)
                <div class="answer">
                    <p class="answer__label">{{ $answer['label'] }}</p>
                    <p class="answer__value {{ $answer['answered'] ? '' : 'answer__value--empty' }}">{{ $answer['value'] }}</p>
                </div>
            @endforeach

            @if (count($entry['files']) > 0)
                <h2 class="entry__section">Anexos</h2>
                @foreach ($entry['files'] as $group)
                    <div class="answer">
                        <p class="answer__label">{{ $group['label'] }}</p>
                        @foreach ($group['files'] as $file)
                            <p class="file">{{ $file['name'] }} <span class="file__size">({{ $file['size'] }})</span></p>
                        @endforeach
                    </div>
                @endforeach
                <p class="note">Os arquivos ficam disponíveis para download no painel de respostas.</p>
            @endif
        </div>
    @endforeach
</body>
</html>
