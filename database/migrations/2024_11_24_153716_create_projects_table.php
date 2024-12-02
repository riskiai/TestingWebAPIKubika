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
        Schema::create('projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('produk_id')->nullable();
            $table->string('name')->nullable();
            $table->string('billing')->nullable();
            $table->string('cost_estimate')->nullable();
            $table->string('margin')->nullable();
            $table->string('percent')->nullable();
            $table->string('status_cost_progres')->nullable();
            $table->string('file')->nullable();
            $table->string('spb_file')->nullable();
            $table->string('date')->nullable();
            $table->string('request_status_owner')->nullable();
            $table->string('status_step_project', 100)->nullable();
            $table->timestamps();

        });

        // Menambahkan kembali foreign key setelah perubahan tipe kolom
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('produk_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Menghapus tabel dan foreign key
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['produk_id']);
            $table->dropForeign(['user_id']);
            $table->dropIndex(['produk_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::dropIfExists('projects');
    }
};
