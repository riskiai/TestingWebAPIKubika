<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('man_powers', function (Blueprint $table) {
            $table->unsignedBigInteger('deleted_by')
                  ->nullable()
                  ->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('man_powers', function (Blueprint $table) {
            $table->dropColumn('deleted_by');
        });
    }
};
