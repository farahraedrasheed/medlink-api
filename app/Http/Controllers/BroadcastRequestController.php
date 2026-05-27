<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BroadcastRequestController extends Controller
{
    // ─── POST /requests (Citizen) ────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'medicineName' => 'required|string|max:255',
            'quantity'     => 'required|integer|min:1',
            'urgency'      => 'nullable|in:standard,urgent,critical',
            'notes'        => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $req = BroadcastRequest::create([
            'citizen_id'    => auth()->id(),
            'medicine_name' => $request->medicineName,
            'quantity'      => $request->quantity,
            'urgency'       => $request->urgency ?? 'standard',
            'notes'         => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request broadcast to network',
            'data'    => $this->formatRequest($req),
        ], 201);
    }

    // ─── GET /requests (Citizen — own requests) ───────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->expireStale();

        $query = BroadcastRequest::where('citizen_id', auth()->id());

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage   = min((int) $request->query('per_page', 10), 50);
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'requests'   => $paginated->map(fn($r) => $this->formatRequest($r)),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ─── GET /requests/network (Pharmacy — open requests) ────────────────────

    public function network(Request $request): JsonResponse
    {
        $this->expireStale();

        $query = BroadcastRequest::with('citizen')
            ->where('status', 'open')
            ->where('expires_at', '>', now());

        if ($urgency = $request->query('urgency')) {
            $query->where('urgency', $urgency);
        }

        $perPage   = min((int) $request->query('per_page', 20), 50);
        $paginated = $query->latest()->paginate($perPage);

        $pharmacyId = auth()->id();

        $requests = $paginated->map(fn(BroadcastRequest $r) => [
            'id'           => $r->id,
            'citizenName'  => $r->citizen?->full_name,
            'medicineName' => $r->medicine_name,
            'quantity'     => $r->quantity,
            'urgency'      => $r->urgency,
            'notes'        => $r->notes,
            'status'       => $r->status,
            'hasResponded' => collect($r->responses)->contains('pharmacyId', $pharmacyId),
            'createdAt'    => $r->created_at,
            'expiresAt'    => $r->expires_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'requests'   => $requests,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ─── POST /requests/:id/respond (Pharmacy) ────────────────────────────────

    public function respond(Request $request, string $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'price'    => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $broadcastReq = BroadcastRequest::where('status', 'open')
            ->where('expires_at', '>', now())
            ->findOrFail($id);

        $pharmacy = auth()->user();

        // Prevent duplicate responses
        $existing = collect($broadcastReq->responses)->firstWhere('pharmacyId', $pharmacy->id);
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already responded to this request.',
                'code'    => 409,
            ], 409);
        }

        $response = [
            'pharmacyId'   => $pharmacy->id,
            'pharmacyName' => $pharmacy->name ?? $pharmacy->full_name,
            'price'        => (float) $request->price,
            'quantity'     => $request->quantity,
            'responseTime' => now()->toISOString(),
            'status'       => 'pending',
        ];

        $broadcastReq->addResponse($response);
        $broadcastReq->save();

        return response()->json([
            'success' => true,
            'message' => 'Response submitted',
            'data'    => $response,
        ]);
    }

    // ─── POST /requests/:id/accept/:pharmacyId (Citizen) ─────────────────────

    public function accept(string $id, string $pharmacyId): JsonResponse
    {
        $broadcastReq = BroadcastRequest::where('citizen_id', auth()->id())
            ->where('status', 'open')
            ->findOrFail($id);

        $pharmacyResp = collect($broadcastReq->responses)->firstWhere('pharmacyId', $pharmacyId);

        if (! $pharmacyResp) {
            return response()->json([
                'success' => false,
                'message' => 'No response found from this pharmacy.',
                'code'    => 404,
            ], 404);
        }

        // Create an order from the accepted response
        $order = Order::create([
            'citizen_id'  => auth()->id(),
            'pharmacy_id' => $pharmacyId,
            'medicines'   => [[
                'medicineId'   => null, // broadcast requests may not have exact medicine IDs
                'medicineName' => $broadcastReq->medicine_name,
                'quantity'     => $broadcastReq->quantity,
                'unitPrice'    => $pharmacyResp['price'],
                'subtotal'     => $pharmacyResp['price'] * $broadcastReq->quantity,
            ]],
            'total_price' => $pharmacyResp['price'] * $broadcastReq->quantity,
            'urgency'     => $broadcastReq->urgency,
            'notes'       => $broadcastReq->notes,
            'order_date'  => now(),
            'expected_delivery' => now()->addHours(6),
        ]);

        // Close the broadcast request
        $broadcastReq->update([
            'status'              => 'accepted',
            'accepted_pharmacy_id'=> $pharmacyId,
            'closed_at'           => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Response accepted, order created',
            'data'    => [
                'requestId'  => $broadcastReq->id,
                'orderId'    => $order->id,
                'pharmacyId' => $pharmacyId,
                'status'     => 'accepted',
            ],
        ]);
    }

    // ─── DELETE /requests/:id (Citizen closes request) ───────────────────────

    public function destroy(string $id): JsonResponse
    {
        $broadcastReq = BroadcastRequest::where('citizen_id', auth()->id())->findOrFail($id);

        $broadcastReq->update(['status' => 'closed', 'closed_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Request closed']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatRequest(BroadcastRequest $r): array
    {
        return [
            'id'            => $r->id,
            'medicineName'  => $r->medicine_name,
            'quantity'      => $r->quantity,
            'urgency'       => $r->urgency,
            'notes'         => $r->notes,
            'status'        => $r->status,
            'responseCount' => count($r->responses ?? []),
            'responses'     => $r->responses ?? [],
            'createdAt'     => $r->created_at,
            'expiresAt'     => $r->expires_at,
        ];
    }

    private function expireStale(): void
    {
        BroadcastRequest::where('status', 'open')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    private function validationError($errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors, 'code' => 422], 422);
    }
}
