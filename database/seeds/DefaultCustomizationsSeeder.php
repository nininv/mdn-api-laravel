<?php

use Illuminate\Database\Seeder;
use App\MachineTag;

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
        $db_blender_actual_weights1 = MachineTag::where('configuration_id', 1)->where('tag_id', 14)->where('offset', 0)->first();
        $db_blender_actual_weights2 = MachineTag::where('configuration_id', 1)->where('tag_id', 14)->where('offset', 1)->first();
        $db_blender_process_rate = MachineTag::where('configuration_id', 1)->where('tag_id', 18)->where('offset', 0)->first();

        $ngx_dryer_dew_point_temp = MachineTag::where('configuration_id', 6)->where('tag_id', 18)->where('offset', 0)->first();
        $ngx_dryer_firty_filter_bit = MachineTag::where('configuration_id', 6)->where('tag_id', 39)->where('offset', 0)->first();
        $ngx_dryer_regen_left_air_temp = MachineTag::where('configuration_id', 6)->where('tag_id', 20)->where('offset', 0)->first();
        $ngx_dryer_regen_right_air_temp = MachineTag::where('configuration_id', 6)->where('tag_id', 21)->where('offset', 0)->first();
        $ngx_dryer_hopper_1_outlet_air_temp = MachineTag::where('configuration_id', 6)->where('tag_id', 11)->where('offset', 0)->first();

        $ngx_dryer = [
            [
                "id"=> $ngx_dryer_dew_point_temp->id,
                "name"=> "Dew Point Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 18,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> $ngx_dryer_firty_filter_bit->id,
                "name"=> "Dirty Filter Bit",
                "configuration_id"=> 6,
                "tag_id"=> 39,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> $ngx_dryer_regen_left_air_temp->id,
                "name"=> "Regen Left Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 20,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> $ngx_dryer_regen_right_air_temp->id,
                "name"=> "Regen Right Air Temperature",
                "configuration_id"=> 6,
                "tag_id"=> 21,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1
            ],
            [
                "id"=> $ngx_dryer_hopper_1_outlet_air_temp->id,
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
                "id"=> $db_blender_actual_weights1->id,
                "name"=> "Actual Weight[1]",
                "configuration_id"=> 1,
                "tag_id"=> 14,
                "type"=> "line",
                "offset"=> 0,
                "divided_by"=> 1000
            ],
            [
                "id"=> $db_blender_actual_weights2->id,
                "name"=> "Actual Weight[2]",
                "configuration_id"=> 1,
                "tag_id"=> 14,
                "type"=> "line",
                "offset"=> 1,
                "divided_by"=> 1000
            ],
            [
                "id"=> $db_blender_process_rate->id,
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
