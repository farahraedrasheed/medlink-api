<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // ─── POST /auth/register ─────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $rules = [
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role'     => 'required|in:citizen,pharmacy',
        ];

        if ($request->role === 'citizen') {
            $rules += [
                'firstName' => 'required|string|max:100',
                'lastName'  => 'required|string|max:100',
                'phone'     => 'nullable|string|max:20',
            ];
        } else {
            $rules += [
                'pharmacyName'     => 'required|string|max:255',
                'phone'            => 'required|string|max:20',
                'address'          => 'required|string',
                'licenseNumber'    => 'required|string|unique:users,license_number',
                'deliveryAvailable'=> 'boolean',
                'deliveryFee'      => 'nullable|numeric|min:0',
            ];
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $userData = [
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'phone'    => $request->phone,
        ];

        if ($request->role === 'citizen') {
            $userData['first_name'] = $request->firstName;
            $userData['last_name']  = $request->lastName;
            $userData['address']    = $request->address;
        } else {
            $userData['name']              = $request->pharmacyName;
            $userData['first_name']        = $request->pharmacyName; // compatibility
            $userData['address']           = $request->address;
            $userData['license_number']    = $request->licenseNumber;
            $userData['delivery_available']= $request->boolean('deliveryAvailable', false);
            $userData['delivery_fee']      = $request->deliveryFee ?? 0;
            $userData['status']            = 'pending'; // awaits admin verification
        }

        $user  = User::create($userData);
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data'    => [
                'id'    => $user->id,
                'email' => $user->email,
                'role'  => $user->role,
                'token' => $token,
            ],
        ], 201);
    }

    // ─── POST /auth/login ────────────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($v->fails()) {
            return $this->validationError($v->errors());
        }

        $credentials = $request->only('email', 'password');

        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'code'    => 401,
            ], 401);
        }

        $user = auth()->user();

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated. Please contact support.',
                'code'    => 403,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'id'        => $user->id,
                'email'     => $user->email,
                'firstName' => $user->first_name,
                'lastName'  => $user->last_name,
                'name'      => $user->full_name,
                'role'      => $user->role,
                'status'    => $user->status,
                'token'     => $token,
                'expiresIn' => config('jwt.ttl') * 60,
            ],
        ]);
    }

    // ─── POST /auth/logout ───────────────────────────────────────────────────

    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    // ─── POST /auth/refresh ──────────────────────────────────────────────────

    public function refresh(): JsonResponse
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());

        return response()->json([
            'success' => true,
            'data'    => [
                'token'     => $token,
                'expiresIn' => config('jwt.ttl') * 60,
            ],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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
