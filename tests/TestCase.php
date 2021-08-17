<?php

namespace Tests;

use App\Profile;
use App\Role;
use App\User;
use App\UserRole;
use Faker\Factory as Faker;
//use Faker\Generator as Faker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseMigrations;

    protected $faker;
    protected $super_admin_user;
    protected $acs_admin_user;
    protected $acs_manager_user;
    protected $acs_viewer_user;
    protected $customer_admin_user;

    public function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();
    }

    public function getSuperAdminUser()
    {
        if (!$this->super_admin_user) {

            $role = factory(Role::class)->create([
                'id' => ROLE_SUPER_ADMIN,
                'key' => 'super_admin',
                'name' => 'Super Admin',
            ]);

            $this->super_admin_user = $this->getFilledUserData($role);
        }

        return $this->super_admin_user;
    }

    public function getAcsAdminUser()
    {
        if (!$this->acs_admin_user) {

            $role = factory(Role::class)->create([
                'id' => ROLE_ACS_ADMIN,
                'key' => 'acs_admin',
                'name' => 'ACS Administrator',
            ]);

            $this->acs_admin_user = $this->getFilledUserData($role);
        }

        return $this->acs_admin_user;
    }

    public function getAcsManagerUser()
    {
        if (!$this->acs_manager_user) {

            $role = factory(Role::class)->create([
                'id' => ROLE_ACS_MANAGER,
                'key' => 'acs_manager',
                'name' => 'ACS Manager',
            ]);

            $this->acs_manager_user = $this->getFilledUserData($role);;
        }

        return $this->acs_manager_user;
    }

    public function getAcsViewerUser()
    {
        if (!$this->acs_viewer_user) {

            $role = factory(Role::class)->create([
                'id' => ROLE_ACS_VIEWER,
                'key' => 'acs_viewer',
                'name' => 'ACS Viewer',
            ]);

            $this->acs_viewer_user = $this->getFilledUserData($role);
        }

        return $this->acs_viewer_user;
    }

    public function getCustomerAdminUser()
    {
        if (!$this->customer_admin_user) {

            $role = factory(Role::class)->create([
                'id' => ROLE_CUSTOMER_ADMIN,
                'key' => 'customer_admin',
                'name' => 'Customer Administrator',
            ]);

            $this->customer_admin_user = $this->getFilledUserData($role);
        }

        return $this->customer_admin_user;
    }

    public function getFilledUserData(Role $role): User
    {
        $user = factory(User::class)->create();

        factory(UserRole::class)->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        if (!$user->profile) {
            factory(Profile::class)->create([
                'user_id' => $user->id
            ]);
        }

        //that is because as usual the Profile is empty
        $user->profile->update([
            'address_1' => $this->faker->address,
            'address_2' => $this->faker->address,
            'zip' => $this->faker->postcode,
            'state' => $this->faker->state,
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'phone' => $this->faker->phoneNumber
        ]);
    }
}
