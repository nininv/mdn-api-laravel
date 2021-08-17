<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddColumnsToDevices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (env('DB_CONNECTION', 'sqlite') !== 'sqlite') {
            DB::statement('ALTER TABLE devices
                    ALTER COLUMN device_id TYPE bigint USING device_id::bigint,
                    ALTER COLUMN serial_number TYPE bigint USING serial_number::bigint');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            //
        });
    }
}
