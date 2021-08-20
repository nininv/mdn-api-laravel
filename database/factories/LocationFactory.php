<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Location;
use Faker\Generator as Faker;

$factory->define(Location::class, function (Faker $faker) {
    return [
        'customer_id'=>1,
        'name' => $faker->name,
        'state' => $faker->state,
        'city' => $faker->city,
        'zip' => $faker->postcode,
        'company_id'=>1
    ];
});
