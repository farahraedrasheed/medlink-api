<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PharmacyController extends Controller
{
    // ─── GET /pharmacies ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = User::pharmacies()->verified()->active();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($area = $request->query('area')) {
            $query->where('area', $area);
        }

        if ($request->boolean('delivery_only')) {
            $query->where('delivery_available', true);
        }

        switch ($request->query('sort', 'default')) {
            case 'rating_high': $query->orderByDesc('rating');   break;
            case 'rating_low':  $query->orderBy('rating');       break;
            case 'name_asc':    $query->orderBy('name');         break;
            default:            $query->latest();
        }

        $perPage   = min((int) $request->query('per_page', 12), 50);
        $paginated = $query->paginate($perPage);

        $userId    = Auth::id();
        $favorites = $userId
            ? Favorite::where('citizen_id', $userId)->where('favorite_type', 'pharmacy')
                      ->pluck('favorite_id')->toArray()
            : [];

        $pharmacies = $paginated->map(fn(User $p) => [
            'id'                => $p->id,
            'name'              => $p->name ?? $p->full_name,
            'area'              => $p->area,
            'address'           => $p->address,
            'rating'            => (float) $p->rating,
            'reviewCount'       => $p->review_count,
            'isOpenNow'         => $p->isOpenNow(),
            'profileImage'      => $p->profile_image,
            'latitude'          => $p->latitude,
            'longitude'         => $p->longitude,
            'deliveryAvailable' => $p->delivery_available,
            'deliveryFee'       => (float) $p->delivery_fee,
            'isFavorite'        => in_array($p->id, $favorites),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'pharmacies' => $pharmacies,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ─── GET /pharmacies/areas ────────────────────────────────────────────────

    public function areas(): JsonResponse
    {
        $areas = User::pharmacies()->verified()->active()
            ->whereNotNull('area')
            ->selectRaw('area, COUNT(*) as pharmacy_count')
            ->groupBy('area')
            ->orderByDesc('pharmacy_count')
            ->get()
            ->map(fn($row) => [
                'name'          => $row->area,
                'pharmacyCount' => $row->pharmacy_count,
            ]);

        return response()->json(['success' => true, 'data' => $areas]);
    }

    // ─── GET /pharmacies/:id ─────────────────────────────────────────────────

    public function show(string $id): JsonResponse
    {
        $pharmacy = User::pharmacies()->with(['inventory.medicine', 'reviews.citizen'])->findOrFail($id);

        $userId = Auth::id();
        $isFav  = $userId
            ? Favorite::where('citizen_id', $userId)
                      ->where('favorite_type', 'pharmacy')
                      ->where('favorite_id', $id)
                      ->exists()
            : false;

        $medicines = $pharmacy->inventory->map(fn($inv) => [
            'medicineId'   => $inv->medicine_id,
            'medicineName' => $inv->medicine?->name,
            'price'        => (float) $inv->price,
            'quantity'     => $inv->quantity,
            'status'       => $inv->status,
        ]);

        $reviews = $pharmacy->reviews->take(10)->map(fn($r) => [
            'rating'       => (float) $r->rating,
            'reviewText'   => $r->review_text,
            'customerName' => $r->citizen?->first_name,
            'createdAt'    => $r->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                => $pharmacy->id,
                'name'              => $pharmacy->name ?? $pharmacy->full_name,
                'email'             => $pharmacy->email,
                'phone'             => $pharmacy->phone,
                'address'           => $pharmacy->address,
                'area'              => $pharmacy->area,
                'latitude'          => $pharmacy->latitude,
                'longitude'         => $pharmacy->longitude,
                'rating'            => (float) $pharmacy->rating,
                'reviewCount'       => $pharmacy->review_count,
                'profileImage'      => $pharmacy->profile_image,
                'workingHours'      => $pharmacy->working_hours,
                'isOpenNow'         => $pharmacy->isOpenNow(),
                'deliveryAvailable' => $pharmacy->delivery_available,
                'deliveryFee'       => (float) $pharmacy->delivery_fee,
                'medicines'         => $medicines,
                'reviews'           => $reviews,
                'status'            => $pharmacy->status,
                'isFavorite'        => $isFav,
                'createdAt'         => $pharmacy->created_at,
            ],
        ]);
    }
}
