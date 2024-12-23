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
        Schema::table('product_company_spbproject', function (Blueprint $table) {
            $table->date('date')->nullable()->after('status_produk'); // Tambahkan kolom `date`
            $table->date('due_date')->nullable()->after('date'); // Tambahkan kolom `due_date`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_company_spbproject', function (Blueprint $table) {
            $table->dropColumn('date'); // Hapus kolom `date`
            $table->dropColumn('due_date'); // Hapus kolom `due_date`
        });
    }
};
