<?php

declare(strict_types=1);

use App\Support\SearchText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Busca nas respostas do painel: o json de `data` escapa acentos (ã),
        // então um LIKE nele não acha "João". Esta cópia normalizada é mantida
        // pelo model sempre que `data` muda.
        Schema::table('submissions', function (Blueprint $table) {
            $table->text('search_text')->nullable()->after('data');
        });

        DB::table('submissions')->select(['id', 'data'])->orderBy('id')->lazyById(500)
            ->each(function (object $row): void {
                $answers = json_decode((string) $row->data, true);

                DB::table('submissions')->where('id', $row->id)->update([
                    'search_text' => SearchText::fromAnswers(is_array($answers) ? $answers : []),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
