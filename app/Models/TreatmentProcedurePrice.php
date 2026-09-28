<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentProcedurePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cost',
        'active',
        'created_by',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'active' => 'boolean',
    ];
}
