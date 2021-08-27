<?php

namespace Tests\Feature;

use App\Role;
use App\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use App\Profile;

class CustomerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_can_update_customer_profile()
    {
        $data = [
            'address_1' => $this->faker->address,
            'address_2' => $this->faker->address,
            'zip' => $this->faker->postcode,
            'state' => $this->faker->state,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'phone' => $this->faker->phoneNumber,
            'user_id' => $this->getAcsAdminUser()->id
        ];

        $profile = Profile::where('user_id', $this->getAcsAdminUser()->id)->first();

        $this->assertNotEquals($data['address_1'], $profile->address_1);

        $this->postJson('/api/customers/update-profile/' . $this->getAcsAdminUser()->id, $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Updated Successfully');

        $profile = Profile::where('user_id', $this->getAcsAdminUser()->id)->first();

        $this->assertEquals($data['address_1'], $profile->address_1);

        //validation check
        $this->postJson('/api/customers/update-profile/' . $this->getAcsAdminUser()->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_update_account()
    {
        $data = [
            'name' => $this->faker->name,
            'administrator_name' => $this->faker->name,
            'administrator_email' => $this->faker->email
        ];

        $this->postJson('/api/customers/update-account/' . $this->getAcsAdminUser()->id, $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Updated Successfully');

        $customer = User::findOrFail($this->getAcsAdminUser()->id);

        $this->assertEquals($data['administrator_name'], $customer->name);

        $this->assertEquals($data['name'], $customer->company->name);

        //validation check
        $this->postJson('/api/customers/update-account/' . $this->getAcsAdminUser()->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_get_customer()
    {
        $this->getJson('/api/customers/' . $this->getAcsAdminUser()->id)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'customer',
                'profile',
                'cities'
            ]);

        //validation check
        $this->getJson('/api/customers/' . 123456)
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_add_customer()
    {
        $data = [
            'company_name' => $this->faker->company,
            'administrator_name' => $this->faker->name,
            'administrator_email' => $this->faker->email,
            'address_1' => $this->faker->address,
            'zip' => $this->faker->postcode,
            'state' => $this->faker->state,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'phone' => $this->faker->phoneNumber
        ];

        //in order to create a role with an ID ROLE_CUSTOMER_ADMIN
        $this->getCustomerAdminUser();

        $this->postJson('/api/customers/add', $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Created successfully.');

        //validation check
        $this->postJson('/api/customers/add')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }
}
