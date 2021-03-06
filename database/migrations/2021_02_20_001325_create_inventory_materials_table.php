<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_materials', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('plc_id')->nullable();
            $table->unsignedBigInteger('material1_id')->nullable();
            $table->unsignedBigInteger('location1_id')->nullable();
            $table->unsignedBigInteger('material2_id')->nullable();
            $table->unsignedBigInteger('location2_id')->nullable();
            $table->unsignedBigInteger('material3_id')->nullable();
            $table->unsignedBigInteger('location3_id')->nullable();
            $table->unsignedBigInteger('material4_id')->nullable();
            $table->unsignedBigInteger('location4_id')->nullable();
            $table->unsignedBigInteger('material5_id')->nullable();
            $table->unsignedBigInteger('location5_id')->nullable();
            $table->unsignedBigInteger('material6_id')->nullable();
            $table->unsignedBigInteger('location6_id')->nullable();
            $table->unsignedBigInteger('material7_id')->nullable();
            $table->unsignedBigInteger('location7_id')->nullable();
            $table->unsignedBigInteger('material8_id')->nullable();
            $table->unsignedBigInteger('location8_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();

            $table->foreign('material1_id')->references('id')->on('materials');
            $table->foreign('material2_id')->references('id')->on('materials');
            $table->foreign('material3_id')->references('id')->on('materials');
            $table->foreign('material4_id')->references('id')->on('materials');
            $table->foreign('material5_id')->references('id')->on('materials');
            $table->foreign('material6_id')->references('id')->on('materials');
            $table->foreign('material7_id')->references('id')->on('materials');
            $table->foreign('material8_id')->references('id')->on('materials');
            $table->foreign('location1_id')->references('id')->on('material_locations');
            $table->foreign('location2_id')->references('id')->on('material_locations');
            $table->foreign('location3_id')->references('id')->on('material_locations');
            $table->foreign('location4_id')->references('id')->on('material_locations');
            $table->foreign('location5_id')->references('id')->on('material_locations');
            $table->foreign('location6_id')->references('id')->on('material_locations');
            $table->foreign('location7_id')->references('id')->on('material_locations');
            $table->foreign('location8_id')->references('id')->on('material_locations');
            $table->foreign('company_id')->references('id')->on('companies');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_materials');
    }
}
