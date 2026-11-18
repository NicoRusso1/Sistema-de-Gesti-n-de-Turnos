<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('password_hash')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
            $table->string('password_hash')->nullable(false)->change();
        });
    }
};
