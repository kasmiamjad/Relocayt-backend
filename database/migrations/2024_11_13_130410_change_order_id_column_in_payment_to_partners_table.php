<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeOrderIdColumnInPaymentToPartnersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        try {
            Schema::table('payment_to_partners', function (Blueprint $table) {
                $table->dropForeign('payment_to_partners_order_id_foreign');
                $table->dropColumn('order_id');
                $table->morphs('model');
            });
        } catch (Throwable) {}

        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->double('tips')->default(0)->comment('Чаевые');
            });
        } catch (Throwable) {}
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('payment_to_partners', function (Blueprint $table) {
            //
        });
    }
}
