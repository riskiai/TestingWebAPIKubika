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
            $table->unsignedBigInteger('id_kategori'); // Foreign key: id kategori
            $table->text('deskripsi')->nullable(); // Deskripsi produk
            $table->string('kode_produk')->unique(); // Kode produk, harus unik
            $table->string('type_pembelian')->nullable(); // Contoh: box, satuan, dll
            $table->string('harga')->nullable(); // Contoh: box, satuan, dll
            $table->integer('stok'); // Stok produk
            $table->timestamps(); // Created at & updated at

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
