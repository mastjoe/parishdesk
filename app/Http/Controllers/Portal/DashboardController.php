<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the member portal home.
     */
    public function __invoke(Request $request): Response
    {
        $member = $request->user()->member?->load(['family' => fn ($query) => $query->withCount('members'), 'community:id,name']);

        return Inertia::render('portal/Dashboard', [
            'member' => $member !== null ? [
                'name' => $member->full_name,
                'firstName' => $member->first_name,
                'memberNo' => $member->member_no,
                'photoUrl' => $member->photoUrl(),
                'community' => $member->community?->name,
                'family' => $member->family !== null ? [
                    'name' => $member->family->name,
                    'membersCount' => $member->family->members_count,
                ] : null,
                'hasPendingChanges' => $member->changeRequests()->pending()->exists(),
            ] : null,
        ]);
    }
}
