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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('file_pembayaran_termin')->nullable()->after('no_dokumen_project');
            $table->string('deskripsi_termin_proyek')->nullable()->after('file_pembayaran_termin');
            $table->string('type_termin_proyek')->nullable()->after('deskripsi_termin_proyek');
            $table->string('harga_termin_proyek')->nullable()->after('type_termin_proyek');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'file_pembayaran_termin',
                'deskripsi_termin_proyek',
                'type_termin_proyek',
                'harga_termin_proyek'
            ]);
        });
    }
};
