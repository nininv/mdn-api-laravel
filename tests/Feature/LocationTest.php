<?php

namespace Tests\Feature;

use App\Location;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LocationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->getJson('/api/locations')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'locations'
            ]);
    }

    public function test_store()
    {
        $location = factory(Location::class)->create([
            'customer_id' => $this->getAcsAdminUser()->id
        ]);

        $this->postJson('/api/locations', $location->toArray())
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Successfully created.');

        $this->assertNotNull(Location::where('name', $location->name)->first());

        $this->postJson('/api/locations', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_update()
    {
        $location = factory(Location::class)->create([
            'customer_id' => $this->getAcsAdminUser()->id
        ]);

        $this->assertEquals($location->name, Location::find($location->id)->name);

        $location->name = 'test_name';

        $this->putJson('/api/locations/' . $location->id, $location->toArray())
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Successfully updated.');

        $this->assertEquals('test_name', Location::find($location->id)->name);

        $this->putJson('/api/locations/' . $location->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }
}
