<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Part;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\WorkCenter;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get dashboard analytics data
     */
    public function analytics(): JsonResponse
    {
        return response()->json([
            'summary' => $this->getSummary(),
            'workOrders' => $this->getWorkOrdersAnalytics(),
            'customerOrders' => $this->getCustomerOrdersAnalytics(),
            'inventory' => $this->getInventoryAnalytics(),
            'suppliers' => $this->getSuppliersAnalytics(),
        ]);
    }

    /**
     * Get summary statistics
     */
    private function getSummary(): array
    {
        $lowStockThreshold = $this->lowStockThreshold();

        return [
            'totalSuppliers' => Supplier::count(),
            'totalParts' => Part::count(),
            'totalWorkCenters' => WorkCenter::count(),
            'totalCustomers' => Customer::count(),
            'totalBoms' => Bom::count(),
            'lowStockItems' => Stock::where('quantity', '<=', $lowStockThreshold)->count(),
        ];
    }

    /**
     * Get work orders analytics
     */
    private function getWorkOrdersAnalytics(): array
    {
        return [
            'byStatus' => [
                // Pending in the dashboard groups both draft and released WOs.
                'pending' => WorkOrder::whereIn('status', ['draft', 'released'])->count(),
                'inProgress' => WorkOrder::where('status', 'in_progress')->count(),
                'completed' => WorkOrder::where('status', 'completed')->count(),
                'cancelled' => WorkOrder::where('status', 'cancelled')->count(),
            ],
            'total' => WorkOrder::count(),
            'recentWorkOrders' => WorkOrder::with('bom')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn($wo) => [
                    'id' => $wo->id,
                    'wo_number' => $wo->wo_number,
                    'status' => $wo->status,
                    'bom_name' => $wo->bom?->name ?? 'N/A',
                ]),
        ];
    }

    /**
     * Get customer orders analytics
     */
    private function getCustomerOrdersAnalytics(): array
    {
        return [
            'byStatus' => [
                'registered' => CustomerOrder::where('status', CustomerOrder::STATUS_REGISTERED)->count(),
                'confirmed' => CustomerOrder::where('status', CustomerOrder::STATUS_CONFIRMED)->count(),
                'picking' => CustomerOrder::where('status', CustomerOrder::STATUS_PICKING)->count(),
                'delivered' => CustomerOrder::where('status', CustomerOrder::STATUS_DELIVERED)->count(),
            ],
            'total' => CustomerOrder::count(),
            'monthlyTrend' => $this->getMonthlyCustomerOrdersTrend(),
            'topCustomers' => $this->getTopCustomers(),
        ];
    }

    /**
     * Get inventory analytics
     */
    private function getInventoryAnalytics(): array
    {
        $stocks = Stock::with('part', 'warehouse')->get();
        $lowStockThreshold = $this->lowStockThreshold();
        $overStockThreshold = $this->overStockThreshold();

        return [
            'total' => $stocks->count(),
            'lowStock' => $stocks->filter(fn($s) => $s->quantity <= $lowStockThreshold)->count(),
            'overstock' => $stocks->filter(fn($s) => $s->quantity >= $overStockThreshold)->count(),
            'topStockItems' => $stocks
                ->sortByDesc('quantity')
                ->take(5)
                ->map(fn($s) => [
                    'part_name' => $s->part?->name ?? 'N/A',
                    'quantity' => $s->quantity,
                    'warehouse' => $s->warehouse?->name ?? 'N/A',
                ]),
        ];
    }

    /**
     * Get suppliers analytics
     */
    private function getSuppliersAnalytics(): array
    {
        return [
            'total' => Supplier::count(),
            'topSuppliers' => Supplier::withCount('parts')
                ->orderByDesc('parts_count')
                ->limit(5)
                ->get()
                ->map(fn($s) => [
                    'supplier_name' => $s->name,
                    'parts_count' => $s->parts_count,
                ]),
        ];
    }

    /**
     * Get monthly customer orders trend
     */
    private function getMonthlyCustomerOrdersTrend(): array
    {
        $months = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');
            $label = $date->format('M Y');

            $count = CustomerOrder::whereBetween('order_date', [
                $date->startOfMonth(),
                $date->endOfMonth(),
            ])->count();

            $labels[] = $label;
            $months[] = $count;
        }

        return [
            'labels' => $labels,
            'data' => $months,
        ];
    }

    /**
     * Get top customers
     */
    private function getTopCustomers(): array
    {
        return Customer::withCount('orders')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'customer_name' => $c->name,
                'orders_count' => $c->orders_count,
            ])
            ->toArray();
    }

    /**
     * Low stock threshold from app settings.
     */
    private function lowStockThreshold(): int
    {
        return max(0, (int) AppSetting::get('inventory_low_stock_threshold', 10));
    }

    /**
     * Overstock threshold from app settings.
     */
    private function overStockThreshold(): int
    {
        $lowStockThreshold = $this->lowStockThreshold();
        $defaultOverStock = max($lowStockThreshold + 1, 100);

        return max($lowStockThreshold + 1, (int) AppSetting::get('inventory_overstock_threshold', $defaultOverStock));
    }
}
