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
        Schema::table('spb_project_termins', function (Blueprint $table) {
            $table->unsignedBigInteger('file_attachment_id')->nullable()->after('type_termin_spb');
            $table->foreign('file_attachment_id')->references('id')->on('document_spb')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spb_project_termins', function (Blueprint $table) {
            $table->dropForeign(['file_attachment_id']);
            $table->dropColumn('file_attachment_id');
        });
    }
};
