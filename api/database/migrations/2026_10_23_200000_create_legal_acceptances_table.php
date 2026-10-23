<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prova do consentimento (LGPD): uma linha por documento e versão aceitos.
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('document', 20);
            $table->string('version', 20);
            $table->timestamp('accepted_at');
            $table->char('ip_hash', 64)->nullable();

            $table->index(['user_id', 'document', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
