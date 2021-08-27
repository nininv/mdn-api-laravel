<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\User;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(User::class, function (Faker $faker) {

    $email = $faker->email;

    $password_string = md5(uniqid($email, true));

    return [
        'name' => $faker->name,
        'email' => $email,
        'email_verified_at' => now(),
        'verified' => 1,
        'password' => bcrypt($password_string),
        'remember_token' => Str::random(10),
    ];
});
