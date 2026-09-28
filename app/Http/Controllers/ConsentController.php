<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ConsentController extends Controller
{
    // Templates (admin)
    public function templatesIndex()
    {
        $this->authorize('viewReports'); // reuse admin/receptionist gate; ideally a dedicated policy
        $templates = ConsentTemplate::orderByDesc('updated_at')->get();
        return Inertia::render('Consents/Templates', [
            'auth' => ['user' => auth()->user()],
            'templates' => $templates,
        ]);
    }

    public function templatesStore(Request $request)
    {
        $this->authorize('viewReports');
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'active' => 'boolean',
            'signature_required' => 'nullable|boolean',
        ]);
        $latestVersion = (int) (ConsentTemplate::where('title', $data['title'])->max('version') ?? 0);
        $template = ConsentTemplate::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'active' => $data['active'] ?? true,
            'signature_required' => array_key_exists('signature_required', $data) ? (bool)$data['signature_required'] : true,
            'version' => $latestVersion + 1,
        ]);
        try {
            \App\Models\AuditLog::create([
                'user_id' => optional($request->user())->id,
                'action' => 'consent_template.create',
                'subject_type' => ConsentTemplate::class,
                'subject_id' => $template->id,
                'metadata' => ['title' => $template->title, 'version' => $template->version],
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {}
        return back()->with('success', 'Template saved');
    }

    // Patient Consents
    public function patientIndex(Request $request, Patient $patient)
    {
        $this->authorize('view', $patient);
        $consents = Consent::with(['template'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('signed_at')
            ->get();
        $templates = ConsentTemplate::where('active', true)->orderBy('title')->get(['id','title','version','active','body','signature_required']);
        $user = $request->user();
        if (method_exists($user, 'loadMissing')) $user->loadMissing('roles');
        $roles = collect(optional($user)->roles)->pluck('name')->all();
        $dentistName = (is_array($roles) && in_array('dentist', $roles)) ? ($user->name ?? null) : null;
        $contextVars = [
            'clinic_name' => config('app.name', 'Victoria Dental Lounge'),
            'dentist_name' => $dentistName,
            'appointment_date' => null,
        ];
        return Inertia::render('Patients/Consents', [
            'auth' => ['user' => auth()->user()],
            'patient' => $patient->only(['id','name','email']),
            'consents' => $consents,
            'templates' => $templates,
            'context_vars' => $contextVars,
        ]);
    }

    public function patientSign(Request $request, Patient $patient)
    {
        $this->authorize('update', $patient);
        $data = $request->validate([
            'template_id' => 'required|exists:consent_templates,id',
            'signed_by_name' => 'required|string|max:255',
            'signature_data' => 'nullable|string',
        ]);
        $template = ConsentTemplate::findOrFail($data['template_id']);

        $signaturePath = null;
        // Enforce signature if required by template
        if ($template->signature_required && empty($data['signature_data'])) {
            return back()->withErrors(['signature_data' => 'Signature is required for this consent template.']);
        }
        if (!empty($data['signature_data'])) {
            try {
                $raw = $data['signature_data'];
                if (str_starts_with($raw, 'data:image')) {
                    [$meta, $b64] = explode(',', $raw, 2);
                } else {
                    $b64 = $raw;
                }
                $bin = base64_decode($b64);
                $dir = 'consents/'.date('Y/m/d');
                $name = uniqid('sig_').'.png';
                \Storage::disk('public')->put($dir.'/'.$name, $bin);
                $signaturePath = 'storage/'.$dir.'/'.$name;
            } catch (\Throwable $e) {}
        }

        $clinicName = config('app.name', 'Victoria Dental Lounge');
        $user = $request->user();
        $dentistName = null;
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('dentist')) {
            $dentistName = $user->name;
        }

        $renderedBody = $this->renderConsentHtml($template->body, $patient, [
            'clinic_name' => $clinicName,
            'dentist_name' => $dentistName,
            'signed_by_name' => $data['signed_by_name'],
            'date' => now()->timezone(config('app.timezone'))->toDateString(),
            'time' => now()->timezone(config('app.timezone'))->format('H:i'),
        ]);

        $consent = Consent::create([
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'template_version' => $template->version,
            'title' => $template->title,
            'content_snapshot' => $renderedBody,
            'signed_by_name' => $data['signed_by_name'],
            'signed_by_user_id' => optional($request->user())->id,
            'signed_at' => now(),
            'ip_address' => $request->ip(),
            'signature_path' => $signaturePath,
        ]);

        try {
            \App\Models\AuditLog::create([
                'user_id' => optional($request->user())->id,
                'action' => 'consent.sign',
                'subject_type' => Consent::class,
                'subject_id' => $consent->id,
                'metadata' => [
                    'patient_id' => $patient->id,
                    'template_id' => $template->id,
                    'template_version' => $template->version,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->header('User-Agent'),
            ]);
        } catch (\Throwable $e) {}

        return redirect()->route('patients.consents.index', $patient)->with('success', 'Consent recorded');
    }

    public function patientPdf(Patient $patient, Consent $consent)
    {
        $this->authorize('view', $patient);
        if ($consent->patient_id !== $patient->id) {
            abort(404);
        }
        $signaturePath = $this->signatureDataUri($consent->signature_path);
        $contentHtml = $this->renderConsentHtml($consent->content_snapshot ?? '', $patient, [
            'clinic_name' => config('app.name', 'Victoria Dental Lounge'),
            'signed_by_name' => $consent->signed_by_name,
            'dentist_name' => optional($consent->signer)->name ?? null,
            'date' => optional($consent->signed_at)->timezone(config('app.timezone'))?->toDateString(),
            'time' => optional($consent->signed_at)->timezone(config('app.timezone'))?->format('H:i'),
        ]);
        $pdf = Pdf::loadView('consents.pdf', [
            'patient' => $patient,
            'consent' => $consent,
            'signature_path' => $signaturePath,
            'content_html' => $contentHtml,
        ])->setPaper('a4');

        return $pdf->download('consent-'.$patient->id.'-'.$consent->id.'.pdf');
    }

    /**
     * Inline the stored signature as a data URI so the PDF works whether the
     * public disk is local or a cloud bucket.
     */
    protected function signatureDataUri(?string $storedPath): ?string
    {
        if (!$storedPath) {
            return null;
        }
        $path = preg_replace('#^/?storage/#', '', $storedPath);
        try {
            $disk = \Storage::disk('public');
            if (!$disk->exists($path)) {
                return null;
            }
            return 'data:image/png;base64,'.base64_encode($disk->get($path));
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function renderConsentHtml(string $body, Patient $patient, array $variables = []): string
    {
        $defaults = [
            'patient_name' => $patient->name,
            'patient_email' => $patient->email ?? '',
            'date' => now()->timezone(config('app.timezone'))->toDateString(),
            'time' => now()->timezone(config('app.timezone'))->format('H:i'),
            'clinic_name' => config('app.name', 'Victoria Dental Lounge'),
            'dentist_name' => '',
            'appointment_date' => '',
            'signed_by_name' => '',
        ];

        $vars = array_merge($defaults, array_filter($variables, fn ($value) => $value !== null));

        $rendered = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($matches) use ($vars) {
            $key = $matches[1];
            return $vars[$key] ?? '';
        }, $body);

        return trim($rendered ?? '');
    }
}
