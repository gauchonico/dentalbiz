<?php

namespace Tests\Feature;

use App\Mail\EodReportMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_receipt_email_renders(): void
    {
        config(['mail.from.address' => 'billing@clinic.test']);
        $patient = Patient::create(['name' => 'Jane Nakato', 'phone' => '0772000000', 'dob' => '1990-05-12']);
        $invoice = Invoice::create(['patient_id' => $patient->id, 'amount' => 150000, 'status' => 'pending', 'due_date' => '2026-10-15']);
        $payment = $invoice->payments()->create(['amount' => 100000, 'method' => 'mobile_money', 'received_at' => now()]);

        $mail = new PaymentReceiptMail($invoice->fresh(), $payment->fresh());
        $html = $mail->render();

        $this->assertStringContainsString('Jane Nakato', $html);
        $this->assertStringContainsString('UGX 100,000', $html);
        $this->assertStringContainsString('Mobile Money', $html);
        $this->assertStringContainsString('UGX 50,000', $html);

        // The configured MAIL_FROM_ADDRESS is used, not a hard-coded sender
        config(['mail.default' => 'array']);
        Mail::to('patient@example.com')->send($mail);
        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertSame('billing@clinic.test', $sent[0]->getOriginalMessage()->getFrom()[0]->getAddress());
    }

    public function test_eod_report_command_emails_admins_with_yesterdays_figures(): void
    {
        config(['mail.default' => 'array']);
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['email' => 'owner@clinic.test']);
        $admin->assignRole('admin');

        Carbon::setTestNow('2026-09-29 00:10:00');
        $patient = Patient::create(['name' => 'Jane Nakato', 'phone' => '0772000000', 'dob' => '1990-05-12']);
        $invoice = Invoice::create(['patient_id' => $patient->id, 'amount' => 80000, 'status' => 'pending', 'due_date' => '2026-10-15']);
        $invoice->payments()->create(['amount' => 80000, 'method' => 'cash', 'received_at' => '2026-09-28 15:00:00']);

        $this->artisan('payments:eod-report', ['--email' => 'admins', '--output' => storage_path('framework/testing/eod.csv')])
            ->expectsOutputToContain('emailed to: owner@clinic.test')
            ->assertSuccessful();

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $body = $sent[0]->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('Sep 28, 2026', $body);
        $this->assertStringContainsString('UGX 80,000', $body);
    }
}
