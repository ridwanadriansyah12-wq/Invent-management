<?php

namespace App\Jobs;

use App\Mail\ProcurementReorderAlertMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReorderEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * @param array $alertData
     */
    public function __construct(
        public array $alertData
    ) {}

    public function handle(): void
    {
        // Cari pengguna dengan role procurement atau admin aktif
        $recipients = User::whereIn('role', ['procurement', 'admin'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->toArray();

        // Fallback jika tidak ada user procurement khusus di DB
        if (empty($recipients)) {
            $recipients = [config('mail.from.address', 'procurement@company.com')];
        }

        try {
            Mail::to($recipients)->send(new ProcurementReorderAlertMail($this->alertData));
            Log::info("SendReorderEmailNotification: Alert email sent for item #{$this->alertData['item_id']} to " . implode(',', $recipients));
        } catch (\Throwable $e) {
            Log::error("SendReorderEmailNotification: Failed to send email for item #{$this->alertData['item_id']}: " . $e->getMessage());
            
            // Pada mode queue 'sync' (development/CLI), jangan gagalkan proses batch looping checker
            if (config('queue.default') !== 'sync') {
                throw $e; // Trigger asynchronous queue retry
            }
        }
    }
}
