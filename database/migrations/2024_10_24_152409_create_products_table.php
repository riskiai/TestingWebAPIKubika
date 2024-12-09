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
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Primary key: id produk
            $table->string('nama'); // Nama produk
            $table->unsignedBigInteger('id_kategori')->nullable(); 
            $table->text('deskripsi')->nullable(); 
            $table->string('kode_produk')->unique(); 
            $table->string('type_pembelian')->nullable(); 
            $table->string('harga')->nullable(); 
            $table->integer('stok')->nullable();
            $table->string('ongkir')->nullable();
            $table->timestamps(); 

            // Foreign key constraint
            $table->foreign('id_kategori')->references('id')->on('kategori')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
