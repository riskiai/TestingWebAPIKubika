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
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id'); 
            $table->unsignedBigInteger('user_id'); 
            $table->string('name');
            $table->string('billing')->nullable();
            $table->string('cost_estimate')->nullable(); 
            $table->string('margin')->nullable(); 
            $table->string('percent')->nullable(); 
            $table->string('status_cost_progres')->nullable();
            $table->string('file')->nullable();
            $table->string('date')->nullable();
            $table->string('request_status_owner')->nullable(); 
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
