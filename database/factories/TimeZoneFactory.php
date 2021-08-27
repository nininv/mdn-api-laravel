<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Timezone;
use Faker\Generator as Faker;

$factory->define(Timezone::class, function (Faker $faker) {
    return [
        'name' => $faker->timezone
    ];
});
