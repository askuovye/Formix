<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Models\Form;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * PDF das respostas de um formulário (uma por página), com dompdf (D35).
 *
 * - Respostas na ordem e com os rótulos da versão de envio (SubmissionPresenter),
 *   como no painel e nos emails.
 * - Anexos entram só pelo nome e tamanho: o conteúdo nunca é processado
 *   (AGENTS.md §6, sem antivírus), então imagens não são embutidas.
 * - dompdf sem recursos remotos, sem PHP e sem JavaScript; todo conteúdo do
 *   respondente sai escapado pelo Blade.
 */
final class SubmissionsPdfRenderer
{
    private const DATE_FORMAT = 'd/m/Y \à\s H:i';

    private const DISPLAY_TIMEZONE = 'America/Sao_Paulo';

    public function __construct(
        private readonly SubmissionPresenter $presenter,
        private readonly PublishedFormVersions $versions,
    ) {}

    /**
     * @param  iterable<Submission>  $submissions  com `media` carregado
     */
    public function render(Form $form, iterable $submissions): string
    {
        return Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            // Só os caracteres usados da fonte: o arquivo cai de ~800 KB para dezenas de KB.
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ])
            ->loadHTML($this->html($form, $submissions))
            ->setPaper('a4')
            ->output();
    }

    /**
     * HTML que vira o PDF (separado para os testes lerem o conteúdo).
     *
     * @param  iterable<Submission>  $submissions  com `media` carregado
     */
    public function html(Form $form, iterable $submissions): string
    {
        $entries = [];

        foreach ($submissions as $submission) {
            $schema = $this->versions->get($form, $submission->schema_version)['schema'];

            $entries[] = [
                'received_at' => $this->date($submission->created_at),
                'edited_at' => $this->date($submission->edited_at),
                'answers' => $this->presenter->present($schema, $submission->data),
                'files' => $this->presenter->presentFiles($schema, $submission->media),
            ];
        }

        return view('pdf.submissions', [
            'title' => $form->title,
            'entries' => $entries,
            'generatedAt' => $this->date(now()),
        ])->render();
    }

    private function date(?DateTimeInterface $date): ?string
    {
        return $date === null
            ? null
            : CarbonImmutable::instance($date)->setTimezone(self::DISPLAY_TIMEZONE)->format(self::DATE_FORMAT);
    }
}
