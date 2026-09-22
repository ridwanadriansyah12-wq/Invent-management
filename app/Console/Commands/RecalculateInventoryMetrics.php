<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\MLInventoryEngine;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class RecalculateInventoryMetrics extends Command
{
    protected $signature   = 'inventory:recalculate {--item= : Hanya hitung satu item berdasarkan ID}';
    protected $description = 'Hitung ulang SS, ROP, dan Max Stok untuk semua barang aktif (ML Engine)';

    public function __construct(
        private MLInventoryEngine   $ml,
        private NotificationService $notifService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Item::active()->with('monthlyUsages');

        if ($itemId = $this->option('item')) {
            $query->where('id', $itemId);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->warn('Tidak ada barang aktif ditemukan.');
            return self::SUCCESS;
        }

        $this->info("Memproses {$items->count()} barang...");
        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        $updated = 0;
        $errors  = 0;

        foreach ($items as $item) {
            try {
                $this->ml->recalculate($item);
                $this->notifService->checkAndNotify($item->fresh());
                $updated++;
            } catch (\Throwable $e) {
                $this->error("\n[ERROR] Item #{$item->id} {$item->name}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Selesai: {$updated} barang diperbarui, {$errors} error.");

        return self::SUCCESS;
    }
}
