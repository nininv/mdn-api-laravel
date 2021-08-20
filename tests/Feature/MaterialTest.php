<?php

namespace Tests\Feature;

use App\Material;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->getJson('/api/materials')
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'materials'
        ]);
    }

    public function test_store()
    {
        $test_data = [
            'material' => $this->faker->name
        ];

        $this->postJson('/api/materials', $test_data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Created Successfully');

        $this->assertNotNull(Material::where('material', $test_data['material'])->first());

        $this->postJson('/api/materials', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_update()
    {
        $material = factory(Material::class)->create([
            'company_id' => $this->getAcsAdminUser()->company_id
        ]);

        $this->assertEquals($material->material, Material::find($material->id)->material);

        $material->material = $this->faker->name;

        $this->putJson('/api/materials/' . $material->id, $material->toArray())
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Updated Successfully');

        $this->assertEquals($material->material, Material::find($material->id)->material);

        $this->putJson('/api/materials/' . $material->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_destroy()
    {
        $material = factory(Material::class)->create([
            'company_id' => $this->getAcsAdminUser()->company_id
        ]);

        $this->assertEquals($material->material, Material::find($material->id)->material);

        $this->deleteJson('/api/materials/' . $material->id)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Deleted Successfully');

        $this->assertNull(Material::find($material->id));

        $this->deleteJson('/api/materials/' . 0)
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
