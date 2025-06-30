<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtraTimePriceInBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {

            if (!Schema::hasColumn('bookings', 'service_id')) {
                $table->foreignId('service_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            }

            if (!Schema::hasColumn('bookings', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            }

            if (!Schema::hasColumn('bookings', 'extra_time_price')) {
                $table->double('extra_time_price')->nullable();
            }

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign('bookings_service_id_foreign');
            $table->dropColumn('service_id');
            $table->dropForeign('bookings_category_id_foreign');
            $table->dropColumn('category_id');
            $table->dropColumn('extra_time_price');
        });
    }
}
