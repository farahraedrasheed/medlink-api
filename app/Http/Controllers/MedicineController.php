<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Favorite;
use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicineController extends Controller
{
    // ─── GET /medicines ──────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Medicine::with('category')->active();

        // Search
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Category filter
        if ($category = $request->query('category')) {
            $query->whereHas('category', fn($q) => $q->where('name', 'like', "%{$category}%"));
        }

        // Prescription filter
        if ($request->has('requires_prescription')) {
            $query->where('requires_prescription', $request->boolean('requires_prescription'));
        }

        // Sorting
        switch ($request->query('sort', 'default')) {
            case 'name_asc':
                $query->orderBy('name');
                break;
            case 'name_desc':
                $query->orderByDesc('name');
                break;
            case 'price_asc':
                $query->withMin('inventory', 'price')->orderBy('inventory_min_price');
                break;
            case 'price_desc':
                $query->withMax('inventory', 'price')->orderByDesc('inventory_max_price');
                break;
            case 'availability':
                $query->withCount(['inventory' => fn($q) => $q->where('status', '!=', 'out_of_stock')])
                      ->orderByDesc('inventory_count');
                break;
            default:
                $query->latest();
        }

        $perPage  = min((int) $request->query('per_page', 12), 50);
        $paginated = $query->paginate($perPage);

        $userId   = auth()->id();
        $favorites = $userId
            ? Favorite::where('citizen_id', $userId)->where('favorite_type', 'medicine')
                      ->pluck('favorite_id')->toArray()
            : [];

        $medicines = $paginated->map(function (Medicine $m) use ($favorites) {
            return [
                'id'                   => $m->id,
                'name'                 => $m->name,
                'genericName'          => $m->generic_name,
                'category'             => $m->category?->name,
                'strength'             => $m->strength,
                'form'                 => $m->form,
                'manufacturer'         => $m->manufacturer,
                'description'          => $m->description,
                'requiresPrescription' => $m->requires_prescription,
                'pharmaciesCount'      => $m->pharmacies_count,
                'averagePrice'         => $m->average_price,
                'lowestPrice'          => $m->lowest_price,
                'highestPrice'         => $m->highest_price,
                'isFavorite'           => in_array($m->id, $favorites),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'medicines'  => $medicines,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ─── GET /medicines/categories ───────────────────────────────────────────

    public function categories(): JsonResponse
    {
        $categories = Category::withCount(['medicines' => fn($q) => $q->where('is_active', true)])
            ->get()
            ->map(fn(Category $c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'description'   => $c->description,
                'icon'          => $c->icon,
                'medicineCount' => $c->medicines_count,
            ]);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    // ─── GET /medicines/:id ──────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $medicine = Medicine::with(['category', 'inventory.pharmacy'])->active()->findOrFail($id);

        $userId    = auth()->id();
        $isFav     = $userId
            ? Favorite::where('citizen_id', $userId)
                      ->where('favorite_type', 'medicine')
                      ->where('favorite_id', $id)
                      ->exists()
            : false;

        $availableAt = $medicine->inventory
            ->filter(fn($inv) => $inv->status !== 'out_of_stock' && $inv->pharmacy?->status === 'verified')
            ->map(fn($inv) => [
                'pharmacyId'   => $inv->pharmacy_id,
                'pharmacyName' => $inv->pharmacy?->name ?? $inv->pharmacy?->full_name,
                'price'        => (float) $inv->price,
                'quantity'     => $inv->quantity,
                'area'         => $inv->pharmacy?->area,
                'rating'       => (float) $inv->pharmacy?->rating,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                   => $medicine->id,
                'name'                 => $medicine->name,
                'genericName'          => $medicine->generic_name,
                'category'             => $medicine->category?->name,
                'strength'             => $medicine->strength,
                'form'                 => $medicine->form,
                'manufacturer'         => $medicine->manufacturer,
                'description'          => $medicine->description,
                'sideEffects'          => $medicine->side_effects,
                'precautions'          => $medicine->precautions,
                'activeIngredients'    => $medicine->active_ingredients ?? [],
                'requiresPrescription' => $medicine->requires_prescription,
                'isControlled'         => $medicine->is_controlled,
                'availableAt'          => $availableAt,
                'isFavorite'           => $isFav,
                'createdAt'            => $medicine->created_at,
            ],
        ]);
    }

    // ─── POST /medicines (Admin) ──────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'name'                 => 'required|string|max:255',
            'genericName'          => 'nullable|string|max:255',
            'categoryId'           => 'nullable|uuid|exists:categories,id',
            'strength'             => 'nullable|string|max:100',
            'form'                 => 'required|in:tablet,capsule,liquid,injection,cream,drops,inhaler,patch,suppository,powder,other',
            'manufacturer'         => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'sideEffects'          => 'nullable|string',
            'precautions'          => 'nullable|string',
            'activeIngredients'    => 'nullable|array',
            'requiresPrescription' => 'boolean',
            'isControlled'         => 'boolean',
            'expiryDate'           => 'nullable|date',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $medicine = Medicine::create([
            'name'                 => $request->name,
            'generic_name'         => $request->genericName,
            'category_id'          => $request->categoryId,
            'strength'             => $request->strength,
            'form'                 => $request->form,
            'manufacturer'         => $request->manufacturer,
            'description'          => $request->description,
            'side_effects'         => $request->sideEffects,
            'precautions'          => $request->precautions,
            'active_ingredients'   => $request->activeIngredients,
            'requires_prescription'=> $request->boolean('requiresPrescription', false),
            'is_controlled'        => $request->boolean('isControlled', false),
            'expiry_date'          => $request->expiryDate,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Medicine created',
            'data'    => $medicine->load('category'),
        ], 201);
    }

    // ─── PUT /medicines/:id (Admin) ───────────────────────────────────────────

    public function update(Request $request, string $id): JsonResponse
    {
        $medicine = Medicine::findOrFail($id);

        $v = Validator::make($request->all(), [
            'name'                 => 'sometimes|string|max:255',
            'genericName'          => 'nullable|string|max:255',
            'categoryId'           => 'nullable|uuid|exists:categories,id',
            'strength'             => 'nullable|string',
            'form'                 => 'sometimes|in:tablet,capsule,liquid,injection,cream,drops,inhaler,patch,suppository,powder,other',
            'manufacturer'         => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'sideEffects'          => 'nullable|string',
            'precautions'          => 'nullable|string',
            'activeIngredients'    => 'nullable|array',
            'requiresPrescription' => 'boolean',
            'isControlled'         => 'boolean',
            'expiryDate'           => 'nullable|date',
            'isActive'             => 'boolean',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $fieldMap = [
            'name'                 => 'name',
            'genericName'          => 'generic_name',
            'categoryId'           => 'category_id',
            'strength'             => 'strength',
            'form'                 => 'form',
            'manufacturer'         => 'manufacturer',
            'description'          => 'description',
            'sideEffects'          => 'side_effects',
            'precautions'          => 'precautions',
            'activeIngredients'    => 'active_ingredients',
            'requiresPrescription' => 'requires_prescription',
            'isControlled'         => 'is_controlled',
            'expiryDate'           => 'expiry_date',
            'isActive'             => 'is_active',
        ];

        foreach ($fieldMap as $req => $col) {
            if ($request->has($req)) {
                $medicine->$col = $request->$req;
            }
        }

        $medicine->save();

        return response()->json([
            'success' => true,
            'message' => 'Medicine updated',
            'data'    => $medicine->load('category'),
        ]);
    }

    // ─── DELETE /medicines/:id (Admin) ────────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        $medicine = Medicine::findOrFail($id);
        $medicine->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Medicine deactivated']);
    }

    private function validationError($errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors, 'code' => 422], 422);
    }
}
