<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\MonthlyUsage;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $role = auth()->user()->role;

        // ── Common Stats ──────────────────────────────────────────────────
        $totalItems    = Item::active()->count();
        $outOfStock    = Item::active()->outOfStock()->count();
        $lowStock      = Item::active()->lowStock()->count();
        $criticalItems = Item::active()->critical()->with('category')->orderBy('stock_on_hand')->get();
        $outItems      = Item::active()->outOfStock()->with('category')->get();

        $unreadNotifs  = Notification::forRole($role)->unread()->count();

        if ($role === 'it') {
            return $this->itDashboard($totalItems, $outOfStock, $lowStock, $unreadNotifs);
        }

        if ($role === 'procurement') {
            return $this->procurementDashboard($totalItems, $outOfStock, $lowStock, $criticalItems, $outItems, $unreadNotifs);
        }

        // gudang
        return $this->gudangDashboard($totalItems, $outOfStock, $lowStock, $criticalItems, $outItems, $unreadNotifs);
    }

    // ── IT Dashboard ──────────────────────────────────────────────────────────
    private function itDashboard($totalItems, $outOfStock, $lowStock, $unreadNotifs)
    {
        $totalUsers    = \App\Models\User::count();
        $activeUsers   = \App\Models\User::active()->count();
        $usersByRole   = \App\Models\User::select('role', DB::raw('count(*) as total'))
                            ->groupBy('role')->pluck('total', 'role');

        return view('dashboard.it', compact(
            'totalItems', 'outOfStock', 'lowStock',
            'totalUsers', 'activeUsers', 'usersByRole', 'unreadNotifs'
        ));
    }

    // ── Procurement Dashboard ─────────────────────────────────────────────────
    private function procurementDashboard($totalItems, $outOfStock, $lowStock, $criticalItems, $outItems, $unreadNotifs)
    {
        $pendingPO  = PurchaseOrder::where('status', 'pending')->count();
        $partialPO  = PurchaseOrder::where('status', 'partial')->count();
        $recentPOs  = PurchaseOrder::with(['item', 'supplier'])
                        ->orderByDesc('created_at')->limit(5)->get();

        // Top 10 most used items (last 3 months)
        $topItems = $this->getTopUsedItems(3, 10);

        // Monthly trend (last 6 months)
        $monthlyTrend = $this->getMonthlyTrend(6);

        // Items needing reorder
        $reorderItems = Item::active()
            ->whereRaw('(stock_on_hand + stock_on_order - stock_reserved) <= rop')
            ->with(['category', 'supplier'])
            ->orderByRaw('(stock_on_hand / NULLIF(rop,0))')
            ->limit(20)->get();

        return view('dashboard.procurement', compact(
            'totalItems', 'outOfStock', 'lowStock', 'pendingPO', 'partialPO',
            'recentPOs', 'topItems', 'monthlyTrend', 'criticalItems',
            'outItems', 'reorderItems', 'unreadNotifs'
        ));
    }

    // ── Gudang Dashboard ──────────────────────────────────────────────────────
    private function gudangDashboard($totalItems, $outOfStock, $lowStock, $criticalItems, $outItems, $unreadNotifs)
    {
        $todayTransactions = StockTransaction::whereDate('transaction_date', today())
            ->with('item')
            ->orderByDesc('created_at')
            ->limit(10)->get();

        $totalTransactionsToday = StockTransaction::whereDate('transaction_date', today())->count();

        return view('dashboard.gudang', compact(
            'totalItems', 'outOfStock', 'lowStock',
            'criticalItems', 'outItems',
            'todayTransactions', 'totalTransactionsToday', 'unreadNotifs'
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Get top N most used items over last X months.
     * Returns collection with item name and total_out.
     */
    public function getTopUsedItems(int $months = 3, int $limit = 10)
    {
        $since = now()->subMonths($months);

        return MonthlyUsage::select('item_id', DB::raw('SUM(total_out) as total'))
            ->where(function ($q) use ($since) {
                $q->where('year', '>', $since->year)
                  ->orWhere(function ($q2) use ($since) {
                      $q2->where('year', $since->year)->where('month', '>=', $since->month);
                  });
            })
            ->with('item:id,name,code,unit')
            ->groupBy('item_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * Get monthly total out for the last N months.
     */
    public function getMonthlyTrend(int $months = 6)
    {
        $since = now()->subMonths($months - 1);

        return MonthlyUsage::select('year', 'month', DB::raw('SUM(total_out) as total'))
            ->where(function ($q) use ($since) {
                $q->where('year', '>', $since->year)
                  ->orWhere(function ($q2) use ($since) {
                      $q2->where('year', $since->year)->where('month', '>=', $since->month);
                  });
            })
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get()
            ->map(function ($row) {
                $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                $row->label = ($months[$row->month - 1]) . ' ' . $row->year;
                return $row;
            });
    }
}
