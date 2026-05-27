<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(): JsonResponse
    {
        $totalCitizens    = User::citizens()->count();
        $totalPharmacies  = User::pharmacies()->verified()->count();
        $totalMedicines   = Medicine::active()->count();
        $totalOrders      = Order::count();
        $totalRevenue     = Order::where('status', 'delivered')->sum('total_price');
        $avgOrderValue    = Order::where('status', 'delivered')->avg('total_price');
        $recentComplaints = Complaint::where('status', 'open')->count();

        // Month-over-month growth
        $thisMonth = Order::whereMonth('created_at', now()->month)
                         ->whereYear('created_at', now()->year)
                         ->count();
        $lastMonth = Order::whereMonth('created_at', now()->subMonth()->month)
                         ->whereYear('created_at', now()->subMonth()->year)
                         ->count();
        $growth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        // Top medicines — FIXED: no longer uses JSON_TABLE (MySQL 8.0+ only)
        // Instead pulls all delivered orders and aggregates in PHP
        $topMedicines = $this->getTopMedicines();

        return response()->json([
            'success' => true,
            'data'    => [
                'totalCitizens'     => $totalCitizens,
                'totalPharmacies'   => $totalPharmacies,
                'totalMedicines'    => $totalMedicines,
                'totalOrders'       => $totalOrders,
                'totalRevenue'      => round((float) $totalRevenue, 2),
                'averageOrderValue' => round((float) $avgOrderValue, 2),
                'recentComplaints'  => $recentComplaints,
                'monthlyGrowth'     => $growth,
                'topMedicines'      => $topMedicines,
            ],
        ]);
    }

    /**
     * Aggregates medicine order counts in PHP instead of JSON_TABLE SQL.
     * Compatible with MySQL 5.7+, MySQL 8.0+, and MariaDB.
     */
    private function getTopMedicines(): array
    {
        // Pull the medicines JSON column from recent delivered orders
        $orders = Order::where('status', 'delivered')
            ->select('medicines')
            ->latest()
            ->limit(500) // cap for performance
            ->get();

        $counts = [];

        foreach ($orders as $order) {
            $medicines = $order->medicines ?? [];
            foreach ($medicines as $item) {
                $name = $item['medicineName'] ?? null;
                $qty  = (int) ($item['quantity'] ?? 1);
                if ($name) {
                    $counts[$name] = ($counts[$name] ?? 0) + $qty;
                }
            }
        }

        // Sort by quantity descending and return top 5
        arsort($counts);
        $top = array_slice($counts, 0, 5, true);

        return array_map(fn($name, $qty) => [
            'medicineName'  => $name,
            'totalOrdered'  => $qty,
        ], array_keys($top), array_values($top));
    }
}
