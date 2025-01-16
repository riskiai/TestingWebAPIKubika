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
        Schema::table('spb_projects', function (Blueprint $table) {
            $table->string('harga_total_pembayaran_borongan_spb')->nullable()->after('subtotal');
            $table->string('harga_termin_spb')->nullable()->after('harga_total_pembayaran_borongan_spb');
            $table->string('deskripsi_termin_spb')->nullable()->after('harga_termin_spb');
            $table->string('type_termin_spb')->nullable()->after('deskripsi_termin_spb'); // 1=Lunas, 2=Belum Lunas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spb_projects', function (Blueprint $table) {
            $table->dropColumn([
                'harga_total_pembayaran_borongan_spb',
                'harga_termin_spb',
                'deskripsi_termin_spb',
                'type_termin_spb',
            ]);
        });
    }
};
