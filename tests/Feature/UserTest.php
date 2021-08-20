<?php

namespace Tests\Feature;

use App\Role;
use App\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->getJson('/api/users')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'users'
            ]);
    }

    public function test_store()
    {
        //create ROLE_ACS_ADMIN user
        $data = [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'role' => ROLE_ACS_ADMIN,
            'address_1' => $this->faker->address,
            'zip' => $this->faker->postcode,
            'state' => $this->faker->state,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'phone' => $this->faker->phoneNumber
        ];

        $this->postJson('/api/users', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);


        $this->postJson('/api/users', $data)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertSee('Created successfully.');

        $this->assertNotNull(User::where('name', $data['name'])->first());

        //create ROLE_CUSTOMER_ADMIN user
        $data = [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'role' => ROLE_CUSTOMER_ADMIN,
            'address_1' => $this->faker->address,
            'zip' => $this->faker->postcode,
            'state' => $this->faker->state,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'phone' => $this->faker->phoneNumber
        ];

        factory(Role::class)->create([
            'id' => ROLE_CUSTOMER_ADMIN,
            'key' => 'customer_admin',
            'name' => 'Customer Administrator',
        ]);

        $this->postJson('/api/users', $data)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertSee('Created successfully.');

        $this->assertNotNull(User::where('name', $data['name'])->first());
    }
}
