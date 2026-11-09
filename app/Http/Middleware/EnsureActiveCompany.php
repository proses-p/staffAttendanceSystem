<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->is_active === false) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated.',
                ], 403);
            }

            abort(403, 'Your account has been deactivated.');
        }

        if ($user->role === 'super_admin') {
            return $next($request);
        }

        if ($companyAccessMessage = $user->companyAccessMessage()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $companyAccessMessage,
                ], 403);
            }

            abort(403, $companyAccessMessage);
        }

        $company = $user->company;
        $request->attributes->set('company', $company);

        return $next($request);
    }
}
