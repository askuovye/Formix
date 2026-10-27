<?php

declare(strict_types=1);

use App\Enums\DeviceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Resultados: tempo de preenchimento (medido no navegador, informativo) e aparelho.
        Schema::table('submissions', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('user_agent');
            $table->string('device', 10)->nullable()->after('duration_seconds');
        });

        // Respostas antigas: aparelho deduzido do User-Agent já gravado.
        DB::table('submissions')->select(['id', 'user_agent'])->whereNotNull('user_agent')->orderBy('id')->lazyById(500)
            ->each(function (object $row): void {
                $device = DeviceType::fromUserAgent(is_string($row->user_agent) ? $row->user_agent : null);

                if ($device !== null) {
                    DB::table('submissions')->where('id', $row->id)->update(['device' => $device->value]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['duration_seconds', 'device']);
        });
    }
};
