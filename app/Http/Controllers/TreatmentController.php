<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\Prescription;
use App\Models\Patient;
use App\Models\InventoryItem;
use App\Models\TreatmentProcedurePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TreatmentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Treatment::class, 'treatment');
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 10;
        }

        $page = (int) $request->input('page', 1);
        if ($page <= 0) {
            $page = 1;
        }

        // Build query with filters and sorting
        $query = Treatment::with(['patient:id,name,email', 'appointment', 'invoice.payments', 'prescriptions.medicine', 'procedures'])
            ->latest('created_at');

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('procedures', function ($qp) use ($search) {
                      $qp->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('patient', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('prescriptions', function ($q) use ($search) {
                      $q->where('medicine_id', 'like', "%{$search}%");
                  });
            });
        }

        // Apply patient filter
        if ($patient = $request->input('patient')) {
            if ($patient !== 'all') {
                $query->where('patient_id', $patient);
            }
        }

        // Apply invoice status filter
        $invoiceStatus = $request->input('invoice_status');
        if ($invoiceStatus && $invoiceStatus !== 'all') {
            if ($invoiceStatus === 'invoiced') {
                $query->whereHas('invoice');
            } elseif ($invoiceStatus === 'not_invoiced') {
                $query->whereDoesntHave('invoice');
            }
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortBy, ['created_at', 'procedure', 'cost', 'patient_id'])) {
            if ($sortBy === 'patient_id') {
                $query->join('patients', 'treatments.patient_id', '=', 'patients.id')
                      ->orderBy('patients.name', $sortOrder)
                      ->select('treatments.*');
            } elseif ($sortBy === 'procedure') {
                $query->join('treatment_procedures', 'treatment_procedures.treatment_id', '=', 'treatments.id')
                      ->orderBy('treatment_procedures.name', $sortOrder)
                      ->select('treatments.*')
                      ->groupBy('treatments.id');
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        // Paginate results
        $treatments = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        $procedureTemplates = $this->getProcedureTemplates();

        return Inertia::render('Treatments/Index', [
            'auth' => [
                'user' => auth()->user(),
            ],
            'treatments' => [
                'data' => $treatments->items(),
                'meta' => [
                    'current_page' => $treatments->currentPage(),
                    'last_page' => $treatments->lastPage(),
                    'per_page' => $treatments->perPage(),
                    'total' => $treatments->total(),
                    'from' => $treatments->firstItem(),
                    'to' => $treatments->lastItem(),
                ],
            ],
            'filters' => [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $request->input('search', ''),
                'patient' => $request->input('patient', 'all'),
                'invoice_status' => $request->input('invoice_status', 'all'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'patients' => Patient::select('id', 'name', 'email')->get(),
            'medicines' => InventoryItem::select('id', 'name', 'unit_price', 'unit', 'category')->get(),
            'procedureTemplates' => $procedureTemplates,
            'appointmentTypes' => array_column($procedureTemplates, 'name'),
            'stats' => [
                'total_treatments' => Treatment::count(),
                'total_revenue' => Treatment::sum('cost'),
                'this_month_treatments' => Treatment::whereMonth('created_at', now()->month)->count(),
            ],
        ]);
    }

    private function getProcedureTemplates(): array
    {
        return TreatmentProcedurePrice::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['name', 'cost'])
            ->map(fn ($item) => [
                'name' => $item->name,
                'cost' => (int) $item->cost,
            ])
            ->all();
    }

    public function show(Treatment $treatment)
    {
        $treatment->load(['patient', 'appointment', 'prescriptions.inventoryItem']);
        return Inertia::render('Treatments/Show', [
            'auth' => [
                'user' => auth()->user(),
            ],
            'treatment' => $treatment
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'procedures' => 'required|array|min:1',
            'procedures.*.name' => 'required|string|max:255',
            'procedures.*.cost' => 'required|numeric|min:0',
            'prescriptions' => 'nullable|array',
            'prescriptions.*.inventory_item_id' => 'nullable|exists:inventory_items,id',
            'prescriptions.*.medication' => 'nullable|string',
            'prescriptions.*.dosage' => 'nullable|string',
            'prescriptions.*.frequency' => 'nullable|string',
            'prescriptions.*.quantity' => 'required|integer|min:1',
            'prescriptions.*.duration' => 'nullable|string',
            'prescriptions.*.prescription_amount' => 'nullable|numeric|min:0',
            'prescriptions.*.prescription_issue_date' => 'nullable|date',
            'prescriptions.*.prescription_expiry_date' => 'nullable|date',
            'prescriptions.*.prescription_instructions' => 'nullable|string',
            'prescriptions.*.max_refills' => 'nullable|integer|min:0',
        ]);

        $procedureRows = collect($validated['procedures'])
            ->map(fn ($procedure) => [
                'name' => $procedure['name'],
                'cost' => $procedure['cost'],
            ]);

        $validated['cost'] = $procedureRows->sum('cost');

        $prescriptionData = $validated['prescriptions'] ?? [];
        unset($validated['procedures'], $validated['prescriptions']);

        if ($request->hasFile('file')) {
            $validated['file_path'] = $request->file('file')->store('treatments', 'public');
        }

        $treatment = Treatment::create($validated);

        foreach ($procedureRows as $procedure) {
            $treatment->procedures()->create($procedure);
        }

        // Create prescriptions
        foreach ($prescriptionData as $prescription) {
            $prescription['treatment_id'] = $treatment->id;
            $prescription['medicine_id'] = $prescription['inventory_item_id'] ?? null;
            $prescription['inventory_item_id'] = $prescription['inventory_item_id'] ?? null;
            $prescription['prescription_issue_date'] = $prescription['prescription_issue_date'] ?? now()->toDateString();
            $prescription['prescription_status'] = 'active';
            $prescription['refill_count'] = 0;
            \App\Models\Prescription::create($prescription);
        }

        return redirect()->route('treatments.index')->with('success', 'Treatment and prescriptions recorded.');
    }

    public function update(Request $request, Treatment $treatment)
    {
        // Prevent editing if invoice exists
        if ($treatment->invoice()->exists()) {
            return redirect()
                ->route('treatments.index')
                ->with('error', 'This treatment has an invoice and cannot be edited.');
        }
    
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'notes' => 'nullable|string',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        
            // Procedures
            'procedures' => 'required|array|min:1',
            'procedures.*.id' => 'nullable|exists:treatment_procedures,id',
            'procedures.*.name' => 'required|string|max:255',
            'procedures.*.cost' => 'required|numeric|min:0',
        
            // Prescriptions
            'prescriptions' => 'nullable|array',
            'prescriptions.*.id' => 'nullable|exists:prescriptions,id',
            'prescriptions.*.inventory_item_id' => 'nullable|exists:inventory_items,id',
            'prescriptions.*.dosage' => 'nullable|string',
            'prescriptions.*.quantity' => 'required|integer|min:1',
            'prescriptions.*.frequency' => 'nullable|string',
            'prescriptions.*.duration' => 'nullable|string',
            'prescriptions.*.prescription_amount' => 'nullable|numeric|min:0',
            'prescriptions.*.prescription_issue_date' => 'nullable|date',
            'prescriptions.*.prescription_expiry_date' => 'nullable|date',
            'prescriptions.*.prescription_instructions' => 'nullable|string',
            'prescriptions.*.max_refills' => 'nullable|integer|min:0',
            'prescriptions.*.prescription_status' => 'nullable|in:active,completed,expired,cancelled',
        ]);
    
        // -----------------------
        // 1. Procedures
        // -----------------------
        $procedureRows = collect($validated['procedures'])->map(fn($p) => [
            'id' => $p['id'] ?? null,
            'name' => $p['name'],
            'cost' => $p['cost'],
        ]);
    
        $validated['cost'] = $procedureRows->sum('cost');
    
        // Remove non-treatments fields
        $prescriptionRows = $validated['prescriptions'] ?? [];
        unset($validated['procedures'], $validated['prescriptions']);
    
        // Handle file upload
        if ($request->hasFile('file')) {
            if ($treatment->file_path) {
                Storage::disk('public')->delete($treatment->file_path);
            }
            $validated['file_path'] = $request->file('file')->store('treatments', 'public');
        }
    
        // Update treatment
        $treatment->update($validated);
    
        // -----------------------
        // UPDATE PROCEDURES
        // -----------------------
        $existingProcedureIds = $treatment->procedures()->pluck('id')->toArray();
        $updatedProcedureIds = [];
    
        foreach ($procedureRows as $procedure) {
            if ($procedure['id'] && in_array($procedure['id'], $existingProcedureIds)) {
                // update
                $treatment->procedures()->where('id', $procedure['id'])->update([
                    'name' => $procedure['name'],
                    'cost' => $procedure['cost'],
                ]);
                $updatedProcedureIds[] = $procedure['id'];
            } else {
                // create
                $new = $treatment->procedures()->create([
                    'name' => $procedure['name'],
                    'cost' => $procedure['cost'],
                ]);
                $updatedProcedureIds[] = $new->id;
            }
        }
    
        // Delete removed
        $toDelete = array_diff($existingProcedureIds, $updatedProcedureIds);
        if (!empty($toDelete)) {
            $treatment->procedures()->whereIn('id', $toDelete)->delete();
        }
    
        // -----------------------
        // UPDATE PRESCRIPTIONS
        // -----------------------
        $existingPrescriptionIds = $treatment->prescriptions()->pluck('id')->toArray();
        $updatedPrescriptionIds = [];
    
        foreach ($prescriptionRows as $p) {
        
            if (isset($p['id']) && in_array($p['id'], $existingPrescriptionIds)) {
            
                // Update existing
                $treatment->prescriptions()->where('id', $p['id'])->update($p);
                $updatedPrescriptionIds[] = $p['id'];
            
            } else {
            
                // Create new
                $p['treatment_id'] = $treatment->id;
                $p['medicine_id'] = $p['inventory_item_id'] ?? null;
                $p['inventory_item_id'] = $p['inventory_item_id'] ?? null;
                $p['prescription_issue_date'] = $p['prescription_issue_date'] ?? now()->toDateString();
                $p['prescription_status'] = $p['prescription_status'] ?? 'active';
                $p['refill_count'] = $p['refill_count'] ?? 0;
            
                Prescription::create($p);
            }
        }
    
        // Delete removed prescriptions
        $toDeletePrescription = array_diff($existingPrescriptionIds, $updatedPrescriptionIds);
        if (!empty($toDeletePrescription)) {
            $treatment->prescriptions()->whereIn('id', $toDeletePrescription)->delete();
        }
    
        return redirect()
            ->route('treatments.index')
            ->with('success', 'Treatment updated successfully.');
    }


    public function createInvoice(Treatment $treatment)
    {
        // Check if invoice already exists
        if ($treatment->invoice) {
            return back()->with('error', 'Invoice already exists for this treatment');
        }

        // Ensure treatment has a patient
        if (!$treatment->patient_id) {
            return back()->with('error', 'Treatment must be associated with a patient to create an invoice');
        }

        // Calculate total amount including prescription costs
        $totalAmount = $treatment->cost;

        // Add prescription costs if they exist
        $prescriptionCosts = $treatment->prescriptions()->sum('prescription_amount');
        $totalAmount += $prescriptionCosts;

        // Create new invoice
        $invoice = \App\Models\Invoice::create([
            'treatment_id' => $treatment->id,
            'patient_id' => $treatment->patient_id,
            'amount' => $totalAmount,
            'status' => 'pending',
            'due_date' => now()->addDays(14),
        ]);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created');
    }
}