<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    // ─── POST /reviews ────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'pharmacyId' => 'required|uuid|exists:users,id',
            'rating'     => 'required|numeric|min:1|max:5',
            'reviewText' => 'nullable|string|max:1000',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $v->errors(), 'code' => 422], 422);
        }

        // Verify target is a pharmacy
        $pharmacy = User::pharmacies()->findOrFail($request->pharmacyId);

        $review = Review::updateOrCreate(
            ['citizen_id' => Auth::id(), 'pharmacy_id' => $pharmacy->id],
            ['rating' => $request->rating, 'review_text' => $request->reviewText]
        );

        return response()->json([
            'success' => true,
            'message' => 'Review submitted',
            'data'    => [
                'id'          => $review->id,
                'pharmacyId'  => $review->pharmacy_id,
                'rating'      => (float) $review->rating,
                'reviewText'  => $review->review_text,
                'createdAt'   => $review->created_at,
            ],
        ], 201);
    }

    // ─── GET /reviews/pharmacy/:pharmacyId ────────────────────────────────────

    public function forPharmacy(Request $request, string $pharmacyId): JsonResponse
    {
        User::pharmacies()->findOrFail($pharmacyId);

        $perPage   = min((int) $request->query('per_page', 10), 50);
        $paginated = Review::with('citizen')
            ->where('pharmacy_id', $pharmacyId)
            ->latest()
            ->paginate($perPage);

        $reviews = $paginated->map(fn(Review $r) => [
            'id'           => $r->id,
            'rating'       => (float) $r->rating,
            'reviewText'   => $r->review_text,
            'customerName' => $r->citizen?->full_name,
            'createdAt'    => $r->created_at,
        ]);

        $stats = Review::where('pharmacy_id', $pharmacyId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'reviews'       => $reviews,
                'averageRating' => round((float) $stats->avg_rating, 2),
                'totalReviews'  => (int) $stats->total,
                'pagination'    => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    // ─── DELETE /reviews/:id ─────────────────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        $review = Review::where('citizen_id', Auth::id())->findOrFail($id);
        $review->delete();

        return response()->json(['success' => true, 'message' => 'Review deleted']);
    }
}
