<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Prescription;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'dob', 'address', 'medical_history', 'allergies'
    ];

    protected $casts = [
        'dob' => 'date',
        'allergies' => 'array',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function prescriptions()
    {
        return $this->hasManyThrough(
            Prescription::class,
            Treatment::class,
            'patient_id', // Foreign key on treatments table...
            'treatment_id' // Foreign key on prescriptions table...
        );
    }

    public function medicalHistory()
    {
        return $this->hasOne(MedicalHistory::class);
    }

    public function images()
    {
        return $this->hasMany(PatientImage::class);
    }
}