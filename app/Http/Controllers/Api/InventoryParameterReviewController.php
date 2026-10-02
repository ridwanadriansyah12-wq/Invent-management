<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryParameter;
use App\Services\Inventory\GuardrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * InventoryParameterReviewController — Endpoint API untuk persetujuan / penolakan
 * parameter yang terkena clamping (PENDING_REVIEW).
 */
class InventoryParameterReviewController extends Controller
{
    public function __construct(
        protected GuardrailService $guardrailService
    ) {}

    /**
     * Daftar parameter yang memerlukan peninjauan manusia (Human-in-the-loop).
     */
    public function pendingReviews(): JsonResponse
    {
        $parameters = InventoryParameter::with(['item.category', 'item.warehouse'])
            ->where('status', 'PENDING_REVIEW')
            ->orderBy('computed_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data'    => $parameters,
        ]);
    }

    /**
     * Menyetujui parameter usulan (Proposed -> Effective).
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var InventoryParameter|null $param */
        $param = InventoryParameter::where('id', $id)
            ->where('status', 'PENDING_REVIEW')
            ->first();

        if (!$param) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory parameter not found or not in PENDING_REVIEW status.',
            ], 404);
        }

        $userId = Auth::id() ?? 1; // Default to system/auth user ID
        $updated = $this->guardrailService->approveReview($param, $userId, $validated['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Parameter successfully approved.',
            'data'    => $updated,
        ]);
    }

    /**
     * Menolak usulan perubahan (tetap memakai nilai clamped yang aman).
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var InventoryParameter|null $param */
        $param = InventoryParameter::where('id', $id)
            ->where('status', 'PENDING_REVIEW')
            ->first();

        if (!$param) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory parameter not found or not in PENDING_REVIEW status.',
            ], 404);
        }

        $userId = Auth::id() ?? 1;
        $updated = $this->guardrailService->rejectReview($param, $userId, $validated['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Parameter rejected. Clamped value retained.',
            'data'    => $updated,
        ]);
    }
}
