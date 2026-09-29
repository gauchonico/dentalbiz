<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Payment $payment,
    ) {}

    public function build()
    {
        $receiptUrl = route('invoices.payments.receipt', [$this->invoice->id, $this->payment->id]);

        return $this
            ->subject('Payment Receipt - Invoice #'.$this->invoice->id)
            ->view('emails.payment-receipt')
            ->with([
                'invoice' => $this->invoice,
                'payment' => $this->payment,
                'receiptUrl' => $receiptUrl,
            ]);
    }
}
