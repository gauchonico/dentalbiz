<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Treatment;
use App\Models\InventoryItem;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $todayAppointments = Appointment::with(['patient'])
            ->whereBetween('start_time', [$todayStart, $todayEnd])
            ->orderBy('start_time')
            ->get();

        if ($todayAppointments->isEmpty()) {
            $todayAppointments = Appointment::with(['patient'])
                ->where('start_time', '>', now())
                ->orderBy('start_time')
                ->take(3)
                ->get();
        }

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => auth()->user()?->load('roles'),
            ],
            'stats' => [
                'total_patients' => Patient::count(),
                'upcoming_appointments' => Appointment::where('start_time', '>', now())->count(),
                'monthly_revenue' => Invoice::whereMonth('created_at', now()->month)
                    ->whereIn('status', ['paid', 'partial'])
                    ->sum('amount'),
                'low_stock_items' => InventoryItem::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
            ],
            'todaysAppointments' => $todayAppointments
                ->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'patient_name' => $appointment->patient->name ?? 'Unknown',
                        'time' => Carbon::parse($appointment->start_time)->format('h:i A'),
                        'status' => ucfirst((string) $appointment->status),
                        'notes' => $appointment->notes,
                    ];
                }),
            'recentActivity' => $this->getRecentActivity(),
            'pendingTasks' => [
                'unpaid_invoices' => Invoice::whereIn('status', ['pending', 'overdue'])->count(),
                'pending_appointments' => Appointment::whereIn('status', ['pending', 'scheduled'])->count(),
                'low_stock_count' => InventoryItem::whereColumn('quantity', '<=', 'low_stock_threshold')->count(),
            ],
        ]);
    }

    private function getRecentActivity()
    {
        $activities = collect();

        // Recent patients
        $recentPatients = Patient::orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($patient) => [
                'type' => 'patient',
                'icon' => 'fa-user',
                'color' => 'blue',
                'message' => "New patient registered: {$patient->name}",
                'time' => Carbon::parse($patient->created_at)->diffForHumans(),
            ]);

        // Recent treatments
        $recentTreatments = Treatment::with('patient')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($treatment) => [
                'type' => 'treatment',
                'icon' => 'fa-tooth',
                'color' => 'green',
                'message' => "Treatment completed for {$treatment->patient->name}",
                'time' => Carbon::parse($treatment->created_at)->diffForHumans(),
            ]);

        // Recent invoices
        $recentInvoices = Invoice::with('patient')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($invoice) => [
                'type' => 'invoice',
                'icon' => 'fa-file-invoice',
                'color' => 'purple',
                'message' => "Invoice {$invoice->status} for {$invoice->patient->name}",
                'time' => Carbon::parse($invoice->created_at)->diffForHumans(),
            ]);

        return $activities
            ->merge($recentPatients)
            ->merge($recentTreatments)
            ->merge($recentInvoices)
            ->sortByDesc('time')
            ->take(8)
            ->values();
    }
}