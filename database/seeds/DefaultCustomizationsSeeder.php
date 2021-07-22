<?php

use Illuminate\Database\Seeder;

class DefaultCustomizationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('default_customizations')->delete();

        $ngx_dryer = [
            [
                "id"=> 14903,
                "name"=> "Dew Point Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 18,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14931,
                "name"=> "Dirty Filter Bit",
                "configuration_id"=> 6,
                "tag_id"=> 39,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14905,
                "name"=> "Regen Left Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 20,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14906,
                "name"=> "Regen Right Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 21,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14896,
                "name"=> "Drying Hopper 1 Outlet Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 11,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ]
        ];

        $bd_blender = [
            [
                "id"=> 14560,
                "name"=> "Actual Weight[1]",
                "configuration_id"=> 1,
                "tag_id"=> 14,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1000
            ],
            [
                "id"=> 14561,
                "name"=> "Actual Weight[2]",
                "configuration_id"=> 1,
                "tag_id"=> 14,
                "type"=> "line",
                "offset"=> 1,
                "divided_by"=> 1000
            ],
            [
                "id"=> 14548,
                "name"=> "Process Rate",
                "configuration_id"=> 1,
                "tag_id"=> 18,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ]
        ];

        $customizations = [
            [
                'machine_id' => MACHINE_NGX_DRYER,
                'customization' => json_encode($ngx_dryer)
            ], [
                'machine_id' => MACHINE_BD_BATCH_BLENDER,
                'customization' => json_encode($bd_blender)
            ]
        ];

        DB::table('default_customizations')->insert($customizations);
    }
}
