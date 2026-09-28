<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\User;

class PatientImagePolicy
{
    /**
     * Clinical staff who can see the patient can add images; dentists and
     * assistants take most radiographs, so this is broader than patient update.
     */
    public function create(User $user, Patient $patient): bool
    {
        return $user->hasAnyRole(['admin', 'dentist', 'assistant', 'receptionist'])
            && $user->can('view', $patient);
    }

    /**
     * Images are clinical records: only admins or the original uploader may remove one.
     */
    public function delete(User $user, PatientImage $image): bool
    {
        return $user->hasRole('admin') || $image->uploaded_by === $user->id;
    }
}
