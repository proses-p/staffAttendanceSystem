<?php

namespace App\Http\Middleware;

use App\Models\Company;
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

        $company = $user->company;
        if (! $company || $company->status !== Company::STATUS_ACTIVE) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $company
                        ? 'Your company account is suspended. Contact platform support.'
                        : 'Your company assignment is missing. Contact your administrator.',
                ], 403);
            }

            abort(403, $company
                ? 'Your company account is suspended. Contact platform support.'
                : 'Your company assignment is missing. Contact your administrator.');
        }

        $request->attributes->set('company', $company);

        return $next($request);
    }
}
