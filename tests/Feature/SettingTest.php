<?php

namespace Tests\Feature;

use App\Setting;
use App\Swatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SettingTest extends TestCase
{
    private $setting_default_data = [
        [
            'type' => 'color_background',
            'value' => '#eeeeef'
        ], [
            'type' => 'color_primary',
            'value' => '#096288'
        ], [
            'type' => 'color_secondary',
            'value' => '#c8c62e'
        ], [
            'type' => 'color_accent',
            'value' => '#0f2d52'
        ], [
            'type' => 'color_surface',
            'value' => '#5a5d61'
        ], [
            'type' => 'color_success',
            'value' => '#06d6a0'
        ], [
            'type' => 'color_info',
            'value' => '#29b1b8'
        ], [
            'type' => 'color_warning',
            'value' => '#ffd166'
        ], [
            'type' => 'color_error',
            'value' => '#623266'
        ], [
            'type' => 'page_title',
            'value' => 'ACS Digital Solutions'
        ], [
            'type' => 'is_all_devices_visible',
            'value' => 'all'
        ]
    ];

    private $app_colors = [
        [
            'name' => 'Background',
            'key' => 'color_background',
            'color' => '#eeeeef'
        ], [
            'name' => 'Primary',
            'key' => 'color_primary',
            'color' => '#096288'
        ], [
            'name' => 'Secondary',
            'key' => 'color_secondary',
            'color' => '#c8c62e'
        ], [
            'name' => 'Accent',
            'key' => 'color_accent',
            'color' => '#0f2d52'
        ], [
            'name' => 'Surface',
            'key' => 'color_surface',
            'color' => '#5a5d61'
        ], [
            'name' => 'Success',
            'key' => 'color_success',
            'color' => '#06d6a0'
        ], [
            'name' => 'Info',
            'key' => 'color_info',
            'color' => '#29b1b8'
        ], [
            'name' => 'Warning',
            'key' => 'color_warning',
            'color' => '#ffd166'
        ], [
            'name' => 'Error',
            'key' => 'color_error',
            'color' => '#623266'
        ]
    ];

     public function test_get_setting()
     {
         $this->postJson('/api/app-settings/get-setting')
               ->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'value'
             ]);
     }

     public function test_website_colors()
     {
         $data = [
             'colors' => $this->app_colors
         ];

         $this->postJson('/api/app-settings/website-colors', $data)
             ->assertStatus(Response::HTTP_OK)
             ->assertExactJson([
                 'message' => 'Successfully updated'
             ]);
     }

     public function test_upload_logo()
     {
         Storage::fake('local');

         $file_name = 'logo.png';

         $data = [
             'logo' => UploadedFile::fake()->image($file_name)
         ];

         $this->postJson('/api/app-settings/upload-logo', $data)
             ->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'filepath',
                 'message'
             ]);

         $logo_filename = Setting::where('type', 'logo_filepath')->first();

         $this->assertEquals($logo_filename->value, asset('assets/app/img'). '/' . $file_name);

        //assert fails, check validation
         $this->postJson('/api/app-settings/upload-logo', [])
             ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
     }

     public function test_upload_image()
     {
         Storage::fake('local');

         $file_name = 'logo.png';

         $data = [
             'image' => UploadedFile::fake()->image($file_name)
         ];

         $this->postJson('/api/app-settings/upload-image', $data)
             ->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'filepath',
                 'message'
             ]);

         $logo_filename = Setting::where('type', 'auth_background_filepath')->first();

         $this->assertEquals($logo_filename->value, asset('assets/app/img'). '/' . $file_name);

         //assert fails, check validation
         $this->postJson('/api/app-settings/upload-image', [])
             ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
     }

     public function test_update_auth_background()
     {
         $response = $this->postJson('/api/app-settings/update-auth-background');

         $response->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'filepath'
             ]);

         $auth_background = Setting::where('type', 'auth_background_filepath')->first();

         $this->assertNotNull($auth_background);
         $this->assertArrayHasKey('filepath', $response);
         $this->assertEquals($response['filepath'], $auth_background->value);
     }

    public function test_reset()
    {
        Setting::insert($this->setting_default_data);

        $this->postJson('/api/app-settings/reset')
            ->assertStatus(Response::HTTP_OK)
            ->assertExactJson([
                'success' => 'Reset Successfully.'
            ]);

        $this->assertEquals(NULL, Setting::where('type', 'logo_filepath')
            ->orWhere('type', 'auth_background_filepath')
            ->first());
    }

     public function test_set_product_info()
     {
         $data = [
             'productName' => 'Test productName',
             'productVersion' => 123
         ];

         $this->postJson('/api/app-settings/set-product-info', $data)
             ->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'product_name',
                 'product_version',
                 'message'
             ]);

         //Check validator
         $this->postJson('/api/app-settings/set-product-info', [])
             ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
     }

     public function test_set_page_title()
     {
         $data = [
             'pageTitle' => 'Test pageTitle'
         ];

         $this->postJson('/api/app-settings/set-page-title', $data)
             ->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'page_title',
                 'message'
             ]);

         //Check validator
         $this->postJson('/api/app-settings/set-page-title', [])
             ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
     }

     public function test_grab_colors()
     {
         //assert success
         $data = [
         	 'url' => 'https://github.com/'
         ];

         $this->postJson('/api/app-settings/grab-colors', $data)
             ->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'colors'
             ]);

         $this->assertNotNull(Swatch::where('site_url', $data['url'])->first());

         //assert empty answer
         $data = [
             'url' => 'https://test.test/'
         ];

         $this->postJson('/api/app-settings/grab-colors', $data)
             ->assertStatus(Response::HTTP_OK)
             ->assertJsonStructure([
                 'colors' => []
             ]);
     }
}
