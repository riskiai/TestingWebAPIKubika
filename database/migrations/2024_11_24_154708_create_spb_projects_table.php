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
            // Hapus tabel jika sudah ada (untuk update skema)
            Schema::dropIfExists('spb_projects');
            $table->string('doc_no_spb')->primary(); // Primary key doc_no
            $table->string('doc_type_spb')->nullable(); // Jenis dokumen (misalnya: SPB, Invoice)
            $table->unsignedBigInteger('spbproject_category_id')->nullable();
            $table->unsignedBigInteger('spbproject_status_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // role_kepalagudang
            $table->string('project_id')->nullable();
            $table->unsignedBigInteger('produk_id')->nullable();
            $table->date('tanggal_berahir_spb')->nullable(); // Tanggal berakhir SPB
            $table->date('tanggal_dibuat_spb')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->string('nama_toko')->nullable();
            $table->string('reject_note')->nullable();
            $table->timestamps(); // created_at dan updated_at

            // Menambahkan kolom-kolom baru di bawah
            $table->string('know_marketing')->nullable(); // Menyimpan informasi marketing
            $table->string('know_supervisor')->nullable();
            $table->string('know_kepalagudang')->nullable(); // Menyimpan informasi kepala gudang
            $table->string('request_owner')->nullable(); // Menyimpan informasi pemilik permintaan

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
