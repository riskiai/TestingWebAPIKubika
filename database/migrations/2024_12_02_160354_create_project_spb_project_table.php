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
        Schema::create('project_spb_project', function (Blueprint $table) {
            $table->id();
            $table->string('spb_project_id');
            $table->foreign('spb_project_id')->references('doc_no_spb')->on('spb_projects')->onDelete('cascade');

            // Menyimpan id project
            $table->string('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_spb_project');
    }
};
