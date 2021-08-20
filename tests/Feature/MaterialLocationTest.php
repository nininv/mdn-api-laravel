<?php

namespace Tests\Feature;

use App\MaterialLocation;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class MaterialLocationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->getJson('/api/material-locations')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'locations'
            ]);
    }

    public function test_store()
    {
        $test_data = [
            'location' => $this->faker->name
        ];

        $this->postJson('/api/material-locations', $test_data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Created Successfully');

        $this->assertNotNull(MaterialLocation::where('location', $test_data['location'])->first());

        $this->postJson('/api/material-locations', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_update()
    {
        $material = factory(MaterialLocation::class)->create([
            'company_id' => $this->getAcsAdminUser()->company_id
        ]);

        $this->assertEquals($material->location, MaterialLocation::find($material->id)->location);

        $material->location = $this->faker->name;

        $this->putJson('/api/material-locations/' . $material->id, $material->toArray())
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Updated Successfully');

        $this->assertEquals($material->location, MaterialLocation::find($material->id)->location);

        $this->putJson('/api/material-locations/' . $material->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_destroy()
    {
        $material = factory(MaterialLocation::class)->create([
            'company_id' => $this->getAcsAdminUser()->company_id
        ]);

        $this->assertEquals($material->location, MaterialLocation::find($material->id)->location);

        $this->deleteJson('/api/material-locations/' . $material->id)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Deleted Successfully');

        $this->assertNull(MaterialLocation::find($material->id));

        $this->deleteJson('/api/material-locations/' . 0)
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
