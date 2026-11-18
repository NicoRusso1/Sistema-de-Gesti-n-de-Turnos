<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            // Cuándo salió el recordatorio por Telegram (vía n8n). Independiente del email.
            $table->timestamp('telegram_enviado_at')->nullable()->after('enviada_at');
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropColumn('telegram_enviado_at');
        });
    }
};
