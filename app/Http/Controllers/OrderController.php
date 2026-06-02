<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    // ─── POST /orders ────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'pharmacyId'            => 'required|uuid|exists:users,id',
            'medicines'             => 'required|array|min:1',
            'medicines.*.medicineId'=> 'required|uuid|exists:medicines,id',
            'medicines.*.quantity'  => 'required|integer|min:1',
            'urgency'               => 'nullable|in:standard,urgent,critical',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $pharmacy = User::pharmacies()->verified()->findOrFail($request->pharmacyId);

        // Resolve each medicine from pharmacy inventory
        $resolvedMedicines = [];
        $totalPrice        = 0;
        $errors            = [];

        foreach ($request->medicines as $item) {
            $inv = InventoryItem::where('pharmacy_id', $pharmacy->id)
                ->where('medicine_id', $item['medicineId'])
                ->with('medicine')
                ->first();

            if (! $inv) {
                $errors[] = "Medicine {$item['medicineId']} is not available at this pharmacy.";
                continue;
            }

            if ($inv->quantity < $item['quantity']) {
                $errors[] = "Insufficient stock for {$inv->medicine->name}. Available: {$inv->quantity}.";
                continue;
            }

            $subtotal = $inv->price * $item['quantity'];
            $totalPrice += $subtotal;

            $resolvedMedicines[] = [
                'medicineId'   => $inv->medicine_id,
                'medicineName' => $inv->medicine->name,
                'quantity'     => $item['quantity'],
                'unitPrice'    => (float) $inv->price,
                'subtotal'     => (float) $subtotal,
            ];
        }

        if (! empty($errors)) {
            return response()->json(['success' => false, 'message' => implode(' ', $errors), 'code' => 422], 422);
        }

        $order = DB::transaction(function () use ($request, $pharmacy, $resolvedMedicines, $totalPrice) {
            $order = Order::create([
                'citizen_id'  => Auth::id(),
                'pharmacy_id' => $pharmacy->id,
                'medicines'   => $resolvedMedicines,
                'total_price' => $totalPrice,
                'urgency'     => $request->urgency ?? 'standard',
                'notes'       => $request->notes,
                'order_date'  => now(),
                'expected_delivery' => now()->addHours(6),
            ]);

            // Decrement inventory
            foreach ($resolvedMedicines as $item) {
                InventoryItem::where('pharmacy_id', $pharmacy->id)
                    ->where('medicine_id', $item['medicineId'])
                    ->decrement('quantity', $item['quantity']);
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Order submitted',
            'data'    => $this->formatOrder($order),
        ], 201);
    }

    // ─── GET /orders ─────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $query = Order::with(['citizen', 'pharmacy']);

        if ($user->isCitizen()) {
            $query->where('citizen_id', $user->id);
        } elseif ($user->isPharmacy()) {
            $query->where('pharmacy_id', $user->id);
        }
        // Admin sees all

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($urgency = $request->query('urgency')) {
            $query->where('urgency', $urgency);
        }

        $perPage   = min((int) $request->query('per_page', 10), 50);
        $paginated = $query->latest('order_date')->paginate($perPage);

        $orders = $paginated->map(fn(Order $o) => [
            'id'               => $o->id,
            'pharmacyName'     => $o->pharmacy?->name ?? $o->pharmacy?->full_name,
            'citizenName'      => $o->citizen?->full_name,
            'medicines'        => $o->medicines,
            'totalPrice'       => (float) $o->total_price,
            'urgency'          => $o->urgency,
            'status'           => $o->status,
            'orderDate'        => $o->order_date,
            'expectedDelivery' => $o->expected_delivery,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'orders'     => $orders,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ─── GET /orders/:id ─────────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $order = Order::with(['citizen', 'pharmacy'])->findOrFail($id);

        // Authorization: only involved parties or admin
        if (
            $user->isCitizen()  && $order->citizen_id  !== $user->id ||
            $user->isPharmacy() && $order->pharmacy_id !== $user->id
        ) {
            return response()->json(['success' => false, 'message' => 'Forbidden', 'code' => 403], 403);
        }

        return response()->json(['success' => true, 'data' => $this->formatOrder($order, detailed: true)]);
    }

    // ─── PUT /orders/:id/status ───────────────────────────────────────────────

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $order = Order::findOrFail($id);

        // Only pharmacy or admin can change status
        if ($user->isPharmacy() && $order->pharmacy_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden', 'code' => 403], 403);
        }

        $v = Validator::make($request->all(), [
            'status'   => 'required|in:approved,rejected,preparing,ready,delivered,cancelled',
            'response' => 'nullable|string|max:500',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $newStatus = $request->status;

        // Validate status transition
        $allowed = $this->allowedTransitions($order->status);
        if (! in_array($newStatus, $allowed)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot transition from '{$order->status}' to '{$newStatus}'.",
                'code'    => 422,
            ], 422);
        }

        $order->addStatusEvent($newStatus, $request->response ?? '');
        $order->pharmacy_response = $request->response;
        $order->response_date     = now();
        $order->save();

        // If rejected, restore inventory
        if ($newStatus === 'rejected') {
            foreach ($order->medicines as $item) {
                InventoryItem::where('pharmacy_id', $order->pharmacy_id)
                    ->where('medicine_id', $item['medicineId'])
                    ->increment('quantity', $item['quantity']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated',
            'data'    => $this->formatOrder($order->fresh()),
        ]);
    }

    // ─── DELETE /orders/:id (cancel) ─────────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $order = Order::findOrFail($id);

        if ($user->isCitizen() && $order->citizen_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden', 'code' => 403], 403);
        }

        if (! in_array($order->status, ['pending', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel an order that is already being prepared or completed.',
                'code'    => 422,
            ], 422);
        }

        $order->addStatusEvent('cancelled', 'Cancelled by ' . ($user->isCitizen() ? 'customer' : 'pharmacy'));
        $order->save();

        // Restore inventory
        foreach ($order->medicines as $item) {
            InventoryItem::where('pharmacy_id', $order->pharmacy_id)
                ->where('medicine_id', $item['medicineId'])
                ->increment('quantity', $item['quantity']);
        }

        return response()->json(['success' => true, 'message' => 'Order cancelled']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatOrder(Order $order, bool $detailed = false): array
    {
        $data = [
            'id'               => $order->id,
            'citizen'          => ['id' => $order->citizen_id, 'name' => $order->citizen?->full_name],
            'pharmacy'         => ['id' => $order->pharmacy_id, 'name' => $order->pharmacy?->name ?? $order->pharmacy?->full_name],
            'medicines'        => $order->medicines,
            'totalPrice'       => (float) $order->total_price,
            'urgency'          => $order->urgency,
            'status'           => $order->status,
            'pharmacyResponse' => $order->pharmacy_response,
            'orderDate'        => $order->order_date,
            'expectedDelivery' => $order->expected_delivery,
            'completedDate'    => $order->completed_date,
        ];

        if ($detailed) {
            $data['notes']          = $order->notes;
            $data['statusTimeline'] = $order->status_timeline;
            $data['responseDate']   = $order->response_date;
        }

        return $data;
    }

    private function allowedTransitions(string $current): array
    {
        return match($current) {
            'pending'   => ['approved', 'rejected', 'cancelled'],
            'approved'  => ['preparing', 'cancelled'],
            'preparing' => ['ready'],
            'ready'     => ['delivered'],
            default     => [],
        };
    }

    private function validationError($errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors, 'code' => 422], 422);
    }
}
