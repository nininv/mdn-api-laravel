<?php

namespace Tests\Feature;

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
     }

    public function test_validation_update_error()
    {
        $data = [
            'address_1' => $this->faker->address,
            'user_id' => $this->getAcsAdminUser()->id
        ];

        $this->postJson('/api/customers/update-profile/' . $this->getAcsAdminUser()->id, $data)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }
}
