<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTagIdToActiveAlarms extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('active_alarms', function (Blueprint $table) {

            if (env('DB_CONNECTION', 'sqlite') !== 'sqlite') {
                $table->unsignedBigInteger('tag_id');
            } else {
                $table->unsignedBigInteger('tag_id')->default('');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('active_alarms', function (Blueprint $table) {
            //
        });
    }
}
