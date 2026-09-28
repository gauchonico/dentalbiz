<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientImage extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'periapical' => 'Periapical X-ray',
        'bitewing' => 'Bitewing X-ray',
        'panoramic' => 'Panoramic (OPG)',
        'cephalometric' => 'Cephalometric',
        'cbct' => 'CBCT',
        'intraoral_photo' => 'Intraoral Photo',
        'extraoral_photo' => 'Extraoral Photo',
        'other' => 'Other',
    ];

    protected $fillable = [
        'patient_id',
        'treatment_id',
        'uploaded_by',
        'category',
        'teeth',
        'taken_at',
        'notes',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'taken_at' => 'date',
        'size' => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
