<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeColumnsForDeviceConfigurations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (env('DB_CONNECTION', 'sqlite') !== 'sqlite') {
            DB::statement('ALTER TABLE device_configurations
                    ALTER COLUMN "plc_status" DROP NOT NULL,
                    ALTER COLUMN "plc_type" DROP NOT NULL,
                    ALTER COLUMN "plc_serial_number" DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
