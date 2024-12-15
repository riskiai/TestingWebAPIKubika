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
            $table->string('ongkir', 255)->nullable()->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_company_spbproject', function (Blueprint $table) {
            $table->dropColumn('ongkir');
        });
    }
};
