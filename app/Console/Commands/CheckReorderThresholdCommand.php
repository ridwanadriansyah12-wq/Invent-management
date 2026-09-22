<?php

namespace App\Console\Commands;

use App\Jobs\SendReorderEmailNotification;
use App\Models\Item;
use App\Services\InventoryParameterService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckReorderThresholdCommand extends Command
{
    protected $signature = 'inventory:check-reorder 
                            {--item= : Periksa item tertentu berdasarkan ID}
                            {--force : Abaikan cooldown anti-spam 24 jam dan paksa kirim}';

    protected $description = 'Pemeriksaan latar belakang terjadwal: Kirim email alert jika Stok <= ROP';

    public function __construct(
        private InventoryParameterService $paramService,
        private NotificationService $notifService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Memulai Scheduled Job pemeriksaan threshold ROP...');

        $query = Item::active();

        if ($itemId = $this->option('item')) {
            $query->where('id', $itemId);
        }

        $force = (bool) $this->option('force');
        $triggeredCount = 0;
        $skippedCooldown = 0;
        $scannedCount = 0;

        // Gunakan chunkById untuk efisiensi memory & I/O pada dataset besar
        $query->chunkById(200, function ($items) use (&$triggeredCount, &$skippedCooldown, &$scannedCount, $force) {
            foreach ($items as $item) {
                $scannedCount++;

                // Ambil parameter dengan decoupling lookup (Redis In-Memory first)
                $params = $this->paramService->getEffectiveParameters($item->id);
                $effectiveRop = (float) $params['rop'];

                // Kondisi Pemicu: ROP valid dan Current Stock <= ROP
                if ($effectiveRop > 0 && $item->stock_on_hand <= $effectiveRop) {
                    // Cek Cooldown Anti-Spam
                    if (!$force && $this->paramService->isAlertInCooldown($item->id)) {
                        $skippedCooldown++;
                        continue;
                    }

                    // Hitung Rekomendasi Reorder Qty = Max(0, MaxStock - InventoryPosition)
                    $inventoryPosition = $item->stock_on_hand + $item->stock_on_order - $item->stock_reserved;
                    $recommendedQty    = max(0, $item->max_stock - $inventoryPosition);
                    if ($recommendedQty <= 0) {
                        $recommendedQty = round($effectiveRop * 1.5, 2);
                    }

                    $alertPayload = [
                        'item_id'            => $item->id,
                        'item_code'          => $item->code,
                        'item_name'          => $item->name,
                        'unit'               => $item->unit,
                        'current_stock'      => (float) $item->stock_on_hand,
                        'rop_value'          => $effectiveRop,
                        'is_manual_override' => (bool) $params['is_override'],
                        'recommended_qty'    => (float) $recommendedQty,
                    ];

                    // 1. Dispatch Asynchronous Queue Job untuk Email
                    SendReorderEmailNotification::dispatch($alertPayload);

                    // 2. Set Cooldown di Redis (24 Jam)
                    $this->paramService->setAlertCooldown($item->id, 86400);

                    // 3. Catat juga ke In-App Notification (Database)
                    $this->notifService->checkAndNotify($item);

                    $source = $params['is_override'] ? 'Manual Override' : 'ML Engine';
                    $this->warn("🚨 [ALERT TRIGGERED] {$item->name} (Stok: {$item->stock_on_hand} <= ROP: {$effectiveRop} [{$source}]) -> Email dipicu.");
                    $triggeredCount++;
                }
            }
        });

        $this->newLine();
        $this->info("✅ Pemeriksaan selesai:");
        $this->line("   - Total item diperiksa : {$scannedCount}");
        $this->line("   - Email alert dipicu   : {$triggeredCount}");
        $this->line("   - Dilewati (Cooldown)  : {$skippedCooldown}");

        return self::SUCCESS;
    }
}
