<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Device;
use Faker\Generator as Faker;

$factory->define(Device::class, function (Faker $faker) {
    return [
        'device_id' => random_int(810126, 890126),
        'name' => $faker->name,
        'customer_assigned_name' => $faker->name,
        'serial_number' => random_int(1109634623, 1909634623),
        'imei' => random_int(811641042131671, 891641042131671),//or NULL
        'lan_mac_address' => $faker->macAddress,
        'iccid' => random_int(8101260882276398960, 8901260882276398960),//or NULL
        'public_ip_sim' => null,
        'machine_id' => 1,
        'company_id' => 1,
        'registered' => false,
        'plc_ip' => $faker->ipv4,
        'tcu_added' => false
    ];
});

