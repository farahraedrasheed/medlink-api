<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        switch ($request->query('status')) {
            case 'active':    $query->where('is_active', true);       break;
            case 'inactive':  $query->where('is_active', false);      break;
            case 'suspended': $query->where('status', 'suspended');   break;
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email',      'like', "%{$search}%")
                  ->orWhere('first_name','like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('name',      'like', "%{$search}%");
            });
        }

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginated = $query->latest()->paginate($perPage);

        $users = $paginated->map(fn(User $u) => [
            'id'        => $u->id,
            'name'      => $u->full_name,
            'email'     => $u->email,
            'phone'     => $u->phone,
            'role'      => $u->role,
            'isActive'  => $u->is_active,
            'status'    => $u->status,
            'createdAt' => $u->created_at,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'users'      => $users,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function toggleActive(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate admin accounts.',
                'code'    => 403,
            ], 403);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $action = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "User {$action}",
            'data'    => ['isActive' => $user->is_active],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete admin accounts.',
                'code'    => 403,
            ], 403);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted']);
    }
}
