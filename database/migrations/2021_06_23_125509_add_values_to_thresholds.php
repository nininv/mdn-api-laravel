<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddValuesToThresholds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (env('DB_CONNECTION', 'sqlite') !== 'sqlite') {
            DB::statement('ALTER TABLE thresholds
                    ALTER COLUMN "value" DROP NOT NULL,
                    ALTER COLUMN "is_running" DROP NOT NULL,
                    ALTER COLUMN "email_checked" SET default false');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('thresholds', function (Blueprint $table) {
            //
        });
    }
}
