<?php

namespace Tests\Feature;

use App\Device;
use App\Http\Controllers\DeviceController;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Mockery;
use GuzzleHttp\Psr7\Response as GuzzleHttpPsrResponse;


class DeviceTest extends TestCase
{
    protected $device;
    public function setUp(): void
    {
        parent::setUp();

        $this->device = factory(Device::class)->create([
            'company_id' => $this->getAcsAdminUser()->company_id
        ]);

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->postJson('/api/devices')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'is_visible_only',
                'hidden_devices',
                'devices',
                'companies',
                'last_page',
                'first_page_url',
                'from',
                'last_page_url',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ]);
    }

    public function test_import()
    {
        //response HTTPClient data
        $devices = [
            $this->device->toArray(),
            factory(Device::class)->make([
                'company_id' => $this->getAcsAdminUser()->company_id
            ])->toArray()
        ];

        //additional data for request
        foreach ($devices as $device) {
            $data['data'][] = array_merge($device, [
                'id' => $device['device_id'],
                'mac' => $device['lan_mac_address'],
                'serial' => $device['serial_number']
            ]);
        }

        $response = new GuzzleHttpPsrResponse(200, [], json_encode($data));

        $client = Mockery::mock(Client::class);

        $client->makePartial()
            ->shouldReceive('get')
            ->once()
            ->andReturn($response);

        $this->app->instance(Client::class, $client);

        $this->postJson('/api/devices/import')->assertJson([
            'numAdded' => 1,
            'numDuplicates' => 1
        ]);
    }

    public function test_device_assigned()
    {
        $this->postJson('/api/devices/device-assigned', [
            'plc_ip' => $this->faker->ipv4,
            'device_id' => $this->device->id,
            'company_id' => $this->device->company_id,
            'machine_id' => $this->device->machine_id,
            'device_name' => $this->device->name,
            'tcu_added' => $this->device->tcu_added
        ])
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Successfully assigned.');

        $this->postJson('/api/devices/device-assigned')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }
}
