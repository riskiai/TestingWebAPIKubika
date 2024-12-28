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
            $table->text('description')->nullable()->after('type_pembelian_produk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_company_spbproject', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
