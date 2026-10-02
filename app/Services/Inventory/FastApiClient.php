<?php

namespace App\Services\Inventory;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FastApiClient — HTTP client komunikasi Laravel ke Python FastAPI microservice.
 *
 * Menerapkan:
 * - Autentikasi X-API-Key
 * - Timeout terkonfigurasi
 * - Retry policy
 * - Graceful degradation (mengembalikan null saat microservice offline/error sehingga Guardrail aktif)
 */
class FastApiClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;
    protected int $retry;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('inventory.fastapi.url', 'http://127.0.0.1:8000'), '/');
        $this->apiKey = (string) config('inventory.fastapi.api_key', 'test-api-key');
        $this->timeout = (int) config('inventory.fastapi.timeout', 30);
        $this->retry = (int) config('inventory.fastapi.retry', 2);
    }

    /**
     * Cek status kesehatan microservice Python.
     */
    public function checkHealth(): bool
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-API-Key' => $this->apiKey])
                ->get("{$this->baseUrl}/health");

            return $response->successful();
        } catch (Exception $e) {
            Log::warning("FastApiClient: Health check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Jalankan peramalan batch untuk daftar SKU.
     *
     * @param array<int, array{item_id: int, lead_time_days: float, lead_time_std_days?: float, demand_pattern?: string, as_of_date?: string}> $items
     * @return array<int, array{item_id: int, mu_daily: float, sigma_daily: float, model_used: string, status: string}>|null
     */
    public function forecastBatch(array $items): ?array
    {
        if (empty($items)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retry, 100)
                ->withHeaders([
                    'X-API-Key'    => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post("{$this->baseUrl}/api/v1/forecast/batch", [
                    'items' => $items,
                ]);

            if (!$response->successful()) {
                Log::error("FastApiClient: forecastBatch HTTP error: {$response->status()} - {$response->body()}");
                return null;
            }

            $data = $response->json();
            return $data['results'] ?? null;
        } catch (Exception $e) {
            Log::error("FastApiClient: forecastBatch connection exception: " . $e->getMessage());
            return null; // Memicu Guardrail Fallback di Laravel
        }
    }

    /**
     * Memicu klasifikasi batch ABC-XYZ dan ADI/CV² di Python.
     *
     * @param string|null $asOfDate Tanggal YYYY-MM-DD
     * @return array|null
     */
    public function triggerClassificationBatch(?string $asOfDate = null): ?array
    {
        try {
            $response = Http::timeout($this->timeout * 2)
                ->retry($this->retry, 100)
                ->withHeaders([
                    'X-API-Key'    => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post("{$this->baseUrl}/api/v1/classification/batch", array_filter([
                    'as_of_date' => $asOfDate,
                ]));

            if (!$response->successful()) {
                Log::error("FastApiClient: triggerClassificationBatch HTTP error: {$response->status()}");
                return null;
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error("FastApiClient: triggerClassificationBatch exception: " . $e->getMessage());
            return null;
        }
    }
}
