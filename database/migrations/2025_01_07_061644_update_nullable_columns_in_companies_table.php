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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
            $table->string('npwp')->nullable()->change();
            $table->string('pic_name')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
            $table->string('npwp')->nullable(false)->change();
            $table->string('pic_name')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
