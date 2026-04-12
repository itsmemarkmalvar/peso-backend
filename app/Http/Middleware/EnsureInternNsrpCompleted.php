<?php

namespace App\Http\Middleware;

use App\Models\NsrpForm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternNsrpCompleted
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->isInternOrGip()) {
            return $next($request);
        }

        if ($this->isAllowedWithoutNsrp($request)) {
            return $next($request);
        }

        $isCompleted = NsrpForm::where('user_id', $user->id)
            ->where('is_completed', true)
            ->exists();

        if ($isCompleted) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please complete the NSRP Form before accessing this module.',
        ], 403);
    }

    private function isAllowedWithoutNsrp(Request $request): bool
    {
        return $request->is('api/nsrp*')
            || $request->is('api/interns/me')
            || $request->is('api/interns/me/resume')
            || $request->is('api/auth/me')
            || $request->is('api/auth/logout');
    }
}
