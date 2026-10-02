<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * InventoryPipelineCoordinator — Koordinator eksekusi pipeline persediaan harian dan mingguan.
 *
 * Menghubungkan semua tahapan arsitektur:
 * Tahap 1-3: Python FastAPI (pipeline demand, klasifikasi, forecasting)
 * Tahap 4: ParameterCalculator (SS, ROP, Q_target, MAX)
 * Tahap 5: GuardrailService (validasi, umur SKU, clamping, supersede)
 * Tahap 6: CapacityChecker (penyesuaian bertingkat kapasitas gudang C -> B -> A)
 * Tahap 7: PurchaseRequisitionTrigger (sanitizer & penerbitan PR)
 */
use App\Models\PipelineRun;
use App\Models\SystemAlert;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Str;

class InventoryPipelineCoordinator
{
    public function __construct(
        protected FastApiClient $fastApiClient,
        protected GuardrailService $guardrailService,
        protected CapacityChecker $capacityChecker,
        protected PurchaseRequisitionTrigger $prTrigger,
        protected ?SettingsService $settings = null
    ) {
        $this->settings = $this->settings ?? app(SettingsService::class);
    }

    /**
     * Jalankan pipeline persediaan harian untuk seluruh item aktif.
     *
     * @param Carbon|null $asOfDate Tanggal eksekusi (default: hari ini)
     * @param int|null $triggeredBy ID User pemicu manual, atau null untuk otomatis/scheduler
     * @return array Ringkasan hasil eksekusi pipeline
     */
    public function runDailyPipeline(?Carbon $asOfDate = null, ?int $triggeredBy = null): array
    {
        $now = $asOfDate ?? Carbon::now();
        $dateStr = $now->toDateString();

        Log::info("InventoryPipelineCoordinator: Starting daily pipeline for date {$dateStr}");

        $pipelineRun = PipelineRun::create([
            'run_id'            => (string) Str::uuid(),
            'type'              => 'DAILY',
            'started_at'        => Carbon::now(),
            'status'            => 'RUNNING',
            'triggered_by'      => $triggeredBy,
            'settings_snapshot' => $this->settings->getSnapshot(),
        ]);

        try {
            // 1. Ambil semua item aktif beserta relasi yang diperlukan
            $items = Item::with(['category', 'warehouse', 'classification', 'activeParameter'])
                ->where('is_active', true)
                ->get();

            $defaultLt = (float) $this->settings->get('lead_time_days_default', 15);
            $defaultLtStd = (float) $this->settings->get('lead_time_std_days_default', 0.0);

            // 2. Siapkan item untuk peramalan FastAPI
            $forecastPayload = [];
            foreach ($items as $item) {
                $forecastPayload[] = [
                    'item_id'            => $item->id,
                    'lead_time_days'     => (float) ($item->lead_time_days ?? $defaultLt),
                    'lead_time_std_days' => (float) ($item->lead_time_std_days ?? $defaultLtStd),
                    'demand_pattern'     => $item->classification?->demand_pattern ?? 'smooth',
                    'as_of_date'         => $dateStr,
                ];
            }

            // 3. Panggil FastAPI untuk batch forecast (mengembalikan null jika gagal/timeout)
            $mlResults = $this->fastApiClient->forecastBatch($forecastPayload);
            $mlResultsByItemId = [];
            if ($mlResults) {
                foreach ($mlResults as $res) {
                    if (isset($res['item_id'])) {
                        $mlResultsByItemId[$res['item_id']] = $res;
                    }
                }
            } else {
                SystemAlert::firstOrCreate(
                    [
                        'type'        => 'ML_SERVICE_DOWN',
                        'resolved_at' => null,
                    ],
                    [
                        'message'     => 'FastAPI ML Service tidak merespons atau timeout saat batch forecast harian. Sistem beralih ke rute fallback.',
                    ]
                );
            }

            // 4. Tahap 4 & 5: Hitung parameter dan evaluasi Guardrail per SKU
            $parametersCreated = 0;
            $activeCount = 0;
            $pendingReviewCount = 0;
            $fallbackCount = 0;

            foreach ($items as $item) {
                $forecastData = $mlResultsByItemId[$item->id] ?? null;

                $param = $this->guardrailService->processItem($item, $forecastData, $now);
                $parametersCreated++;

                if ($param->status === 'ACTIVE') {
                    $activeCount++;
                } elseif ($param->status === 'PENDING_REVIEW') {
                    $pendingReviewCount++;
                }

                if ($param->source === 'FALLBACK_LAST_APPROVED') {
                    $fallbackCount++;
                }
            }

            // 5. Tahap 6: Evaluasi Kapasitas Gudang
            $capacityResults = $this->capacityChecker->checkAndAdjustAll();

            // 6. Tahap 7: Evaluasi dan Terbitkan Purchase Requisitions
            $refreshedItems = Item::with(['activeParameter', 'warehouse'])
                ->where('is_active', true)
                ->get();

            $generatedPrs = collect();
            foreach ($refreshedItems as $it) {
                $pr = $this->prTrigger->evaluateItem($it);
                if ($pr) {
                    $generatedPrs->push($pr);
                }
            }

            $summary = [
                'run_id'                => $pipelineRun->run_id,
                'date'                  => $dateStr,
                'items_processed'       => $items->count(),
                'ml_forecast_success'   => $mlResults !== null,
                'parameters_created'    => $parametersCreated,
                'active_parameters'     => $activeCount,
                'pending_review'        => $pendingReviewCount,
                'fallbacks_triggered'   => $fallbackCount,
                'warehouses_evaluated'  => count($capacityResults),
                'prs_generated'         => $generatedPrs->count(),
            ];

            $pipelineRun->update([
                'finished_at'   => Carbon::now(),
                'status'        => 'COMPLETED',
                'total_items'   => $items->count(),
                'failed_items'  => $fallbackCount,
                'error_summary' => $mlResults === null ? 'ML_FORECAST_UNAVAILABLE_FALLBACK_USED' : null,
            ]);

            Log::info("InventoryPipelineCoordinator: Daily pipeline completed.", $summary);

            return $summary;
        } catch (\Throwable $e) {
            $pipelineRun->update([
                'finished_at'   => Carbon::now(),
                'status'        => 'FAILED',
                'error_summary' => $e->getMessage(),
            ]);

            SystemAlert::create([
                'type'    => 'PIPELINE_ERROR',
                'message' => "Kegagalan pada eksekusi pipeline harian run {$pipelineRun->run_id}: {$e->getMessage()}",
            ]);

            throw $e;
        }
    }

    /**
     * Jalankan klasifikasi berkala (mingguan) ABC-XYZ & ADI/CV² via Python microservice.
     */
    public function runWeeklyClassification(?Carbon $asOfDate = null, ?int $triggeredBy = null): ?array
    {
        $now = $asOfDate ?? Carbon::now();
        Log::info("InventoryPipelineCoordinator: Triggering weekly classification for date {$now->toDateString()}");

        $pipelineRun = PipelineRun::create([
            'run_id'            => (string) Str::uuid(),
            'type'              => 'WEEKLY',
            'started_at'        => Carbon::now(),
            'status'            => 'RUNNING',
            'triggered_by'      => $triggeredBy,
            'settings_snapshot' => $this->settings->getSnapshot(),
        ]);

        try {
            $result = $this->fastApiClient->triggerClassificationBatch($now->toDateString());

            $pipelineRun->update([
                'finished_at'   => Carbon::now(),
                'status'        => $result !== null ? 'COMPLETED' : 'FAILED',
                'error_summary' => $result === null ? 'FASTAPI_CLASSIFICATION_FAILED' : null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $pipelineRun->update([
                'finished_at'   => Carbon::now(),
                'status'        => 'FAILED',
                'error_summary' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
