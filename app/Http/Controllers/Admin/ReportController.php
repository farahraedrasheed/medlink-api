<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type'      => 'required|in:shortage,complaints,orders,pharmacy_performance',
            'startDate' => 'nullable|date',
            'endDate'   => 'nullable|date|after_or_equal:startDate',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $v->errors(),
                'code'    => 422,
            ], 422);
        }

        $startDate = $request->query('startDate', now()->subMonth()->toDateString());
        $endDate   = $request->query('endDate',   now()->toDateString());

        $items = match($request->type) {
            'shortage'             => $this->shortageReport($startDate, $endDate),
            'complaints'           => $this->complaintsReport($startDate, $endDate),
            'orders'               => $this->ordersReport($startDate, $endDate),
            'pharmacy_performance' => $this->pharmacyPerformanceReport($startDate, $endDate),
        };

        return response()->json([
            'success' => true,
            'data'    => [
                'reportType' => $request->type,
                'period'     => "{$startDate} to {$endDate}",
                'items'      => $items,
            ],
        ]);
    }

    private function shortageReport(string $start, string $end): array
    {
        return InventoryItem::with('medicine.category')
            ->where('status', 'out_of_stock')
            ->whereBetween('updated_at', [$start, $end])
            ->get()
            ->groupBy('medicine_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'medicineName'       => $first->medicine?->name,
                    'category'           => $first->medicine?->category?->name,
                    'shortagesCount'     => $group->count(),
                    'affectedPharmacies' => $group->pluck('pharmacy_id')->unique()->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function complaintsReport(string $start, string $end): array
    {
        return Complaint::with(['pharmacy', 'reporter'])
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'pharmacy' => $c->pharmacy?->name ?? $c->pharmacy?->full_name,
                'subject'  => $c->subject,
                'severity' => $c->severity,
                'status'   => $c->status,
                'date'     => $c->created_at->toDateString(),
            ])
            ->toArray();
    }

    private function ordersReport(string $start, string $end): array
    {
        $orders = Order::whereBetween('created_at', [$start, $end])->get();

        return [
            'total'     => $orders->count(),
            'delivered' => $orders->where('status', 'delivered')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
            'revenue'   => round($orders->where('status', 'delivered')->sum('total_price'), 2),
            'byUrgency' => [
                'standard' => $orders->where('urgency', 'standard')->count(),
                'urgent'   => $orders->where('urgency', 'urgent')->count(),
                'critical' => $orders->where('urgency', 'critical')->count(),
            ],
        ];
    }

    private function pharmacyPerformanceReport(string $start, string $end): array
    {
        return User::pharmacies()->verified()
            ->withCount([
                'ordersAsPharmacy as total_orders' => fn($q) =>
                    $q->whereBetween('created_at', [$start, $end]),
                'ordersAsPharmacy as delivered_orders' => fn($q) =>
                    $q->where('status', 'delivered')->whereBetween('created_at', [$start, $end]),
            ])
            ->orderByDesc('total_orders')
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'pharmacyId'      => $p->id,
                'pharmacyName'    => $p->name ?? $p->full_name,
                'area'            => $p->area,
                'rating'          => (float) $p->rating,
                'totalOrders'     => $p->total_orders,
                'deliveredOrders' => $p->delivered_orders,
                'fulfillmentRate' => $p->total_orders > 0
                    ? round(($p->delivered_orders / $p->total_orders) * 100, 1)
                    : 0,
            ])
            ->toArray();
    }
}
