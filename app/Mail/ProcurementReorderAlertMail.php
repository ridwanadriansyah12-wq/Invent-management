<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProcurementReorderAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $alertData [
     *    'item_id' => int,
     *    'item_code' => string,
     *    'item_name' => string,
     *    'unit' => string,
     *    'current_stock' => float,
     *    'rop_value' => float,
     *    'is_manual_override' => bool,
     *    'recommended_qty' => float,
     * ]
     */
    public function __construct(
        public array $alertData
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 [PERINGATAN ROP] Pengadaan Dibutuhkan: {$this->alertData['item_name']} ({$this->alertData['item_code']})"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reorder_alert'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
