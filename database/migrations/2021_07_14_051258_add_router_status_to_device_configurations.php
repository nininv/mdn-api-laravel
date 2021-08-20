<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRouterStatusToDeviceConfigurations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('device_configurations', function (Blueprint $table) {
            $table->boolean('router_status')->nullable();
            $table->unsignedBigInteger('router_update_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (env('DB_CONNECTION', 'sqlite') !== 'sqlite') {
            Schema::table('device_configurations', function (Blueprint $table) {
                $table->dropColumn('router_status');
                $table->dropColumn('router_update_time');
            });
        }
    }
}
