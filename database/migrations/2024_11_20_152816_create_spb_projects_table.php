<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spb_projects', function (Blueprint $table) {
            Schema::dropIfExists('spb_projects');
            $table->bigIncrements('id'); // Primary key
            $table->unsignedBigInteger('spbproject_category_id')->nullable();
            $table->unsignedBigInteger('spbproject_status_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // role_kepalagudang
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('produk_id')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('tanggal_dibuat_spb')->nullable();
            $table->string('nama_barang')->nullable();
            $table->string('type_pembelian')->nullable(); // Contoh: box, satuan, dll
            $table->string('jumlah_barang')->nullable();
            $table->string('nama_toko')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps(); // created_at dan updated_at

            // Foreign key constraints
            $table->foreign('spbproject_category_id')->references('id')->on('spb_project__categories')->onDelete('cascade');
            $table->foreign('spbproject_status_id')->references('id')->on('spb_project__statuses')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('produk_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spb_projects');
    }
};
