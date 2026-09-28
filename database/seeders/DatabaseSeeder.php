<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(TreatmentProcedurePriceSeeder::class);

        $this->seedClinicalNoteTemplates();

        // Demo staff with the password "password" must never exist on a live server.
        // In production, create the first admin with: php artisan app:create-admin
        if (app()->environment('production')) {
            return;
        }

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        $receptionist = User::factory()->create([
            'name' => 'Receptionist User',
            'email' => 'receptionist@example.com',
            'password' => Hash::make('password'),
        ]);
        $receptionist->assignRole('receptionist');

        $dentist = User::factory()->create([
            'name' => 'Dentist User',
            'email' => 'dentist@example.com',
            'password' => Hash::make('password'),
        ]);
        $dentist->assignRole('dentist');

        $assistant = User::factory()->create([
            'name' => 'Assistant User',
            'email' => 'assistant@example.com',
            'password' => Hash::make('password'),
        ]);
        $assistant->assignRole('assistant');

        User::factory(5)->create()->each(function (User $user) {
            $user->assignRole('assistant');
        });

    }

    protected function seedClinicalNoteTemplates(): void
    {
        // Default Clinical Note Templates
        \App\Models\ClinicalNoteTemplate::firstOrCreate(
            ['name' => 'Routine Checkup'],
            [
                'subjective' => 'Patient presents for routine check-up and cleaning. No acute complaints.',
                'objective' => 'Vitals stable. Oral hygiene good. No active caries. Gingiva healthy.',
                'assessment' => 'Routine oral health maintenance.',
                'plan' => 'Prophylaxis completed. OHI reinforced. Recall in 6 months.',
                'active' => true,
            ]
        );
        \App\Models\ClinicalNoteTemplate::firstOrCreate(
            ['name' => 'Caries on #46'],
            [
                'subjective' => 'Intermittent sensitivity on lower right molar for several days.',
                'objective' => 'Caries present on #46 occlusal. No swelling. Percussion negative.',
                'assessment' => 'Dental caries on #46.',
                'plan' => 'Composite restoration planned. Risks/benefits discussed. Follow-up in 2 weeks.',
                'active' => true,
            ]
        );
    }
}
