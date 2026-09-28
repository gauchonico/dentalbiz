<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OdontogramController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CashDrawerController;
use App\Http\Controllers\MedicalHistoryController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ClinicalNotesController;
use App\Http\Controllers\CashSessionsAdminController;
use App\Http\Controllers\ClinicalNoteTemplatesAdminController;
use App\Http\Controllers\TreatmentProcedurePriceController;
use App\Http\Controllers\PatientImageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LandingController::class, 'index'])->name('landing');

// Public Demo scheduling page
Route::get('/demo', [DemoController::class, 'index'])->name('demo');
Route::post('/demo', [DemoController::class, 'store'])->name('demo.store');

// Marketing pages
Route::get('/features', function () {
    return Inertia::render('Marketing/Features');
})->name('features');

Route::get('/pricing', function () {
    return Inertia::render('Marketing/Pricing');
})->name('pricing');

Route::get('/about', function () {
    return Inertia::render('Marketing/About');
})->name('about');

Route::get('/contact', function () {
    return Inertia::render('Marketing/Contact');
})->name('contact');

Route::get('/careers', function () {
    return Inertia::render('Marketing/Careers');
})->name('careers');

Route::get('/privacy', function () {
    return Inertia::render('Marketing/Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Marketing/Terms');
})->name('terms');

Route::get('/security', function () {
    return Inertia::render('Marketing/Security');
})->name('security');

// Auth routes (Breeze)
require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Resources
    Route::resource('patients', PatientController::class);

    Route::resource('appointments', AppointmentController::class);

    Route::resource('treatments', TreatmentController::class);

    Route::resource('invoices', InvoiceController::class);

    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])
        ->middleware('can:view,invoice')
        ->name('invoices.download');

    Route::put('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])
        ->middleware('can:update,invoice')
        ->name('invoices.mark-paid');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])
        ->middleware('can:update,invoice')
        ->name('invoices.payments.store');
    Route::get('invoices/{invoice}/payments/{payment}/receipt', [InvoiceController::class, 'paymentReceipt'])
        ->middleware('can:view,invoice')
        ->name('invoices.payments.receipt');
    Route::post('invoices/{invoice}/payments/{payment}/refund', [InvoiceController::class, 'refundPayment'])
        ->middleware('auth')
        ->name('invoices.payments.refund');
    Route::get('invoices/payments/export', [InvoiceController::class, 'exportPayments'])
        ->middleware('can:viewAny,App\\Models\\Invoice')
        ->name('invoices.payments.export');
    // Staff Management Routes
    Route::resource('staff', StaffController::class);

    Route::put('staff/{staff}/update-roles', [StaffController::class, 'updateRoles'])
        ->middleware('can:update,staff')
        ->name('staff.update-roles');

    Route::post('staff/{staff}/send-reset-link', [StaffController::class, 'sendResetLink'])
        ->middleware('can:update,staff')
        ->name('staff.send-reset-link');

    // Inventory routes with admin-only delete
    Route::resource('inventory', InventoryController::class)
        ->parameters(['inventory' => 'id'])
        ->except(['create', 'edit', 'destroy'])
        ->middleware('auth');

    Route::post('/inventory/{id}/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
    Route::post('/inventory/{id}/use', [InventoryController::class, 'useItem'])->name('inventory.use');

    // Explicit delete route with admin-only middleware
    Route::delete('inventory/{id}', [InventoryController::class, 'destroy'])
        ->name('inventory.destroy')
        ->middleware('can:delete,App\Models\InventoryItem');

    Route::resource('expenses', ExpenseController::class);

    // Odontogram
    Route::get('/patients/{patient}/odontogram', [OdontogramController::class, 'show'])
        ->middleware('can:view,patient')
        ->name('patients.odontogram.show');

    Route::post('/patients/{patient}/odontogram', [OdontogramController::class, 'store'])
        ->middleware('can:update,patient')
        ->name('patients.odontogram.store');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('can:viewReports')
        ->name('reports.index');

    // Cash Drawer
    Route::get('/cash-drawer/active', [CashDrawerController::class, 'active'])
        ->middleware('auth')
        ->name('cash-drawer.active');
    Route::get('/cash-drawer', [CashDrawerController::class, 'index'])
        ->middleware('auth')
        ->name('cash-drawer.index');
    Route::post('/cash-drawer/open', [CashDrawerController::class, 'open'])
        ->middleware('auth')
        ->name('cash-drawer.open');
    Route::post('/cash-drawer/close', [CashDrawerController::class, 'close'])
        ->middleware('auth')
        ->name('cash-drawer.close');
    Route::post('/cash-drawer/sessions/{session}/reconcile', [CashDrawerController::class, 'reconcile'])
        ->name('cash-drawer.reconcile');
    Route::post('/cash-drawer/adjust', [CashDrawerController::class, 'adjust'])
        ->middleware('auth')
        ->name('cash-drawer.adjust');

    // Cash Sessions Admin
    Route::get('/admin/cash-sessions', [CashSessionsAdminController::class, 'index'])
        ->middleware('can:viewReports')
        ->name('admin.cash-sessions.index');

    // Clinical Note Templates Admin
    Route::get('/admin/treatment-procedure-prices', [TreatmentProcedurePriceController::class, 'index'])
        ->middleware('can:manageTreatmentPrices')
        ->name('admin.treatment-procedure-prices.index');
    Route::post('/admin/treatment-procedure-prices', [TreatmentProcedurePriceController::class, 'store'])
        ->middleware('can:manageTreatmentPrices')
        ->name('admin.treatment-procedure-prices.store');
    Route::post('/admin/treatment-procedure-prices/{price}', [TreatmentProcedurePriceController::class, 'update'])
        ->middleware('can:manageTreatmentPrices')
        ->name('admin.treatment-procedure-prices.update');
    Route::post('/admin/treatment-procedure-prices/{price}/toggle', [TreatmentProcedurePriceController::class, 'toggle'])
        ->middleware('can:manageTreatmentPrices')
        ->name('admin.treatment-procedure-prices.toggle');
    Route::delete('/admin/treatment-procedure-prices/{price}', [TreatmentProcedurePriceController::class, 'destroy'])
        ->middleware('can:manageTreatmentPrices')
        ->name('admin.treatment-procedure-prices.destroy');

    Route::get('/admin/clinical-note-templates', [ClinicalNoteTemplatesAdminController::class, 'index'])
        ->middleware('can:manageClinicalTemplates')
        ->name('admin.clinical-note-templates.index');
    Route::post('/admin/clinical-note-templates', [ClinicalNoteTemplatesAdminController::class, 'store'])
        ->middleware('can:manageClinicalTemplates')
        ->name('admin.clinical-note-templates.store');
    Route::post('/admin/clinical-note-templates/{template}', [ClinicalNoteTemplatesAdminController::class, 'update'])
        ->middleware('can:manageClinicalTemplates')
        ->name('admin.clinical-note-templates.update');
    Route::post('/admin/clinical-note-templates/{template}/toggle', [ClinicalNoteTemplatesAdminController::class, 'toggle'])
        ->middleware('can:manageClinicalTemplates')
        ->name('admin.clinical-note-templates.toggle');
    Route::delete('/admin/clinical-note-templates/{template}', [ClinicalNoteTemplatesAdminController::class, 'destroy'])
        ->middleware('can:manageClinicalTemplates')
        ->name('admin.clinical-note-templates.destroy');

    // Imaging (X-rays and clinical photos)
    Route::scopeBindings()->group(function () {
        Route::get('/patients/{patient}/imaging', [PatientImageController::class, 'index'])
            ->name('patients.images.index');
        Route::post('/patients/{patient}/imaging', [PatientImageController::class, 'store'])
            ->name('patients.images.store');
        Route::get('/patients/{patient}/imaging/{image}/file', [PatientImageController::class, 'file'])
            ->name('patients.images.file');
        Route::delete('/patients/{patient}/imaging/{image}', [PatientImageController::class, 'destroy'])
            ->name('patients.images.destroy');
    });

    // Medical History
    Route::get('/patients/{patient}/medical-history', [MedicalHistoryController::class, 'show'])
        ->middleware('can:view,patient')
        ->name('patients.medical-history.show');
    Route::post('/patients/{patient}/medical-history', [MedicalHistoryController::class, 'upsert'])
        ->middleware('can:update,patient')
        ->name('patients.medical-history.upsert');

    // Consents (per patient)
    Route::get('/patients/{patient}/consents', [ConsentController::class, 'patientIndex'])
        ->middleware('can:view,patient')
        ->name('patients.consents.index');
    Route::post('/patients/{patient}/consents/sign', [ConsentController::class, 'patientSign'])
        ->middleware('can:update,patient')
        ->name('patients.consents.sign');
    Route::get('/patients/{patient}/consents/{consent}/pdf', [ConsentController::class, 'patientPdf'])
        ->middleware('can:view,patient')
        ->name('patients.consents.pdf');

    // Clinical Notes (SOAP)
    Route::get('/patients/{patient}/notes', [ClinicalNotesController::class, 'index'])
        ->middleware('can:view,patient')
        ->name('patients.notes.index');
    Route::post('/patients/{patient}/notes', [ClinicalNotesController::class, 'store'])
        ->middleware('can:update,patient')
        ->name('patients.notes.store');
    Route::post('/patients/{patient}/notes/{note}', [ClinicalNotesController::class, 'update'])
        ->middleware('can:update,patient')
        ->name('patients.notes.update');
    Route::post('/patients/{patient}/notes/{note}/sign', [ClinicalNotesController::class, 'sign'])
        ->middleware('can:update,patient')
        ->name('patients.notes.sign');
    Route::get('/patients/{patient}/notes/{note}/pdf', [ClinicalNotesController::class, 'pdf'])
        ->middleware('can:view,patient')
        ->name('patients.notes.pdf');
    Route::delete('/patients/{patient}/notes/{note}', [ClinicalNotesController::class, 'destroy'])
        ->middleware('can:update,patient')
        ->name('patients.notes.destroy');

    // Consent templates (admin/receptionist via viewReports gate)
    Route::get('/consent-templates', [ConsentController::class, 'templatesIndex'])
        ->middleware('can:viewReports')
        ->name('consents.templates.index');
    Route::post('/consent-templates', [ConsentController::class, 'templatesStore'])
        ->middleware('can:viewReports')
        ->name('consents.templates.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('treatments/{treatment}/create-invoice', [TreatmentController::class, 'createInvoice'])->name('treatments.createInvoice');
});