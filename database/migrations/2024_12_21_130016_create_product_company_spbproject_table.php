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
        Schema::create('product_company_spbproject', function (Blueprint $table) {
            Schema::dropIfExists('product_company_spbproject');
            $table->id();
            $table->string('spb_project_id')->nullable();
            $table->foreign('spb_project_id')->references('doc_no_spb')->on('spb_projects')->onDelete('cascade');
            
            $table->unsignedBigInteger('produk_id')->nullable();
            $table->foreign('produk_id')->references('id')->on('products')->onDelete('cascade');
        
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->string('ongkir')->nullable();
            $table->string('harga')->nullable();
            $table->string('stok')->nullable();
            $table->string('ppn')->nullable();
            $table->string('pph')->nullable();
            $table->string('subtotal_produk')->nullable();
            $table->string('status_produk')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_company_spbproject');
    }
};
