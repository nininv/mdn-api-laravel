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
                "id"=> 14067,
                "name"=> "Dew Point Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 18,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14095,
                "name"=> "Dirty Filter Bit",
                "configuration_id"=> 6,
                "tag_id"=> 39,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14069,
                "name"=> "Regen Left Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 20,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14070,
                "name"=> "Regen Right Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 21,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> 14060,
                "name"=> "Drying Hopper 1 Outlet Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 11,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ]
        ];

        $customizations = [
            [
                'machine_id' => MACHINE_NGX_DRYER,
                'customization' => json_encode($ngx_dryer)
            ]
        ];

        DB::table('default_customizations')->insert($customizations);
    }
}
