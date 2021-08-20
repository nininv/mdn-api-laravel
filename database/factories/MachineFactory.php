<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Machine;
use Faker\Generator as Faker;

$factory->define(Machine::class, function (Faker $faker) {
    return [
        'id' => random_int(1, 11),
        'name' => $faker->name,
        'full_json' => json_encode([]),
        'device_type' => 0
    ];
});
