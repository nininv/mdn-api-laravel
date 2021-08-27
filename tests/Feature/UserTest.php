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

        $this->actingAs($this->getCustomerAdminUser(), 'api');

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

        $this->actingAs($this->getAcsManagerUser(), 'api');

        $this->postJson('/api/users', $data)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJson([
                'message' => 'Unauthorized'
            ]);
    }

    public function test_update()
    {
        $data = [
            'name' => $this->faker->name,
            'email' => $this->getAcsAdminUser()->email,
            'role' => $this->getAcsAdminUser()->role->id,
            'address_1' => $this->faker->address,
            'zip' => $this->faker->postcode,
            'state' => $this->faker->state,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'phone' => $this->faker->phoneNumber
        ];

        $this->patchJson('/api/users/' . $this->getAcsAdminUser()->id, $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Updated successfully.');

        //validation check
        $this->patchJson('/api/users/' . $this->getAcsAdminUser()->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_edit()
    {
        $this->getJson('/api/users/' . $this->getAcsAdminUser()->id . '/edit')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'user',
                'cities'
            ]);
    }

    public function test_delete()
    {
        //TODO: it need to be fixed 'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed (SQL: delete from \"users\"'
        //$response =$this->postJson('/api/users/delete', ['email' => $this->getAcsAdminUser()->email]);

        $this->postJson('/api/users/delete')
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertSee('Email not found');
    }
}
