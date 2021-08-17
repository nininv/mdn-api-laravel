<?php

use Illuminate\Database\Seeder;
use App\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Setting::query()->delete();;

        $app_colors = [
            [
                'name' => 'Background',
                'type' => 'color_background',
                'value' => '#eeeeef'
            ], [
                'name' => 'Primary',
                'type' => 'color_primary',
                'value' => '#096288'
            ], [
                'name' => 'Secondary',
                'type' => 'color_secondary',
                'value' => '#c8c62e'
            ], [
                'name' => 'Accent',
                'type' => 'color_accent',
                'value' => '#0f2d52'
            ], [
                'name' => 'Surface',
                'type' => 'color_surface',
                'value' => '#5a5d61'
            ], [
                'name' => 'Success',
                'type' => 'color_success',
                'value' => '#06d6a0'
            ], [
                'name' => 'Info',
                'type' => 'color_info',
                'value' => '#29b1b8'
            ], [
                'name' => 'Warning',
                'type' => 'color_warning',
                'value' => '#ffd166'
            ], [
                'name' => 'Error',
                'type' => 'color_error',
                'value' => '#623266'
            ]
        ];

        $data = [
            [
                'type' => 'page_title',
                'value' => 'ACS Digital Solutions'
            ], [
                'type' => 'is_all_devices_visible',
                'value' => 'all'
            ]
        ];

        /* There are more setting types:
         * logo_file_name
         * logo_filepath
         * auth_background_filepath
         * product_name
         * product_version
         * */

        foreach($app_colors as $setting) {
            Setting::insert([
                'type' => $setting['type'],
                'value' => $setting['value']
            ]);
        }

        foreach($data as $setting) {
            Setting::insert([
                'type' => $setting['type'],
                'value' => $setting['value']
            ]);
        }

    }
}
