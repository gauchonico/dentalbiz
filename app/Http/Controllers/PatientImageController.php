<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PatientImageController extends Controller
{
    public function index(Request $request, Patient $patient)
    {
        $this->authorize('view', $patient);
        $user = $request->user();

        $images = $patient->images()
            ->with(['uploader:id,name', 'treatment:id,created_at'])
            ->orderByDesc('taken_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PatientImage $image) => [
                'id' => $image->id,
                'category' => $image->category,
                'teeth' => $image->teeth,
                'taken_at' => optional($image->taken_at)->toDateString(),
                'notes' => $image->notes,
                'original_name' => $image->original_name,
                'mime_type' => $image->mime_type,
                'size' => $image->size,
                'is_image' => $image->isImage(),
                'treatment_id' => $image->treatment_id,
                'uploader' => $image->uploader?->only(['id', 'name']),
                'created_at' => $image->created_at,
                'url' => route('patients.images.file', [$patient, $image]),
                'can_delete' => $user->can('delete', $image),
            ]);

        return Inertia::render('Patients/Imaging', [
            'patient' => $patient->only(['id', 'name', 'email', 'phone', 'dob']),
            'images' => $images,
            'categories' => PatientImage::CATEGORIES,
            'treatments' => $patient->treatments()->latest()->get(['id', 'created_at'])
                ->map(fn ($t) => ['id' => $t->id, 'label' => 'Treatment #'.$t->id.' — '.$t->created_at->format('M d, Y')]),
            'can_upload' => $user->can('create', [PatientImage::class, $patient]),
        ]);
    }

    public function store(Request $request, Patient $patient)
    {
        $this->authorize('create', [PatientImage::class, $patient]);

        $data = $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:20480',
            'category' => ['required', Rule::in(array_keys(PatientImage::CATEGORIES))],
            'teeth' => ['nullable', 'string', 'max:191', 'regex:/^\s*\d{2}(\s*,\s*\d{2})*\s*$/'],
            'taken_at' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:2000',
            'treatment_id' => ['nullable', Rule::exists('treatments', 'id')->where('patient_id', $patient->id)],
        ], [
            'teeth.regex' => 'Use FDI tooth numbers separated by commas, e.g. 36, 37.',
            'files.*.mimes' => 'Only JPG, PNG, WEBP or PDF files are allowed.',
            'files.*.max' => 'Each file must be 20 MB or smaller.',
        ]);

        $teeth = isset($data['teeth']) ? preg_replace('/\s+/', '', $data['teeth']) : null;

        foreach ($request->file('files') as $file) {
            $disk = config('clinic.imaging_disk', 'local');
            $path = $file->store("patients/{$patient->id}/imaging", $disk);

            $image = $patient->images()->create([
                'treatment_id' => $data['treatment_id'] ?? null,
                'uploaded_by' => $request->user()->id,
                'category' => $data['category'],
                'teeth' => $teeth ?: null,
                'taken_at' => $data['taken_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);

            $this->audit($request, 'patient_image.upload', $image, ['patient_id' => $patient->id, 'category' => $image->category]);
        }

        $count = count($request->file('files'));

        return redirect()->route('patients.images.index', $patient)
            ->with('success', $count === 1 ? 'Image uploaded.' : "{$count} images uploaded.");
    }

    /**
     * Stream the file from private storage; never exposed via a public URL.
     */
    public function file(Request $request, Patient $patient, PatientImage $image)
    {
        $this->authorize('view', $patient);

        $disk = Storage::disk($image->disk);
        abort_unless($disk->exists($image->path), 404);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return $disk->response($image->path, $image->original_name, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'private, max-age=3600',
        ], $disposition);
    }

    public function destroy(Request $request, Patient $patient, PatientImage $image)
    {
        $this->authorize('delete', $image);

        // Soft delete: the file is kept so the clinical record can be recovered.
        $image->delete();

        $this->audit($request, 'patient_image.delete', $image, ['patient_id' => $patient->id]);

        return redirect()->route('patients.images.index', $patient)->with('success', 'Image removed.');
    }

    protected function audit(Request $request, string $action, PatientImage $image, array $metadata): void
    {
        try {
            AuditLog::create([
                'user_id' => optional($request->user())->id,
                'action' => $action,
                'subject_type' => PatientImage::class,
                'subject_id' => $image->id,
                'metadata' => $metadata,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {}
    }
}
