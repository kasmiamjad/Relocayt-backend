<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProfileFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('street_address')->nullable();
        $table->string('apartment_number')->nullable();
        $table->string('city')->nullable();
        $table->string('province')->nullable();
        $table->string('country')->nullable();
    });
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['street_address', 'apartment_number', 'city', 'province', 'country']);
        });
    }
}
