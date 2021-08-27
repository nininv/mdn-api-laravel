<?php

namespace Tests\Feature;

use App\Company;
use App\User;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class AuthTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

//        Artisan::call('passport:install');
    }

    public function test_get_user()
    {
        $this->getJson('/api/auth/user')
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee('Unauthorized.');

        Passport::actingAs($this->getAcsAdminUser());

        $this->getJson('/api/auth/user')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'user'
            ]);
    }

    public function test_update_password()
    {
        $current_password = 'test123';

        $user = factory(User::class)->create(['password' => bcrypt($current_password)]);

        $this->postJson('/api/auth/update-password')
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee('Unauthorized.');

        Passport::actingAs($user);

        $this->postJson('/api/auth/update-password')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);

        $this->postJson('/api/auth/update-password', [
            'current_password' => 'test',
            'new_password' => $current_password,
        ])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson(['error' => 'Current password incorrect.']);

        $this->postJson('/api/auth/update-password', [
            'current_password' => $current_password,
            'new_password' => $current_password,
        ])
            ->assertStatus(Response::HTTP_OK)
            ->assertJson(['message' => 'Successfully updated.']);
    }

    public function test_logout()
    {
        $this->getJson('/api/auth/logout')
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee('Unauthorized.');

        $user = factory(User::class)->create();

        Passport::actingAs($user);

        $this->getJson('/api/auth/logout')
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'message' => 'Successfully logged out'
            ]);
    }

    public function test_password_reset()
    {
        $this->postJson('/api/auth/password-reset', ['email' => $this->getAcsAdminUser()->email])
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Email sent successfully.');

        $this->postJson('/api/auth/password-reset', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);

        $this->postJson('/api/auth/password-reset', ['email' => $this->faker->email])
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertSee('Email not found');
    }

    public function test_signin()
    {
        //error case
        $this->postJson('/api/auth/signin')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);

//        TODO: make correct this test to check login method
//        $user = factory(User::class)->create(['password' => bcrypt('test')]);
////
////        $password_string = md5(uniqid($user->email, true));
//
//        $data = [
//          'email' => $user->email,
//          'password' => 'test'
//        ];
//
//        $response =$this->postJson('/api/auth/signin', $data);
//
//        var_dump($response->getContent());
//        die('ttt');
//        $this->actingAs($user, 'api');

//        $this->postJson('/api/auth/check')
//            ->assertStatus(Response::HTTP_OK)
    }

    public function test_check()
    {
        //not an authorized user
        $this->postJson('/api/auth/check')
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('false');

        //regular user check
        $this->actingAs($this->getAcsAdminUser(), 'api');

        $this->postJson('/api/auth/check')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'name',
                'email',
                'email_verified_at',
                'verified',
                'company_id',
                'updated_at',
                'created_at',
                'id',
                'role',
                'companyName',
                'phoneNumber',
                'profile',
                'roles',
                'company'
            ]);

        //ROLE_SUPER_ADMIN check
        $this->actingAs($this->getSuperAdminUser(), 'api');

        $this->postJson('/api/auth/check')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'name',
                'email',
                'email_verified_at',
                'verified',
                'company_id',
                'updated_at',
                'created_at',
                'id',
                'role',
                'profile',
                'roles'
            ]);

        //user without roles
        $company = factory(Company::class)->create();

        $user = factory(User::class)->create([
            'company_id' => $company->id
        ]);

        $this->actingAs($user, 'api');

        $this->postJson('/api/auth/check')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'name',
                'email',
                'email_verified_at',
                'verified',
                'company_id',
                'updated_at',
                'created_at',
                'id',
                'role',
                'companyName',
                'phoneNumber',
                'roles',
                'company',
                'profile'
            ]);


        //check some role
        $this->postJson('/api/auth/check', ['role' => 'bad_role'])
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('false');
    }
}
