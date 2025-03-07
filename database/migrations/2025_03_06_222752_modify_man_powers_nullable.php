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
        Schema::table('man_powers', function (Blueprint $table) {
            // Ubah kolom project_id agar nullable (tetap sebagai foreign key)
            $table->char('project_id', 36)->nullable()->change();
            
            // Ubah kolom user_id agar nullable (tetap sebagai foreign key)
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('man_powers', function (Blueprint $table) {
            // Kembalikan project_id ke NOT NULL
            $table->char('project_id', 36)->nullable(false)->change();
            
            // Kembalikan user_id ke NOT NULL
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
