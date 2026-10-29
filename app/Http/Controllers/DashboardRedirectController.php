<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    /**
     * Send the user to the admin dashboard or the member portal.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()->canAccessAdmin()
            ? to_route('admin.dashboard')
            : to_route('portal.dashboard');
    }
}
