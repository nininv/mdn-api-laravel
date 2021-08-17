<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\City;
use Faker\Generator as Faker;

$factory->define(City::class, function (Faker $faker) {
    return [
		'zip' => $faker->postcode,
		'state' => $faker->state,
		'city' => $faker->city
    ];
});
