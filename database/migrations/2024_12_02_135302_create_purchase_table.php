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
        Schema::create('purchase', function (Blueprint $table) {
            $table->string('doc_no')->primary(); // Primary key doc_no
            $table->string('doc_type')->nullable();
            $table->string('project_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('purchase_category_id'); 
            $table->unsignedBigInteger('purchase_status_id');
            $table->integer('type_purchase_id')->nullable();
            $table->string('tab_purchase')->default('1');
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->string('sub_total')->nullable();
            $table->string('ppn')->nullable();
            $table->string('pph')->nullable();
            $table->string('reject_note_purchase')->nullable();
            $table->date('date')->nullable();
            $table->date('due_date')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('purchase_category_id')->references('id')->on('purchase_category')->onDelete('cascade');
            $table->foreign('purchase_status_id')->references('id')->on('purchase_status')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase');
    }
};
