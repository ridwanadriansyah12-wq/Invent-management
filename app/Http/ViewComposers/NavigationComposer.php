<?php

namespace App\Http\ViewComposers;

use App\Models\InventoryParameter;
use App\Models\PurchaseRequisition;
use App\Models\SystemAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class NavigationComposer
{
    /**
     * Bind data counter badge ke view navigation/layout (cache 60 detik).
     */
    public function compose(View $view): void
    {
        $pendingReviewsCount = Cache::remember('prism_pending_review_count', 60, function () {
            return InventoryParameter::where('status', 'PENDING_REVIEW')->count();
        });

        $unresolvedAlertsCount = Cache::remember('prism_unresolved_alerts_count', 60, function () {
            return SystemAlert::whereNull('resolved_at')->count();
        });

        $openPrCount = Cache::remember('prism_open_pr_count', 60, function () {
            return PurchaseRequisition::where('status', 'OPEN')->count();
        });

        $view->with([
            'navPendingReviewsCount'   => $pendingReviewsCount,
            'navUnresolvedAlertsCount' => $unresolvedAlertsCount,
            'navOpenPrCount'           => $openPrCount,
        ]);
    }
}
