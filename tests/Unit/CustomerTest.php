<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Profile;
use App\City;

class CustomerTest extends TestCase
{
     public function test_customer_has_cities()
     {
         $profile = $this->getAcsAdminUser()->profile;

         factory(City::class)->create([
             'state' => $profile->state
         ]);

         $this->assertEquals($profile->cities->last()->state, $profile->state);
     }
}
