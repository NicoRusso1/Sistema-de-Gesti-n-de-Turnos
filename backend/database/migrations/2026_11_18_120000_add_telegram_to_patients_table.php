<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // chat_id de Telegram del paciente, lo carga n8n cuando el paciente vincula el bot
            $table->string('telegram_chat_id', 64)->nullable()->unique()->after('phone');
            // Token de un solo uso que viaja en el link t.me/<bot>?start=<token>
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropUnique(['telegram_link_token']);
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token']);
        });
    }
};
