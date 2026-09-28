<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreatmentInventoryPrescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatment_store_uses_inventory_item_for_prescriptions(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $patient = Patient::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '0772000000',
            'dob' => '1990-01-01',
            'address' => 'Kampala',
        ]);
        $inventoryItem = InventoryItem::create([
            'name' => 'Amoxicillin Capsule',
            'quantity' => 20,
            'unit_price' => 1500,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->actingAs($user)->post(route('treatments.store'), [
            'patient_id' => $patient->id,
            'notes' => 'Routine treatment',
            'procedures' => [
                ['name' => 'Cleaning', 'cost' => 50000],
            ],
            'prescriptions' => [
                [
                    'inventory_item_id' => $inventoryItem->id,
                    'dosage' => '500mg twice daily',
                    'quantity' => 3,
                    'prescription_amount' => 4500,
                ],
            ],
        ]);

        $response->assertRedirect(route('treatments.index'));
        $this->assertDatabaseHas('treatments', ['patient_id' => $patient->id]);

        $treatment = Treatment::latest()->first();
        $this->assertNotNull($treatment);

        $prescription = Prescription::where('treatment_id', $treatment->id)->first();
        $this->assertNotNull($prescription);
        $this->assertSame($inventoryItem->id, $prescription->medicine_id);
        $this->assertSame('4500.00', (string) $prescription->prescription_amount);
    }
}
