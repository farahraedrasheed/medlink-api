<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ComplaintController extends Controller
{
    // ─── POST /complaints (Citizen) ───────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'againstPharmacyId' => 'required|uuid|exists:users,id',
            'subject'           => 'required|string|max:255',
            'details'           => 'required|string|max:5000',
            'severity'          => 'nullable|in:low,medium,high,critical',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $v->errors(), 'code' => 422], 422);
        }

        User::pharmacies()->findOrFail($request->againstPharmacyId);

        $complaint = Complaint::create([
            'reporter_id'          => Auth::id(),
            'against_pharmacy_id'  => $request->againstPharmacyId,
            'subject'              => $request->subject,
            'details'              => $request->details,
            'severity'             => $request->severity ?? 'medium',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Complaint submitted',
            'data'    => [
                'id'                 => $complaint->id,
                'againstPharmacyId'  => $complaint->against_pharmacy_id,
                'subject'            => $complaint->subject,
                'severity'           => $complaint->severity,
                'status'             => $complaint->status,
                'createdAt'          => $complaint->created_at,
            ],
        ], 201);
    }

    // ─── GET /complaints (Citizen — own) ──────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with('pharmacy')
            ->where('reporter_id', Auth::id());

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage   = min((int) $request->query('per_page', 10), 50);
        $paginated = $query->latest()->paginate($perPage);

        $complaints = $paginated->map(fn(Complaint $c) => [
            'id'             => $c->id,
            'againstPharmacy'=> ['id' => $c->against_pharmacy_id, 'name' => $c->pharmacy?->name ?? $c->pharmacy?->full_name],
            'subject'        => $c->subject,
            'severity'       => $c->severity,
            'status'         => $c->status,
            'resolution'     => $c->resolution,
            'createdAt'      => $c->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'complaints' => $complaints,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }
}
