<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Favorite::where('citizen_id', auth()->id());

        if ($type = $request->query('type')) {
            if ($type !== 'all') {
                $query->where('favorite_type', $type);
            }
        }

        $perPage   = min((int) $request->query('per_page', 20), 50);
        $paginated = $query->latest('created_at')->paginate($perPage);

        $favorites = $paginated->map(fn(Favorite $f) => [
            'id'         => $f->id,
            'type'       => $f->favorite_type,
            'targetId'   => $f->favorite_id,
            'targetData' => $f->favorite_data,
            'addedAt'    => $f->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'favorites'  => $favorites,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type'       => 'required|in:medicine,pharmacy',
            'targetId'   => 'required|string',
            'targetData' => 'nullable|array',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $v->errors(), 'code' => 422], 422);
        }

        $favorite = Favorite::firstOrCreate(
            [
                'citizen_id'    => auth()->id(),
                'favorite_type' => $request->type,
                'favorite_id'   => $request->targetId,
            ],
            ['favorite_data' => $request->targetData, 'created_at' => now()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Added to favorites',
            'data'    => [
                'id'         => $favorite->id,
                'type'       => $favorite->favorite_type,
                'targetId'   => $favorite->favorite_id,
                'targetData' => $favorite->favorite_data,
                'addedAt'    => $favorite->created_at,
            ],
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $favorite = Favorite::where('citizen_id', auth()->id())->findOrFail($id);
        $favorite->delete();

        return response()->json(['success' => true, 'message' => 'Removed from favorites']);
    }

    public function toggle(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'type'     => 'required|in:medicine,pharmacy',
            'targetId' => 'required|string',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $existing = Favorite::where('citizen_id', auth()->id())
            ->where('favorite_type', $request->type)
            ->where('favorite_id', $request->targetId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'message' => 'Removed from favorites', 'isFavorite' => false]);
        }

        Favorite::create([
            'citizen_id'    => auth()->id(),
            'favorite_type' => $request->type,
            'favorite_id'   => $request->targetId,
            'favorite_data' => $request->targetData ?? [],
            'created_at'    => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Added to favorites', 'isFavorite' => true]);
    }
}
