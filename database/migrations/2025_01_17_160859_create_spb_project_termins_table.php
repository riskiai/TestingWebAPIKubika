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
        Schema::create('spb_project_termins', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no_spb'); // Relasi ke spb_projects
            $table->string('harga_termin');
            $table->string('deskripsi_termin');
            $table->date('tanggal'); // Tanggal termin
            $table->timestamps();

            // Foreign key untuk menjaga konsistensi data
            $table->foreign('doc_no_spb')
                ->references('doc_no_spb')
                ->on('spb_projects')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spb_project_termins');
    }
};
