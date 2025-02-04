<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentColumnsToProductCompanySpbproject extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_company_spbproject', function (Blueprint $table) {
            $table->dateTime('payment_date')->nullable()->after('due_date');
            $table->string('file_payment')->nullable()->after('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_company_spbproject', function (Blueprint $table) {
            $table->dropColumn('payment_date');
            $table->dropColumn('file_payment');
        });
    }
}
