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
        Schema::table('spb_project_termins', function (Blueprint $table) {
            $table->string('type_termin_spb')->after('tanggal')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spb_project_termins', function (Blueprint $table) {
            $table->dropColumn('type_termin_spb');
        });
    }
};
