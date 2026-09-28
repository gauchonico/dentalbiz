<?php

namespace Tests\Feature;

use App\Models\TreatmentProcedurePrice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertableInertia;
use Tests\TestCase;

class TreatmentProcedurePricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatment_index_uses_procedure_prices_from_the_database(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        TreatmentProcedurePrice::create([
            'name' => 'Dental Cleaning',
            'cost' => 65000,
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('treatments.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('procedureTemplates', 1)
            ->where('procedureTemplates.0.name', 'Dental Cleaning')
            ->where('procedureTemplates.0.cost', 65000)
        );
    }
}
