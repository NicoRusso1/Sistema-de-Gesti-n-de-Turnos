<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->enum('estado_emision', ['pendiente', 'emitida', 'fallida'])->default('pendiente')->after('certificate_url');
            $table->text('error_emision')->nullable()->after('estado_emision');
            $table->timestamp('ultimo_intento_at')->nullable()->after('error_emision');
            $table->dateTime('issue_date')->nullable()->change();

            $table->index('estado_emision');
        });

        // Los registros que ya tienen factura quedaron emitidos con el flujo anterior
        DB::table('certificados')->whereNotNull('invoice_url')->update(['estado_emision' => 'emitida']);
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropIndex(['estado_emision']);
            $table->dropColumn(['estado_emision', 'error_emision', 'ultimo_intento_at']);
        });
    }
};
