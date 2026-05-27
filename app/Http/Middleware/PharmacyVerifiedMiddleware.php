<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PharmacyVerifiedMiddleware
{
    /**
     * Ensures pharmacy accounts are verified before they can access inventory/orders.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->isPharmacy() && ! $user->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Your pharmacy account is pending verification. Please wait for admin approval.',
                'code'    => 403,
            ], 403);
        }

        return $next($request);
    }
}
