<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Imagens de capa e logo do tema (D32): públicas e imutáveis. O tema de
        // cada versão aponta para um id; a linha só sai quando nada a referencia.
        Schema::create('form_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('disk', 50);
            $table->string('path');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_assets');
    }
};
