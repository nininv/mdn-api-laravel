<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\MaterialLocation;
use Faker\Generator as Faker;

$factory->define(MaterialLocation::class, function (Faker $faker) {
    return [
        'location'=>$faker->name,
        'company_id'=>1
    ];
});
