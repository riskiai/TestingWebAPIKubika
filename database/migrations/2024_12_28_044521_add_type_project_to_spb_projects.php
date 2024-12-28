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
        Schema::table('spb_projects', function (Blueprint $table) {
            $table->string('type_project', 255)->nullable()->after('approve_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spb_projects', function (Blueprint $table) {
            $table->dropColumn('type_project');
        });
    }
};
