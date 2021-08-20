<?php

namespace Tests\Feature;

use App\Device;
use App\Machine;
use App\DowntimePlan;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DowntimePlanTest extends TestCase
{
    protected $machine;
    protected $device;
    protected $downtime_plan;

    public function setUp(): void
    {
        parent::setUp();

        $this->machine = factory(Machine::class)->create();

        $this->device = factory(Device::class)->create([
            'machine_id' => $this->machine->id,
            'company_id' => $this->getCustomerAdminUser()->company_id
        ]);

        $this->downtime_plan = factory(DowntimePlan::class)->create([
            'machine_id' => $this->machine->id,
            'company_id' => $this->getCustomerAdminUser()->company_id
        ]);

        $this->actingAs($this->getCustomerAdminUser(), 'api');
    }

    public function test_index()
    {
         $this->getJson('/api/downtime-plans')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'downtimePlans',
                'machines'
            ]);
    }


    public function test_store()
    {
        $data = [
            'machine' => $this->downtime_plan->machine_id,
            'timeTo' => $this->downtime_plan->time_to,
            'timeFrom' => $this->downtime_plan->time_from,
            'dateTo' => $this->downtime_plan->date_to,
            'dateFrom' => $this->downtime_plan->date_from,
            'reason' => $this->downtime_plan->reason
        ];

        $this->postJson('/api/downtime-plans/store', $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Created successfully');

        $this->assertNotNull(DowntimePlan::where('reason', $this->downtime_plan->reason)->first());

        $this->postJson('/api/downtime-plans/store', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }

    public function test_update()
    {
        $data = [
            'machine' => $this->downtime_plan->machine_id,
            'timeTo' => $this->downtime_plan->time_to,
            'timeFrom' => $this->downtime_plan->time_from,
            'dateTo' => $this->downtime_plan->date_to,
            'dateFrom' => $this->downtime_plan->date_from,
            'reason' => $this->downtime_plan->reason
        ];

        $this->assertEquals($this->downtime_plan->reason, DowntimePlan::find($this->downtime_plan->id)->reason);

        $data['reason'] = $this->faker->sentence;

        $this->postJson('/api/downtime-plans/update/' . $this->downtime_plan->id, $data)
            ->assertStatus(Response::HTTP_OK)
            ->assertSee('Updated successfully');

        $this->assertEquals($data['reason'], DowntimePlan::find($this->downtime_plan->id)->reason);

        $this->postJson('/api/downtime-plans/update/' . $this->downtime_plan->id, [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'error'
            ]);
    }
}
