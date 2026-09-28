<?php

namespace Database\Seeders;

use App\Models\TreatmentProcedurePrice;
use Illuminate\Database\Seeder;

class TreatmentProcedurePriceSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Dental Cleaning', 'cost' => 60000],
            ['name' => 'Tooth Extraction', 'cost' => 30000],
            ['name' => 'Root Canal', 'cost' => 350000],
            ['name' => 'Dental Filling', 'cost' => 90000],
            ['name' => 'Dental Crown', 'cost' => 450000],
            ['name' => 'Dental Bridge', 'cost' => 500000],
            ['name' => 'Dental Implant', 'cost' => 2500000],
            ['name' => 'Teeth Whitening', 'cost' => 300000],
            ['name' => 'Orthodontic Treatment', 'cost' => 1500000],
            ['name' => 'Periodontal Treatment', 'cost' => 200000],
            ['name' => 'Dental X-Ray', 'cost' => 50000],
            ['name' => 'Oral Surgery', 'cost' => 300000],
            ['name' => 'Emergency Dental Care', 'cost' => 150000],
            ['name' => 'Dental Consultation', 'cost' => 40000],
        ];

        foreach ($defaults as $item) {
            TreatmentProcedurePrice::firstOrCreate(
                ['name' => $item['name']],
                ['cost' => $item['cost'], 'active' => true]
            );
        }
    }
}
