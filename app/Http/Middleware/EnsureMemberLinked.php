<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only accounts linked to a member record into member-only portal pages.
 */
class EnsureMemberLinked
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->member === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Your account is not linked to a parish member record yet. Please contact the parish office.')]);

            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}
