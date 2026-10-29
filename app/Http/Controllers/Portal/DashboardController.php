<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the member portal home.
     */
    public function __invoke(): Response
    {
        return Inertia::render('portal/Dashboard');
    }
}
