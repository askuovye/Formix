<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Busca usada ao pedir (mesma do painel): o arquivo sai igual à lista filtrada.
        // Formato: CSV (planilha) ou PDF (uma resposta por página, D35).
        Schema::table('exports', function (Blueprint $table) {
            $table->string('search', 100)->nullable()->after('to');
            $table->string('format', 10)->default('csv')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropColumn(['search', 'format']);
        });
    }
};
