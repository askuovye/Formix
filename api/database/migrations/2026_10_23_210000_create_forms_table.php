<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // O índice da FK (user_id + PK implícita no InnoDB) atende a listagem por dono ordenada por id.
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            // Único inclusive entre excluídos: um link antigo nunca passa a abrir outro formulário.
            $table->string('slug', 80)->unique();
            $table->string('status', 20);
            // Rascunho de trabalho; o que o público vê fica em form_versions.
            $table->json('schema');
            // Número da última versão publicada (0 = nunca publicado).
            $table->unsignedInteger('schema_version')->default(0);
            $table->boolean('has_unpublished_changes')->default(true);
            $table->json('settings');
            // Contador mantido na transação de envio: checar o limite sem COUNT(*) a cada resposta.
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        // Snapshot imutável do conteúdo a cada publicação (AGENTS.md §4).
        Schema::create('form_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('schema');
            $table->timestamp('published_at');

            $table->unique(['form_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('forms');
    }
};
