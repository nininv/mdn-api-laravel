<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\DowntimePlan;
use Carbon\Carbon;
use Faker\Generator as Faker;

$factory->define(DowntimePlan::class, function (Faker $faker) {
    return [
        'company_id' => 1,
        'machine_id' => 1,
        'date_from' => Carbon::now()->subDay()->toDateString(),
        'date_to' => today()->toDateString(),
        'time_from' => today()->toTimeString(),
        'time_to' => now()->toTimeString(),
        'reason' => $faker->sentence
    ];
});

