<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->enum('estado_envio', ['pendiente', 'enviada', 'fallida'])->default('pendiente')->after('leido');
            $table->text('error_envio')->nullable()->after('estado_envio');
            $table->timestamp('enviada_at')->nullable()->after('error_envio');

            $table->index('estado_envio');
        });

        DB::table('notificaciones')->update([
            'estado_envio' => 'enviada',
            'enviada_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropIndex(['estado_envio']);
            $table->dropColumn(['estado_envio', 'error_envio', 'enviada_at']);
        });
    }
};
