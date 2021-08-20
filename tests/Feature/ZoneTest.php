<?php

namespace Tests\Feature;

use App\Location;
use App\Zone;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->getJson('/api/zones')
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_store()
    {
        $location = factory(Location::class)->create([
            'customer_id' => $this->getAcsAdminUser()->id,
            'company_id' => $this->getAcsAdminUser()->company_id
        ]);

        $test_data = [
            'name' => $this->faker->name,
            'location_id' => $location->id,
            'customer_id' => $this->getAcsAdminUser()->id,
        ];

        $this->postJson('/api/zones', $test_data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Successfully created.');

        $this->assertNotNull(Zone::where('name', $test_data['name'])->first());

        $this->postJson('/api/zones', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_update()
    {
        $location = factory(Location::class)->create([
            'customer_id' => $this->getAcsAdminUser()->id,
            'company_id' => $this->getAcsAdminUser()->company_id
        ]);

        $zone = factory(Zone::class)->create([
            'location_id' => $location->id,
            'customer_id' => $this->getAcsAdminUser()->id,
            'company_id' => $location->company_id
        ]);

        $this->assertEquals($zone->name, Zone::find($zone->id)->name);

        $zone->name = $this->faker->name;

        $this->putJson('/api/zones/' . $zone->id, $zone->toArray())
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Successfully updated.');

        $this->assertEquals($zone->name, Zone::find($zone->id)->name);

        $this->putJson('/api/zones/' . $zone->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }
}
