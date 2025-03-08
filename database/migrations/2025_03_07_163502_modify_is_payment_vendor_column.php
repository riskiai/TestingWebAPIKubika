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
            $table->tinyInteger('is_payment_vendor')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spb_projects', function (Blueprint $table) {
            $table->tinyInteger('is_payment_vendor')->default(1)->change();
        });
    }
};
