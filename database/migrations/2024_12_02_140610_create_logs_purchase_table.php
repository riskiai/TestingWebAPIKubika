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
        Schema::create('logs_purchase', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_id');  // Kolom yang merujuk ke doc_no_spb
            $table->integer('tab_purchase');
            $table->string('name');
            $table->string('message')->nullable(); // Menambahkan kolom message
            $table->timestamps();  // Menambahkan kolom created_at dan updated_at
            
            // Menambahkan relasi foreign key
            $table->foreign('purchase_id')->references('doc_no')->on('purchase')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_purchase');
    }
};
