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
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contact_type_id')->nullable();
            $table->string('name');
            $table->string('address');
            $table->string('npwp');
            $table->string('pic_name');
            $table->string('phone');
            $table->string('email');
            $table->string('file')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch')->nullable();
            $table->string('account_name')->nullable();
            $table->string('currency')->nullable();
            $table->string('account_number')->nullable();
            $table->string('swift_code')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('contact_type_id')->references('id')->on('contact_type')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
