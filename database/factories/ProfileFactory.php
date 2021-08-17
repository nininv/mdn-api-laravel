<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Profile;
use Faker\Generator as Faker;

$factory->define(Profile::class, function (Faker $faker) {
    return [
    	'address_1' => $faker->address,
		'address_2' => $faker->address,
		'zip' => $faker->postcode,
		'state' => $faker->state,
		'city' => $faker->city,
		'country' => $faker->country,
		'phone' => $faker->phoneNumber,
		'user_id' => 1
    ];
});
