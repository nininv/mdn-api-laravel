<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimestampToDeviceConfigurations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('device_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('plc_update_time')->nullable();
            $table->unsignedBigInteger('tcu_update_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('device_configurations', function (Blueprint $table) {
            //
        });
    }
}
