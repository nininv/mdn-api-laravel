<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSerialNumberToSerialNumberUnit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('serial_number_unit', function (Blueprint $table) {
            if (!Schema::hasColumn('serial_number_unit', 'serial_number')) {
                $table->string('serial_number', 20)->default('');
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
        Schema::table('serial_number_unit', function (Blueprint $table) {
            $table->dropColumn('serial_number');
        });
    }
}
