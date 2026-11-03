<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the current tenant (company) for the request based on the user's
 * current_company_id. All BelongsToCompany models are scoped to it.
 */
class EnsureCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $company = $user->currentCompany;

        if (! $company) {
            $company = $user->companies()->wherePivot('is_active', true)->first();

            if ($company) {
                $user->update(['current_company_id' => $company->id]);
            }
        }

        if (! $company) {
            abort(403, 'No company membership.');
        }

        app()->instance('relay.company', $company);

        return $next($request);
    }
}
