<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = [
        'treatment_id',
        'medicine_id',
        'inventory_item_id',
        'medication',
        'dosage',
        'frequency',
        'duration',
        'prescription_amount',
        'prescription_issue_date',
        'prescription_expiry_date',
        'prescription_instructions',
        'max_refills',
        'prescription_status',
        'refill_count'
    ];

    protected $casts = [
        'prescription_issue_date' => 'date',
        'prescription_expiry_date' => 'date',
        'prescription_amount' => 'decimal:2',
        'max_refills' => 'integer',
        'refill_count' => 'integer'
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function medicine()
    {
        return $this->belongsTo(DentalMedicine::class, 'medicine_id', 'medicine_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
