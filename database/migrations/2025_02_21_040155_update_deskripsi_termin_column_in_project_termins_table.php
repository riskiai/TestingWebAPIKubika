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
        Schema::table('project_termins', function (Blueprint $table) {
            $table->text('deskripsi_termin')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_termins', function (Blueprint $table) {
            $table->string('deskripsi_termin', 255)->nullable()->change();
        });
    }
};
