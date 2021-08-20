<?php

namespace Tests\Feature;

use App\Machine;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    protected $machine;

    public function setUp(): void
    {
        parent::setUp();

        $this->machine = factory(Machine::class)->create();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->getJson('/api/configurations')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'configurations'
            ]);
    }

    public function test_show()
    {
        $this->getJson('/api/configurations/' . $this->machine->id)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'configuration'
            ]);

        $this->getJson('/api/configurations/' . $this->faker->randomNumber(7))
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_update()
    {
        $this->assertEquals($this->machine->name, Machine::find($this->machine->id)->name);

        $this->machine->name = $this->faker->name;
        $this->machine->new_json = json_encode(['plctags' => []]);

        $this->putJson('/api/configurations/' . $this->machine->id, $this->machine->toArray())
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Successfully updated');

        $this->assertEquals($this->machine->name, Machine::find($this->machine->id)->name);

        $this->assertEquals($this->machine->new_json, Machine::find($this->machine->id)->full_json);

        $this->putJson('/api/configurations/' . $this->machine->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }
}
