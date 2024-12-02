<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('product_spb_project', function (Blueprint $table) {
            $table->id();
            // Referensikan kolom 'doc_no_spb' di spb_projects
            $table->string('spb_project_id');  // Ubah ke string karena doc_no_spb adalah string
            $table->foreign('spb_project_id')->references('doc_no_spb')->on('spb_projects')->onDelete('cascade');
            
            // Referensikan kolom 'id' di products
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_spb_project');
    }
};
