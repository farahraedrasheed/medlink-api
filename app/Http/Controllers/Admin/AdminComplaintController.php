<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with(['reporter', 'pharmacy', 'assignedAdmin']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginated = $query->latest()->paginate($perPage);

        $complaints = $paginated->map(fn(Complaint $c) => [
            'id'              => $c->id,
            'reporter'        => [
                'id'   => $c->reporter_id,
                'name' => $c->reporter?->full_name,
            ],
            'againstPharmacy' => [
                'id'   => $c->against_pharmacy_id,
                'name' => $c->pharmacy?->name ?? $c->pharmacy?->full_name,
            ],
            'subject'         => $c->subject,
            'severity'        => $c->severity,
            'status'          => $c->status,
            'assignedAdmin'   => $c->assigned_admin_id ? [
                'id'   => $c->assigned_admin_id,
                'name' => $c->assignedAdmin?->full_name,
            ] : null,
            'createdAt'       => $c->created_at,
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

    public function update(Request $request, string $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'status'     => 'required|in:in_review,resolved,rejected',
            'resolution' => 'nullable|string|max:2000',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $v->errors(),
                'code'    => 422,
            ], 422);
        }

        $complaint = Complaint::findOrFail($id);
        $complaint->update([
            'status'            => $request->status,
            'resolution'        => $request->resolution,
            'assigned_admin_id' => Auth::id(),
            'resolution_date'   => in_array($request->status, ['resolved', 'rejected']) ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Complaint updated',
            'data'    => $complaint->fresh(['reporter', 'pharmacy', 'assignedAdmin']),
        ]);
    }

    public function assign(string $id): JsonResponse
    {
        $complaint = Complaint::findOrFail($id);
        $complaint->update([
            'assigned_admin_id' => Auth::id(),
            'status'            => 'in_review',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Complaint assigned to you',
        ]);
    }
}
