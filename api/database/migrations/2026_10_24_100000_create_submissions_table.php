<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // O índice da FK (form_id + PK implícita no InnoDB) atende a listagem por formulário ordenada por id.
            $table->foreignUlid('form_id')->constrained()->cascadeOnDelete();
            // Versão do schema com que a resposta foi enviada (AGENTS.md §4).
            $table->unsignedInteger('schema_version');
            $table->json('data');
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Hash da chave enviada no cabeçalho Idempotency-Key (nunca a chave pura).
            $table->char('idempotency_key', 64)->nullable();
            // Edição pelo respondente (D15): só o hash do token; janela fixa de 4 horas.
            $table->char('edit_token_hash', 64)->nullable();
            $table->timestamp('editable_until')->nullable();
            $table->timestamp('edited_at')->nullable();
            // Idempotência dos emails (fase 6): marcados logo após o envio; retentativas não reenviam.
            $table->timestamp('owner_notified_at')->nullable();
            $table->timestamp('respondent_notified_at')->nullable();
            $table->timestamps();

            // Garante no banco que a mesma chave nunca gera duas respostas, mesmo com corrida.
            $table->unique(['form_id', 'idempotency_key']);
            $table->index(['form_id', 'created_at']);
        });

        // Versões anteriores de uma resposta editada pelo respondente (auditoria; D15).
        Schema::create('submission_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained()->cascadeOnDelete();
            $table->json('data');
            // Metadados dos anexos substituídos na edição (nome, tamanho, tipo); os arquivos em si são apagados.
            $table->json('files')->nullable();
            $table->timestamp('replaced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_revisions');
        Schema::dropIfExists('submissions');
    }
};
