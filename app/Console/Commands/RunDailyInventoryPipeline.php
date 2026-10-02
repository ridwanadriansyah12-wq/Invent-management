<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryPipelineCoordinator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunDailyInventoryPipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:pipeline-daily {--date= : Tanggal evaluasi YYYY-MM-DD (default: hari ini)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan pipeline persediaan harian (Forecast -> Guardrail -> Capacity -> PR Trigger)';

    /**
     * Execute the console command.
     */
    public function handle(InventoryPipelineCoordinator $coordinator): int
    {
        $dateOption = $this->option('date');
        $asOfDate = $dateOption ? Carbon::parse($dateOption) : Carbon::now();

        $this->info("Menjalankan Daily Inventory Pipeline untuk tanggal: {$asOfDate->toDateString()}");

        $summary = $coordinator->runDailyPipeline($asOfDate);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Tanggal', $summary['date']],
                ['Item Diproses', $summary['items_processed']],
                ['Status Microservice ML', $summary['ml_forecast_success'] ? 'Sukses' : 'Gagal / Fallback'],
                ['Parameter Dibuat', $summary['parameters_created']],
                ['Parameter Status ACTIVE', $summary['active_parameters']],
                ['Parameter PENDING_REVIEW', $summary['pending_review']],
                ['Parameter FALLBACK', $summary['fallbacks_triggered']],
                ['Gudang Dievaluasi', $summary['warehouses_evaluated']],
                ['PR Diterbitkan', $summary['prs_generated']],
            ]
        );

        $this->info('Daily Inventory Pipeline selesai.');
        return Command::SUCCESS;
    }
}
