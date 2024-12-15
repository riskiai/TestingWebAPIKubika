<?php

use App\Models\ManPower;
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
        Schema::create('log_man_powers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ManPower::class)->references("id")->on("man_powers")->cascadeOnDelete();
            $table->string("created_by", 100)->default("-");
            $table->text("message")->default("-");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_man_powers');
    }
};
