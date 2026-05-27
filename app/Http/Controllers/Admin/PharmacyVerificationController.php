<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PharmacyVerificationController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $status    = $request->query('status', 'pending');
        $perPage   = min((int) $request->query('per_page', 20), 100);

        $paginated = User::pharmacies()
            ->where('status', $status)
            ->latest()
            ->paginate($perPage);

        $pharmacies = $paginated->map(fn(User $p) => [
            'id'            => $p->id,
            'name'          => $p->name ?? $p->full_name,
            'email'         => $p->email,
            'phone'         => $p->phone,
            'licenseNumber' => $p->license_number,
            'licenseExpiry' => $p->license_expiry,
            'address'       => $p->address,
            'area'          => $p->area,
            'status'        => $p->status,
            'submittedAt'   => $p->created_at,
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

    public function verify(Request $request, string $pharmacyId): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'status' => 'required|in:verified,rejected,suspended',
            'notes'  => 'nullable|string|max:500',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $v->errors(),
                'code'    => 422,
            ], 422);
        }

        $pharmacy = User::pharmacies()->findOrFail($pharmacyId);
        $pharmacy->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Pharmacy status updated',
            'data'    => [
                'id'     => $pharmacy->id,
                'name'   => $pharmacy->name ?? $pharmacy->full_name,
                'status' => $pharmacy->status,
            ],
        ]);
    }
}
