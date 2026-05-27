<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // ─── GET /users/me ───────────────────────────────────────────────────────

    public function me(): JsonResponse
    {
        $user = auth()->user();

        $data = [
            'id'           => $user->id,
            'firstName'    => $user->first_name,
            'lastName'     => $user->last_name,
            'name'         => $user->full_name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'address'      => $user->address,
            'profileImage' => $user->profile_image,
            'role'         => $user->role,
            'isActive'     => $user->is_active,
            'createdAt'    => $user->created_at,
        ];

        if ($user->isPharmacy()) {
            $data += [
                'licenseNumber'     => $user->license_number,
                'licenseExpiry'     => $user->license_expiry,
                'area'              => $user->area,
                'latitude'          => $user->latitude,
                'longitude'         => $user->longitude,
                'status'            => $user->status,
                'workingHours'      => $user->working_hours,
                'deliveryAvailable' => $user->delivery_available,
                'deliveryFee'       => $user->delivery_fee,
                'rating'            => $user->rating,
                'reviewCount'       => $user->review_count,
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─── PUT /users/me ───────────────────────────────────────────────────────

    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        $rules = [
            'firstName' => 'sometimes|string|max:100',
            'lastName'  => 'sometimes|string|max:100',
            'phone'     => 'sometimes|string|max:20',
            'address'   => 'sometimes|string',
        ];

        if ($user->isPharmacy()) {
            $rules += [
                'workingHours'      => 'sometimes|array',
                'deliveryAvailable' => 'sometimes|boolean',
                'deliveryFee'       => 'sometimes|numeric|min:0',
                'area'              => 'sometimes|string|max:100',
                'latitude'          => 'sometimes|numeric',
                'longitude'         => 'sometimes|numeric',
            ];
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $map = [
            'firstName' => 'first_name',
            'lastName'  => 'last_name',
            'phone'     => 'phone',
            'address'   => 'address',
        ];

        if ($user->isPharmacy()) {
            $map += [
                'workingHours'      => 'working_hours',
                'deliveryAvailable' => 'delivery_available',
                'deliveryFee'       => 'delivery_fee',
                'area'              => 'area',
                'latitude'          => 'latitude',
                'longitude'         => 'longitude',
            ];
        }

        foreach ($map as $req => $col) {
            if ($request->has($req)) {
                $user->$col = $request->$req;
            }
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated',
            'data'    => $user->fresh(),
        ]);
    }

    // ─── POST /users/change-password ─────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'currentPassword' => 'required',
            'newPassword'     => 'required|min:8',
            'confirmPassword' => 'required|same:newPassword',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $user = auth()->user();

        if (! Hash::check($request->currentPassword, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'code'    => 422,
            ], 422);
        }

        $user->password = Hash::make($request->newPassword);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    // ─── POST /users/upload-avatar ───────────────────────────────────────────

    public function uploadAvatar(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $user = auth()->user();

        // Delete old avatar
        if ($user->profile_image) {
            $old = str_replace(config('app.url') . '/storage/', '', $user->profile_image);
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('file')->store("avatars/{$user->id}", 'public');
        $url  = Storage::url($path);

        $user->profile_image = config('app.url') . $url;
        $user->save();

        return response()->json([
            'success' => true,
            'data'    => ['profileImage' => $user->profile_image],
        ]);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function validationError($errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $errors,
            'code'    => 422,
        ], 422);
    }
}
