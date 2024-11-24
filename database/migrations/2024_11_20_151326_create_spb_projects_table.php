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
            $table->id(); // Primary key
            $table->foreignId('spbproject_category_id')->constrained('spb_project__categories')->onDelete('cascade');
            $table->foreignId('spbproject_status_id')->constrained('spb_project__statuses')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // role_kepalagudang
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('produk_id')->constrained('products')->onDelete('cascade');
            $table->string('unit_kerja');
            $table->string('tanggal_dibuat_spb');
            $table->string('nama_barang');
            $table->string('type_pembelian'); // Contoh: box, satuan, dll
            $table->string('jumlah_barang');
            $table->string('nama_toko');
            $table->string('keterangan')->nullable();
            $table->timestamps(); // created_at dan updated_at
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
