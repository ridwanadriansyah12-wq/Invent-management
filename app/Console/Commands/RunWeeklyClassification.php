<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryPipelineCoordinator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunWeeklyClassification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:classify-weekly {--date= : Tanggal evaluasi YYYY-MM-DD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan klasifikasi berkala mingguan (ABC-XYZ & ADI/CV²) via Python microservice';

    /**
     * Execute the console command.
     */
    public function handle(InventoryPipelineCoordinator $coordinator): int
    {
        $dateOption = $this->option('date');
        $asOfDate = $dateOption ? Carbon::parse($dateOption) : Carbon::now();

        $this->info("Menjalankan klasifikasi mingguan untuk tanggal: {$asOfDate->toDateString()}");

        $result = $coordinator->runWeeklyClassification($asOfDate);

        if ($result === null) {
            $this->error('Gagal menghubungi Python microservice untuk klasifikasi.');
            return Command::FAILURE;
        }

        $this->info('Klasifikasi mingguan selesai.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
