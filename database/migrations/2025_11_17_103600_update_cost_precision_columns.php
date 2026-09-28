<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('treatments', 'cost')) {
            DB::statement('ALTER TABLE treatments MODIFY `cost` DECIMAL(12, 2)');
        }

        if (Schema::hasColumn('treatments', 'prescription_amount')) {
            DB::statement('ALTER TABLE treatments MODIFY `prescription_amount` DECIMAL(12, 2) NULL');
        }

        if (Schema::hasTable('treatment_procedures') && Schema::hasColumn('treatment_procedures', 'cost')) {
            DB::statement('ALTER TABLE treatment_procedures MODIFY `cost` DECIMAL(12, 2) DEFAULT 0');
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'amount')) {
            DB::statement('ALTER TABLE invoices MODIFY `amount` DECIMAL(12, 2)');
        }

        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'prescription_amount')) {
            DB::statement('ALTER TABLE prescriptions MODIFY `prescription_amount` DECIMAL(12, 2) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('treatments', 'cost')) {
            DB::statement('ALTER TABLE treatments MODIFY `cost` DECIMAL(8, 2)');
        }

        if (Schema::hasColumn('treatments', 'prescription_amount')) {
            DB::statement('ALTER TABLE treatments MODIFY `prescription_amount` DECIMAL(8, 2) NULL');
        }

        if (Schema::hasTable('treatment_procedures') && Schema::hasColumn('treatment_procedures', 'cost')) {
            DB::statement('ALTER TABLE treatment_procedures MODIFY `cost` DECIMAL(12, 2) DEFAULT 0');
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'amount')) {
            DB::statement('ALTER TABLE invoices MODIFY `amount` DECIMAL(8, 2)');
        }

        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'prescription_amount')) {
            DB::statement('ALTER TABLE prescriptions MODIFY `prescription_amount` DECIMAL(8, 2) NULL');
        }
    }
};
