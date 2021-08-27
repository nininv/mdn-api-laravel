<?php

namespace Tests\Feature;

use App\Timezone;
use App\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_update()
    {
        $data['data'] = [
                'name' => $this->faker->name,
                'phone' => $this->faker->phoneNumber,
                'email' => $this->faker->email
        ];

        $this->assertEquals($this->getAcsAdminUser()->name, User::find($this->getAcsAdminUser()->id)->name);

        $this->postJson('/api/profile/update', $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertJson(['message' => 'Updated successfully.']);

        $this->assertEquals($data['data']['name'], User::find($this->getAcsAdminUser()->id)->name);

        //validation check
        $this->postJson('/api/profile/update', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_timezones()
    {
        $this->getJson('/api/profile/timezones')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'timezones'
            ]);
    }

    public function test_timezone()
    {
        //validation check
        $this->postJson('/api/profile/timezone')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);

        $this->postJson('/api/profile/timezone', ['timezone' => 'test'])
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'message' => "Can't find timezone"
            ]);

        $time_zone = factory(Timezone::class)->create();

        $this->postJson('/api/profile/timezone', ['timezone' => $time_zone->id])
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'message' => 'Successfully updated.'
            ]);
    }
}
