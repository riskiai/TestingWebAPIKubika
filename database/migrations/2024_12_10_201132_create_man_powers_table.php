<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('man_powers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->references("id")->on("users")->cascadeOnDelete();
            $table->foreignIdFor(Project::class)->references("id")->on("projects")->cascadeOnDelete();
            $table->boolean('work_type')->default(true)->comment("1: Tukang Harian, 0: Tukang Borongan");
            $table->boolean('project_type')->default(true)->comment("1: Aktif Project, 0: Non Project");
            $table->unsignedInteger('daily_salary_master')->default(0);
            $table->unsignedInteger('hourly_salary_master')->default(0);
            $table->unsignedInteger('hourly_overtime_salary_master')->default(0);
            $table->double('hour_salary')->default(0);
            $table->double('hour_overtime')->default(0);
            $table->unsignedInteger('current_salary')->default(0);
            $table->unsignedInteger('current_overtime_salary')->default(0);
            $table->text('description');
            $table->string("created_by", 100)->default("-");
            $table->timestamp("entry_at")->default(DB::raw("CURRENT_TIMESTAMP"));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('man_powers');
    }
};
