<?php

declare(strict_types=1);

use App\Services\Submissions\RespondentEmail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Todas as respostas desta pessoa" (painel): email do primeiro campo de
        // email, em minúsculas, mantido pelas Actions de envio e edição.
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('respondent_email')->nullable()->after('search_text');
            $table->index('respondent_email');
        });

        // A exportação leva o mesmo filtro da lista.
        Schema::table('exports', function (Blueprint $table) {
            $table->string('respondent_email')->nullable()->after('search');
        });

        /** @var array<string, array{fields: list<array<string, mixed>>}> $schemas */
        $schemas = [];

        DB::table('submissions')->select(['id', 'form_id', 'schema_version', 'data'])->orderBy('id')->lazyById(500)
            ->each(function (object $row) use (&$schemas): void {
                $key = $row->form_id.'#'.$row->schema_version;

                if (! array_key_exists($key, $schemas)) {
                    $schema = DB::table('form_versions')
                        ->where('form_id', $row->form_id)
                        ->where('version', $row->schema_version)
                        ->value('schema');
                    $decoded = json_decode((string) $schema, true);
                    $schemas[$key] = is_array($decoded) && is_array($decoded['fields'] ?? null) ? $decoded : ['fields' => []];
                }

                $answers = json_decode((string) $row->data, true);
                /** @var array{fields: list<array<string, mixed>>} $schema */
                $schema = $schemas[$key];
                $email = RespondentEmail::from($schema, is_array($answers) ? $answers : []);

                if ($email !== null) {
                    DB::table('submissions')->where('id', $row->id)->update(['respondent_email' => $email]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn('respondent_email');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['respondent_email']);
            $table->dropColumn('respondent_email');
        });
    }
};
