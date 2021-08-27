<?php

namespace Tests\Feature;

use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->getAcsAdminUser(), 'api');
    }

    public function test_index()
    {
        $this->getJson('/api/companies')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'companies'
            ]);
    }

    public function test_admins()
    {
        //validation check
        $this->getJson('/api/companies/admins')
            ->assertStatus(Response::HTTP_NOT_FOUND);

        $this->getCustomerAdminUser();

        $this->getJson('/api/companies/admins')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'customer_admins'
            ]);
    }
}
