<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    // ─── GET /inventory ──────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $pharmacyId = Auth::id();

        $query = InventoryItem::with('medicine.category')
            ->where('pharmacy_id', $pharmacyId);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('medicine', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }

        switch ($request->query('sort', 'default')) {
            case 'price_asc':    $query->orderBy('price');                break;
            case 'price_desc':   $query->orderByDesc('price');            break;
            case 'stock_low':    $query->orderBy('quantity');             break;
            case 'stock_high':   $query->orderByDesc('quantity');         break;
            case 'recent':       $query->latest();                        break;
            default:             $query->orderBy('status')->orderBy('quantity'); break;
        }

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginated = $query->paginate($perPage);

        $items = $paginated->map(fn(InventoryItem $inv) => [
            'id'              => $inv->id,
            'medicineId'      => $inv->medicine_id,
            'medicineName'    => $inv->medicine?->name,
            'category'        => $inv->medicine?->category?->name,
            'quantity'        => $inv->quantity,
            'price'           => (float) $inv->price,
            'costPrice'       => (float) $inv->cost_price,
            'minimumStock'    => $inv->minimum_stock,
            'maximumStock'    => $inv->maximum_stock,
            'status'          => $inv->status,
            'lastRestockDate' => $inv->last_restock_date,
            'expiryDate'      => $inv->expiry_date,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'medicines'  => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ─── POST /inventory ─────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'medicineId'   => 'required|uuid|exists:medicines,id',
            'quantity'     => 'required|integer|min:0',
            'price'        => 'required|numeric|min:0',
            'costPrice'    => 'nullable|numeric|min:0',
            'minimumStock' => 'nullable|integer|min:0',
            'maximumStock' => 'nullable|integer|min:0',
            'expiryDate'   => 'nullable|date',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $pharmacyId = Auth::id();

        // Check if this medicine is already in pharmacy's inventory
        $existing = InventoryItem::where('pharmacy_id', $pharmacyId)
            ->where('medicine_id', $request->medicineId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This medicine is already in your inventory. Use PUT to update it.',
                'code'    => 409,
            ], 409);
        }

        $item = InventoryItem::create([
            'pharmacy_id'       => $pharmacyId,
            'medicine_id'       => $request->medicineId,
            'quantity'          => $request->quantity,
            'price'             => $request->price,
            'cost_price'        => $request->costPrice,
            'minimum_stock'     => $request->minimumStock ?? 10,
            'maximum_stock'     => $request->maximumStock ?? 1000,
            'expiry_date'       => $request->expiryDate,
            'last_restock_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Medicine added to inventory',
            'data'    => $item->load('medicine'),
        ], 201);
    }

    // ─── PUT /inventory/:id ──────────────────────────────────────────────────

    public function update(Request $request, string $id): JsonResponse
    {
        $item = InventoryItem::where('pharmacy_id', Auth::id())->findOrFail($id);

        $v = Validator::make($request->all(), [
            'quantity'     => 'sometimes|integer|min:0',
            'price'        => 'sometimes|numeric|min:0',
            'costPrice'    => 'nullable|numeric|min:0',
            'minimumStock' => 'nullable|integer|min:0',
            'maximumStock' => 'nullable|integer|min:0',
            'expiryDate'   => 'nullable|date',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $map = [
            'quantity'     => 'quantity',
            'price'        => 'price',
            'costPrice'    => 'cost_price',
            'minimumStock' => 'minimum_stock',
            'maximumStock' => 'maximum_stock',
            'expiryDate'   => 'expiry_date',
        ];

        foreach ($map as $req => $col) {
            if ($request->has($req)) {
                $item->$col = $request->$req;
            }
        }

        if ($request->has('quantity')) {
            $item->last_restock_date = now();
        }

        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Inventory updated',
            'data'    => $item->load('medicine'),
        ]);
    }

    // ─── DELETE /inventory/:id ───────────────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        $item = InventoryItem::where('pharmacy_id', Auth::id())->findOrFail($id);
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Item removed from inventory']);
    }

    private function validationError($errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors, 'code' => 422], 422);
    }
}
