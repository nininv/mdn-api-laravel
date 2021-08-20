<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Zone;
use Faker\Generator as Faker;

$factory->define(Zone::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'location_id' => 1,
        'customer_id' => 1,
        'company_id'=>1
    ];
});
