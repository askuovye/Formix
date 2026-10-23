<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exportações CSV geradas em fila; arquivo temporário (expira e é apagado).
        Schema::create('exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('form_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            // Período exportado (filtros usados ao pedir).
            $table->timestamp('from')->nullable();
            $table->timestamp('to')->nullable();
            $table->string('disk', 50)->nullable();
            $table->string('path')->nullable();
            $table->unsignedInteger('rows_count')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
