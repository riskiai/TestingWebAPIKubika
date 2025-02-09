<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('project_termins', function (Blueprint $table) {
            $table->text('file_attachment_pembayaran')->change(); // Ubah menjadi TEXT
        });
    }

    public function down()
    {
        Schema::table('project_termins', function (Blueprint $table) {
            $table->string('file_attachment_pembayaran', 255)->change(); // Kembalikan ke VARCHAR(255) jika rollback
        });
    }
};
